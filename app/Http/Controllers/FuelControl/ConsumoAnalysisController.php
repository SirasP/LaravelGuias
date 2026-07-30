<?php

namespace App\Http\Controllers\FuelControl;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ConsumoAnalysisController extends Controller
{
    public function index(Request $request)
    {
        $periodoActual = $this->resolveMonth($request->query('mes'));
        $periodoAnterior = $request->filled('comparar')
            ? $this->resolveMonth($request->query('comparar'))
            : $periodoActual->copy()->subMonthNoOverflow();

        if ($periodoAnterior->isSameMonth($periodoActual)) {
            $periodoAnterior = $periodoActual->copy()->subMonthNoOverflow();
        }

        $actual = $this->consumoPorPeriodo(
            $periodoActual->copy()->startOfMonth(),
            $periodoActual->copy()->endOfMonth()
        );
        $anterior = $this->consumoPorPeriodo(
            $periodoAnterior->copy()->startOfMonth(),
            $periodoAnterior->copy()->endOfMonth()
        );

        $resumenActual = $this->resumenCombustible($actual);
        $resumenAnterior = $this->resumenCombustible($anterior);

        $metricas = collect([
            ['label' => 'Diésel', 'key' => 'diesel', 'color' => 'amber'],
            ['label' => 'Gasolina', 'key' => 'gasolina', 'color' => 'sky'],
            ['label' => 'Total combustible', 'key' => 'total', 'color' => 'violet'],
        ])->map(function (array $metrica) use ($resumenActual, $resumenAnterior) {
            $actual = $resumenActual[$metrica['key']];
            $anterior = $resumenAnterior[$metrica['key']];

            return [
                ...$metrica,
                'actual' => $actual,
                'anterior' => $anterior,
                'diferencia' => $actual - $anterior,
                'porcentaje' => $anterior > 0 ? (($actual - $anterior) / $anterior) * 100 : null,
            ];
        });

        $movimientosActuales = $this->movimientosPorPeriodo(
            $periodoActual->copy()->startOfMonth(),
            $periodoActual->copy()->endOfMonth()
        );
        $movimientosAnteriores = $this->movimientosPorPeriodo(
            $periodoAnterior->copy()->startOfMonth(),
            $periodoAnterior->copy()->endOfMonth()
        );

        $vehiculos = DB::connection('fuelcontrol')
            ->table('movimientos as m')
            ->leftJoin('vehiculos as v', 'v.id', '=', 'm.vehiculo_id')
            ->leftJoin('productos as p', 'p.id', '=', 'm.producto_id')
            ->where('m.tipo', 'salida')
            ->whereBetween('m.fecha_movimiento', [
                $periodoActual->copy()->startOfMonth(),
                $periodoActual->copy()->endOfMonth(),
            ])
            ->where(function ($query) {
                $query->where('m.estado', 'aprobado')->orWhereNull('m.estado');
            })
            ->selectRaw("COALESCE(NULLIF(v.patente, ''), NULLIF(v.descripcion, ''), 'Sin vehículo') as nombre")
            ->selectRaw('SUM(ABS(m.cantidad)) as litros')
            ->selectRaw('COUNT(*) as movimientos')
            ->groupBy('v.id', 'v.patente', 'v.descripcion')
            ->orderByDesc('litros')
            ->limit(8)
            ->get();

        $tendencia = collect(range(5, 0))->map(function (int $mesesAtras) use ($periodoActual) {
            $mes = $periodoActual->copy()->subMonthsNoOverflow($mesesAtras);
            $resumen = $this->resumenCombustible($this->consumoPorPeriodo(
                $mes->copy()->startOfMonth(),
                $mes->copy()->endOfMonth()
            ));

            return [
                'label' => ucfirst($mes->copy()->locale('es')->translatedFormat('M y')),
                'diesel' => $resumen['diesel'],
                'gasolina' => $resumen['gasolina'],
            ];
        });

        return view('fuelcontrol.analisis-consumo.index', compact(
            'periodoActual',
            'periodoAnterior',
            'metricas',
            'movimientosActuales',
            'movimientosAnteriores',
            'vehiculos',
            'tendencia'
        ));
    }

    private function resolveMonth(?string $month): Carbon
    {
        if (is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month)) {
            try {
                return Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            } catch (\Throwable) {
                // Si llega un mes inválido, se usa el actual.
            }
        }

        return now()->startOfMonth();
    }

    private function consumoPorPeriodo(Carbon $desde, Carbon $hasta): Collection
    {
        return DB::connection('fuelcontrol')
            ->table('movimientos as m')
            ->join('productos as p', 'p.id', '=', 'm.producto_id')
            ->where('m.tipo', 'salida')
            ->whereBetween('m.fecha_movimiento', [$desde, $hasta])
            ->where(function ($query) {
                $query->where('m.estado', 'aprobado')->orWhereNull('m.estado');
            })
            ->selectRaw('LOWER(p.nombre) as producto, SUM(ABS(m.cantidad)) as litros')
            ->groupBy('producto')
            ->get();
    }

    private function resumenCombustible(Collection $consumo): array
    {
        $diesel = (float) $consumo
            ->filter(fn ($item) => str_contains($item->producto, 'diesel') || str_contains($item->producto, 'diésel'))
            ->sum('litros');
        $gasolina = (float) $consumo
            ->filter(fn ($item) => str_contains($item->producto, 'gasolina') || str_contains($item->producto, 'gas'))
            ->sum('litros');

        return [
            'diesel' => $diesel,
            'gasolina' => $gasolina,
            'total' => $diesel + $gasolina,
        ];
    }

    private function movimientosPorPeriodo(Carbon $desde, Carbon $hasta): int
    {
        return DB::connection('fuelcontrol')
            ->table('movimientos')
            ->where('tipo', 'salida')
            ->whereBetween('fecha_movimiento', [$desde, $hasta])
            ->where(function ($query) {
                $query->where('estado', 'aprobado')->orWhereNull('estado');
            })
            ->count();
    }
}
