<?php

namespace App\Http\Controllers\FuelControl;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductoController extends Controller
{
    public function index()
    {
        $productos = DB::connection('fuelcontrol')
            ->table('productos')
            ->orderBy('nombre')
            ->get();

        return view('fuelcontrol.productos.index', compact('productos'));
    }

    public function create()
    {
        return view('fuelcontrol.productos.create');
    }

    public function store(Request $request)
    {
        // 1️⃣ Normalizar nombre (evita Diesel / diesel / DIESEL)
        $nombre = trim(mb_strtolower($request->nombre));

        // 2️⃣ Validación (unique real en fuelcontrol.productos)
        $request->merge(['nombre' => $nombre]);

        $request->validate([
            'nombre' => 'required|string|max:100|unique:fuelcontrol.productos,nombre',
            'cantidad' => 'required|numeric|min:0',
        ]);

        // 3️⃣ Insert seguro
        DB::connection('fuelcontrol')
            ->table('productos')
            ->insert([
                'nombre' => $nombre,
                'cantidad' => $request->cantidad,
                'usuario' => auth()->user()->name ?? 'sistema',
                'fecha_registro' => now(),
            ]);

        return redirect()
            ->route('fuelcontrol.productos')
            ->with('success', 'Producto creado correctamente');
    }


    public function edit($id)
    {
        $producto = DB::connection('fuelcontrol')
            ->table('productos')
            ->where('id', $id)
            ->first();

        abort_if(!$producto, 404);

        return view('fuelcontrol.productos.edit', compact('producto'));
    }
    public function destroy($id)
    {
        $producto = DB::connection('fuelcontrol')
            ->table('productos')
            ->where('id', $id)
            ->first();

        abort_if(!$producto, 404);

        DB::connection('fuelcontrol')
            ->table('productos')
            ->where('id', $id)
            ->delete();

        return redirect()
            ->route('fuelcontrol.productos')
            ->with('success', 'Producto eliminado correctamente');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'cantidad' => 'required|numeric|min:0',
        ]);

        DB::connection('fuelcontrol')
            ->table('productos')
            ->where('id', $id)
            ->update([
                'nombre' => $request->nombre,
                'cantidad' => $request->cantidad,
                'usuario' => auth()->user()->name ?? 'sistema',
            ]);

        return redirect()
            ->route('fuelcontrol.productos')
            ->with('success', 'Producto actualizado correctamente');
    }
}
