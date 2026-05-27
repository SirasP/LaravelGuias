<?php

namespace App\Console\Commands;

use App\Models\OdooAccountMove;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class OdooImportToDte extends Command
{
    protected $signature   = 'odoo:import-dte';
    protected $description = 'Importa odoo_account_moves a gmail_dte_documents sin duplicar registros existentes';

    // Mapa move_type → tipo_dte chileno
    private const TIPO_DTE_MAP = [
        'in_invoice'  => 33,
        'out_invoice' => 33,
        'in_refund'   => 61,
        'out_refund'  => 61,
    ];

    // Mapa payment_state de Odoo → payment_status de gmail_dte_documents
    private const PAYMENT_STATUS_MAP = [
        'paid'       => 'pagado',
        'in_payment' => 'pagado',
        'not_paid'   => 'sin_pagar',
        'partial'    => 'sin_pagar',
        'reversed'   => 'sin_pagar',
    ];

    // Mapa state de Odoo → workflow_status de gmail_dte_documents
    private const WORKFLOW_STATUS_MAP = [
        'posted' => 'aceptado',
        'draft'  => 'borrador',
        'cancel' => 'cancelado',
    ];

    public function handle(): int
    {
        $this->info('Importando documentos de Odoo a gmail_dte_documents...');

        $dte = DB::connection('fuelcontrol');

        // Pre-cargar hashes ya existentes (odoo_XXXX) para no re-insertar
        $existingHashes = $dte->table('gmail_dte_documents')
            ->where('hash_unico', 'like', 'odoo_%')
            ->pluck('hash_unico')
            ->flip()
            ->toArray();

        // Pre-cargar combinaciones folio+rut de documentos de Gmail (para evitar duplicados reales)
        $existingGmail = $dte->table('gmail_dte_documents')
            ->where('hash_unico', 'not like', 'odoo_%')
            ->whereNotNull('proveedor_rut')
            ->get(['folio', 'proveedor_rut'])
            ->mapWithKeys(fn($r) => ["{$r->folio}|{$r->proveedor_rut}" => true])
            ->toArray();

        $moves = OdooAccountMove::whereIn('move_type', array_keys(self::TIPO_DTE_MAP))
            ->orderBy('id')
            ->get();

        $inserted  = 0;
        $skipped   = 0;
        $duplicate = 0;

        foreach ($moves as $move) {
            $hash = 'odoo_' . $move->odoo_id;

            // 1) Ya fue importado antes
            if (isset($existingHashes[$hash])) {
                $skipped++;
                continue;
            }

            // 2) Ya existe en Gmail con mismo folio + RUT
            $rutNorm = $move->partner_vat
                ? strtoupper(preg_replace('/[.\-\s]/', '', $move->partner_vat))
                : null;

            if ($rutNorm && $move->folio) {
                $key = "{$move->folio}|{$rutNorm}";
                if (isset($existingGmail[$key])) {
                    // Actualizar hash para que quede vinculado
                    $dte->table('gmail_dte_documents')
                        ->where('folio', (string) $move->folio)
                        ->where('proveedor_rut', $rutNorm)
                        ->update(['hash_unico' => $hash]);
                    $duplicate++;
                    $existingHashes[$hash] = true;
                    continue;
                }
            }

            // 3) Insertar registro nuevo
            $tipoDte       = self::TIPO_DTE_MAP[$move->move_type] ?? 33;
            $paymentStatus = self::PAYMENT_STATUS_MAP[$move->payment_state ?? 'not_paid'] ?? 'sin_pagar';
            $workflowStatus = self::WORKFLOW_STATUS_MAP[$move->state ?? 'draft'] ?? 'borrador';

            // Calcular neto e IVA aproximados (19% IVA Chile)
            $total   = (float) $move->amount_total;
            $neto    = round($total / 1.19);
            $iva     = $total - $neto;

            $dte->table('gmail_dte_documents')->insert([
                'gmail_message_id'  => null,
                'xml_filename'      => $move->name ?? "ODO-{$move->odoo_id}",
                'xml_path'          => null,
                'xml_raw'           => null,
                'hash_unico'        => $hash,
                'tipo_dte'          => $tipoDte,
                'folio'             => $move->folio ? (string) $move->folio : null,
                'proveedor_rut'     => $rutNorm,
                'proveedor_nombre'  => $move->partner_name,
                'fecha_factura'     => $move->invoice_date?->format('Y-m-d'),
                'fecha_contable'    => $move->invoice_date?->format('Y-m-d'),
                'fecha_vencimiento' => null,
                'referencia'        => $move->ref,
                'monto_neto'        => $neto,
                'monto_iva'         => $iva,
                'monto_total'       => $total,
                'payment_status'    => $paymentStatus,
                'workflow_status'   => $workflowStatus,
                'inventory_status'  => 'pendiente',
                'paid_at'           => null,
                'stock_posted_at'   => null,
                'stock_movement_id' => null,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            $inserted++;
            $existingHashes[$hash] = true;
        }

        $total_dte = $dte->table('gmail_dte_documents')->count();

        $this->info('');
        $this->info('✅ Importación completada.');
        $this->table(
            ['Resultado', 'Cantidad'],
            [
                ['Insertados desde Odoo', $inserted],
                ['Duplicados vinculados', $duplicate],
                ['Ya existían (salteados)', $skipped],
                ['Total en gmail_dte_documents', $total_dte],
            ]
        );

        return self::SUCCESS;
    }
}
