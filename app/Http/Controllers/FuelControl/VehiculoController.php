<?php

namespace App\Http\Controllers\FuelControl;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Vehiculo;

class VehiculoController extends Controller
{
    public function index(Request $request)
    {
        // 🔹 Query base
        $baseQuery = Vehiculo::on('fuelcontrol');

        // 🔹 Filtros compartidos
        $baseQuery->when($request->filled('search'), function ($q) use ($request) {
            $q->where(function ($sub) use ($request) {
                $sub->where('patente', 'like', '%' . $request->search . '%')
                    ->orWhere('descripcion', 'like', '%' . $request->search . '%');
            });
        });

        $baseQuery->when($request->filled('tipo'), function ($q) use ($request) {
            $q->where('tipo', $request->tipo);
        });

        // 🔹 Listado (clonar para no romper la query)
        $vehiculos = (clone $baseQuery)
            ->orderBy('patente')
            ->paginate(10)
            ->withQueryString();

        $stats = (clone $baseQuery)
            ->selectRaw('
        COUNT(*) as total,

        SUM(LOWER(descripcion) REGEXP "tractor|excavadora|telescopico|pala|fumigador") as maquinaria,

        SUM(LOWER(descripcion) REGEXP "camioneta|camion|minibus") as vehiculos,

        SUM(LOWER(descripcion) REGEXP "moto") as motos,

        SUM(
            LOWER(descripcion) NOT REGEXP "tractor|excavadora|telescopico|pala|fumigador|camioneta|camion|minibus|moto"
        ) as otros
    ')
            ->first();


        return view('fuelcontrol.vehiculos.index', compact('vehiculos', 'stats'));
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
