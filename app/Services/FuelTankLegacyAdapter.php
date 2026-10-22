<?php

namespace App\Services;

use App\Http\Controllers\FuelControl\EstanqueController;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Conserva las URLs móviles existentes y activa el contrato nuevo por configuración. */
class FuelTankLegacyAdapter
{
    public function handle(string $action, Request $request): ?Response
    {
        if (! config('fuelcontrol.estanques')) {
            return null;
        }

        $controller = app(EstanqueController::class);
        if ($action === 'catalogo') {
            return $request->query('estanques') === '1' ? $controller->catalog() : null;
        }

        if ($action === 'salidas') {
            $inbox = app(FuelPendingQueueInbox::class)->receive($request);
            if ($inbox !== null) {
                return $inbox;
            }

            // El servicio sólo recupera diésel antiguo con operador y destino confirmados.
            return $controller->store($request, app(FuelTankService::class));
        }

        throw new \InvalidArgumentException('Operación móvil desconocida.');
    }
}
