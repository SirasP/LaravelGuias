<?php

namespace App\Http\Controllers\FuelControl;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Vehiculo;

class VehiculoController extends Controller
{
    public function index()
    {
        $vehiculos = Vehiculo::on('fuelcontrol')
            ->orderBy('patente')
            ->paginate(10);

        return view('fuelcontrol.vehiculos.index', compact('vehiculos'));
    }

    public function create()
    {
        return view('fuelcontrol.vehiculos.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'patente' => 'required|string|max:10',
            'descripcion' => 'nullable|string|max:255',
            'tipo' => 'required|string|max:50',
        ]);

        Vehiculo::on('fuelcontrol')->create([
            'patente' => strtoupper($request->patente),
            'descripcion' => $request->descripcion,
            'tipo' => $request->tipo,
            'fecha_registro' => now(),
            'usuario' => auth()->user()->name ?? 'sistema',
        ]);

        return redirect()
            ->route('fuelcontrol.vehiculos.index')
            ->with('success', 'Vehículo creado correctamente');
    }

    public function show($id)
    {
        $vehiculo = Vehiculo::on('fuelcontrol')->findOrFail($id);

        return view('fuelcontrol.vehiculos.show', compact('vehiculo'));
    }

    public function edit($id)
    {
        $vehiculo = Vehiculo::on('fuelcontrol')->findOrFail($id);

        return view('fuelcontrol.vehiculos.edit', compact('vehiculo'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'patente' => 'required|string|max:10',
            'descripcion' => 'nullable|string|max:255',
            'tipo' => 'required|string|max:50',
        ]);

        $vehiculo = Vehiculo::on('fuelcontrol')->findOrFail($id);

        $vehiculo->update([
            'patente' => strtoupper($request->patente),
            'descripcion' => $request->descripcion,
            'tipo' => $request->tipo,
            'usuario' => auth()->user()->name ?? 'sistema',
        ]);

        return redirect()
            ->route('fuelcontrol.vehiculos.index')
            ->with('success', 'Vehículo actualizado correctamente');
    }

    public function destroy($id)
    {
        $vehiculo = Vehiculo::on('fuelcontrol')->findOrFail($id);

        $vehiculo->delete();

        return redirect()
            ->route('fuelcontrol.vehiculos.index')
            ->with('success', 'Vehículo eliminado correctamente');
    }
}
