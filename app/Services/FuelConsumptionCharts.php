<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class FuelConsumptionCharts
{
    public function series(CarbonInterface $from, CarbonInterface $until): array
    {
        $days = collect();
        for ($day = $from->copy()->startOfDay(); $day->lte($until); $day->addDay()) {
            $days->push($day->toDateString());
        }

        $tankView = app(FuelTankViewData::class);
        $tanks = $tankView->tanks();
        $query = DB::connection('fuelcontrol')->table('movimientos as m')
            ->join('productos as p', 'p.id', '=', 'm.producto_id')
            ->whereIn('m.tipo', ['salida', 'vehiculo'])
            ->whereBetween('m.fecha_movimiento', [$from->copy()->startOfDay(), $until->copy()->endOfDay()])
            ->where(fn ($q) => $q->whereNull('m.estado')->orWhere('m.estado', 'aprobado'));

        $totals = (clone $query)
            ->selectRaw('DATE(m.fecha_movimiento) as fecha, LOWER(p.nombre) as producto, SUM(ABS(m.cantidad)) as total')
            ->groupBy('fecha', 'producto')->get();

        $values = fn ($rows) => $days->map(fn ($date) => round((float) $rows->where('fecha', $date)->sum('total'), 2))->all();
        $byTank = $tankView->enabled()
            ? (clone $query)->where('m.tipo', 'salida')->whereIn('m.estanque_id', $tanks->pluck('id'))
                ->whereIn('m.producto_id', $tanks->pluck('producto_id')->unique())
                ->selectRaw('DATE(m.fecha_movimiento) as fecha, m.estanque_id, m.producto_id, SUM(ABS(m.cantidad)) as total')
                ->groupBy('fecha', 'm.estanque_id', 'm.producto_id')->get()
            : collect();

        return [
            'labels' => $days->all(),
            'gasolina' => $values($totals->filter(fn ($row) => str_contains($row->producto, 'gas'))),
            'diesel' => $values($totals->filter(fn ($row) => str_contains($row->producto, 'die'))),
            'estanques' => $tanks->map(fn ($tank) => [
                'id' => (int) $tank->id,
                'nombre' => $tank->nombre,
                'data' => $values($byTank->where('estanque_id', $tank->id)->where('producto_id', $tank->producto_id)),
            ])->values()->all(),
        ];
    }
}
