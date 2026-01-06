<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AgrakRegistro;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Services\AgrakOdooMatcher;

class AgrakController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $campo = trim((string) $request->get('campo', ''));
        $cuartel = trim((string) $request->get('cuartel', ''));
        $especie = trim((string) $request->get('especie', ''));
        $view = $request->get('view', 'list'); // list | group

        /* ======================================================
         |  VISTA AGRUPADA: VIAJES POR CAMIÓN (gap de tiempo)
         ======================================================*/
        if ($view === 'group') {

            // 1) Base query con filtros/búsqueda (sin aggregate todavía)
            $base = AgrakRegistro::query();

            if ($campo !== '')
                $base->where('nombre_campo', $campo);
            if ($cuartel !== '')
                $base->where('cuartel', $cuartel);
            if ($especie !== '')
                $base->where('especie', $especie);

            if ($q !== '') {
                $base->where(function ($w) use ($q) {
                    $w->where('codigo_bin', 'like', "%{$q}%")
                        ->orWhere('nombre_cosecha', 'like', "%{$q}%")
                        ->orWhere('nombre_campo', 'like', "%{$q}%")
                        ->orWhere('cuartel', 'like', "%{$q}%")
                        ->orWhere('especie', 'like', "%{$q}%")
                        ->orWhere('variedad', 'like', "%{$q}%")
                        ->orWhere('usuario', 'like', "%{$q}%")
                        ->orWhere('id_usuario', 'like', "%{$q}%")
                        ->orWhere('patente_camion', 'like', "%{$q}%")
                        ->orWhere('nombre_chofer', 'like', "%{$q}%");
                });
            }

            //$base->whereNotNull('patente_camion')->where('patente_camion', '!=', '');


            // 2) Paginamos por "grupo" (fecha + patente), no por bins
            $groupQuery = (clone $base)
                ->selectRaw('fecha_registro, patente_norm, MAX(hora_registro) as last_hora')
                ->groupBy('fecha_registro', 'patente_norm')
                ->orderByDesc('fecha_registro')
                ->orderByDesc('last_hora');



            $perPage = 15;
            $page = (int) $request->get('page', 1);


            $groupKeys = (clone $groupQuery)->get();

            // 3) Para cada grupo, traemos bins y cortamos viajes por gap
            $matcher = app(AgrakOdooMatcher::class);

            $groups = [];

            foreach ($groupKeys as $key) {

                $bins = AgrakRegistro::query()
                    ->where('fecha_registro', $key->fecha_registro)
                    ->where('patente_norm', $key->patente_norm)
                    ->orderBy('hora_registro')
                    ->orderBy('id')
                    ->get();

                // 1️⃣ Construir viajes
                $trips = $this->buildTripsFromBins($bins, 60);

                // 2️⃣ Match AGRak ↔ Odoo A NIVEL GRUPO
                $groupMatch = $matcher->matchGroup([
                    'fecha' => $key->fecha_registro,
                    'patente' => $key->patente_norm,
                    'trips' => $trips,
                ]);

                // 3️⃣ Agregar grupo
                $groups[] = (object) [
                    'fecha_registro' => $key->fecha_registro,
                    'patente_norm' => $key->patente_norm,
                    'trips' => $trips,
                    'trips_count' => count($trips),
                    'odoo_match' => $groupMatch, // 👈 AQUÍ
                ];
            }


            $modo = $request->get('modo', 'all'); // all | pendientes | ok

            if ($modo !== 'all') {
                $groups = array_values(array_filter($groups, function ($g) use ($modo) {

                    $tienePendientes = collect($g->trips)
                        ->contains(fn($t) => !$t['camion_existe']);

                    return $modo === 'pendientes'
                        ? $tienePendientes
                        : !$tienePendientes;
                }));
            }

            $total = count($groups);

            $groupsPage = array_slice(
                $groups,
                ($page - 1) * $perPage,
                $perPage
            );

            $paginator = new LengthAwarePaginator(
                $groupsPage,
                $total,
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );


            // combos filtros
            $campos = AgrakRegistro::select('nombre_campo')->whereNotNull('nombre_campo')->distinct()->orderBy('nombre_campo')->pluck('nombre_campo');
            $cuarteles = AgrakRegistro::select('cuartel')->whereNotNull('cuartel')->distinct()->orderBy('cuartel')->pluck('cuartel');
            $especies = AgrakRegistro::select('especie')->whereNotNull('especie')->distinct()->orderBy('especie')->pluck('especie');

            return view('agrak.index_group', [
                'groups' => $paginator,
                'q' => $q,
                'campo' => $campo,
                'cuartel' => $cuartel,
                'especie' => $especie,
                'campos' => $campos,
                'cuarteles' => $cuarteles,
                'especies' => $especies,
                'gap' => 120,
            ]);
        }

        /* ======================================================
         |  VISTA NORMAL (LISTA)
         ======================================================*/
        $orderBy = $request->get('order_by', 'fecha_registro');
        $dir = $request->get('dir', 'desc');

        $query = AgrakRegistro::query();

        if ($campo !== '')
            $query->where('nombre_campo', $campo);
        if ($cuartel !== '')
            $query->where('cuartel', $cuartel);
        if ($especie !== '')
            $query->where('especie', $especie);

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('codigo_bin', 'like', "%{$q}%")
                    ->orWhere('nombre_cosecha', 'like', "%{$q}%")
                    ->orWhere('nombre_campo', 'like', "%{$q}%")
                    ->orWhere('cuartel', 'like', "%{$q}%")
                    ->orWhere('especie', 'like', "%{$q}%")
                    ->orWhere('variedad', 'like', "%{$q}%")
                    ->orWhere('usuario', 'like', "%{$q}%")
                    ->orWhere('id_usuario', 'like', "%{$q}%")
                    ->orWhere('patente_camion', 'like', "%{$q}%")
                    ->orWhere('nombre_chofer', 'like', "%{$q}%");
            });
        }

        $allowed = ['fecha_registro', 'created_at', 'codigo_bin'];
        if (!in_array($orderBy, $allowed, true))
            $orderBy = 'fecha_registro';
        $dir = $dir === 'asc' ? 'asc' : 'desc';

        if ($orderBy === 'fecha_registro') {
            $query->orderByRaw('fecha_registro IS NULL ASC')
                ->orderBy('fecha_registro', $dir)
                ->orderByRaw('hora_registro IS NULL ASC')
                ->orderBy('hora_registro', $dir);
        } else {
            $query->orderBy($orderBy, $dir);
        }

        $query->orderBy('id', 'desc');

        $items = $query->paginate(20)->withQueryString();

        $campos = AgrakRegistro::select('nombre_campo')->whereNotNull('nombre_campo')->distinct()->orderBy('nombre_campo')->pluck('nombre_campo');
        $cuarteles = AgrakRegistro::select('cuartel')->whereNotNull('cuartel')->distinct()->orderBy('cuartel')->pluck('cuartel');
        $especies = AgrakRegistro::select('especie')->whereNotNull('especie')->distinct()->orderBy('especie')->pluck('especie');

        return view('agrak.index', compact('items', 'q', 'campo', 'cuartel', 'especie', 'orderBy', 'dir', 'campos', 'cuarteles', 'especies'));
    }

    public function show(int $id)
    {
        $item = AgrakRegistro::findOrFail($id);
        return view('agrak.show', compact('item'));
    }

    /**
     * Convierte bins ordenados en "viajes", cortando por gap de tiempo.
     * Retorna array de viajes con resumen + items.
     */
    private function buildTripsFromBins($bins, int $gapMinutes = 120): array
    {
        $trips = [];
        $current = [];

        $prev = null;

        foreach ($bins as $b) {
            $t = $this->timeToSeconds($b->hora_registro);

            if ($prev !== null) {
                $gap = $t - $prev;
                if ($gap > ($gapMinutes * 60)) {
                    // cerramos viaje actual
                    if (!empty($current)) {
                        $trips[] = $this->summarizeTrip($current);
                    }
                    $current = [];
                }
            }

            $current[] = $b;
            $prev = $t;
        }

        if (!empty($current)) {
            $trips[] = $this->summarizeTrip($current);
        }

        return $trips;
    }

    private function summarizeTrip(array $items): array
    {
        $bins = count($items);
        $totalBandejas = 0;

        $horaIni = $items[0]->hora_registro ?? null;
        $horaFin = $items[$bins - 1]->hora_registro ?? null;

        foreach ($items as $it) {
            $totalBandejas += (int) ($it->numero_bandejas_palet ?? 0);
        }

        // ===== valores dominantes =====
        $chofer = $this->mode(array_map(
            fn($x) => $x->chofer_norm,
            $items
        )) ?: null;

        $export = $this->mode(array_map(
            fn($x) => $x->exportadora_norm,
            $items
        )) ?: null;

        $patente = $this->mode(array_map(
            fn($x) => $x->patente_norm,
            $items
        ));

        // ===== chequeos =====
        $patentesUnicas = collect($items)
            ->pluck('patente_norm')
            ->filter()
            ->unique();

        // 🔥 AQUÍ LA CLAVE
        $camionExiste = $patente
            ? \App\Models\Camion::where('patente_norm', $patente)->exists()
            : false;

        return [
            'hora_inicio' => $horaIni,
            'hora_fin' => $horaFin,
            'bins' => $bins,
            'total_bandejas' => $totalBandejas,

            // identidad del viaje
            'patente' => $patente,
            'camion_existe' => $camionExiste,
            'patentes_inconsistentes' => $patentesUnicas->count() > 1,

            // info humana
            'nombre_chofer' => $chofer,
            'exportadora' => $export,

            // detalle
            'items' => collect($items),
        ];
    }


    private function timeToSeconds(?string $hhmmss): int
    {
        $s = trim((string) $hhmmss);
        if ($s === '')
            return 0;

        // soporta HH:MM o HH:MM:SS
        $parts = explode(':', $s);
        $h = (int) ($parts[0] ?? 0);
        $m = (int) ($parts[1] ?? 0);
        $sec = (int) ($parts[2] ?? 0);

        return $h * 3600 + $m * 60 + $sec;
    }

    private function mode(array $values): ?string
    {
        $values = array_values(array_filter($values, fn($v) => $v !== '' && $v !== '0'));
        if (!$values)
            return null;

        $count = [];
        foreach ($values as $v) {
            $count[$v] = ($count[$v] ?? 0) + 1;
        }
        arsort($count);
        return array_key_first($count);
    }
}
