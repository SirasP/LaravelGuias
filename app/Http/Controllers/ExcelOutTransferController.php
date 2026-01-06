<?php

namespace App\Http\Controllers;

use App\Models\ExcelOutTransfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Models\PdfImport;
use App\Models\ExcelOutTransferLine;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
class ExcelOutTransferController extends Controller
{

    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $exists = $request->get('exists'); // '1' | '0' | null
        $orderBy = $request->get('order_by', 'fecha_prevista');
        $dir = $request->get('dir', 'desc');

        // ============================
        // QUERY BASE (SIN GUÍAS VACÍAS)
        // ============================
        $query = ExcelOutTransfer::query()
            // 🔒 SOLO GUÍAS REALES
            ->where('estado', 'Realizado')

            // seguridad extra (opcional)
            ->whereNotNull('guia_entrega')
            ->whereRaw("TRIM(guia_entrega) <> ''")

            // 🔥 SOLO TRANSFERS REALES
            ->whereNotNull('patente')
            ->whereRaw("TRIM(patente) <> ''")
            ->whereNotNull('chofer')
            ->whereRaw("TRIM(chofer) <> ''")

            ->select('excel_out_transfers.*')
            ->selectRaw("
        CASE
            WHEN EXISTS (
                SELECT 1
                FROM pdf_imports p
                WHERE TRIM(LEADING '0' FROM p.guia_no)
                  = TRIM(LEADING '0' FROM excel_out_transfers.guia_entrega)
            )
            THEN 1 ELSE 0
        END as exists_guia
    ");



        // ============================
        // BÚSQUEDA
        // ============================
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('contacto', 'like', "%{$q}%")
                    ->orWhere('patente', 'like', "%{$q}%")
                    ->orWhere('guia_entrega', 'like', "%{$q}%")
                    ->orWhere('referencia', 'like', "%{$q}%")
                    ->orWhere('archivo_dte', 'like', "%{$q}%");
            });
        }

        // ============================
        // FILTRO MATCH / NO MATCH
        // ============================
        if ($exists === '1') {
            $query->having('exists_guia', 1);
        } elseif ($exists === '0') {
            $query->having('exists_guia', 0);
        }

        // ============================
        // ORDEN
        // ============================
        if ($orderBy === 'exists_guia') {
            $query->orderBy('exists_guia', $dir)
                ->orderBy('fecha_prevista', 'desc');
        } elseif ($orderBy === 'guia_entrega') {
            $query->orderBy('guia_entrega', $dir);
        } else {
            $query->orderBy('fecha_prevista', $dir);
        }

        $rows = $query->paginate(25)->withQueryString();

        // ============================
        // CONTADORES (MISMA REGLA)
        // ============================
        $baseCountQuery = ExcelOutTransfer::query()
            ->whereNotNull('guia_entrega')
            ->whereRaw("TRIM(guia_entrega) <> ''");

        if ($q !== '') {
            $baseCountQuery->where(function ($w) use ($q) {
                $w->where('contacto', 'like', "%{$q}%")
                    ->orWhere('patente', 'like', "%{$q}%")
                    ->orWhere('guia_entrega', 'like', "%{$q}%")
                    ->orWhere('referencia', 'like', "%{$q}%")
                    ->orWhere('archivo_dte', 'like', "%{$q}%");
            });
        }

        $total = (clone $baseCountQuery)->count();

        $matched = (clone $baseCountQuery)
            ->whereRaw("
            EXISTS (
                SELECT 1
                FROM pdf_imports p
                WHERE TRIM(LEADING '0' FROM p.guia_no)
                    = TRIM(LEADING '0' FROM excel_out_transfers.guia_entrega)
            )
        ")
            ->count();

        $unmatched = $total - $matched;

        return view('excel_out_transfers.index', compact(
            'rows',
            'q',
            'exists',
            'orderBy',
            'dir',
            'total',
            'matched',
            'unmatched'
        ));
    }

    public function importExcelOutTransfers(Request $request)
    {
        $request->validate([
            'excel' => ['required', 'file', 'mimes:xlsx,xls', 'max:20480'],
        ]);

        $file = $request->file('excel');
        $fileName = $file->getClientOriginalName();
        $file->store('imports/excel', 'public');

        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        if (count($rows) < 2) {
            return back()->withErrors(['excel' => 'El Excel no tiene filas de datos.']);
        }

        // ===== headers =====
        $headerRow = $rows[1] ?? [];
        $headerToCol = [];
        foreach ($headerRow as $col => $name) {
            $key = trim((string) $name);
            if ($key !== '')
                $headerToCol[mb_strtolower($key)] = $col;
        }

        $get = function (array $row, string $header) use ($headerToCol) {
            $h = mb_strtolower(trim($header));
            $col = $headerToCol[$h] ?? null;
            return $col ? ($row[$col] ?? null) : null;
        };

        $val = function ($v) {
            if ($v === null)
                return null;
            if (is_string($v)) {
                $v = trim($v);
                return $v === '' ? null : $v;
            }
            return $v;
        };

        $parseDateTime = function ($v): ?string {
            if ($v === null)
                return null;
            $s = trim((string) $v);
            if ($s === '')
                return null;

            if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}$/', $s))
                return $s;

            if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})\s+(\d{2}:\d{2}:\d{2})$/', $s, $m)) {
                return "{$m[3]}-{$m[2]}-{$m[1]} {$m[4]}";
            }

            return null;
        };

        $normalizeGuia = function ($v): ?string {
            $s = trim((string) $v);
            if ($s === '')
                return null;
            $s = preg_replace('/\D+/', '', $s);
            $s = ltrim($s, '0');
            return $s ?: null;
        };

        $normalizeRef = function ($v): ?string {
            $s = trim((string) $v);
            if ($s === '')
                return null;
            $s = preg_replace('/\s+/', ' ', $s);
            return mb_strtoupper($s);
        };

        $parseNumber = function ($v): ?float {
            if ($v === null)
                return null;
            if (is_int($v) || is_float($v))
                return (float) $v;

            $s = trim((string) $v);
            if ($s === '')
                return null;

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

        $createdHeaders = 0;
        $updatedHeaders = 0;
        $createdLines = 0;
        $updatedLines = 0;
        $skipped = 0;
        $importReport = [];

        DB::transaction(function () use ($rows, $fileName, $get, $val, $parseDateTime, $normalizeGuia, $normalizeRef, $parseNumber, &$createdHeaders, &$updatedHeaders, &$createdLines, &$updatedLines, &$skipped, &$importReport) {
            $currentKey = null;       // "435" o "REF:WH/OUT/00020"
            $currentHeaderId = null;

            for ($i = 2; $i <= count($rows); $i++) {
                $r = $rows[$i] ?? [];

                $guiaRow = $normalizeGuia($get($r, 'Número de guía de entrega'));
                $refRow = $normalizeRef($get($r, 'Referencia'));

                $producto = trim((string) $get($r, 'Movimiento de stock/Producto'));
                $cantidad = $parseNumber($get($r, 'Movimiento de stock/Cantidad'));

                // 1) Definir agrupación / contexto
                if ($guiaRow) {
                    $currentKey = $guiaRow;         // guía real
                    $currentHeaderId = null;
                } elseif ($refRow) {
                    $currentKey = "REF:" . $refRow; // SIN guía => por referencia
                    $currentHeaderId = null;
                }
                // si viene fila sin guía y sin referencia, seguimos usando el contexto anterior (si existe)

                // 2) Si no hay contexto, skip
                if (!$currentKey) {
                    // fila realmente vacía => skip silencioso
                    if ($producto === '' && $cantidad === null) {
                        $skipped++;
                        continue;
                    }

                    $skipped++;
                    $importReport[] = [
                        'file' => $fileName,
                        'status' => 'skip',
                        'template' => 'EXCEL',
                        'guia' => null,
                        'reason' => "Fila {$i}: sin guía y sin referencia (no se puede agrupar)",
                    ];
                    continue;
                }

                $isNumericGuia = ctype_digit((string) $currentKey);
                $importKey = $isNumericGuia ? null : $currentKey;

                // 3) Armar cabecera (NO pisar con null)
                $headerData = [
                    'source_file' => $fileName,
                    'excel_row' => $i,
                    'guia_entrega' => $isNumericGuia ? $currentKey : null,
                    'import_key' => $importKey,
                    'raw' => $r,
                ];

                if ($v = $val($get($r, 'Contacto')))
                    $headerData['contacto'] = $v;
                if ($v = $val($get($r, 'Patente')))
                    $headerData['patente'] = $v;
                if ($v = $val($get($r, 'Chofer')))
                    $headerData['chofer'] = $v;
                if ($v = $normalizeRef($get($r, 'Referencia')))
                    $headerData['referencia'] = $v;

                if ($v = $val($get($r, 'Estado')))
                    $headerData['estado'] = $v;
                if ($v = $val($get($r, 'Documento origen')))
                    $headerData['documento_origen'] = $v;
                if ($v = $val($get($r, 'Prioridad')))
                    $headerData['prioridad'] = $v;
                if ($v = $val($get($r, 'Archivo DTE')))
                    $headerData['archivo_dte'] = $v;
                if ($v = $val($get($r, 'Ubicación origen')))
                    $headerData['ubicacion_origen'] = $v;
                if ($v = $val($get($r, 'Ubicación de destino')))
                    $headerData['ubicacion_destino'] = $v;

                if ($fechaPrev = $parseDateTime($get($r, 'Fecha prevista')))
                    $headerData['fecha_prevista'] = $fechaPrev;
                if ($fechaTras = $parseDateTime($get($r, 'Fecha de traslado')))
                    $headerData['fecha_traslado'] = $fechaTras;

                // 4) Upsert cabecera (NO DUPLICAR)
                // 4) Buscar cabecera existente
                if ($isNumericGuia) {
                    $transfer = ExcelOutTransfer::where('guia_entrega', $currentKey)->first();
                } else {
                    $transfer = ExcelOutTransfer::where('import_key', $importKey)->first();
                }

                // 5) Crear o actualizar
                if (!$transfer) {

                    // 👉 SOLO al crear permites estado
                    if ($v = $val($get($r, 'Estado'))) {
                        $headerData['estado'] = $v;
                    }

                    $transfer = ExcelOutTransfer::create($headerData);
                    $currentHeaderId = $transfer->id;
                    $createdHeaders++;

                    $importReport[] = [
                        'file' => $fileName,
                        'status' => 'imported',
                        'template' => 'EXCEL',
                        'guia' => $isNumericGuia
                            ? $currentKey
                            : str_replace('REF:', '', (string) $importKey),
                        'reason' => "Fila {$i}: cabecera creada",
                    ];

                } else {

                    // 🚫 JAMÁS tocar estado en update
                    unset($headerData['estado']);

                    $transfer->update($headerData);
                    $currentHeaderId = $transfer->id;
                    $updatedHeaders++;
                }


                $currentHeaderId = $transfer->id;



                // 5) Upsert línea por (transfer_id + excel_row) => NO DUPLICA
                if ($producto !== '' || $cantidad !== null) {
                    $line = ExcelOutTransferLine::updateOrCreate(
                        [
                            'excel_out_transfer_id' => $currentHeaderId,
                            'excel_row' => $i,
                        ],
                        [
                            'producto' => $producto !== '' ? $producto : null,
                            'cantidad' => $cantidad,
                            'source_file' => $fileName,
                            'raw' => $r,
                        ]
                    );

                    if ($line->wasRecentlyCreated)
                        $createdLines++;
                    else
                        $updatedLines++;
                }
            }
        });

        return redirect()
            ->route('excel_out_transfers.index')
            ->with('ok', "Excel importado ✅ | Cabeceras nuevas: {$createdHeaders} | Cabeceras actualizadas: {$updatedHeaders} | Ítems nuevos: {$createdLines} | Ítems actualizados: {$updatedLines} | Saltados: {$skipped}")
            ->with('import_report', $importReport);
    }

    public function show(ExcelOutTransfer $transfer)
    {
        $transfer->load('lines');

        return view('excel_out_transfers.show', compact('transfer'));
    }
    public function export(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $exists = $request->get('exists');
        $orderBy = $request->get('order_by', 'fecha_prevista');
        $dir = strtolower((string) $request->get('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        // ===== 1) Tipos de bandejas (dinámico) =====
        $trayTypes = DB::table('excel_out_transfer_lines')
            ->whereNotNull('producto')
            ->where('producto', '!=', '')
            ->whereRaw("UPPER(producto) LIKE '%BANDE%'")

            ->selectRaw("TRIM(UPPER(producto)) as t")
            ->distinct()
            ->orderBy('t')
            ->pluck('t')
            ->values()
            ->all();

        $trayTypes = array_slice($trayTypes, 0, 25);

        // ===== 1b) Tipos de productos KG (NO bandejas) =====
        $productTypes = DB::table('excel_out_transfer_lines')
            ->whereNotNull('producto')
            ->where('producto', '!=', '')
            ->whereRaw("UPPER(producto) NOT LIKE '%BANDE%'") // <- todo lo que NO sea bandeja/bandejón
            ->whereRaw("UPPER(producto) NOT LIKE '%HONDA TRX 250 TM%'")
            ->selectRaw("TRIM(UPPER(producto)) as t")
            ->distinct()
            ->orderBy('t')
            ->pluck('t')
            ->values()
            ->all();

        $productTypes = array_slice($productTypes, 0, 25);

        $qtyNorm = "(
  CASE
    WHEN l.cantidad IS NULL OR l.cantidad = '' THEN 0

    -- 622.000 => 622
    WHEN CAST(l.cantidad AS CHAR) REGEXP '^[0-9]+\\.[0]{3}$' THEN
      CAST(SUBSTRING_INDEX(CAST(l.cantidad AS CHAR), '.', 1) AS UNSIGNED)

    -- 622,000 => 622
    WHEN CAST(l.cantidad AS CHAR) REGEXP '^[0-9]+,[0]{3}$' THEN
      CAST(SUBSTRING_INDEX(CAST(l.cantidad AS CHAR), ',', 1) AS UNSIGNED)

    -- 6.240 => 6240 (punto como miles cuando NO termina en .000)
    WHEN INSTR(CAST(l.cantidad AS CHAR), '.') > 0 AND CAST(l.cantidad AS CHAR) NOT LIKE '%.000' THEN
      CAST(REPLACE(CAST(l.cantidad AS CHAR), '.', '') AS UNSIGNED)

    -- por si viene con coma decimal (raro en bandejas)
    WHEN INSTR(CAST(l.cantidad AS CHAR), ',') > 0 THEN
      CAST(REPLACE(CAST(l.cantidad AS CHAR), ',', '') AS UNSIGNED)

    ELSE
      CAST(CAST(l.cantidad AS CHAR) AS UNSIGNED)
  END
)";

        ;


        $kgNorm = "(
  CASE
    WHEN l.cantidad IS NULL OR l.cantidad = '' THEN 0

    ELSE (
      -- 1) Primero parseamos a número (baseKg)
      CASE
        -- 1,234.56  (coma miles + punto decimal)
        WHEN CAST(l.cantidad AS CHAR) REGEXP '^[0-9]{1,3}(,[0-9]{3})+(\\.[0-9]+)?$' THEN
          CAST(REPLACE(CAST(l.cantidad AS CHAR), ',', '') AS DECIMAL(18,3))

        -- 1.234,56  (punto miles + coma decimal)
        WHEN CAST(l.cantidad AS CHAR) REGEXP '^[0-9]{1,3}(\\.[0-9]{3})+(,[0-9]+)?$' THEN
          CAST(REPLACE(REPLACE(CAST(l.cantidad AS CHAR), '.', ''), ',', '.') AS DECIMAL(18,3))

        -- 846.000 (string) => 846   (termina en .000)
        WHEN CAST(l.cantidad AS CHAR) REGEXP '^[0-9]+\\.[0]{3}$' THEN
          CAST(SUBSTRING_INDEX(CAST(l.cantidad AS CHAR), '.', 1) AS DECIMAL(18,3))

       -- 14.976 / 4.020 => miles SOLO para BANDEJAS (NO KG)
        WHEN CAST(l.cantidad AS CHAR) REGEXP '^[0-9]+\\.[0-9]{3}$'
            AND UPPER(COALESCE(l.producto,'')) LIKE '%BANDE%'
        THEN
        CAST(REPLACE(CAST(l.cantidad AS CHAR), '.', '') AS DECIMAL(18,3))


        -- 366,60 => 366.60
        WHEN INSTR(CAST(l.cantidad AS CHAR), ',') > 0 AND INSTR(CAST(l.cantidad AS CHAR), '.') = 0 THEN
          CAST(REPLACE(CAST(l.cantidad AS CHAR), ',', '.') AS DECIMAL(18,3))

        -- default (ya numérico / normal)
        ELSE
          CAST(CAST(l.cantidad AS CHAR) AS DECIMAL(18,3))
      END
    )
  END
)";

        $kgNormFixed = "(
  CASE
    WHEN {$kgNorm} >= 10000 AND MOD({$kgNorm}, 1000) = 0
      THEN {$kgNorm} / 1000
    ELSE {$kgNorm}
  END
)";

        ;
        $kgText = "(
  CASE
    WHEN MOD({$kgNormFixed}, 1) = 0 THEN CAST({$kgNormFixed} AS UNSIGNED)
    ELSE CAST({$kgNormFixed} AS CHAR)
  END
)";

        $kgNormFrambuesa = "(
  CAST(
    REPLACE(
      REPLACE(CAST(l.cantidad AS CHAR), '.', ''),
      ',', '.'
    ) AS DECIMAL(18,3)
  ) / 1000
)";
        ;
        // ===== 2) Subquery agregado con columnas dinámicas =====
        $linesAgg = DB::table('excel_out_transfer_lines as l')
            ->selectRaw("l.excel_out_transfer_id as transfer_id")
            ->selectRaw("COUNT(*) as items_count")
            ->selectRaw("
                                    SUM(
                                        CASE
                                            WHEN UPPER(COALESCE(l.producto,'')) LIKE '%BANDE%'
                                            THEN {$qtyNorm}
                                            ELSE 0
                                        END
                                    ) as bandejas_total
                                ")
            ->selectRaw("
                                    GROUP_CONCAT(
                                        CASE
                                            WHEN UPPER(COALESCE(l.producto,'')) LIKE '%BANDE%'
                                            THEN CONCAT(COALESCE(l.producto,'(sin producto)'), '=', {$qtyNorm})
                                            ELSE NULL
                                        END
                                        SEPARATOR ' | '
                                    ) as bandejas_detalle
                                ")

            ->selectRaw("
  GROUP_CONCAT(
    CONCAT(
      COALESCE(l.producto,'(sin producto)'),
      '=',
      CASE
        WHEN UPPER(COALESCE(l.producto,'')) LIKE '%BANDE%'
        THEN CAST({$qtyNorm} AS CHAR)
        ELSE {$kgText}
      END
    )
    SEPARATOR ' | '
  ) as items_detalle
");
        ;

        // 2a) Columnas por bandeja (UNIDADES enteras)
        foreach ($trayTypes as $t) {
            $alias = 'tray_' . substr(md5($t), 0, 10);

            $linesAgg->selectRaw("
        SUM(
            CASE
                WHEN TRIM(UPPER(COALESCE(l.producto,''))) = ?
                THEN {$qtyNorm}
                ELSE 0
            END
        ) as {$alias}
    ", [$t]);
        }

        // 2b) Columnas por producto (KG decimales)
        foreach ($productTypes as $t) {
            $alias = 'prod_' . substr(md5($t), 0, 10);

            $linesAgg->selectRaw("
      SUM(
        CASE
          WHEN TRIM(UPPER(COALESCE(l.producto,''))) = ?
          THEN (
            CASE
              -- 🔥 FIX REAL para Frambuesa (ANTES de kgNorm)
              WHEN ? = 'FRAMBUESA ORGÁNICA WAKEFIELD'
              THEN {$kgNormFrambuesa}

              -- resto intacto
              ELSE {$kgNormFixed}
            END
          )
          ELSE 0
        END
      ) as {$alias}
    ", [$t, $t]);
        }


        // ===== 2c) Subquery BANDEJAS desde PDF =====
        $pdfBandejasAgg = DB::table('pdf_lines as pl')
            ->selectRaw('pl.pdf_import_id')
            ->selectRaw("
        SUM(
            CASE
                WHEN pl.content LIKE 'Material:%'
                THEN CAST(
                    REPLACE(
                        REGEXP_SUBSTR(pl.content, '[0-9]+([.,][0-9]+)?$'),
                        ',',
                        '.'
                    ) AS DECIMAL(10,2)
                )
                ELSE 0
            END
        ) AS bandejas_material_total
    ")
            ->groupBy('pl.pdf_import_id');

        ;

        $linesAgg->groupBy('l.excel_out_transfer_id');

        // ===== 3) meta limpio (comillas externas + escapes) =====
        $metaClean = "REPLACE(TRIM(BOTH '\"' FROM p.meta), '\\\\\"', '\"')";

        // ===== 4) Query principal =====
        $query = ExcelOutTransfer::query()
            ->from('excel_out_transfers as e')

            // 🔥 SOLO TRANSFERS REALES
            ->where('e.estado', 'Realizado')

            ->whereNotNull('e.guia_entrega')
            ->whereRaw("TRIM(e.guia_entrega) <> ''")

            ->whereNotNull('e.patente')
            ->whereRaw("TRIM(e.patente) <> ''")

            ->whereNotNull('e.chofer')
            ->whereRaw("TRIM(e.chofer) <> ''")

            // ===============================
            // JOIN con PDFs
            // ===============================
            ->leftJoin('pdf_imports as p', function ($join) {
                $join->on(
                    DB::raw("TRIM(LEADING '0' FROM p.guia_no)"),
                    '=',
                    DB::raw("TRIM(LEADING '0' FROM e.guia_entrega)")
                );
            })
            ->leftJoinSub($pdfBandejasAgg, 'pb', function ($join) {
                $join->on('pb.pdf_import_id', '=', 'p.id');
            })

            ->leftJoinSub($linesAgg, 'la', function ($join) {
                $join->on('la.transfer_id', '=', 'e.id');
            })
            ->select([
                'e.contacto',
                'e.fecha_prevista',
                'e.patente',
                'e.chofer',
                'e.guia_entrega',
                'e.referencia',

                DB::raw("CASE WHEN p.id IS NULL THEN 0 ELSE 1 END as exists_guia"),
                'p.id as pdf_id',
                'p.guia_no as pdf_guia_no',
                'p.doc_fecha as pdf_doc_fecha',
                'p.template as pdf_template',

                DB::raw("JSON_UNQUOTE(JSON_EXTRACT($metaClean, '$.kgs_recibido')) as pdf_kgs_recibido"),
                DB::raw("
    COALESCE(
        CAST(
            JSON_UNQUOTE(
                JSON_EXTRACT(
                    $metaClean,
                    '$.total_bandejas'
                )
            ) AS DECIMAL(10,2)
        ),
        pb.bandejas_material_total,
        0
    ) as pdf_bandejas_recibidas
"),


                DB::raw("JSON_UNQUOTE(JSON_EXTRACT($metaClean, '$.guia_pesaje')) as pdf_guia_pesaje"),

                DB::raw("COALESCE(la.bandejas_total, 0) as excel_bandejas_total"),
                DB::raw("COALESCE(la.bandejas_detalle, '') as excel_bandejas_detalle"),
                DB::raw("COALESCE(la.items_detalle, '') as excel_items_detalle"),
            ]);

        // 4a) incluir columnas dinámicas bandejas
        foreach ($trayTypes as $t) {
            $alias = 'tray_' . substr(md5($t), 0, 10);
            $query->addSelect(DB::raw("COALESCE(la.{$alias}, 0) as {$alias}"));
        }

        // 4b) incluir columnas dinámicas productos KG
        foreach ($productTypes as $t) {
            $alias = 'prod_' . substr(md5($t), 0, 10);
            $query->addSelect(DB::raw("COALESCE(la.{$alias}, 0) as {$alias}"));
        }

        // ===== filtros =====
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('e.contacto', 'like', "%{$q}%")
                    ->orWhere('e.patente', 'like', "%{$q}%")
                    ->orWhere('e.guia_entrega', 'like', "%{$q}%")
                    ->orWhere('e.referencia', 'like', "%{$q}%")
                    ->orWhere('e.archivo_dte', 'like', "%{$q}%");
            });
        }

        if ($exists === '1')
            $query->whereNotNull('p.id');
        if ($exists === '0')
            $query->whereNull('p.id');

        if ($orderBy === 'exists_guia') {
            $query->orderByRaw("CASE WHEN p.id IS NULL THEN 0 ELSE 1 END {$dir}")
                ->orderBy('e.fecha_prevista', 'desc');
        } elseif ($orderBy === 'guia_entrega') {
            $query->orderBy('e.guia_entrega', $dir);
        } else {
            $query->orderBy('e.fecha_prevista', $dir);
        }

        $rows = $query->get();

        // ===== reglas de cálculo de palets (manuales, NO BD) =====
        $paletRules = [
            'BANDEJA PLOMA' => 17 * 5,
            'BANDEJAS AZUL' => 30 * 8, // 240
            'BANDEJÓN' => 16 * 5,
            'BANDEJÓN AMARILLO' => 22 * 5,
            'BANDEJON ARANDANERO' => 30 * 8,
            'BANDEJON FRUTILLERO' => 30 * 8,
        ];

        // ===== 5) Excel =====
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('OUT Transfers');

        $headers = [
            'Contacto',
            'Fecha prevista',
            'Patente',
            'Chofer',
            'Guía entrega',
            'Referencia',
            'Match PDF',
            'PDF ID',
            'PDF Guía',
            'PDF Fecha',
            'PDF Template',
            'PDF kgs_recibido',
            'PDF BANDEJAS RECIBIDAS',
            'PDF guia_pesaje',
            'Excel bandejas total',
            'Excel bandejas detalle',
            'Excel items detalle',
        ];

        // headers por bandeja
        foreach ($trayTypes as $t) {
            $nice = ucwords(mb_strtolower($t));
            $headers[] = $nice;
        }
        // headers de palets calculados
        foreach ($trayTypes as $t) {
            if (isset($paletRules[$t])) {
                $headers[] = 'Palets ' . ucwords(mb_strtolower($t));
            }
        }
        // headers por producto KG
        foreach ($productTypes as $t) {
            $nice = ucwords(mb_strtolower($t));
            $headers[] = $nice . ' (KG)';
        }

        foreach ($headers as $i => $h) {
            $cell = Coordinate::stringFromColumnIndex($i + 1) . '1';
            $sheet->setCellValue($cell, $h);
        }

        $rowNum = 2;


        foreach ($rows as $r) {
            $pdfKgs = $this->normalizePdfKg($r->pdf_kgs_recibido);





            $values = [
                $r->contacto ?? '',
                $r->fecha_prevista ?? '',
                $r->patente ?? '',
                strtoupper((string) ($r->chofer ?? '')),
                $r->guia_entrega ?? '',
                $r->referencia ?? '',
                (int) ($r->exists_guia ?? 0) === 1 ? 'SI' : 'NO',
                $r->pdf_id ?? '',
                $r->pdf_guia_no ?? '',
                $r->pdf_doc_fecha ?? '',
                $r->pdf_template ?? '',
                $pdfKgs,
                (float) ($r->pdf_bandejas_recibidas ?? 0),
                $r->pdf_guia_pesaje ?? '',
                (float) ($r->excel_bandejas_total ?? 0),
                $r->excel_bandejas_detalle ?? '',
                $r->excel_items_detalle ?? '',
            ];

            // ===== BANDEJAS =====
            $bandejaValues = [];

            foreach ($trayTypes as $t) {
                $alias = 'tray_' . substr(md5($t), 0, 10);
                $v = (float) ($r->{$alias} ?? 0);
                $bandejaValues[$t] = $v;
                $values[] = $v;
            }

            // ===== PALETS CALCULADOS =====
            foreach ($trayTypes as $t) {
                if (!isset($paletRules[$t])) {
                    continue;
                }

                $bandejas = $bandejaValues[$t] ?? 0;
                $porPalet = $paletRules[$t];

                // redondeo a 2 decimales (ajustable)
                $palets = $porPalet > 0
                    ? round($bandejas / $porPalet, 2)
                    : 0;

                $values[] = $palets;
            }


            // valores por producto KG (desde Odoo / BD)
            foreach ($productTypes as $t) {
                $alias = 'prod_' . substr(md5($t), 0, 10);
                $raw = $r->{$alias} ?? null;

                $kg = $this->kgFromOdoo($raw);

                // 🔥 ODOO mezcla kg / toneladas
                // TONELADAS solo si < 20 y con decimales reales
                if ($kg !== null && $kg < 20 && fmod($kg, 1.0) !== 0.0) {
                    $kg = $kg * 1000;
                }

                // 2️⃣ Export Excel
                $values[] = $kg; // float

                // 3️⃣ Vista / PDF
               // $text = $this->formatKgCL($kg); // string
            }




            foreach ($values as $i => $v) {
                $cell = Coordinate::stringFromColumnIndex($i + 1) . $rowNum;

                $sheet->setCellValue($cell, $v ?? '');
            }

            $rowNum++;
        }
        // ===== FORMATO NUMÉRICO PDF kgs_recibido =====
        // En tus headers, 'PDF kgs_recibido' es la columna 12 → letra L
        $lastDataRow = $rowNum - 1;

        $sheet->getStyle("L2:L{$lastDataRow}")
            ->getNumberFormat()
            ->setFormatCode('#,##0.00');
        // PDF BANDEJAS RECIBIDAS → entero sin separadores
        $sheet->getStyle("M2:M{$lastDataRow}")
            ->getNumberFormat()
            ->setFormatCode('0');

        foreach ($values as $i => $v) {
            $cell = Coordinate::stringFromColumnIndex($i + 1) . $rowNum;

            if (is_numeric($v)) {
                $sheet->setCellValueExplicit($cell, (float) $v, DataType::TYPE_NUMERIC);
                $sheet->getStyle($cell)
                    ->getNumberFormat()
                    ->setFormatCode('#,##0.0');
            } else {
                $sheet->setCellValue($cell, $v);
            }
        }


        for ($i = 1; $i <= count($headers); $i++) {
            $col = Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'out_transfers_bandejas_productos_' . now()->format('Ymd_His') . '.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'max-age=0',
        ]);
    }


    public function formatKgCL($value): string
    {
        if ($value === null) {
            return '';
        }

        $v = (float) $value;

        // entero → sin miles
        if (fmod($v, 1.0) === 0.0) {
            return (string) ((int) $v);
        }

        // decimal → coma decimal, sin miles
        // max 2 decimales, sin ceros basura
        $s = rtrim(rtrim(number_format($v, 2, ',', ''), '0'), ',');

        return $s;
    }

    public function kgFromOdoo($raw): ?float
    {
        if ($raw === null)
            return null;

        // viene de BD / Odoo → ya es decimal con punto
        if (is_int($raw) || is_float($raw)) {
            return (float) $raw;
        }

        $raw = trim((string) $raw);
        if ($raw === '')
            return null;

        // texto tipo "971.100" / "249.000" / "1.673"
        if (is_numeric($raw)) {
            return (float) $raw;
        }

        return null;
    }

    public function normalizeKgFromOdoo($raw): ?float
    {
        if ($raw === null) {
            return null;
        }

        $v = (float) $raw;

        // 1️⃣ si es entero exacto → kilos
        if (fmod($v, 1.0) === 0.0) {
            return $v;
        }

        // 2️⃣ si es < 20 y tiene decimales → toneladas
        if ($v < 20) {
            return $v * 1000;
        }

        // 3️⃣ resto → kilos con decimales
        return $v;
    }

    public function normalizePdfKg($raw): ?float
    {
        if ($raw === null) {
            return null;
        }

        $s = trim((string) $raw);
        if ($s === '') {
            return null;
        }

        // 1️⃣ Formato chileno: 99.000,00 → 99000
        if (preg_match('/^\d{1,3}(\.\d{3})*,\d+$/', $s)) {
            $num = (float) str_replace(',', '.', str_replace('.', '', $s));
        }
        // 2️⃣ 971.100 / 1.658
        elseif (is_numeric($s)) {
            $num = (float) $s;
        } else {
            return null;
        }

        // 3️⃣ TONELADAS SOLO si el valor original es < 20
        if ($num < 20) {
            return $num * 1000;
        }

        return $num;
    }

}



