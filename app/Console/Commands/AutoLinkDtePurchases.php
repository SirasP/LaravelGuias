<?php

namespace App\Console\Commands;

use App\Services\DtePurchases\PurchaseCandidates;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AutoLinkDtePurchases extends Command
{
    protected $signature = 'dtes:auto-link-purchases {--days=90 : Facturas de los últimos días}';

    protected $description = 'Vincula facturas con partidas de solicitudes cuando la coincidencia no deja dudas. No modifica Odoo ni inventario.';

    public function handle(PurchaseCandidates $candidates): int
    {
        $since = now()->subDays(max(1, (int) $this->option('days')))->toDateString();
        $ids = DB::connection('fuelcontrol')->table('gmail_dte_documents')->whereIn('tipo_dte', [33, 34, 46])
            ->whereDate('fecha_factura', '>=', $since)
            // Oldest first: an earlier invoice takes its items before a later one is considered.
            ->orderBy('fecha_factura')->orderBy('id')->pluck('id');
        $created = 0;
        foreach ($ids as $id) {
            $created += $candidates->autoLink($id);
        }
        $this->info($created.' relaciones nuevas en '.$ids->count().' facturas.');

        return self::SUCCESS;
    }
}
