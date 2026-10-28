<?php

namespace App\Console\Commands;

use App\Models\DtePurchaseReference;
use App\Services\DtePurchases\OdooPurchaseReferenceSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncDtePurchaseReferences extends Command
{
    protected $signature = 'dtes:sync-purchase-references {--document= : DTE local a verificar} {--limit=100 : Máximo de documentos recientes} {--dry-run : Verificar sin guardar relaciones}';

    protected $description = 'Verifica en Odoo relaciones factura → orden → partida de solicitud. No modifica Odoo ni inventario.';

    public function handle(OdooPurchaseReferenceSync $sync): int
    {
        if (! config('dte_purchase_references.odoo_sync_enabled')) {
            $this->warn('Verificación desactivada. Configura DTE_PURCHASE_REFERENCES_ODOO_SYNC antes de ejecutarla.');

            return self::FAILURE;
        }
        if (($this->option('document') !== null && ! ctype_digit((string) $this->option('document')))
            || ! ctype_digit((string) $this->option('limit')) || (int) $this->option('limit') < 1 || (int) $this->option('limit') > 10000) {
            $this->error('Usa un identificador numérico y un límite entre 1 y 10000.');

            return self::FAILURE;
        }
        $ids = DB::connection('fuelcontrol')->table('gmail_dte_documents')->whereIn('tipo_dte', [33, 34, 46])
            ->when($this->option('document'), fn ($q, $id) => $q->where('id', $id))
            ->orderByDesc('id')->limit((int) $this->option('limit'))->pluck('id');
        if (! $this->option('document')) {
            // Older active links must also be revalidated; the recent-invoice limit
            // must not make them expire merely because new documents arrived.
            $ids = $ids->merge(DtePurchaseReference::whereNull('revoked_at')->where('source', 'odoo')->pluck('document_id'))->unique();
        }
        $count = 0;
        $refreshed = 0;
        $failures = 0;
        foreach ($ids as $id) {
            try {
                $result = $sync->sync($id, (bool) $this->option('dry-run'));
                $count += $result['linked'];
                $refreshed += $result['refreshed'] ?? 0;
                $this->line('DTE '.$id.': '.$result['reason']);
                foreach ($result['warnings'] ?? [] as $warning) {
                    $this->warn($warning);
                }
            } catch (\Throwable) {
                // Do not expose credentials or request bodies from an upstream exception.
                $failures++;
                $this->warn('DTE '.$id.': no se pudo verificar el origen; las relaciones automáticas previas requieren una nueva comprobación.');
            }
        }
        $this->info(($this->option('dry-run') ? 'Simulación: ' : '').$count.' relaciones nuevas; '.$refreshed.' revalidadas; '.$failures.' errores de consulta.');

        return $failures ? self::FAILURE : self::SUCCESS;
    }
}
