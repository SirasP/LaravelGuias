<?php

namespace App\Console\Commands;

use App\Models\OdooAccountMove;
use App\Models\OdooAccountMoveLine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class OdooImportToDte extends Command
{
    protected $signature = 'odoo:import-dte';

    protected $description = 'Importa odoo_account_moves a gmail_dte_documents sin duplicar registros existentes';

    // Mapa move_type → tipo_dte chileno
    private const TIPO_DTE_MAP = [
        'in_invoice' => 33,
        'out_invoice' => 33,
        'in_refund' => 61,
        'out_refund' => 61,
    ];

    // Mapa payment_state de Odoo → payment_status de gmail_dte_documents
    private const PAYMENT_STATUS_MAP = [
        'paid' => 'pagado',
        'in_payment' => 'pagado',
        'not_paid' => 'sin_pagar',
        'partial' => 'sin_pagar',
        'reversed' => 'sin_pagar',
    ];

    // Mapa state de Odoo → workflow_status de gmail_dte_documents
    private const WORKFLOW_STATUS_MAP = [
        'posted' => 'aceptado',
        'draft' => 'borrador',
        'cancel' => 'cancelado',
    ];

    public function handle(): int
    {
        $this->info('Importando documentos de Odoo a gmail_dte_documents...');

        $dte = DB::connection('fuelcontrol');

        // Pre-cargar hashes ya existentes (odoo_XXXX) para no re-insertar y
        // poder refrescar su estado de pago desde Odoo.
        $existingHashes = $dte->table('gmail_dte_documents')
            ->where('hash_unico', 'like', 'odoo_%')
            ->get(['id', 'hash_unico'])
            ->groupBy('hash_unico')
            ->map(fn ($rows) => $rows->pluck('id')->all())
            ->toArray();

        // Pre-cargar combinaciones folio+RUT normalizado de documentos recibidos
        // por Gmail. El RUT puede venir con puntos y guion en el XML, mientras que
        // Odoo lo entrega sin formato.
        $existingGmail = $dte->table('gmail_dte_documents')
            ->where(function ($query) {
                $query->whereNull('hash_unico')
                    ->orWhere('hash_unico', 'not like', 'odoo_%');
            })
            ->whereNotNull('proveedor_rut')
            ->get(['id', 'folio', 'proveedor_rut'])
            ->reduce(function (array $index, object $document) {
                $key = $this->documentKey($document->folio, $document->proveedor_rut);

                if ($key !== null) {
                    $index[$key][] = $document->id;
                }

                return $index;
            }, []);

        $moves = OdooAccountMove::whereIn('move_type', array_keys(self::TIPO_DTE_MAP))
            ->orderBy('id')
            ->get();

        $inserted = 0;
        $updated = 0;
        $unchanged = 0;
        $duplicate = 0;

        foreach ($moves as $move) {
            $hash = 'odoo_'.$move->odoo_id;
            $paymentStatus = self::PAYMENT_STATUS_MAP[$move->payment_state ?? 'not_paid'] ?? 'sin_pagar';

            // 1) Ya fue importado antes: mantener el estado de pago local alineado
            // con Odoo sin modificar nada en Odoo.
            if (isset($existingHashes[$hash])) {
                $changed = $dte->table('gmail_dte_documents')
                    ->whereIn('id', $existingHashes[$hash])
                    ->where('payment_status', '<>', $paymentStatus)
                    ->update([
                        'payment_status' => $paymentStatus,
                        'updated_at' => now(),
                    ]);

                $updated += $changed;
                $unchanged += $changed === 0 ? 1 : 0;

                continue;
            }

            // 2) Ya existe en Gmail con mismo folio + RUT
            $rutNorm = $this->normalizeRut($move->partner_vat);

            if ($rutNorm !== '' && $move->folio) {
                $key = $this->documentKey($move->folio, $rutNorm);

                if (isset($existingGmail[$key])) {
                    // Vincular el DTE existente y al mismo tiempo reflejar el pago
                    // informado por Odoo.
                    $dte->table('gmail_dte_documents')
                        ->whereIn('id', $existingGmail[$key])
                        ->update([
                            'hash_unico' => $hash,
                            'payment_status' => $paymentStatus,
                            'updated_at' => now(),
                        ]);
                    $duplicate++;
                    $existingHashes[$hash] = $existingGmail[$key];

                    continue;
                }
            }

            // 3) Insertar registro nuevo
            $tipoDte = self::TIPO_DTE_MAP[$move->move_type] ?? 33;
            $workflowStatus = self::WORKFLOW_STATUS_MAP[$move->state ?? 'draft'] ?? 'borrador';

            // Calcular neto e IVA aproximados (19% IVA Chile)
            $total = (float) $move->amount_total;
            $neto = round($total / 1.19);
            $iva = $total - $neto;

            $dte->table('gmail_dte_documents')->insert([
                'gmail_message_id' => null,
                'xml_filename' => $move->name ?? "ODO-{$move->odoo_id}",
                'xml_path' => null,
                'xml_raw' => null,
                'hash_unico' => $hash,
                'tipo_dte' => $tipoDte,
                'folio' => $move->folio ? (string) $move->folio : null,
                'proveedor_rut' => $rutNorm,
                'proveedor_nombre' => $move->partner_name,
                'fecha_factura' => $move->invoice_date?->format('Y-m-d'),
                'fecha_contable' => $move->invoice_date?->format('Y-m-d'),
                'fecha_vencimiento' => null,
                'referencia' => $move->ref,
                'monto_neto' => $neto,
                'monto_iva' => $iva,
                'monto_total' => $total,
                'payment_status' => $paymentStatus,
                'workflow_status' => $workflowStatus,
                'inventory_status' => 'pendiente',
                'paid_at' => null,
                'stock_posted_at' => null,
                'stock_movement_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $inserted++;
            $existingHashes[$hash] = [];
        }

        [$filled, $unbalanced] = $this->fillLinesFromOdoo($dte);

        $total_dte = $dte->table('gmail_dte_documents')->count();

        $this->info('');
        $this->info('✅ Importación completada.');
        $this->table(
            ['Resultado', 'Cantidad'],
            [
                ['Insertados desde Odoo', $inserted],
                ['Duplicados vinculados', $duplicate],
                ['Estados actualizados', $updated],
                ['Ya existían sin cambios', $unchanged],
                ['Detalle armado desde Odoo', $filled],
                ['Detalle que no cuadra con el total', $unbalanced],
                ['Total en gmail_dte_documents', $total_dte],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * Arma el detalle de las facturas que sólo existen en Odoo.
     *
     * Son las que nunca llegaron con XML al correo y alguien digitó en Odoo:
     * entraban acá con el puro encabezado y un neto adivinado dividiendo por
     * 1,19. Las líneas de producto de Odoo traen cantidad, unidad y precio.
     * Sólo se arma si producto más impuestos da el total de la factura, y una
     * factura que ya tiene líneas —por XML o de una pasada anterior— no se toca.
     *
     * @return array{0: int, 1: int} facturas completadas y facturas que no cuadran
     */
    private function fillLinesFromOdoo($dte): array
    {
        $documents = $dte->table('gmail_dte_documents as d')
            ->where('d.hash_unico', 'like', 'odoo_%')
            ->whereNull('d.xml_raw')
            ->whereNotExists(fn ($query) => $query->from('gmail_dte_document_lines as l')->whereColumn('l.document_id', 'd.id'))
            ->get(['d.id', 'd.hash_unico', 'd.monto_total']);

        $filled = 0;
        $unbalanced = 0;

        foreach ($documents as $document) {
            $lines = OdooAccountMoveLine::where('move_odoo_id', (int) substr($document->hash_unico, 5))->orderBy('odoo_id')->get();
            $products = $lines->where('display_type', 'product')->values();

            // Sin detalle todavía: la sincronización anterior a este cambio no lo traía.
            if ($products->isEmpty() || $products->contains(fn ($line) => $line->quantity === null)) {
                continue;
            }

            // En el asiento, los impuestos van del mismo lado que los productos si
            // suman (IVA) y del otro si restan (retención de honorarios); en una
            // nota de crédito todo va al revés. El signo de los productos lo dice.
            $sign = $products->sum(fn ($line) => $line->debit - $line->credit) < 0 ? -1 : 1;
            $taxLines = $lines->where('display_type', 'tax');
            $taxes = round($sign * $taxLines->sum(fn ($line) => $line->debit - $line->credit));
            $neto = round($products->sum(fn ($line) => (float) $line->price_subtotal));
            $iva = round($sign * $taxLines->filter(fn ($line) => $this->vatRate((string) $line->name) !== null || str_contains(mb_strtoupper((string) $line->name), 'IVA'))
                ->sum(fn ($line) => $line->debit - $line->credit));

            if (abs($neto + $taxes - (float) $document->monto_total) > 1) {
                $unbalanced++;

                continue;
            }

            $dte->transaction(function () use ($dte, $document, $products, $neto, $iva) {
                foreach ($products as $index => $line) {
                    [$codigo, $descripcion] = $this->splitCode((string) $line->name);
                    $taxes = $line->taxes ?? [];
                    $tasa = collect($taxes)
                        ->map(fn ($tax) => $this->vatRate((string) $tax))
                        ->filter(fn ($rate) => $rate !== null)
                        ->first();

                    $dte->table('gmail_dte_document_lines')->insert([
                        'document_id' => $document->id,
                        'nro_linea' => $index + 1,
                        'codigo' => $codigo,
                        'descripcion' => $descripcion,
                        'cantidad' => (float) $line->quantity,
                        'unidad' => $this->unit($line->uom_name),
                        'precio_unitario' => (float) $line->price_unit,
                        'monto_item' => (float) $line->price_subtotal,
                        'impuesto_codigo' => null,
                        'impuesto_tasa' => $tasa,
                        'impuesto_label' => $this->taxLabel($taxes, $tasa),
                        'es_exento' => $taxes === [] ? 1 : 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // El neto y el IVA de Odoo reemplazan los que se adivinaron al importar.
                $dte->table('gmail_dte_documents')->where('id', $document->id)
                    ->update(['monto_neto' => $neto, 'monto_iva' => $iva, 'updated_at' => now()]);
            });

            $filled++;
        }

        return [$filled, $unbalanced];
    }

    /** «IVA 19%» cuando es sólo IVA; si hay ILA o retención, se nombran todos. */
    private function taxLabel(array $taxes, ?float $tasa): string
    {
        if ($taxes === []) {
            return 'Exento';
        }

        if (count($taxes) === 1 && $tasa !== null) {
            return 'IVA '.rtrim(rtrim((string) $tasa, '0'), '.').'%';
        }

        return implode(', ', array_map(fn ($tax) => preg_replace(['/\bVAT\b/', '/\bWH\b/'], ['IVA', 'Ret.'], (string) $tax), $taxes));
    }

    /** Odoo nombra el IVA según el idioma del usuario: «IVA 19% Compra» o «19% VAT». */
    private function vatRate(string $tax): ?float
    {
        return preg_match('/IVA\D*?(\d+(?:[.,]\d+)?)\s*%|(\d+(?:[.,]\d+)?)\s*%\s*VAT/i', $tax, $m)
            ? (float) str_replace(',', '.', $m[1] !== '' ? $m[1] : $m[2])
            : null;
    }

    /** «[6510] HUINCHA DE TELA NEGRA» trae el código del producto delante. */
    private function splitCode(string $name): array
    {
        $name = trim($name);

        return preg_match('/^\[([^\]]+)\]\s*(.+)$/s', $name, $m) ? [trim($m[1]), trim($m[2])] : [null, $name ?: null];
    }

    private function unit(?string $uom): ?string
    {
        $uom = trim((string) $uom);

        return in_array(mb_strtolower($uom), ['units', 'unit', 'unidades', 'unidad'], true) ? 'UN' : (mb_strtoupper($uom) ?: null);
    }

    private function documentKey(mixed $folio, ?string $rut): ?string
    {
        $folio = trim((string) $folio);
        $rut = $this->normalizeRut($rut);

        return $folio !== '' && $rut !== '' ? "{$folio}|{$rut}" : null;
    }

    private function normalizeRut(?string $rut): string
    {
        return strtoupper((string) preg_replace('/[^0-9k]/i', '', (string) $rut));
    }
}
