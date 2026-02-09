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
        /**
         * ⚠️ DEFINICIÓN CORRECTA POR ID (NO POR NOMBRE)
         * Esto evita errores por tildes, mayúsculas, collation, etc.
         */
        $productos = [
            13 => ['nombre' => 'Diésel', 'minimo' => 500],
            14 => ['nombre' => 'Gasolina', 'minimo' => 200],
        ];

        foreach ($productos as $productoId => $data) {

            // 🔍 Obtener stock real
            $stockActual = DB::connection('fuelcontrol')
                ->table('productos')
                ->where('id', $productoId)
                ->value('cantidad');

            Log::info('CheckStockJob ejecutado', [
                'producto_id' => $productoId,
                'stock' => $stockActual,
                'minimo' => $data['minimo'],
            ]);

            // Si no existe o el stock está OK → no alertar
            if ($stockActual === null || $stockActual >= $data['minimo']) {
                continue;
            }

            // 🛑 Anti-spam: solo 1 correo por producto por día
            $yaEnviado = DB::connection('fuelcontrol')
                ->table('stock_alerts')
                ->where('producto_id', $productoId)
                ->where('fecha', now()->toDateString())
                ->exists();

            if ($yaEnviado) {
                Log::info('Alerta ya enviada hoy', [
                    'producto_id' => $productoId,
                    'fecha' => now()->toDateString(),
                ]);
                continue;
            }

            // 📧 Envío de correo
            Mail::to('s.lopez.epple@gmail.com')
                ->send(new StockBajoMail($data['nombre'], $stockActual));

            // 📝 Registro de alerta enviada
            DB::connection('fuelcontrol')
                ->table('stock_alerts')
                ->insert([
                    'producto_id' => $productoId,
                    'fecha' => now()->toDateString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            Log::warning('Correo de stock bajo enviado', [
                'producto_id' => $productoId,
                'stock' => $stockActual,
            ]);
        }
    }
}
