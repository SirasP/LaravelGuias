<?php

namespace App\Services;

use App\Models\AgrakOdooMatch;
use App\Models\ExcelOutTransfer;
use Carbon\Carbon;

class AgrakOdooMatcher
{
    /**
     * MATCH AGRak (producción) ↔ Odoo (salida logística)
     *
     * $group esperado:
     * [
     *   'fecha' => '2026-01-02',
     *   'patente' => 'JF6764',
     *   'trips' => [
     *      [
     *          'total_bandejas' => 550,
     *          'nombre_chofer' => 'CRISTIAN GORMAZ',
     *          'exportadora_norm' => 'VITAFOODS SPA',
     *      ],
     *      ...
     *   ]
     * ]
     */
    public function matchGroup(array $group): ?AgrakOdooMatch
    {
        // =========================
        // 1️⃣ Datos AGRak agregados
        // =========================
        $fechaAgrak = Carbon::parse($group['fecha']);
        $patente = $this->norm($group['patente']);

        $trips = collect($group['trips']);

        if ($trips->isEmpty()) {
            return null;
        }

        $agrakBandejas = (int) $trips->sum('total_bandejas');
        $agrakChofer = $this->dominant($trips, 'nombre_chofer');
        $agrakExportadora = $this->dominant($trips, 'exportadora_norm');

        // Ventana horaria AGRak (solo referencia)
        $agrakIni = Carbon::createFromFormat(
            'H:i:s',
            $trips->min('hora_inicio')
        );

        $agrakFin = Carbon::createFromFormat(
            'H:i:s',
            $trips->max('hora_fin')
        );

        // =========================
        // 2️⃣ Candidatos Odoo
        // SOLO por patente + realizado
        // =========================
        $candidates = ExcelOutTransfer::query()
            ->whereRaw('UPPER(TRIM(patente)) = ?', [$patente])
            ->where('estado', 'Realizado')
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        // =========================
        // 3️⃣ Scoring REAL
        // =========================
        $best = null;
        $bestScore = 0;

        foreach ($candidates as $odoo) {

            // ❌ No reutilizar OUT ya asignado
            if (
                AgrakOdooMatch::where('excel_out_transfer_id', $odoo->id)->exists()
            ) {
                continue;
            }

            $score = 0;

            // 🚚 Patente (obligatoria)
            $score += 4;

            // 🏭 EXPORTADORA (CLAVE)
            if ($odoo->contacto && $agrakExportadora) {
                if ($this->norm($odoo->contacto) === $agrakExportadora) {
                    $score += 5;
                }
            }

            // 📅 Fecha cercana (prevista o traslado)
            $dateScore = 0;

            if ($odoo->fecha_prevista) {
                $diff = abs(
                    Carbon::parse($odoo->fecha_prevista)->diffInDays($fechaAgrak)
                );
                if ($diff === 0)
                    $dateScore = max($dateScore, 3);
                elseif ($diff === 1)
                    $dateScore = max($dateScore, 1);
            }

            if ($odoo->fecha_traslado) {
                $diff = abs(
                    Carbon::parse($odoo->fecha_traslado)->diffInDays($fechaAgrak)
                );
                if ($diff === 0)
                    $dateScore = max($dateScore, 3);
                elseif ($diff === 1)
                    $dateScore = max($dateScore, 1);
            }

            $score += $dateScore;

            // 👤 Chofer
            if ($agrakChofer && $odoo->chofer) {
                $a = $this->norm($agrakChofer);
                $b = $this->norm($odoo->chofer);

                if ($a === $b) {
                    $score += 2;
                } elseif ($this->apellidoMatch($a, $b)) {
                    $score += 1;
                }
            }

            // ⏱ Hora (suave, NO bloqueante)
            if ($odoo->fecha_traslado) {
                $odooHora = Carbon::parse($odoo->fecha_traslado);

                if (
                    $odooHora->between(
                        $agrakIni->copy()->subMinutes(120),
                        $agrakFin->copy()->addMinutes(120)
                    )
                ) {
                    $score += 1;
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $odoo;
            }
        }

        if (!$best) {
            return null;
        }

        // =========================
        // 4️⃣ Estado final
        // =========================
        $estado = match (true) {
            $bestScore >= 10 => 'ok',
            $bestScore >= 7 => 'probable',
            default => 'manual',
        };

        // =========================
        // 5️⃣ Guardar MATCH
        // =========================
        return AgrakOdooMatch::updateOrCreate(
            [
                'agrak_fecha' => $fechaAgrak->toDateString(),
                'agrak_patente' => $group['patente'],
            ],
            [
                'agrak_hora_inicio' => $agrakIni->format('H:i:s'),
                'agrak_hora_fin' => $agrakFin->format('H:i:s'),
                'agrak_chofer' => $agrakChofer,
                'excel_out_transfer_id' => $best->id,
                'score' => $bestScore,
                'estado' => $estado,
            ]
        );
    }

    // =========================
    // HELPERS
    // =========================

    private function norm(?string $v): ?string
    {
        if (!$v)
            return null;
        return preg_replace('/\s+/', ' ', strtoupper(trim($v)));
    }

    private function apellidoMatch(string $a, string $b): bool
    {
        return count(array_intersect(explode(' ', $a), explode(' ', $b))) > 0;
    }

    private function dominant($collection, string $field): ?string
    {
        return $collection
            ->pluck($field)
            ->filter()
            ->map(fn($v) => $this->norm($v))
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();
    }
}
