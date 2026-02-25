<?php

namespace App\Http\Controllers;

use App\Services\GmailDteInventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GmailInventoryController extends Controller
{
    private function db()
    {
        return DB::connection('fuelcontrol');
    }

    // GET /gmail/inventario/salida
    public function exitCreate()
    {
        return view('gmail.inventory.exit_form');
    }

    // POST /gmail/inventario/salida
    public function exitStore(Request $request, GmailDteInventoryService $service)
    {
        $validated = $request->validate([
            'destinatario'         => 'required|string|max:200',
            'notas'                => 'nullable|string|max:1000',
            'items'                => 'required|array|min:1',
            'items.*.product_id'   => 'required|integer',
            'items.*.quantity'     => 'required|numeric|gt:0',
        ]);

        // Verify all product_ids exist
        $productIds = collect($validated['items'])->pluck('product_id')->unique()->values()->all();
        $existingIds = $this->db()
            ->table('gmail_inventory_products')
            ->whereIn('id', $productIds)
            ->pluck('id')
            ->all();

        $missing = array_diff($productIds, $existingIds);
        if (!empty($missing)) {
            return back()->withInput()->withErrors(['items' => 'Uno o más productos no son válidos.']);
        }

        try {
            $result = $service->processExit(
                $validated['items'],
                auth()->id(),
                $validated['destinatario'],
                $validated['notas'] ?? null
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['items' => $e->getMessage()]);
        }

        return redirect()
            ->route('gmail.inventory.exits')
            ->with('success', 'Salida registrada correctamente (movimiento #' . $result['movement_id'] . ').');
    }

    // GET /gmail/inventario/salidas
    public function exitList(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $query = $this->db()
            ->table('gmail_inventory_movements')
            ->where('tipo', 'SALIDA')
            ->orderByDesc('ocurrio_el')
            ->orderByDesc('id');

        if ($q !== '') {
            $query->where('destinatario', 'like', "%{$q}%");
        }

        $movements = $query->paginate(25)->withQueryString();

        // Enrich with line counts
        $ids = $movements->pluck('id')->all();
        $lineCounts = $this->db()
            ->table('gmail_inventory_movement_lines')
            ->whereIn('movement_id', $ids)
            ->selectRaw('movement_id, count(distinct product_id) as n_products')
            ->groupBy('movement_id')
            ->pluck('n_products', 'movement_id');

        return view('gmail.inventory.exits', compact('movements', 'lineCounts', 'q'));
    }

    // GET /gmail/inventario/api/productos
    public function productsApi(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $query = $this->db()
            ->table('gmail_inventory_products')
            ->where('is_active', 1)
            ->where('stock_actual', '>', 0)
            ->orderBy('nombre')
            ->limit(20);

        if ($q !== '') {
            $query->where(function ($qb) use ($q) {
                $qb->where('nombre', 'like', "%{$q}%")
                   ->orWhere('codigo', 'like', "%{$q}%");
            });
        }

        $products = $query->get(['id', 'nombre', 'codigo', 'unidad', 'stock_actual', 'costo_promedio']);

        return response()->json($products);
    }
}
