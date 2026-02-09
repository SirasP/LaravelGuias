<?php

namespace App\Jobs;

use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\StockBajoMail;

class CheckStockJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Log::info('🔥 CheckStockJob INICIADO por scheduler');

        $productos = [
            'Diésel' => 500,
            'Gasolina' => 200,
        ];

        foreach ($productos as $nombreProducto => $minimo) {

            $stockActual = DB::connection('fuelcontrol')
                ->table('productos')
                ->where('nombre', $nombreProducto)
                ->value('cantidad');

            Log::info('📦 Stock leído', [
                'producto' => $nombreProducto,
                'stock' => $stockActual,
                'minimo' => $minimo,
            ]);

            if ($stockActual === null || $stockActual >= $minimo) {
                continue;
            }

            // ✅ Anti-spam correcto
            $yaEnviado = DB::connection('fuelcontrol')
                ->table('stock_alerts')
                ->where('producto', $nombreProducto)
                ->where('fecha', now()->toDateString())
                ->exists();

            if ($yaEnviado) {
                Log::info('🔕 Ya enviado hoy', ['producto' => $nombreProducto]);
                continue;
            }

            // 📧 Mail
            Mail::to('s.lopez.epple@gmail.com')
                ->send(new StockBajoMail($nombreProducto, $stockActual));

            // ✅ Insert correcto (NO se inserta id)
            DB::connection('fuelcontrol')
                ->table('stock_alerts')
                ->insert([
                    'producto' => $nombreProducto,
                    'fecha' => now()->toDateString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            Log::warning('📧 Correo enviado', ['producto' => $nombreProducto]);
        }

        Log::info('✅ CheckStockJob FINALIZADO');
    }
}
