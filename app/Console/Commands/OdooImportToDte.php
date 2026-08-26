<?php

namespace App\Console\Commands;

use App\Models\OdooAccountMove;
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
                ['Total en gmail_dte_documents', $total_dte],
            ]
        );

        return self::SUCCESS;
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
