<?php

namespace App\Http\Controllers\FuelControl;

use App\Http\Controllers\Controller;
use App\Services\FuelTankService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EstanqueController extends Controller
{
    public function catalog()
    {
        $db = DB::connection('fuelcontrol');

        return response()->json([
            'entorno' => app()->environment(),
            'productos' => $db->table('productos')->orderBy('nombre')->get(['id', 'nombre', 'cantidad']),
            'estanques' => $db->table('fuel_estanques')->orderBy('id')->get(),
        ]);
    }

    public function vehicles()
    {
        return response()->json(DB::connection('fuelcontrol')->table('vehiculos')->where('is_active', 1)->orderBy('patente')->get(['id', 'patente', 'descripcion', 'tipo']));
    }

    public function store(Request $request, FuelTankService $service)
    {
        $data = $request->validate([
            // Las apps anteriores reenvían su cola completa, que puede superar 20 salidas.
            'salidas' => 'required|array|min:1|max:500',
            'salidas.*.uuid' => 'required|uuid|distinct',
            'salidas.*.producto_id' => 'required|integer|exists:fuelcontrol.productos,id',
            'salidas.*.vehiculo_id' => 'required|integer|exists:fuelcontrol.vehiculos,id',
            'salidas.*.estanque_id' => 'nullable|integer',
            'salidas.*.cantidad' => 'required|numeric|min:0.01|decimal:0,2',
            'salidas.*.usuario' => 'required|string|max:100',
            'salidas.*.odometro' => 'required|numeric|min:0',
            'salidas.*.odometro_bomba' => 'required|numeric|min:0',
            'salidas.*.fecha_movimiento' => 'nullable|date',
        ]);

        return response()->json(['status' => 'ok', 'detalle' => $service->salidas($data['salidas'])]);
    }
}
