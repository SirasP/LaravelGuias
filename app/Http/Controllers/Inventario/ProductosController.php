<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductosController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $productos = DB::table('productos')
            ->select('id', 'nombre', 'sku', 'activo', 'created_at')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('nombre', 'like', "%{$q}%")
                        ->orWhere('sku', 'like', "%{$q}%");
                });
            })
            ->orderBy('id', 'desc')
            ->paginate(20)
            ->withQueryString();

        return view('inventario.productos', compact('productos', 'q'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:64', 'unique:productos,sku'],
            'descripcion' => ['nullable', 'string'],
            'activo' => ['nullable'],
        ], [
            'sku.unique' => 'Ese SKU ya existe. Usa otro o edita el producto existente.',
            'nombre.required' => 'El nombre es obligatorio.',
        ]);

        DB::table('productos')->insert([
            'nombre' => $data['nombre'],
            'sku' => $data['sku'] ? strtoupper(trim($data['sku'])) : null,
            'descripcion' => $data['descripcion'] ?? null,
            'activo' => isset($data['activo']) ? 1 : 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('inventario.productos')
            ->with('ok', 'Producto creado correctamente ✅');
    }
    public function toggle($id)
    {
        $producto = DB::table('productos')->where('id', $id)->first();
        if (!$producto)
            return response()->json(['success' => false], 404);

        $nuevo = !(bool) $producto->activo;

        DB::table('productos')->where('id', $id)->update([
            'activo' => $nuevo,
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'activo' => $nuevo]);
    }
}
