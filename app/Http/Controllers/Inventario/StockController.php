<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $stock = DB::table('productos as p')
            ->leftJoin('lotes_inventario as l', 'l.producto_id', '=', 'p.id')
            ->selectRaw('
        p.id,
        p.nombre,
        p.sku,
        p.activo,
        COALESCE(SUM(l.cantidad_disponible), 0) as stock_actual
    ')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('p.nombre', 'like', "%{$q}%")
                        ->orWhere('p.sku', 'like', "%{$q}%");
                });
            })
            ->groupBy('p.id', 'p.nombre', 'p.sku', 'p.activo')
            ->orderBy('p.nombre')
            ->get();

        return view('inventario.stock', compact('stock', 'q'));
    }
   
}
