<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\StockBajoMail;

class CheckStockJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        $productos = [
            'Diesel' => 500,
            'Gasolina' => 200,
        ];

        foreach ($productos as $producto => $minimo) {

            // 🔴 ACÁ AJUSTAS A TU QUERY REAL
            $stockActual = DB::connection('fuelcontrol')
                ->table('stocks')
                ->where('producto', $producto)
                ->value('litros');

            if ($stockActual >= $minimo) {
                continue;
            }

            $yaEnviado = DB::table('stock_alerts')
                ->where('producto', $producto)
                ->where('fecha', now()->toDateString())
                ->exists();

            if ($yaEnviado) {
                continue;
            }

            Mail::to('s.lopez.epple@gmail.com')
                ->send(new StockBajoMail($producto, $stockActual));

            DB::table('stock_alerts')->insert([
                'producto' => $producto,
                'fecha' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}

