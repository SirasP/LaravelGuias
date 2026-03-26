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
            ->select('vehiculos.*')
            ->selectSub(function ($query) {
                $query->from('movimientos')
                    ->select('id')
                    ->whereColumn('vehiculo_id', 'vehiculos.id')
                    ->orderByDesc('fecha_movimiento')
                    ->orderByDesc('id')
                    ->limit(1);
            }, 'ultimo_movimiento_id')
            ->orderBy('patente')
            ->paginate(10)
            ->withQueryString();

        $stats = (clone $baseQuery)
            ->selectRaw('
        COUNT(*) as total,

        -- Clasificación por descripción
        SUM(LOWER(descripcion) REGEXP "tractor|excavadora|telescopico|pala|fumigador") as maquinaria,
        SUM(LOWER(descripcion) REGEXP "camioneta|camion|minibus") as vehiculos,
        SUM(LOWER(descripcion) REGEXP "moto") as motos,
        SUM(
            LOWER(descripcion) NOT REGEXP "tractor|excavadora|telescopico|pala|fumigador|camioneta|camion|minibus|moto"
        ) as otros,

        -- 🔹 PROPIEDAD (ESTO TE FALTABA)
        SUM(LOWER(tipo) = "propio") as propios,
        SUM(LOWER(tipo) = "arrendado") as arrendados,
        SUM(LOWER(tipo) = "prestado") as prestados
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
            'is_active' => true,
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
            'patente' => 'required|string|max:100',
            'descripcion' => 'nullable|string|max:255',
            'tipo' => 'required|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);

        try {
            $vehiculo = Vehiculo::on('fuelcontrol')->findOrFail($id);

            $vehiculo->update([
                'patente' => strtoupper($request->patente),
                'descripcion' => $request->descripcion,
                'tipo' => $request->tipo,
                'is_active' => $request->has('is_active') ? (bool) $request->boolean('is_active') : (bool) ($vehiculo->is_active ?? true),
                'usuario' => auth()->user()->name ?? 'sistema',
            ]);

            return redirect()
                ->route('fuelcontrol.vehiculos.index')
                ->with('success', 'Vehículo actualizado correctamente');

        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'No se pudo actualizar el vehículo');
        }
    }

    public function toggleActive(Request $request, $id)
    {
        $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $vehiculo = Vehiculo::on('fuelcontrol')->findOrFail($id);
        $vehiculo->is_active = (bool) $request->boolean('is_active');
        $vehiculo->usuario = auth()->user()->name ?? 'sistema';
        $vehiculo->save();

        return redirect()
            ->route('fuelcontrol.vehiculos.index')
            ->with('success', $vehiculo->is_active ? 'Vehículo activado correctamente' : 'Vehículo desactivado correctamente');
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
