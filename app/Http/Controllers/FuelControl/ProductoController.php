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
            ->table('movimientos as m')
            ->leftJoin('productos as p', 'p.id', '=', 'm.producto_id')
            ->select(
                'm.*',
                'p.nombre as producto_nombre'
            )
            ->orderByDesc('m.fecha_movimiento')
            ->get();


        return view('fuelcontrol.productos.index', compact('productos'));
    }

    public function create()
    {
        return view('fuelcontrol.productos.create');
    }

    public function store(Request $request)
    {
        $nombre = trim(mb_strtolower($request->nombre));

        $request->validate([
            'nombre' => 'required|string|max:100',
            'cantidad' => 'required|numeric|min:0',
        ]);

        DB::connection('fuelcontrol')->transaction(function () use ($nombre, $request) {

            $producto = DB::connection('fuelcontrol')
                ->table('productos')
                ->where('nombre', $nombre)
                ->first();

            if ($producto) {
                // 🔁 Sumar stock existente
                DB::connection('fuelcontrol')
                    ->table('productos')
                    ->where('id', $producto->id)
                    ->update([
                        'cantidad' => $producto->cantidad + $request->cantidad,
                        'usuario' => auth()->user()->name ?? 'sistema',
                        'fecha_registro' => now(),
                    ]);

                session()->flash('success', 'Stock actualizado correctamente');
                return;
            }

            // 🆕 Crear producto
            DB::connection('fuelcontrol')
                ->table('productos')
                ->insert([
                    'nombre' => $nombre,
                    'cantidad' => $request->cantidad,
                    'usuario' => auth()->user()->name ?? 'sistema',
                    'fecha_registro' => now(),
                ]);

            session()->flash('success', 'Producto creado correctamente');
        });

        return redirect()->route('fuelcontrol.productos');
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


    public function importarXml($id)
    {
        $producto = DB::connection('fuelcontrol')
            ->table('productos')
            ->where('id', $id)
            ->first();

        abort_if(!$producto, 404);

        // buscar XML pendientes
        $xmls = DB::table('xml_facturas')
            ->where('estado', 'pendiente')
            ->orderBy('id')
            ->get();

        foreach ($xmls as $xml) {

            $data = simplexml_load_file(storage_path('app/' . $xml->archivo));

            $descripcion = strtolower((string) $data->Detalle->NmbItem);
            $litros = (float) $data->Detalle->QtyItem;

            // validar producto
            if (
                str_contains($producto->nombre, 'diesel') &&
                !str_contains($descripcion, 'diesel')
            ) {
                continue;
            }

            if (
                str_contains($producto->nombre, 'gasolina') &&
                !str_contains($descripcion, 'gasolina')
            ) {
                continue;
            }

            // ✅ SUMAR STOCK
            DB::connection('fuelcontrol')
                ->table('productos')
                ->where('id', $producto->id)
                ->update([
                    'cantidad' => $producto->cantidad + $litros,
                    'usuario' => auth()->user()->name ?? 'sistema',
                ]);

            // marcar XML como procesado
            DB::table('xml_facturas')
                ->where('id', $xml->id)
                ->update([
                    'estado' => 'procesado',
                    'producto_detectado' => $producto->nombre,
                    'litros' => $litros,
                ]);

            return redirect()
                ->route('fuelcontrol.productos')
                ->with('success', "Se importaron {$litros} L desde XML");
        }

        return redirect()
            ->route('fuelcontrol.productos')
            ->with('warning', 'No hay XML pendientes para este producto');
    }

}
