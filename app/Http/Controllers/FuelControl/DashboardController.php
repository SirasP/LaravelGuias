<?php

namespace App\Http\Controllers\FuelControl;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function index()
    {

        try {
            /* =========================
             * PRODUCTOS 
             * ========================= */
            $productos = DB::connection('fuelcontrol')
                ->table('productos')
                ->select('id', 'nombre', 'cantidad')
                ->orderBy('nombre', 'asc')
                ->get();

            /* =========================
             * ÚLTIMOS MOVIMIENTOS
             * ========================= */
            $movimientos = DB::connection('fuelcontrol')
                ->table('movimientos as m')
                ->leftJoin('productos as p', 'p.id', '=', 'm.producto_id')
                ->select(
                    'm.*',
                    'p.nombre as producto_nombre'
                )
                ->orderByDesc('m.fecha_movimiento')
                ->limit(5)

                ->get();


            /* =========================
             * NOTIFICACIONES (SOLO ADMIN)
             * ========================= */
            $notificaciones = collect();

            $notificaciones = DB::connection('fuelcontrol')
                ->table('notificaciones as n')
                ->join('notificacion_usuarios as nu', 'nu.notificacion_id', '=', 'n.id')
                ->leftJoin('movimientos as m', 'm.id', '=', 'n.movimiento_id') // 🔥 ESTA LÍNEA FALTA
                ->where('nu.user_id', auth()->id())
                ->where('nu.leido', 0)
                ->orderByDesc('n.created_at')
                ->limit(5)
                ->get([
                    'n.id',
                    'n.titulo',
                    'n.tipo',
                    'n.movimiento_id',
                    'n.mensaje',
                    'n.created_at',
                    'm.estado' // 🔥 IMPORTANTE

                ]);


            /* =========================
             * RESUMEN
             * ========================= */
            $resumen = [
                'total_productos' => $productos->count(),

                'total_vehiculos' => DB::connection('fuelcontrol')
                    ->table('vehiculos')
                    ->count(),

                'movimientos_hoy' => DB::connection('fuelcontrol')
                    ->table('movimientos')
                    ->whereDate('fecha_movimiento', now()->toDateString())
                    ->count(),
            ];

            /* =========================
             * GRÁFICOS: CONSUMO ÚLTIMOS 30 DÍAS
             * ========================= */
            $desde = now()->subDays(30)->startOfDay();

            $consumo = DB::connection('fuelcontrol')
                ->table('movimientos')
                ->join('productos', 'productos.id', '=', 'movimientos.producto_id')
                ->selectRaw('
        DATE(fecha_movimiento) as fecha,
        LOWER(productos.nombre) as producto,
        SUM(ABS(movimientos.cantidad)) as total
    ')
                ->where('tipo', 'salida')
                ->where('fecha_movimiento', '>=', $desde)
                ->groupBy('fecha', 'producto')
                ->orderBy('fecha')
                ->get();

            /* =========================
             * GASOLINA
             * ========================= */

            $gasolina = $consumo->filter(fn($r) => str_contains($r->producto, 'gas'));

            $labelsGasolina = $gasolina
                ->pluck('fecha')
                ->map(fn($f) => Carbon::parse($f)->format('d-m'))
                ->values();

            $dataGasolina = $gasolina
                ->pluck('total')
                ->values();

            /* =========================
             * DIESEL
             * ========================= */

            $diesel = $consumo->filter(fn($r) => str_contains($r->producto, 'die'));

            $labelsDiesel = $diesel
                ->pluck('fecha')
                ->map(fn($f) => Carbon::parse($f)->format('d-m'))
                ->values();

            $dataDiesel = $diesel
                ->pluck('total')
                ->values();

            /* =========================
             * CHARTS VEHÍCULOS (30 DÍAS)
             * ========================= */
            // Cálculo de km por vehículo usando odómetro (fallback: odómetro bomba)
            $hasOdomCol = Schema::connection('fuelcontrol')->hasColumn('movimientos', 'odometro');
            $hasOdomBombaCol = Schema::connection('fuelcontrol')->hasColumn('movimientos', 'odometro_bomba');
            $hasOdomAny = $hasOdomCol || $hasOdomBombaCol;
            $hasVehiculoId = Schema::connection('fuelcontrol')->hasColumn('movimientos', 'vehiculo_id');

            $topVehiculosLabels = collect();
            $topVehiculosLitros = collect();
            $topVehiculosKmL = collect();
            $usoDiarioLabels = collect();
            $usoDiarioLitros = collect();
            $usoDiarioKmL = collect();
            $vehiculosDebug = [];

            if ($hasVehiculoId && $hasOdomAny) {
                $vehRows = DB::connection('fuelcontrol')
                    ->table('movimientos as m')
                    ->leftJoin('vehiculos as v', 'v.id', '=', 'm.vehiculo_id')
                    ->select(
                        'm.vehiculo_id',
                        'm.fecha_movimiento',
                        'm.cantidad',
                        'm.odometro',
                        'm.odometro_bomba'
                    )
                    ->selectRaw("
                        COALESCE(
                            NULLIF(TRIM(v.patente), ''),
                            CONCAT('Vehículo #', m.vehiculo_id)
                        ) as vehiculo
                    ")
                    ->where('m.fecha_movimiento', '>=', $desde)
                    ->whereNotNull('m.vehiculo_id')
                    ->where(function ($q) {
                        $q->whereNull('m.estado')
                            ->orWhere('m.estado', 'aprobado');
                    })
                    ->orderBy('m.vehiculo_id')
                    ->orderBy('m.fecha_movimiento')
                    ->get();

                $vehiculosCalc = collect();
                $daily = [];

                foreach ($vehRows->groupBy('vehiculo_id') as $vehiculoId => $rows) {
                    $prevOdo = null;
                    $vehLitros = 0.0;
                    $vehKm = 0.0;
                    $vehName = (string) ($rows->first()->vehiculo ?? "Vehículo #{$vehiculoId}");

                    foreach ($rows as $r) {
                        $litros = abs((float) ($r->cantidad ?? 0));
                        $odoPrincipal = (float) ($r->odometro ?? 0);
                        $odoBomba = (float) ($r->odometro_bomba ?? 0);
                        $odo = $odoPrincipal > 0 ? $odoPrincipal : ($odoBomba > 0 ? $odoBomba : 0);
                        $fecha = Carbon::parse($r->fecha_movimiento)->toDateString();

                        $vehLitros += $litros;
                        if (!isset($daily[$fecha])) {
                            $daily[$fecha] = ['litros' => 0.0, 'km' => 0.0];
                        }
                        $daily[$fecha]['litros'] += $litros;

                        if ($odo > 0 && !is_null($prevOdo) && $odo > $prevOdo) {
                            $deltaKm = $odo - $prevOdo;
                            $vehKm += $deltaKm;
                            $daily[$fecha]['km'] += $deltaKm;
                        }
                        if ($odo > 0) {
                            $prevOdo = $odo;
                        }
                    }

                    $vehiculosCalc->push([
                        'vehiculo' => $vehName,
                        'litros' => round($vehLitros, 2),
                        'km' => round($vehKm, 2),
                        'kml' => ($vehLitros > 0 && $vehKm > 0) ? round($vehKm / $vehLitros, 2) : null,
                    ]);
                }

                $top = $vehiculosCalc
                    ->sortByDesc('litros')
                    ->take(8)
                    ->values();

                $topVehiculosLabels = $top
                    ->pluck('vehiculo')
                    ->map(fn($v) => mb_strimwidth((string) $v, 0, 18, '…'))
                    ->values();

                $topVehiculosLitros = $top
                    ->pluck('litros')
                    ->values();

                $topVehiculosKmL = $top
                    ->pluck('kml')
                    ->values();

                ksort($daily);
                $usoDiarioAgg = collect($daily)->map(function ($r) {
                    $litros = (float) ($r['litros'] ?? 0);
                    $km = (float) ($r['km'] ?? 0);
                    return [
                        'litros' => round($litros, 2),
                        'kml' => ($litros > 0 && $km > 0) ? round($km / $litros, 2) : null,
                    ];
                });

                $usoDiarioLabels = $usoDiarioAgg
                    ->keys()
                    ->map(fn($f) => Carbon::parse($f)->format('d-m'))
                    ->values();

                $usoDiarioLitros = $usoDiarioAgg
                    ->pluck('litros')
                    ->values();

                $usoDiarioKmL = $usoDiarioAgg
                    ->pluck('kml')
                    ->values();

                $vehiculosDebug = [
                    'rows_source' => $vehRows->count(),
                    'rows_top' => $top->count(),
                    'rows_daily_grouped' => $usoDiarioAgg->count(),
                    'litros_top' => round($top->sum(fn($r) => (float) ($r['litros'] ?? 0)), 2),
                    'litros_daily' => round($usoDiarioAgg->sum(fn($r) => (float) ($r['litros'] ?? 0)), 2),
                    'labels_top' => $topVehiculosLabels->count(),
                    'labels_daily' => $usoDiarioLabels->count(),
                ];
            }

        } catch (\Throwable $e) {
            dd([
                'error' => true,
                'mensaje' => $e->getMessage(),
                'conexion' => 'fuelcontrol',
            ]);
        }

        return view('fuelcontrol.index', compact(
            'productos',
            'movimientos',
            'resumen',
            'labelsGasolina',
            'dataGasolina',
            'labelsDiesel',
            'dataDiesel',
            'topVehiculosLabels',
            'topVehiculosLitros',
            'topVehiculosKmL',
            'usoDiarioLabels',
            'usoDiarioLitros',
            'usoDiarioKmL',
            'hasOdomAny',
            'vehiculosDebug',
            'notificaciones'
        ));
    }

    public function exportVehiculosExcel(): StreamedResponse
    {
        $desde = now()->subDays(30)->startOfDay();
        $hasVehiculoId = Schema::connection('fuelcontrol')->hasColumn('movimientos', 'vehiculo_id');
        $hasOdomCol = Schema::connection('fuelcontrol')->hasColumn('movimientos', 'odometro');
        $hasOdomBombaCol = Schema::connection('fuelcontrol')->hasColumn('movimientos', 'odometro_bomba');
        $hasOdomAny = $hasOdomCol || $hasOdomBombaCol;

        $vehiculos = collect();
        $daily = [];

        if ($hasVehiculoId && $hasOdomAny) {
            $vehRows = DB::connection('fuelcontrol')
                ->table('movimientos as m')
                ->leftJoin('vehiculos as v', 'v.id', '=', 'm.vehiculo_id')
                ->leftJoin('productos as p', 'p.id', '=', 'm.producto_id')
                ->select(
                    'm.vehiculo_id',
                    'm.fecha_movimiento',
                    'm.cantidad',
                    'm.odometro',
                    'm.odometro_bomba',
                    'p.nombre as combustible_nombre'
                )
                ->selectRaw("
                    COALESCE(
                        NULLIF(TRIM(v.patente), ''),
                        CONCAT('Vehículo #', m.vehiculo_id)
                    ) as vehiculo
                ")
                ->where('m.fecha_movimiento', '>=', $desde)
                ->whereNotNull('m.vehiculo_id')
                ->where(function ($q) {
                    $q->whereNull('m.estado')
                        ->orWhere('m.estado', 'aprobado');
                })
                ->orderBy('m.vehiculo_id')
                ->orderBy('m.fecha_movimiento')
                ->get();

            foreach ($vehRows->groupBy('vehiculo_id') as $vehiculoId => $rows) {
                $prevOdo = null;
                $vehLitros = 0.0;
                $vehKm = 0.0;
                $cargas = 0;
                $vehName = (string) ($rows->first()->vehiculo ?? "Vehículo #{$vehiculoId}");
                $combustibles = [];
                $odoInicial = null;
                $odoFinal = null;
                $odoBombaInicial = null;
                $odoBombaFinal = null;

                foreach ($rows as $r) {
                    $litros = abs((float) ($r->cantidad ?? 0));
                    $odoPrincipal = (float) ($r->odometro ?? 0);
                    $odoBomba = (float) ($r->odometro_bomba ?? 0);
                    $odo = $odoPrincipal > 0 ? $odoPrincipal : ($odoBomba > 0 ? $odoBomba : 0);
                    $fecha = Carbon::parse($r->fecha_movimiento)->toDateString();

                    $vehLitros += $litros;
                    $cargas++;
                    $comb = trim((string) ($r->combustible_nombre ?? ''));
                    if ($comb !== '') {
                        $k = mb_strtolower($comb);
                        $combustibles[$k] = ($combustibles[$k] ?? 0) + $litros;
                    }

                    if (!isset($daily[$fecha])) {
                        $daily[$fecha] = ['litros' => 0.0, 'km' => 0.0];
                    }
                    $daily[$fecha]['litros'] += $litros;

                    if ($odo > 0 && !is_null($prevOdo) && $odo > $prevOdo) {
                        $deltaKm = $odo - $prevOdo;
                        $vehKm += $deltaKm;
                        $daily[$fecha]['km'] += $deltaKm;
                    }

                    if ($odoPrincipal > 0) {
                        if (is_null($odoInicial)) {
                            $odoInicial = $odoPrincipal;
                        }
                        $odoFinal = $odoPrincipal;
                    }
                    if ($odoBomba > 0) {
                        if (is_null($odoBombaInicial)) {
                            $odoBombaInicial = $odoBomba;
                        }
                        $odoBombaFinal = $odoBomba;
                    }

                    if ($odo > 0) {
                        $prevOdo = $odo;
                    }
                }

                $combustiblePrincipal = '—';
                $combustiblesUsados = '—';
                if (!empty($combustibles)) {
                    arsort($combustibles);
                    $principalKey = array_key_first($combustibles);
                    $combustiblePrincipal = ucfirst((string) $principalKey);
                    $combustiblesUsados = collect(array_keys($combustibles))
                        ->map(fn($k) => ucfirst((string) $k))
                        ->join(', ');
                }

                $vehiculos->push([
                    'vehiculo' => $vehName,
                    'combustible_principal' => $combustiblePrincipal,
                    'combustibles_usados' => $combustiblesUsados,
                    'cargas' => $cargas,
                    'litros' => round($vehLitros, 2),
                    'km' => round($vehKm, 2),
                    'kml' => ($vehLitros > 0 && $vehKm > 0) ? round($vehKm / $vehLitros, 2) : null,
                    'odo_inicial' => $odoInicial,
                    'odo_final' => $odoFinal,
                    'odo_bomba_inicial' => $odoBombaInicial,
                    'odo_bomba_final' => $odoBombaFinal,
                ]);
            }
        }

        $vehiculos = $vehiculos->sortByDesc('litros')->values();
        ksort($daily);
        $dailyRows = collect($daily)->map(function ($r, $fecha) {
            $litros = (float) ($r['litros'] ?? 0);
            $km = (float) ($r['km'] ?? 0);
            return [
                'fecha' => $fecha,
                'litros' => round($litros, 2),
                'km' => round($km, 2),
                'kml' => ($litros > 0 && $km > 0) ? round($km / $litros, 2) : null,
            ];
        })->values();

        $spreadsheet = new Spreadsheet();
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Vehiculos');
        $sheet1->mergeCells('A1:K1');
        $sheet1->setCellValue('A1', 'FuelControl - Consumo de Vehículos (últimos 30 días)');
        $sheet1->setCellValue('A2', 'Generado: ' . now()->format('d-m-Y H:i'));
        $sheet1->fromArray(
            ['Vehículo', 'Combustible principal', 'Combustibles usados', 'Cargas', 'Litros', 'Km', 'Km/L', 'Odómetro inicial', 'Odómetro final', 'Odómetro bomba inicial', 'Odómetro bomba final'],
            null,
            'A4'
        );

        $row = 5;
        if ($vehiculos->isEmpty()) {
            $sheet1->setCellValue("A{$row}", 'Sin datos para exportar');
        } else {
            foreach ($vehiculos as $v) {
                $sheet1->setCellValue("A{$row}", $v['vehiculo']);
                $sheet1->setCellValue("B{$row}", $v['combustible_principal']);
                $sheet1->setCellValue("C{$row}", $v['combustibles_usados']);
                $sheet1->setCellValue("D{$row}", $v['cargas']);
                $sheet1->setCellValue("E{$row}", $v['litros']);
                $sheet1->setCellValue("F{$row}", $v['km']);
                $sheet1->setCellValue("G{$row}", $v['kml']);
                $sheet1->setCellValue("H{$row}", $v['odo_inicial']);
                $sheet1->setCellValue("I{$row}", $v['odo_final']);
                $sheet1->setCellValue("J{$row}", $v['odo_bomba_inicial']);
                $sheet1->setCellValue("K{$row}", $v['odo_bomba_final']);
                $row++;
            }

            $sheet1->setCellValue("A{$row}", 'TOTAL');
            $sheet1->setCellValue("D{$row}", $vehiculos->sum('cargas'));
            $sheet1->setCellValue("E{$row}", round((float) $vehiculos->sum('litros'), 2));
            $sheet1->setCellValue("F{$row}", round((float) $vehiculos->sum('km'), 2));
            $totalLitros = (float) $vehiculos->sum('litros');
            $totalKm = (float) $vehiculos->sum('km');
            $sheet1->setCellValue("G{$row}", ($totalLitros > 0 && $totalKm > 0) ? round($totalKm / $totalLitros, 2) : null);
            $sheet1->getStyle("A{$row}:K{$row}")->getFont()->setBold(true);
            $row++;
        }

        foreach (range('A', 'K') as $col) {
            $sheet1->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet1->freezePane('A5');
        $sheet1->setAutoFilter('A4:K4');
        $sheet1->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet1->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet1->getStyle('A4:K4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E40AF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $lastVehRow = max(5, $row - 1);
        $sheet1->getStyle("A4:K{$lastVehRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
        ]);
        $sheet1->getStyle("D5:D{$lastVehRow}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet1->getStyle("E5:F{$lastVehRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet1->getStyle("G5:G{$lastVehRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet1->getStyle("H5:K{$lastVehRow}")->getNumberFormat()->setFormatCode('#,##0');

        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Diario');
        $sheet2->mergeCells('A1:D1');
        $sheet2->setCellValue('A1', 'FuelControl - Resumen Diario');
        $sheet2->setCellValue('A2', 'Generado: ' . now()->format('d-m-Y H:i'));
        $sheet2->fromArray(['Fecha', 'Litros', 'Km', 'Km/L'], null, 'A4');

        $row = 5;
        if ($dailyRows->isEmpty()) {
            $sheet2->setCellValue("A{$row}", 'Sin datos para exportar');
        } else {
            foreach ($dailyRows as $d) {
                $sheet2->setCellValue("A{$row}", Carbon::parse($d['fecha'])->format('d-m-Y'));
                $sheet2->setCellValue("B{$row}", $d['litros']);
                $sheet2->setCellValue("C{$row}", $d['km']);
                $sheet2->setCellValue("D{$row}", $d['kml']);
                $row++;
            }
        }

        foreach (range('A', 'D') as $col) {
            $sheet2->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet2->freezePane('A5');
        $sheet2->setAutoFilter('A4:D4');
        $sheet2->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet2->getStyle('A4:D4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F766E']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $lastDailyRow = max(5, $row - 1);
        $sheet2->getStyle("A4:D{$lastDailyRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
        ]);
        $sheet2->getStyle("B5:C{$lastDailyRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet2->getStyle("D5:D{$lastDailyRow}")->getNumberFormat()->setFormatCode('#,##0.00');

        $filename = 'fuelcontrol_vehiculos_' . now()->format('Ymd_His') . '.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /* =========================
     * VER XML (MODAL)
     * ========================= */


    public function show($movimientoId)
    {
        $movimiento = DB::connection('fuelcontrol')
            ->table('movimientos')
            ->where('id', $movimientoId)
            ->firstOrFail();

        $ruta = 'xml/' . $movimiento->xml_path;

        if (!Storage::disk('local')->exists($ruta)) {
            abort(404, 'Archivo XML no encontrado');
        }

        $contenidoXml = Storage::disk('local')->get($ruta);

        return view('fuelcontrol.xml.modal', [
            'xml' => $contenidoXml,
            'movimiento' => $movimiento
        ]);
    }

    public function aprobar($movimientoId)
    {
        $db = DB::connection('fuelcontrol');

        $movimiento = $db->table('movimientos')
            ->where('id', $movimientoId)
            ->first();

        if (!$movimiento) {
            return response()->json(['error' => 'Movimiento no encontrado'], 404);
        }

        // 🔒 Evitar doble proceso
        if ($movimiento->estado !== 'pendiente') {
            return response()->json([
                'error' => 'Este documento ya fue procesado'
            ], 400);
        }

        $db->beginTransaction();

        try {

            // 🔥 SOLO VEHICULO debe entrar acá
            if ($movimiento->tipo === 'vehiculo') {

                $db->table('productos')
                    ->where('id', $movimiento->producto_id)
                    ->increment('cantidad', $movimiento->cantidad);
            }

            $db->table('movimientos')
                ->where('id', $movimientoId)
                ->update([
                    'estado' => 'aprobado',
                    'updated_at' => now()
                ]);

            $db->commit();

            return response()->json(['ok' => true]);

        } catch (\Throwable $e) {

            $db->rollBack();

            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }

    }

    public function rechazar($movimientoId)
    {
        $db = DB::connection('fuelcontrol');

        $movimiento = $db->table('movimientos')
            ->where('id', $movimientoId)
            ->first();

        if (!$movimiento) {
            return response()->json(['error' => 'Movimiento no encontrado'], 404);
        }

        // 🔒 Evitar doble proceso
        if ($movimiento->estado !== 'pendiente') {
            return response()->json([
                'error' => 'Este documento ya fue procesado'
            ], 400);
        }

        try {

            $db->table('movimientos')
                ->where('id', $movimientoId)
                ->update([
                    'estado' => 'rechazado',
                    'updated_at' => now()
                ]);

            return response()->json(['ok' => true]);

        } catch (\Throwable $e) {

            return response()->json([
                'error' => 'Error interno al rechazar'
            ], 500);
        }
    }





}
