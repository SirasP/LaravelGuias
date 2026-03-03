<?php

namespace App\Http\Controllers;

use App\Services\GmailDteInventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryAdjustController extends Controller
{
    private function db()
    {
        return DB::connection('fuelcontrol');
    }

    public function adjustCreate()
    {
        return view('gmail.inventory.adjust_form');
    }

    public function adjustStore(Request $request, GmailDteInventoryService $service)
    {
        $validated = $request->validate([
            'product_id' => 'required|integer',
            'quantity'   => 'required|numeric|gt:0',
            'direccion'  => 'required|in:POSITIVO,NEGATIVO',
            'motivo'     => 'required|string|max:100',
            'notas'      => 'nullable|string|max:1000',
        ]);

        $exists = $this->db()->table('gmail_inventory_products')->where('id', $validated['product_id'])->exists();
        if (!$exists) {
            return back()->withInput()->withErrors(['product_id' => 'Producto no válido.']);
        }

        try {
            $result = $service->processAdjustment(
                (int)   $validated['product_id'],
                (float) $validated['quantity'],
                        $validated['direccion'],
                        $validated['motivo'],
                auth()->id(),
                $validated['notas'] ?? null
            );
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['quantity' => $e->getMessage()]);
        }

        return redirect()
            ->route('gmail.inventory.exits.show', $result['movement_id'])
            ->with('success', 'Ajuste de inventario registrado correctamente.');
    }

    public function adjustList(Request $request)
    {
        $q     = trim((string) $request->query('q', ''));
        $dir   = trim((string) $request->query('dir', ''));   // AJUSTE+ | AJUSTE-
        $desde = trim((string) $request->query('desde', ''));
        $hasta = trim((string) $request->query('hasta', ''));

        $query = $this->db()
            ->table('gmail_inventory_movements as m')
            ->where('m.tipo', 'AJUSTE')
            ->orderByDesc('m.ocurrio_el')
            ->orderByDesc('m.id');

        if ($dir !== '') {
            $query->where('m.tipo_salida', $dir);
        }
        if ($q !== '') {
            $query->where(function ($qb) use ($q) {
                $qb->where('m.destinatario', 'like', "%{$q}%")
                   ->orWhere('m.notas', 'like', "%{$q}%");
            });
        }
        if ($desde !== '') {
            $query->where('m.ocurrio_el', '>=', $desde);
        }
        if ($hasta !== '') {
            $query->where('m.ocurrio_el', '<=', $hasta);
        }

        $movements = $query->limit(300)->get();
        $ids       = $movements->pluck('id')->all();

        $lines = $ids
            ? $this->db()
                ->table('gmail_inventory_movement_lines as ml')
                ->join('gmail_inventory_products as p', 'p.id', '=', 'ml.product_id')
                ->whereIn('ml.movement_id', $ids)
                ->orderBy('p.nombre')
                ->get(['ml.movement_id', 'p.nombre as producto', 'p.unidad', 'ml.cantidad', 'ml.costo_unitario', 'ml.costo_total'])
                ->groupBy('movement_id')
            : collect();

        // KPIs mes actual
        $mesInicio = now()->startOfMonth()->toDateString();
        $mesFin    = now()->endOfMonth()->toDateString();

        $kpiPos = $this->db()->table('gmail_inventory_movements')
            ->where('tipo', 'AJUSTE')->where('tipo_salida', 'AJUSTE+')
            ->whereBetween('ocurrio_el', [$mesInicio, $mesFin])
            ->selectRaw('count(*) as cnt, coalesce(sum(cantidad_total),0) as qty, coalesce(sum(costo_total),0) as costo')
            ->first();

        $kpiNeg = $this->db()->table('gmail_inventory_movements')
            ->where('tipo', 'AJUSTE')->where('tipo_salida', 'AJUSTE-')
            ->whereBetween('ocurrio_el', [$mesInicio, $mesFin])
            ->selectRaw('count(*) as cnt, coalesce(sum(cantidad_total),0) as qty, coalesce(sum(costo_total),0) as costo')
            ->first();

        return view('gmail.inventory.adjustments', compact(
            'movements', 'lines', 'kpiPos', 'kpiNeg',
            'q', 'dir', 'desde', 'hasta'
        ));
    }
}
