<?php

namespace App\Http\Controllers;

use App\Models\PdfImport;
use App\Models\PdfLine;
use App\Models\AgrakRegistro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ExcelImportController extends Controller
{
    public function excelForm()
    {
        return redirect()->route('pdf.import.form');
    }

    /**
     * Importa un Excel con columnas como:
     * - "GDD" (guía)
     * - "Fecha Recepción"
     * - "Razón Social Productor"
     * - "RUT Productor"
     * - "Kilos" (numérico)
     *
     * 1 fila = 1 PdfImport
     */
    public function importExcelQc(Request $request)
    {
        $request->validate([
            'excel' => ['required', 'file', 'mimes:xlsx,xls', 'max:20480'],
        ]);

        $file = $request->file('excel');
        $storedPath = $file->store('imports/excel', 'public');
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        if (count($rows) < 2) {
            return back()->withErrors(['excel' => 'El Excel no tiene filas de datos.']);
        }

        $headerRow = $rows[1];
        $headerToCol = [];
        foreach ($headerRow as $colLetter => $headerName) {
            $key = trim((string) $headerName);
            if ($key !== '') {
                $headerToCol[mb_strtolower($key)] = $colLetter;
            }
        }

        $get = function (array $row, string $header) use ($headerToCol) {
            $h = mb_strtolower(trim($header));
            $col = $headerToCol[$h] ?? null;
            return $col ? ($row[$col] ?? null) : null;
        };

        $parseNumber = function ($v): ?float {
            if ($v === null) return null;
            if (is_int($v) || is_float($v)) return (float) $v;
            $s = trim((string) $v);
            if ($s === '') return null;
            $s = str_replace("\xc2\xa0", '', $s);
            $hasDot = str_contains($s, '.');
            $hasComma = str_contains($s, ',');
            if ($hasDot && $hasComma) {
                $s = str_replace('.', '', $s);
                $s = str_replace(',', '.', $s);
            } elseif ($hasComma) {
                $s = str_replace(',', '.', $s);
            }
            return is_numeric($s) ? (float) $s : null;
        };

        $parseDate = function ($v): ?string {
            if ($v === null) return null;
            $s = trim((string) $v);
            if ($s === '') return null;
            if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $s, $m)) return "{$m[3]}-{$m[2]}-{$m[1]}";
            if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $s, $m)) return "{$m[3]}-{$m[2]}-{$m[1]}";
            return null;
        };

        $created = 0;
        $duplicates = 0;
        $skippedNoGuia = 0;
        $importReport = [];
        $template = 'VT';

        for ($i = 2; $i <= count($rows); $i++) {
            $r = $rows[$i];
            $guia = trim((string) $get($r, 'GDD'));

            if ($guia === '') {
                $skippedNoGuia++;
                $importReport[] = [
                    'file' => $file->getClientOriginalName(),
                    'status' => 'skip',
                    'template' => $template,
                    'guia' => null,
                    'reason' => "Fila {$i}: sin GDD",
                ];
                continue;
            }

            $fecha = $parseDate($get($r, 'Fecha Recepción'));
            $rutProd = trim((string) $get($r, 'RUT Productor'));
            $razonProd = trim((string) $get($r, 'Razón Social Productor'));
            $kgs = $parseNumber($get($r, 'Cantidad Recepcionada'));
            $unidad = trim((string) $get($r, 'Kilos')); 

            $exists = PdfImport::where('guia_no', $guia)->exists();

            if ($exists) {
                $duplicates++;
                $importReport[] = [
                    'file' => $file->getClientOriginalName(),
                    'status' => 'duplicate',
                    'template' => $template,
                    'guia' => $guia,
                    'reason' => "Fila {$i}: guía ya importada",
                ];
                continue;
            }

            $meta = [
                'source' => 'excel',
                'excel_file' => $file->getClientOriginalName(),
                'excel_stored_path' => $storedPath,
                'row' => $i,
                'kgs_recibido' => $kgs,
                'unidad' => $unidad,
                'rut_productor' => $rutProd,
                'razon_social_productor' => $razonProd,
                'empresa' => $get($r, 'Empresa'),
                'sucursal' => $get($r, 'Desc. Sucursal'),
                'guia_pesaje' => $get($r, 'Guía de pesaje'),
                'producto' => $get($r, 'Descripción Producto'),
                'cantidad_recepcionada' => $kgs,
            ];

            DB::transaction(function () use ($file, $storedPath, $template, $guia, $fecha, $rutProd, $razonProd, $kgs, $unidad, $meta, $i, $r, &$created) {
                $import = PdfImport::create([
                    'original_name' => 'EXCEL: ' . $file->getClientOriginalName() . " (GDD {$guia})",
                    'stored_path' => $storedPath,
                    'template' => $template,
                    'guia_no' => $guia,
                    'doc_fecha' => $fecha,
                    'productor' => trim(($rutProd ? $rutProd . ' - ' : '') . $razonProd) ?: null,
                    'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
                ]);

                $detailLines = [
                    "EXCEL_ROW: {$i}",
                    "GDD: {$guia}",
                    "Fecha Recepción: " . ($fecha ?? '—'),
                    "RUT Productor: " . ($rutProd !== '' ? $rutProd : '—'),
                    "Razón Social Productor: " . ($razonProd !== '' ? $razonProd : '—'),
                    "Kgs recibidos: " . (is_null($kgs) ? '—' : (string) $kgs),
                    "Unidad: " . ($unidad !== '' ? $unidad : '—'),
                    "Empresa: " . (!empty($meta['empresa']) ? $meta['empresa'] : '—'),
                    "Sucursal: " . (!empty($meta['sucursal']) ? $meta['sucursal'] : '—'),
                    "Guía de pesaje: " . (!empty($meta['guia_pesaje']) ? $meta['guia_pesaje'] : '—'),
                    "Producto: " . (!empty($meta['producto']) ? $meta['producto'] : '—'),
                    "Cantidad recepcionada: " . (is_null($meta['cantidad_recepcionada'] ?? null) ? '—' : (string) $meta['cantidad_recepcionada']),
                ];

                $detailLines[] = "— DETALLE COMPLETO FILA —";
                foreach ($r as $col => $val) {
                    if ($val === null) continue;
                    $valStr = is_string($val) ? trim($val) : (string) $val;
                    if ($valStr === '') continue;
                    $detailLines[] = "COL {$col}: {$valStr}";
                }

                $rowsToInsert = [];
                foreach ($detailLines as $idx => $line) {
                    $rowsToInsert[] = [
                        'pdf_import_id' => $import->id,
                        'line_no' => $idx + 1,
                        'content' => $line,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if (!empty($rowsToInsert)) {
                    PdfLine::insert($rowsToInsert);
                }

                $created++;
            });

            $importReport[] = [
                'file' => $file->getClientOriginalName(),
                'status' => 'imported',
                'template' => $template,
                'guia' => $guia,
                'reason' => "Fila {$i}: importado OK (con detalle PdfLine)",
            ];
        }

        return redirect()
            ->route('pdf.index')
            ->with('ok', "Excel importado ✅ | Creados: {$created} | Duplicados: {$duplicates} | Sin guía: {$skippedNoGuia}")
            ->with('import_report', $importReport);
    }

    /**
     * Agrak
     * Importa un Excel con columnas como:
     * - "Código bin"
     * - "Fecha registro"
     * - "Hora registro"
     * - "Exportadora" (puede venir duplicado)
     * - "Número de sello" (puede venir duplicado)
     *
     * 1 fila = 1 registro de huerto
     */
    public function importExcelAgrak(Request $request)
    {
        $request->validate([
            'excel' => ['required', 'file', 'mimes:xlsx,xls', 'max:20480'],
        ]);

        $file = $request->file('excel');
        $storedPath = $file->store('imports/excel_agrak', 'public');
        $spreadsheet = IOFactory::load($file->getRealPath());

        $sheet = $spreadsheet->getActiveSheet();
        $found = false;

        foreach ($spreadsheet->getWorksheetIterator() as $ws) {
            $tmp = $ws->toArray(null, true, true, true);
            for ($r = 1; $r <= min(30, count($tmp)); $r++) {
                $row = $tmp[$r] ?? [];
                $joined = mb_strtolower(implode(' ', array_map(fn($v) => trim((string) $v), $row)));
                if (str_contains($joined, 'bin')) {
                    $sheet = $ws;
                    $found = true;
                    break 2;
                }
            }
        }

        $rows = $sheet->toArray(null, true, true, true);

        if (count($rows) < 2) {
            return back()->withErrors(['excel' => 'El Excel no tiene filas de datos.']);
        }

        $norm = function ($s): string {
            $s = (string) $s;
            $s = str_replace("\xc2\xa0", ' ', $s);
            $s = preg_replace('/\s+/u', ' ', $s);
            $s = trim($s);
            $s = mb_strtolower($s);

            $noAcc = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
            if ($noAcc !== false && $noAcc !== '') {
                $s = mb_strtolower($noAcc);
            }
            return $s;
        };

        $headerRowIndex = null;
        for ($r = 1; $r <= min(30, count($rows)); $r++) {
            $row = $rows[$r] ?? [];
            $keys = array_map(fn($v) => $norm($v), $row);
            $hasBin = false;
            $hasFecha = false;

            foreach ($keys as $k) {
                if ($k === '') continue;
                if (str_contains($k, 'bin')) $hasBin = true;
                if (str_contains($k, 'fecha') && str_contains($k, 'registro')) $hasFecha = true;
            }

            if ($hasBin && $hasFecha) {
                $headerRowIndex = $r;
                break;
            }
        }

        if (!$headerRowIndex) {
            return back()->withErrors([
                'excel' => 'No pude detectar la fila de encabezados (busqué "bin" y "fecha registro" en las primeras 30 filas).'
            ]);
        }

        $headerRow = $rows[$headerRowIndex];
        $headerToCol = [];
        foreach ($headerRow as $colLetter => $headerName) {
            $k = $norm($headerName);
            if ($k !== '') $headerToCol[$k] = $colLetter;
        }

        $get = function (array $row, string $headerExact) use ($headerToCol, $norm) {
            $h = $norm($headerExact);
            $col = $headerToCol[$h] ?? null;
            return $col ? ($row[$col] ?? null) : null;
        };

        $findColsByContains = function (string $needle) use ($headerRow, $norm) {
            $needle = $norm($needle);
            $cols = [];
            foreach ($headerRow as $col => $name) {
                $n = $norm($name);
                if ($n !== '' && str_contains($n, $needle)) $cols[] = $col;
            }
            return $cols;
        };

        $exportadoraCols = $findColsByContains('exportadora');
        $selloCols = $findColsByContains('sello');
        $binCols = $findColsByContains('bin');
        $binCol = $binCols[0] ?? null;

        $parseDate = function ($v): ?string {
            if ($v === null) return null;
            $s = trim((string) $v);
            if ($s === '') return null;
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return $s;
            if (preg_match('/^(\d{2})[\/\-](\d{2})[\/\-](\d{4})$/', $s, $m)) return "{$m[3]}-{$m[2]}-{$m[1]}";
            return null;
        };

        $parseTime = function ($v): ?string {
            if ($v === null) return null;
            $s = trim((string) $v);
            if ($s === '') return null;
            if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $s)) return strlen($s) === 5 ? ($s . ':00') : $s;
            return null;
        };

        $parseInt = function ($v): ?int {
            if ($v === null) return null;
            if (is_int($v)) return $v;
            if (is_float($v)) return (int) round($v);
            $s = trim((string) $v);
            if ($s === '') return null;
            $s = str_replace(['.', ' '], '', $s);
            return ctype_digit($s) ? (int) $s : null;
        };

        $normalizeBin = function ($v): ?string {
            if ($v === null) return null;
            $s = trim((string) $v);
            if ($s === '') return null;
            if (str_contains($s, ';')) {
                $parts = explode(';', $s, 2);
                $s = trim($parts[1] ?? $s);
            }
            return $s !== '' ? $s : null;
        };

        $created = 0;
        $duplicates = 0;
        $skipped = 0;
        $importReport = [];
        $startRow = $headerRowIndex + 1;

        for ($i = $startRow; $i <= count($rows); $i++) {
            $r = $rows[$i];
            $rawBin = $binCol ? ($r[$binCol] ?? null) : null;
            $codigoBin = $normalizeBin($rawBin);

            if (!$codigoBin) {
                $skipped++;
                $importReport[] = [
                    'file' => $file->getClientOriginalName(),
                    'status' => 'skip',
                    'reason' => "Fila {$i}: sin Código bin",
                ];
                continue;
            }

            $fecha = $parseDate($get($r, 'Fecha registro'));
            $hora = $parseTime($get($r, 'Hora registro'));

            $exists = AgrakRegistro::where('codigo_bin', $codigoBin)
                ->where('fecha_registro', $fecha)
                ->where('hora_registro', $hora)
                ->exists();

            if ($exists) {
                $duplicates++;
                $importReport[] = [
                    'file' => $file->getClientOriginalName(),
                    'status' => 'duplicate',
                    'reason' => "Fila {$i}: duplicado (bin+fecha+hora)",
                    'codigo_bin' => $codigoBin,
                ];
                continue;
            }

            $export1 = $exportadoraCols[0] ?? null;
            $export2 = $exportadoraCols[1] ?? null;
            $sello1 = $selloCols[0] ?? null;
            $sello2 = $selloCols[1] ?? null;

            AgrakRegistro::create([
                'codigo_bin' => $codigoBin,
                'nombre_cosecha' => trim((string) $get($r, 'Nombre cosecha')) ?: null,
                'nombre_campo' => trim((string) $get($r, 'Nombre campo')) ?: null,
                'ceco_campo' => trim((string) $get($r, 'Ceco campo')) ?: null,
                'etiquetas_campo' => trim((string) $get($r, 'Etiquetas campo')) ?: null,
                'cuartel' => trim((string) $get($r, 'Cuartel')) ?: null,
                'ceco_cuartel' => trim((string) $get($r, 'Ceco cuartel')) ?: null,
                'etiquetas_cuartel' => trim((string) $get($r, 'Etiquetas cuartel')) ?: null,
                'especie' => trim((string) $get($r, 'Especie')) ?: null,
                'variedad' => trim((string) $get($r, 'Variedad')) ?: null,
                'fecha_registro' => $fecha,
                'hora_registro' => $hora,
                'coordenadas' => trim((string) $get($r, 'Coordenadas')) ?: null,
                'usuario' => trim((string) $get($r, 'Usuario')) ?: null,
                'id_usuario' => trim((string) $get($r, 'ID usuario')) ?: null,
                'cuadrilla' => trim((string) $get($r, 'Cuadrilla')) ?: null,
                'numero_bandejas_palet' => $parseInt($get($r, 'Número bandejas en PALET.')),
                'maquina' => trim((string) $get($r, 'Maquina')) ?: null,
                'nombre_chofer' => trim((string) $get($r, 'NOMBRE CHOFER')) ?: null,
                'patente_camion' => trim((string) $get($r, 'PATENTE CAMIÓN')) ?: null,
                'exportadora_1' => $export1 ? (trim((string) ($r[$export1] ?? '')) ?: null) : null,
                'exportadora_2' => $export2 ? (trim((string) ($r[$export2] ?? '')) ?: null) : null,
                'vuelta' => $parseInt($get($r, 'VUELTA')),
                'observacion' => trim((string) $get($r, 'OBSERVACIÓN')) ?: null,
                'numero_sello_1' => $sello1 ? (trim((string) ($r[$sello1] ?? '')) ?: null) : null,
                'numero_sello_2' => $sello2 ? (trim((string) ($r[$sello2] ?? '')) ?: null) : null,
                'source_file' => $file->getClientOriginalName(),
                'source_row' => $i,
            ]);

            $created++;
            $importReport[] = [
                'file' => $file->getClientOriginalName(),
                'status' => 'imported',
                'reason' => "Fila {$i}: importado OK",
                'codigo_bin' => $codigoBin,
            ];
        }

        return redirect()
            ->back()
            ->with('ok', "Agrak importado ✅ | Creados: {$created} | Duplicados: {$duplicates} | Saltados: {$skipped}")
            ->with('import_report', $importReport);
    }

    /**
     * Importa un Excel con columnas como:
     * - "Guía Despacho"
     * - "Fecha"
     * - "Productor"
     * - "Albaran"
     * - "Tipo Fruta"
     * - "Origen"
     * - "Bandejas"
     * - "Kg Recepcionados"
     * - "Clasificación"
     * - "% IQF"
     * - "% Block"
     * - "Calidad"
     *
     * 1 fila = 1 guía recepción fruta granel
     */
    public function importExcelRfp(Request $request)
    {
        $request->validate([
            'excel' => ['required', 'file', 'mimes:xlsx,xls', 'max:20480'],
        ]);

        $file = $request->file('excel');
        $storedPath = $file->store('imports/excel_rfp', 'public');

        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        if (count($rows) < 2) {
            return back()->withErrors(['excel' => 'El Excel no tiene filas de datos.']);
        }

        $norm = function ($s): string {
            $s = (string) $s;
            $s = str_replace(["\xc2\xa0", 'í', 'Í'], [' ', 'i', 'i'], $s);
            $s = preg_replace('/\s+/u', ' ', $s);
            $s = trim($s);
            return mb_strtolower($s);
        };

        $headerRow = $rows[1];
        $headerToCol = [];

        foreach ($headerRow as $col => $name) {
            $k = $norm($name);
            if ($k !== '') {
                $headerToCol[$k] = $col;
            }
        }

        $get = function (array $row, array $aliases) use ($headerToCol) {
            foreach ($aliases as $a) {
                if (isset($headerToCol[$a])) return $row[$headerToCol[$a]] ?? null;
            }
            return null;
        };

        $parseNumber = function ($v): ?float {
            if ($v === null || $v === '') return null;
            if (is_int($v) || is_float($v)) return (float) $v;
            $s = trim((string) $v);
            if (preg_match('/^\d{1,3}(\.\d{3})*,\d+$/', $s)) {
                $s = str_replace('.', '', $s);
                $s = str_replace(',', '.', $s);
            } elseif (str_contains($s, ',')) {
                $s = str_replace(',', '.', $s);
            }
            return is_numeric($s) ? (float) $s : null;
        };

        $parseDate = function ($v): ?string {
            if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', (string) $v, $m)) return "{$m[3]}-{$m[2]}-{$m[1]}";
            return null;
        };

        $template = 'RFP';
        $created = $duplicates = $skipped = 0;
        $report = [];

        for ($i = 2; $i <= count($rows); $i++) {
            $r = $rows[$i];
            $guia = trim((string) $get($r, ['guia despacho', 'guia']));

            if ($guia === '') {
                $skipped++;
                $report[] = [
                    'row' => $i,
                    'status' => 'skip',
                    'reason' => 'Guía vacía / no detectada',
                ];
                continue;
            }

            if (PdfImport::where('guia_no', $guia)->exists()) {
                $duplicates++;
                $report[] = [
                    'row' => $i,
                    'status' => 'duplicate',
                    'guia' => $guia,
                ];
                continue;
            }

            $fecha = $parseDate($get($r, ['fecha']));
            $productor = trim((string) $get($r, ['productor']));

            $meta = [
                'albaran' => $get($r, ['albaran']),
                'kgs_recibido' => $parseNumber($get($r, ['kg recepcionados', 'kgs recepcionados'])),
                'bandejas_total' => $parseNumber($get($r, ['bandejas'])),
                'clasificacion' => $get($r, ['clasificacion']),
                'iqf_pct' => $parseNumber($get($r, ['% iqf'])),
                'block_pct' => $parseNumber($get($r, ['% block'])),
                'calidad' => $get($r, ['calidad']),
                'row' => $i,
            ];

            DB::transaction(function () use ($file, $storedPath, $template, $guia, $fecha, $productor, $meta, &$created) {
                $import = PdfImport::create([
                    'original_name' => 'EXCEL RFP: ' . $file->getClientOriginalName(),
                    'stored_path' => $storedPath,
                    'template' => $template,
                    'guia_no' => $guia,
                    'doc_fecha' => $fecha,
                    'productor' => $productor ?: null,
                    'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
                ]);

                foreach ($meta as $k => $v) {
                    if ($v !== null && $v !== '') {
                        PdfLine::create([
                            'pdf_import_id' => $import->id,
                            'line_no' => 0,
                            'content' => "{$k}: {$v}",
                        ]);
                    }
                }
                $created++;
            });

            $report[] = [
                'row' => $i,
                'status' => 'imported',
                'guia' => $guia,
            ];
        }

        return redirect()
            ->route('pdf.index')
            ->with('ok', "RFP importado ✅ | Creados: {$created} | Duplicados: {$duplicates} | Saltados: {$skipped}")
            ->with('import_report', $report);
    }
}
