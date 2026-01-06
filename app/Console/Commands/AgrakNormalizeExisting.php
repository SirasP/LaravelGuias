<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AgrakRegistro;
use App\Support\AgrakNormalizer;

class AgrakNormalizeExisting extends Command
{
    protected $signature = 'agrak:normalize-existing';
    protected $description = 'Normaliza patente, chofer y exportadora en agrak_registros';

    public function handle()
    {
        $this->info('Iniciando normalización Agrak...');

        AgrakRegistro::chunk(200, function ($rows) {
            foreach ($rows as $r) {

                $r->patente_norm = AgrakNormalizer::patente($r->patente_camion);
                $r->chofer_norm = AgrakNormalizer::nombre($r->nombre_chofer);
                $r->exportadora_norm = AgrakNormalizer::exportadora(
                    $r->exportadora_1,
                    $r->exportadora_2
                );

                $r->estado_norm = (
                    $r->patente_norm &&
                    $r->chofer_norm &&
                    $r->exportadora_norm
                ) ? 'OK' : 'PENDIENTE';

                $r->save();
            }
        });

        $this->info('Normalización Agrak finalizada');
    }
}
