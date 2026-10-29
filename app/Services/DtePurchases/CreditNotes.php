<?php

namespace App\Services\DtePurchases;

use App\Services\PurchaseRequests\Products\ProductSimilarity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Las notas de crédito que corrigen una factura.
 *
 * Supplytech facturó la P00184 en la 3274 y la anuló con la NC 92; la 3278 la
 * reemplazó. Sin mirar la nota, la orden aparecía facturada dos veces. Sólo
 * cuentan las notas cuyo XML dice qué factura corrigen: las que entran sólo
 * desde Odoo no traen esa referencia.
 */
class CreditNotes
{
    /** Código de referencia del SII: 1 anula, 2 corrige texto, 3 corrige montos. */
    public const ANNULS = '1';

    public const TEXT_ONLY = '2';

    /** @var array<string, list<array>>|null rut|tipo|folio de la factura → notas */
    private ?array $index = null;

    public function __construct(private ProductSimilarity $similarity) {}

    /**
     * Las notas de cada factura.
     *
     * @return array<int, list<array{id: int, folio: string, date: ?string, code: string, reason: ?string, annuls: bool, net: float, shared: bool, lines: list<array{description: string, quantity: float, amount: float}>}>>
     */
    public function forDocuments(iterable $documents): array
    {
        $notes = [];
        foreach ($documents as $document) {
            $found = $this->index()[$this->key($document->proveedor_rut, $document->tipo_dte, $document->folio)] ?? [];
            if ($found !== []) {
                $notes[(int) $document->id] = $found;
            }
        }

        return $notes;
    }

    /**
     * Cuánto le resta la nota al monto de la factura: todo si la anula, su neto
     * si corrige montos y es la única factura que corrige.
     */
    public function credited(array $notes, float $invoiceNet): float
    {
        $credited = 0.0;
        foreach ($notes as $note) {
            if ($note['annuls']) {
                return $invoiceNet;
            }
            if (! $note['shared']) {
                $credited += $note['net'];
            }
        }

        return min($invoiceNet, $credited);
    }

    /**
     * Unidades que devolvió la nota de una línea de la factura: todas si la
     * anula; si no, las de cada línea de la nota que más se parece a ésta entre
     * las de su factura. La nota ya dice qué factura corrige, así que basta con
     * parecerse más que las otras líneas: «SPRAY 300MLEXCELENTE» de Odoo y
     * «SPRAY 300ML» de la nota se parecen 0,84.
     *
     * @param  Collection<int, object>  $invoiceLines  todas las líneas de la factura
     */
    public function returned(array $notes, object $line, Collection $invoiceLines): float
    {
        $returned = 0.0;
        foreach ($notes as $note) {
            if ($note['annuls']) {
                return (float) $line->cantidad;
            }
            foreach ($note['lines'] as $noteLine) {
                if ($this->bestLine($noteLine['description'], $invoiceLines)?->id === $line->id) {
                    $returned += $noteLine['quantity'];
                }
            }
        }

        return min((float) $line->cantidad, $returned);
    }

    /** La línea de la factura que corresponde a una línea de la nota, si alguna. */
    private function bestLine(string $description, Collection $invoiceLines): ?object
    {
        $candidates = $invoiceLines->reject(fn ($line): bool => Measures::differ(PurchaseReferences::text($line->descripcion), $description));
        if ($invoiceLines->count() === 1) {
            return $candidates->first();
        }
        $best = null;
        $score = PurchaseCandidates::SUGGEST;
        foreach ($candidates as $line) {
            $value = $this->similarity->score(PurchaseReferences::text($line->descripcion), $description);
            if ($value >= $score) {
                [$best, $score] = [$line, $value];
            }
        }

        return $best;
    }

    private function index(): array
    {
        if ($this->index !== null) {
            return $this->index;
        }
        $fuel = DB::connection('fuelcontrol');
        $notes = $fuel->table('gmail_dte_documents')->where('tipo_dte', 61)->whereNotNull('xml_raw')
            ->where('xml_raw', 'like', '%<Referencia>%')
            ->get(['id', 'folio', 'proveedor_rut', 'fecha_factura', 'monto_neto', 'xml_raw']);
        $lines = $fuel->table('gmail_dte_document_lines')->whereIn('document_id', $notes->pluck('id'))->get()->groupBy('document_id');
        $this->index = [];
        foreach ($notes as $note) {
            $references = $this->references((string) $note->xml_raw);
            $shared = count($references) > 1;
            foreach ($references as [$type, $folio, $code, $reason]) {
                if ($code === self::TEXT_ONLY) {
                    continue;
                }
                $this->index[$this->key($note->proveedor_rut, $type, $folio)][] = [
                    'id' => (int) $note->id, 'folio' => (string) $note->folio, 'date' => $note->fecha_factura,
                    'code' => $code, 'reason' => $reason, 'annuls' => $code === self::ANNULS,
                    'net' => (float) $note->monto_neto, 'shared' => $shared,
                    'lines' => ($lines->get($note->id) ?? new Collection)->map(fn ($line): array => [
                        'description' => PurchaseReferences::text($line->descripcion),
                        'quantity' => (float) $line->cantidad, 'amount' => (float) $line->monto_item,
                    ])->values()->all(),
                ];
            }
        }

        return $this->index;
    }

    /**
     * Las facturas que corrige: tipo, folio, código y razón de cada referencia.
     *
     * @return list<array{0: int, 1: string, 2: string, 3: ?string}>
     */
    private function references(string $xml): array
    {
        preg_match_all('/<Referencia>(.*?)<\/Referencia>/s', $xml, $blocks);
        $references = [];
        foreach ($blocks[1] as $block) {
            $tag = fn (string $name): ?string => preg_match("/<{$name}>\\s*([^<]*?)\\s*<\\/{$name}>/", $block, $m) ? $m[1] : null;
            $type = (int) $tag('TpoDocRef');
            if (in_array($type, [33, 34, 46], true) && filled($tag('FolioRef'))) {
                $references[] = [$type, (string) $tag('FolioRef'), (string) $tag('CodRef'), $tag('RazonRef')];
            }
        }

        return $references;
    }

    private function key(?string $rut, mixed $type, mixed $folio): string
    {
        $plain = strtoupper((string) preg_replace('/[^0-9k]/i', '', preg_replace('/^CL/i', '', trim((string) $rut))));
        $folio = trim((string) $folio);

        return $plain.'|'.(int) $type.'|'.(ctype_digit($folio) ? (ltrim($folio, '0') ?: '0') : mb_strtoupper($folio));
    }
}
