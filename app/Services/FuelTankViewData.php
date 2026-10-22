<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Presenta ubicaciones del mismo producto sin crear productos ni alterar saldos. */
class FuelTankViewData
{
    private ?Collection $tanks = null;

    public function enabled(): bool
    {
        return config('fuelcontrol.estanques') && config('fuelcontrol.web_estanques');
    }

    public function tanks(): Collection
    {
        return $this->tanks ??= $this->enabled()
            ? DB::connection('fuelcontrol')->table('fuel_estanques')->orderBy('id')->get()
            : collect();
    }

    public function inventory(Collection $products): Collection
    {
        if (! $this->enabled()) {
            return $products;
        }
        $byProduct = $this->tanks()->groupBy('producto_id');
        $rows = collect();
        foreach ($products as $product) {
            $tanks = $byProduct->get($product->id, collect());
            if ($tanks->isEmpty()) {
                $rows->push(clone $product);

                continue;
            }
            foreach ($tanks as $tank) {
                $capacity = config('fuelcontrol.capacidades.'.$tank->id);
                $capacity = is_numeric($capacity) && $capacity > 0 ? (float) $capacity : null;
                $row = clone $product;
                $row->producto_id = (int) $product->id;
                $row->combustible_nombre = $product->nombre;
                $row->nombre = $tank->nombre.' - '.ucfirst($product->nombre);
                $row->estanque_id = (int) $tank->id;
                $row->estanque_nombre = $tank->nombre;
                $row->cantidad = $tank->inicializado ? (float) $tank->cantidad : null;
                $row->capacidad = $capacity;
                $row->porcentaje = $capacity !== null && $row->cantidad !== null
                    ? min(100, max(0, round($row->cantidad / $capacity * 100))) : null;
                $rows->push($row);
            }
        }

        return $rows->sortBy(fn ($p) => isset($p->estanque_id) ? '0-'.sprintf('%010d', $p->estanque_id) : '1-'.$p->nombre)->values();
    }

    public function movements(Collection $rows): Collection
    {
        if (! $this->enabled()) {
            return $rows;
        }
        $tanks = $this->tanks()->keyBy('id');
        $managedIds = $this->tanks()->pluck('producto_id')->unique();

        return $rows->map(function ($row) use ($tanks, $managedIds) {
            $row = clone $row;
            $base = ucfirst($row->producto_nombre ?? 'Combustible');
            $row->combustible_nombre = $row->producto_nombre;
            $tank = $tanks->get($row->estanque_id ?? null);
            if ($tank && (int) $tank->producto_id === (int) $row->producto_id) {
                $row->estanque_nombre = $tank->nombre;
                $row->producto_nombre = $tank->nombre.' - '.$base;
            } elseif ($managedIds->contains($row->producto_id)) {
                $origin = ($row->tipo ?? null) === 'vehiculo' ? 'Bencinera'
                    : (($row->origen ?? null) === 'xml_oc_estanque' ? 'Destino pendiente' : 'Histórico sin estanque');
                $row->producto_nombre = $base.' · '.$origin;
            }

            return $row;
        });
    }

    public function filter(Builder $query, mixed $selected, ?int $productId = null): Builder
    {
        if (! $this->enabled() || $selected === null || $selected === '') {
            return $query;
        }
        abort_unless(is_string($selected) || is_int($selected), 422, 'Estanque no válido.');
        if ((string) $selected === 'sin_asignar') {
            $ids = $this->tanks()->pluck('producto_id')->unique();
            abort_unless($productId === null || $ids->contains($productId), 422, 'Este combustible no usa estanques.');

            return $query->whereNull('m.estanque_id')->whereIn('m.producto_id', $ids)->where('m.tipo', '!=', 'vehiculo');
        }
        $tank = $this->tanks()->first(fn ($t) => (string) $t->id === (string) $selected);
        abort_unless($tank && ($productId === null || (int) $tank->producto_id === $productId), 422, 'Estanque no válido para este combustible.');

        return $query->where('m.estanque_id', $tank->id)->where('m.producto_id', $tank->producto_id);
    }
}
