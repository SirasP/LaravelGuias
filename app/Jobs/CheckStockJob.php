<?php

namespace App\Jobs;

use App\Mail\StockBajoMail;
use App\Services\InventoryConfigService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CheckStockJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(InventoryConfigService $settings): void
    {
        Log::info('CheckStockJob iniciado');

        $emails = $settings->getLowStockEmails();

        $productos = [
            'Diésel'   => $settings->getFuelMinimo('diesel'),
            'Gasolina' => $settings->getFuelMinimo('gasolina'),
        ];

        foreach ($productos as $nombreProducto => $minimo) {
            $stockActual = DB::connection('fuelcontrol')
                ->table('productos')
                ->where('nombre', $nombreProducto)
                ->value('cantidad');

            Log::info('Stock leído', [
                'producto' => $nombreProducto,
                'stock'    => $stockActual,
                'minimo'   => $minimo,
            ]);

            if ($stockActual === null || $stockActual >= $minimo) {
                continue;
            }

            $yaEnviado = DB::connection('fuelcontrol')
                ->table('stock_alerts')
                ->where('producto', $nombreProducto)
                ->where('fecha', now()->toDateString())
                ->exists();

            if ($yaEnviado) {
                Log::info('Ya notificado hoy', ['producto' => $nombreProducto]);
                continue;
            }

            Mail::to($emails[0])
                ->cc(array_slice($emails, 1))
                ->send(new StockBajoMail(
                    producto: $nombreProducto,
                    stock: (float) $stockActual,
                    stockMinimo: (float) $minimo,
                    codigoProducto: null,
                    categoria: null
                ));

            DB::connection('fuelcontrol')
                ->table('stock_alerts')
                ->insert([
                    'producto'   => $nombreProducto,
                    'fecha'      => now()->toDateString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            Log::warning('Alerta enviada', ['producto' => $nombreProducto, 'emails' => $emails]);
        }

        Log::info('CheckStockJob finalizado');
    }
}
