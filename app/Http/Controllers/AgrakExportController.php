<?php

namespace App\Http\Controllers;

use App\Models\AgrakRegistro;
use App\Models\AgrakOdooMatch;
use App\Services\AgrakOdooMatcher;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AgrakExportController extends Controller
{
    public function exportAll(Request $request)
    {
        // 1️⃣ Traer bins ordenados
        $bins = AgrakRegistro::orderBy('fecha_registro')
            ->orderBy('patente_norm')
            ->orderBy('hora_registro')
            ->get()
            ->groupBy(fn($b) => $b->fecha_registro . '|' . $b->patente_norm);

        $matcher = app(AgrakOdooMatcher::class);

        // 2️⃣ Excel
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // 3️⃣ Headers
        $headers = [
            'Fecha',
            'Patente',
            'Exportadora',
            'Chofer',
            'Hora bin',
            'Código BIN',
            'Guía Odoo',
            'Bandejas BIN',
            'Estado Odoo',
            'Odoo ID',
        ];

        for ($i = 1; $i <= 10; $i++) {
            $headers[] = "Palet {$i}";
        }

        $sheet->fromArray($headers, null, 'A1');

        $row = 2;

        // 4️⃣ Procesar por GRUPO
        foreach ($bins as $groupKey => $groupBins) {

            [$fecha, $patente] = explode('|', $groupKey);

            // Armar viajes como en la vista
            $trips = app()->call(
                [app(\App\Http\Controllers\AgrakController::class), 'buildTripsFromBins'],
                ['bins' => $groupBins, 'gapMinutes' => 60]
            );

            // MATCH DE GRUPO (UNA VEZ)
            $groupMatch = $matcher->matchGroup([
                'fecha' => $fecha,
                'patente' => $patente,
                'trips' => $trips,
            ]);

            $guia = $groupMatch?->excelOutTransfer?->guia_entrega;
            $estado = $groupMatch?->estado ?? 'SIN MATCH';
            $odooId = $groupMatch?->excel_out_transfer_id ?? '';

            // 5️⃣ Escribir bins
            foreach ($groupBins as $bin) {

                $palets = array_pad(
                    [$bin->numero_bandejas_palet],
                    10,
                    ''
                );

                $sheet->fromArray([
                    array_merge([
                        $bin->fecha_registro,
                        $bin->patente_norm,
                        $bin->exportadora_norm,
                        $bin->nombre_chofer,
                        $bin->hora_registro,
                        $bin->codigo_bin,
                        $guia ?? '',
                        $bin->numero_bandejas_palet,
                        $estado,
                        $odooId,
                    ], $palets)
                ], null, "A{$row}");

                $row++;
            }
        }

        // 6️⃣ Descargar
        $filename = 'AGRak_Odoo_CONSOLIDADO_' . now()->format('Ymd_His') . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(
            fn() => $writer->save('php://output'),
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }
}
