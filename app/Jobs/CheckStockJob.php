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
        Log::info('🔥 CheckStockJob INICIADO por scheduler', [
            'hora' => now()->toDateTimeString(),
        ]);

        // 👉 IDs reales de tus productos
        $productos = [
            13 => ['nombre' => 'Diésel',   'minimo' => 500],
            14 => ['nombre' => 'Gasolina', 'minimo' => 200],
        ];

        foreach ($productos as $productoId => $data) {

            $stockActual = DB::connection('fuelcontrol')
                ->table('productos')
                ->where('id', $productoId)
                ->value('cantidad');

            Log::info('📦 Stock leído', [
                'id' => $productoId,
                'stock'       => $stockActual,
                'minimo'      => $data['minimo'],
            ]);

            // Si no existe o stock OK → no alertar
            if ($stockActual === null || $stockActual >= $data['minimo']) {
                continue;
            }

            // Anti-spam diario
            $yaEnviado = DB::connection('fuelcontrol')
                ->table('stock_alerts')
                ->where('id', $productoId)
                ->where('fecha', now()->toDateString())
                ->exists();

            if ($yaEnviado) {
                Log::info('🔕 Alerta ya enviada hoy', [
                    'id' => $productoId,
                ]);
                continue;
            }

            // 📧 Envío de correo
            Mail::to('s.lopez.epple@gmail.com')
                ->send(new StockBajoMail($data['nombre'], $stockActual));

            Log::warning('📧 Correo de stock bajo ENVIADO', [
                'id' => $productoId,
                'stock'       => $stockActual,
            ]);

            // Registrar alerta
            DB::connection('fuelcontrol')
                ->table('stock_alerts')
                ->insert([
                    'id' => $productoId,
                    'fecha'       => now()->toDateString(),
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
        }

        Log::info('✅ CheckStockJob FINALIZADO');
    }
}
