<?php

namespace App\Services\DtePurchases;

use App\Models\DteOdooOrderLink;
use App\Models\OdooPurchaseOrder;
use App\Models\User;
use App\Services\PurchaseRequests\Products\ProductSimilarity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Une las facturas con las órdenes hechas directo en Odoo.
 *
 * Odoo no ayuda: de 1.752 facturas de proveedor, 7 están asociadas a su
 * orden. Lo que sí hay es la referencia a la orden en algunos XML (29 de 334
 * desde septiembre) y, en el resto, el proveedor, los productos y el monto.
 * Sólo escribe en Laravel: ni Odoo ni las facturas de fuelcontrol se tocan.
 */
class OdooOrderLinks
{
    /** Antes de la factura, cuánto puede haber esperado una orden. */
    private const DAYS_BEFORE = 120;

    /** Hay órdenes que el jefe crea después de recibir la factura. */
    private const DAYS_AFTER = 15;

    public function __construct(private ProductSimilarity $similarity, private CreditNotes $creditNotes) {}

    /** Las órdenes que el XML nombra como referencia (tipo 801, «orden de compra»). */
    public function referencedOrders(object $document): array
    {
        preg_match_all('/<TpoDocRef>\s*801\s*<\/TpoDocRef>.*?<FolioRef>([^<]*)<\/FolioRef>/s', (string) $document->xml_raw, $matches);
        $names = [];
        foreach ($matches[1] as $folio) {
            if (preg_match('/\bP\s*0*(\d{1,6})\b/i', $folio, $m)) {
                $names[] = sprintf('P%05d', (int) $m[1]);
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Las órdenes que podrían ser de esta factura, la más probable primero.
     * Sin búsqueda: las del mismo proveedor en fechas cercanas. Con búsqueda:
     * cualquiera por número o proveedor, para lo que nada encuentra solo.
     */
    public function candidates(int $documentId, string $search = ''): array
    {
        $document = $this->document($documentId);
        if (! $this->eligible($document)) {
            return [];
        }
        $linked = DteOdooOrderLink::active()->where('document_id', $documentId)->pluck('odoo_order_id')->all();
        $referenced = $this->referencedOrders($document);
        $date = Carbon::parse($document->fecha_factura);
        $query = OdooPurchaseOrder::query()->with('lines')->where('state', '<>', 'cancel')->whereNotIn('odoo_id', $linked);
        if (trim($search) !== '') {
            $term = '%'.addcslashes(trim($search), '%_\\').'%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('partner_name', 'like', $term))->limit(25);
        } else {
            $rut = $this->plainRut($document->proveedor_rut);
            if ($rut === '') {
                return [];
            }
            $query->where(fn ($q) => $q->whereIn('name', $referenced ?: ['-'])->orWhere(fn ($q) => $q
                ->whereNotNull('partner_vat')
                ->whereBetween('date_order', [$date->copy()->subDays(self::DAYS_BEFORE), $date->copy()->addDays(self::DAYS_AFTER)->endOfDay()])));
        }
        $orders = $query->orderByDesc('date_order')->get();
        if (trim($search) === '') {
            $rut = $this->plainRut($document->proveedor_rut);
            $orders = $orders->filter(fn (OdooPurchaseOrder $order): bool => in_array($order->name, $referenced, true)
                || $this->plainRut($order->partner_vat) === $rut);
        }
        $lines = $this->lines($documentId);
        $net = $this->net($document);
        $invoiced = $this->invoicedByOrder($orders->pluck('odoo_id')->all());
        $invoiceCount = DteOdooOrderLink::active()->whereIn('odoo_order_id', $orders->pluck('odoo_id'))
            ->groupBy('odoo_order_id')->selectRaw('odoo_order_id, count(*) as total')->pluck('total', 'odoo_order_id');

        $candidates = $orders->map(function (OdooPurchaseOrder $order) use ($referenced, $lines, $net, $invoiced, $invoiceCount, $document): array {
            // Lo facturado está en pesos: sólo se resta de una orden en pesos.
            $clp = in_array($order->currency, [null, 'CLP'], true);
            $remaining = $clp ? max(0.0, $order->amount_untaxed - ($invoiced[$order->odoo_id] ?? 0.0)) : null;
            $quick = $clp && $remaining > 0 ? min($net, $remaining) : $net;

            return [
                'odoo_id' => $order->odoo_id, 'name' => $order->name, 'partner' => $order->partner_name,
                'state' => $order->stateLabel(), 'date' => $order->date_order?->toDateString(),
                'created_by' => $order->created_by_name, 'currency' => $order->currency ?: 'CLP',
                'amount' => $order->amount_untaxed, 'remaining' => $remaining, 'invoices' => (int) ($invoiceCount[$order->odoo_id] ?? 0),
                'referenced' => in_array($order->name, $referenced, true),
                'same_supplier' => $this->plainRut($order->partner_vat) === $this->plainRut($document->proveedor_rut),
                'coverage' => round($this->coverage($lines, $order), 3),
                'same_amount' => $clp && $net > 0 && $remaining > 0 && abs($net - $remaining) <= max(2.0, $remaining * 0.01),
                'quick_amount' => $quick, 'partial' => $quick < $net - 1,
                'url' => $order->odooUrl(),
            ];
        });
        if (trim($search) === '') {
            // Solas, sólo las que tienen algo a favor; «mismo proveedor y fechas
            // cercanas» no basta. Una orden en pesos ya facturada entera, tampoco.
            $candidates = $candidates->filter(fn (array $c): bool => $c['referenced']
                || (($c['coverage'] > 0 || $c['same_amount']) && ($c['remaining'] === null || $c['remaining'] > 0)));
        }

        return $candidates->sortByDesc(fn (array $c): array => [$c['referenced'], $c['same_amount'] && $c['coverage'] >= 0.999, $c['coverage'], $c['same_amount']])
            ->values()->all();
    }

    /**
     * Vincula lo que no deja dudas: la orden que el XML nombra, o la única del
     * mismo proveedor cuyos productos están todos en la factura con el mismo
     * monto pendiente. Una vinculación que alguien retiró no se rehace.
     *
     * @return int vínculos creados
     */
    public function autoLink(int $documentId): int
    {
        $document = $this->document($documentId);
        if (! $this->eligible($document) || DteOdooOrderLink::active()->where('document_id', $documentId)->exists()) {
            return 0;
        }
        // Una factura anulada por su nota de crédito no pagó nada.
        if (collect($this->creditNotes->forDocuments([$document])[$documentId] ?? [])->contains('annuls', true)) {
            return 0;
        }
        $candidates = collect($this->candidates($documentId));
        $chosen = $candidates->where('referenced', true);
        $source = 'reference';
        if ($chosen->isEmpty()) {
            $chosen = $candidates->filter(fn (array $c): bool => $c['same_supplier'] && $c['same_amount'] && $c['coverage'] >= 0.999);
            $source = 'auto';
            if ($chosen->count() !== 1) {
                return 0;
            }
        }
        $created = 0;
        $left = $this->net($document);
        foreach ($chosen as $candidate) {
            $key = $source.':'.$documentId.':'.$candidate['odoo_id'];
            if (DteOdooOrderLink::where('source_key', $key)->exists()) {
                continue;
            }
            $amount = $chosen->count() === 1 ? $this->net($document) : min($left, $candidate['remaining'] ?: $left);
            $this->link($documentId, $candidate['odoo_id'], $amount, null, $source, $source === 'reference'
                ? 'La factura nombra la orden '.$candidate['name'].' en sus referencias.'
                : 'Mismo proveedor, mismos productos y mismo monto pendiente.', $key);
            $left = max(0.0, $left - $amount);
            $created++;
        }

        return $created;
    }

    public function link(int $documentId, int $odooOrderId, ?float $amount, ?User $actor, string $source = 'manual', ?string $note = null, ?string $key = null): DteOdooOrderLink
    {
        return DB::transaction(function () use ($documentId, $odooOrderId, $amount, $actor, $source, $note, $key): DteOdooOrderLink {
            $document = $this->document($documentId);
            $this->require($this->eligible($document), 'Este documento no admite vínculos con órdenes de compra.');
            $order = OdooPurchaseOrder::where('odoo_id', $odooOrderId)->first();
            $this->require($order !== null, 'La orden ya no está entre las hechas directo en Odoo. Recarga la pantalla.');
            $this->require(! DteOdooOrderLink::active()->where('document_id', $documentId)->where('odoo_order_id', $odooOrderId)->exists(),
                'Esta factura ya está vinculada con esa orden.');
            $amount ??= $this->net($document);
            $this->require($amount > 0, 'El monto debe ser mayor que cero.');

            return DteOdooOrderLink::create([
                'document_id' => $documentId, 'odoo_order_id' => $odooOrderId, 'order_name' => $order->name,
                'amount' => round($amount, 2), 'source' => $source, 'source_key' => $key,
                'note' => $note ?: 'Vínculo confirmado.', 'actor_id' => $actor?->id, 'actor_name' => $actor?->name,
            ]);
        });
    }

    public function revoke(int $documentId, int $linkId, User $actor, string $note): void
    {
        $link = DteOdooOrderLink::active()->where('document_id', $documentId)->findOrFail($linkId);
        $link->update(['revoked_at' => now(), 'revoked_by' => $actor->id, 'revocation_note' => $note]);
    }

    /** Lo que muestra la factura: sus órdenes vinculadas y las sugerencias. */
    public function report(int $documentId): array
    {
        $document = $this->document($documentId);
        $links = DteOdooOrderLink::active()->where('document_id', $documentId)->orderBy('id')->get();
        $orders = OdooPurchaseOrder::whereIn('odoo_id', $links->pluck('odoo_order_id'))->get()->keyBy('odoo_id');
        $invoiced = $this->invoicedByOrder($links->pluck('odoo_order_id')->all());
        [$credits, $notes] = $this->credits($links);

        return [
            'eligible' => $this->eligible($document), 'net' => $this->net($document),
            'links' => $links->map(fn (DteOdooOrderLink $link): array => [
                'id' => $link->id, 'odoo_id' => $link->odoo_order_id, 'name' => $link->order_name, 'amount' => $link->amount,
                'source' => $link->source, 'source_label' => DteOdooOrderLink::SOURCES[$link->source] ?? $link->source,
                'actor' => $link->actor_name, 'note' => $link->note,
                'credited' => $credits[$link->id] ?? 0.0, 'credit_notes' => $this->noteSummary($notes[$link->document_id] ?? []),
                'order' => ($order = $orders->get($link->odoo_order_id)) ? [
                    'partner' => $order->partner_name, 'state' => $order->stateLabel(), 'date' => $order->date_order?->toDateString(),
                    'created_by' => $order->created_by_name, 'currency' => $order->currency ?: 'CLP',
                    'amount' => $order->amount_untaxed, 'invoiced' => $invoiced[$order->odoo_id] ?? 0.0, 'url' => $order->odooUrl(),
                ] : null,
            ])->values()->all(),
            // Con su orden ya vinculada, sugerir otras sólo distrae: queda la búsqueda.
            'candidates' => $this->eligible($document) && $links->isEmpty() ? array_slice($this->candidates($documentId), 0, 8) : [],
        ];
    }

    /**
     * Las facturas de cada orden, para la pestaña Odoo.
     *
     * @return array<int, list<array{id: int, folio: string, date: ?string, amount: float, source: string}>>
     */
    public function invoicesFor(array $odooOrderIds): array
    {
        $links = DteOdooOrderLink::active()->whereIn('odoo_order_id', $odooOrderIds)->orderBy('id')->get();
        $documents = DB::connection('fuelcontrol')->table('gmail_dte_documents')->whereIn('id', $links->pluck('document_id')->unique())
            ->get(['id', 'folio', 'fecha_factura'])->keyBy('id');
        [$credits, $notes] = $this->credits($links);

        return $links->groupBy('odoo_order_id')->map(fn (Collection $group): array => $group->map(fn (DteOdooOrderLink $link): array => [
            'id' => $link->document_id, 'folio' => (string) ($documents->get($link->document_id)?->folio ?? '¿?'),
            'date' => $documents->get($link->document_id)?->fecha_factura, 'amount' => $link->amount, 'source' => $link->source,
            'credited' => $credits[$link->id] ?? 0.0, 'net' => $link->amount - ($credits[$link->id] ?? 0.0),
            'credit_notes' => $this->noteSummary($notes[$link->document_id] ?? []),
        ])->values()->all())->all();
    }

    /**
     * Vincula las facturas recientes de los proveedores que tienen órdenes, para
     * que la pestaña Odoo diga qué está facturado sin abrir factura por factura.
     */
    public function linkRecent(int $days = 120): int
    {
        $ruts = OdooPurchaseOrder::whereNotNull('partner_vat')->pluck('partner_vat')->map(fn ($vat) => $this->plainRut($vat))->filter()->unique()->flip();
        $created = 0;
        $documents = DB::connection('fuelcontrol')->table('gmail_dte_documents')->whereIn('tipo_dte', [33, 34, 46])
            ->whereDate('fecha_factura', '>=', now()->subDays($days)->toDateString())->orderBy('fecha_factura')->orderBy('id')
            ->get(['id', 'proveedor_rut', 'xml_raw']);
        foreach ($documents as $document) {
            if ($ruts->has($this->plainRut($document->proveedor_rut)) || $this->referencedOrders($document) !== []) {
                $created += $this->autoLink($document->id);
            }
        }

        return $created;
    }

    /**
     * Qué parte del monto de la factura corresponde a productos de la orden.
     * Las medidas mandan: un tubo de 40 X 2.0 no es el de 40 X 3.0 de la orden.
     */
    private function coverage(Collection $lines, OdooPurchaseOrder $order): float
    {
        $total = $lines->sum(fn ($line): float => abs((float) $line->monto_item));
        $names = $order->lines->reject->isNote()->pluck('name')->filter()->map(fn ($name) => (string) $name)->all();
        if ($total <= 0 || $names === []) {
            return 0.0;
        }
        $matched = $lines->sum(function ($line) use ($names): float {
            $description = PurchaseReferences::text($line->descripcion);
            foreach ($names as $name) {
                if (! Measures::differ($description, $name) && $this->similarity->score($description, $name) >= PurchaseCandidates::STRONG) {
                    return abs((float) $line->monto_item);
                }
            }

            return 0.0;
        });

        return $matched / $total;
    }

    /**
     * Lo facturado de cada orden, ya descontadas las notas de crédito.
     *
     * @return array<int, float>
     */
    private function invoicedByOrder(array $odooOrderIds): array
    {
        $links = DteOdooOrderLink::active()->whereIn('odoo_order_id', $odooOrderIds)->get();
        [$credits] = $this->credits($links);
        $invoiced = [];
        foreach ($links as $link) {
            $invoiced[$link->odoo_order_id] = ($invoiced[$link->odoo_order_id] ?? 0.0) + $link->amount - ($credits[$link->id] ?? 0.0);
        }

        return $invoiced;
    }

    /**
     * Cuánto le quitan las notas de crédito a cada vínculo. Si una factura paga
     * varias órdenes, la nota se descuenta en el orden en que se vincularon.
     *
     * @return array{0: array<int, float>, 1: array<int, list<array>>} [id de vínculo → crédito, id de factura → notas]
     */
    private function credits(Collection $links): array
    {
        if ($links->isEmpty()) {
            return [[], []];
        }
        $documents = DB::connection('fuelcontrol')->table('gmail_dte_documents')->whereIn('id', $links->pluck('document_id')->unique())
            ->get(['id', 'folio', 'tipo_dte', 'proveedor_rut', 'monto_neto', 'monto_total', 'monto_iva'])->keyBy('id');
        $notes = $this->creditNotes->forDocuments($documents);
        $credits = [];
        foreach ($links->sortBy('id')->groupBy('document_id') as $documentId => $group) {
            if (! isset($notes[$documentId]) || ! $documents->has($documentId)) {
                continue;
            }
            $left = $this->creditNotes->credited($notes[$documentId], $this->net($documents->get($documentId)));
            foreach ($group as $link) {
                $credits[$link->id] = min($link->amount, $left);
                $left -= $credits[$link->id];
            }
        }

        return [$credits, $notes];
    }

    /** Lo que la pantalla dice de cada nota. */
    private function noteSummary(array $notes): array
    {
        return array_map(fn (array $note): array => ['folio' => $note['folio'], 'annuls' => $note['annuls'], 'net' => $note['net'],
            'reason' => $note['reason'], 'shared' => $note['shared']], $notes);
    }

    /**
     * Lo que la factura le cobra a la orden: su neto; en una exenta, el total
     * menos impuestos. El combustible va por el total: las órdenes se hacen con
     * el precio por litro con todo incluido, y el impuesto específico puede ser
     * negativo. Coagra 2306359: neto $7.210.755, total $6.990.565, y la P00274
     * por $6.990.000; contra el neto salía facturada al 103 %.
     */
    private function net(object $document): float
    {
        if ($this->isFuel($document)) {
            return (float) $document->monto_total;
        }

        return (float) $document->monto_neto > 0 ? (float) $document->monto_neto : (float) $document->monto_total - (float) $document->monto_iva;
    }

    /** @var array<int, bool> */
    private array $fuel = [];

    /** Lleva el impuesto específico del diésel (28) o de las gasolinas (35, 52). */
    private function isFuel(object $document): bool
    {
        return $this->fuel[(int) $document->id] ??= DB::connection('fuelcontrol')->table('gmail_dte_document_lines')
            ->where('document_id', $document->id)->whereIn('impuesto_codigo', ['28', '35', '52'])->exists()
            || (bool) preg_match('/<TipoImp>\s*(28|35|52)\s*<\/TipoImp>/', (string) ($document->xml_raw ?? ''));
    }

    private function document(int $id): object
    {
        return DB::connection('fuelcontrol')->table('gmail_dte_documents')->where('id', $id)->firstOrFail();
    }

    private function lines(int $id): Collection
    {
        return DB::connection('fuelcontrol')->table('gmail_dte_document_lines')->where('document_id', $id)->get();
    }

    private function eligible(object $document): bool
    {
        return app(PurchaseReferences::class)->eligible($document);
    }

    /** Odoo guarda «CL78924030-2» o «789240302»; el XML, «78924030-2». */
    private function plainRut(?string $rut): string
    {
        return strtoupper((string) preg_replace('/[^0-9k]/i', '', preg_replace('/^CL/i', '', trim((string) $rut))));
    }

    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['odoo_order' => $message]);
        }
    }
}
