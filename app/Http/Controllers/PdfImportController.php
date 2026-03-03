<?php

namespace App\Http\Controllers;

use App\Models\PdfImport;
use App\Models\PdfLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Models\AgrakRegistro;
use App\Http\Controllers\ExcelDate;
use Illuminate\Support\Facades\Storage;


use SimpleXMLElement;
class PdfImportController extends Controller
{


    public function create()
    {
        $lastImport = PdfImport::latest('id')->first();
        return view('pdf.import', compact('lastImport'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'pdfs' => ['required', 'array', 'min:1'],
            'pdfs.*' => ['file', 'mimes:pdf', 'max:10240'],
        ]);

        $disk = config('filesystems.default');

        $created = 0;
        $duplicates = 0;
        $skippedNoGuia = 0;

        // ✅ log detallado por archivo
        $report = [];

        foreach ($request->file('pdfs') as $file) {

            $originalName = $file->getClientOriginalName();

            // 1) Leer texto desde el archivo temporal (SIN guardar aún)
            $tmpPath = $file->getRealPath();
            $text = $this->extractText($tmpPath);
            $lines = $this->toLines($text);
            $template = $this->detectTemplate($lines);

            // 2) Parse según template (QC / MP)
            $data = null;


            if ($template === 'QC') {
                $data = $this->parseQC($lines);
            } elseif ($template === 'MP') {
                $data = $this->parseMP($lines);
            } elseif ($template === 'SANCO') {
                $data = $this->parseGuiaRecepcion($lines);


                // ✅ Normaliza para que el resto del flujo funcione igual
                $data['guia_no'] = $data['numero_guia'] ?? null;
                $data['doc_fecha'] = $data['fecha'] ?? null;

                // Si quieres guardar un "productor" consistente:
                // en GRANEL viene productor, especie, variedad separados
                // acá guardamos productor en el campo productor
                $data['productor'] = $data['productor'] ?? null;
            } elseif ($template === 'LIQ_COMPUAGRO') {

                $parsed = $this->parseLiquidacionCompuagro($lines);
                $documento = $parsed['documento'];
                $recepciones = $parsed['recepciones'];
                $path = $file->store('imports/pdfs', $disk);

                // 5) Recién aquí guardas el archivo
                foreach ($recepciones as $r) {

                    $guia = $r['guia_no'] ?? null;
                    if (!$guia) {
                        $skippedNoGuia++;
                        continue;
                    }
                    if (PdfImport::where('guia_no', $guia)->exists()) {
                        $duplicates++;
                        $report[] = [
                            'file' => $originalName,
                            'status' => 'duplicate',
                            'template' => 'LIQ_COMPUAGRO',
                            'guia' => $guia,
                        ];
                        continue;
                    }

                    DB::transaction(function () use ($file, $lines, $path, $documento, $r, &$created) {

                        $import = PdfImport::create([
                            'original_name' => $file->getClientOriginalName(),
                            'stored_path' => $path,
                            'template' => 'LIQ_COMPUAGRO',
                            'guia_no' => $r['guia_no'],
                            'doc_fecha' => $r['doc_fecha'],
                            'productor' => $documento['productor'],
                            'meta' => json_encode([
                                'documento' => $documento,
                                'recepcion' => $r,
                            ], JSON_UNESCAPED_UNICODE),
                        ]);

                        $rows = [];
                        foreach ($lines as $i => $line) {
                            $rows[] = [
                                'pdf_import_id' => $import->id,
                                'line_no' => $i + 1,
                                'content' => $line,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }

                        foreach (array_chunk($rows, 1000) as $chunk) {
                            PdfLine::insert($chunk);
                        }

                        $created++;
                    });

                    $report[] = [
                        'file' => $file->getClientOriginalName(),
                        'status' => 'imported',
                        'template' => 'LIQ_COMPUAGRO',
                        'guia' => $guia,
                    ];
                }

                continue;
            } elseif ($template === 'GUIA_RECEPCION_RESUMEN') {

                $parsed = $this->parseGuiaRecepcionResumen($lines);

                $data = [
                    'guia_no' => $parsed['guia_no'],
                    'doc_fecha' => $parsed['doc_fecha'],
                    'productor' => $parsed['productor'],

                    'source' => 'pdf',
                    'tipo_documento' => 'guia_recepcion_resumen',
                    'emisor' => 'Generado desde Excel',
                    'guia_productor' => $parsed['guia_productor'],
                    'total_cajas' => $parsed['total_cajas'],
                    'recepcion' => [
                        'total_kgs' => $parsed['total_kgs'],
                    ],
                ];
            }


            $guia = $data['guia_no'] ?? $data['numero_guia'] ?? null;


            // si no detecta template o data
            if (!$template || !$data) {
                $report[] = [
                    'file' => $originalName,
                    'status' => 'skip',
                    'reason' => 'No se detectó modelo (template).',
                    'template' => $template,
                    'guia' => $guia,
                ];
                $skippedNoGuia++;
                continue;
            }

            // 3) Si no hay guía, lo saltas
            if (empty($guia)) {
                $skippedNoGuia++;
                $report[] = [
                    'file' => $originalName,
                    'status' => 'skip',
                    'reason' => 'Sin guía detectada.',
                    'template' => $template,
                    'guia' => null,
                ];
                continue;
            }
            // 4) DEDUPE GLOBAL por guia_no (🔥 CLAVE)
            if (PdfImport::where('guia_no', $guia)->exists()) {
                $duplicates++;
                $report[] = [
                    'file' => $originalName,
                    'status' => 'duplicate',
                    'reason' => "La guía {$guia} ya existe en el sistema.",
                    'template' => $template,
                    'guia' => $guia,
                ];
                continue;
            }


            // 5) Recién aquí guardas el archivo
            $path = $file->store('imports/pdfs', $disk);

            DB::transaction(function () use ($file, $path, $lines, $template, $data, &$created) {

                $import = PdfImport::create([
                    'original_name' => $file->getClientOriginalName(),
                    'stored_path' => $path,
                    'template' => $template,
                    'guia_no' => $data['guia_no'] ?? null,
                    'doc_fecha' => $data['doc_fecha'] ?? null,
                    'productor' => $data['productor'] ?? null,
                    'meta' => $data ? json_encode($data, JSON_UNESCAPED_UNICODE) : null,
                ]);

                $rows = [];
                foreach ($lines as $i => $line) {
                    $rows[] = [
                        'pdf_import_id' => $import->id,
                        'line_no' => $i + 1,
                        'content' => $line,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                foreach (array_chunk($rows, 1000) as $chunk) {
                    PdfLine::insert($chunk);
                }

                $created++;
            });

            $report[] = [
                'file' => $originalName,
                'status' => 'imported',
                'reason' => 'Importado OK',
                'template' => $template,
                'guia' => $guia,
            ];
        }

        return redirect()
            ->route('pdf.index')
            ->with('ok', "Importados: {$created} | Duplicados por guía: {$duplicates} | Sin guía: {$skippedNoGuia}")
            ->with('import_report', $report); // ✅
    }



    private function parseQC(array $lines): array
    {
        $text = implode("\n", $lines);

        // Fecha (toma la primera que aparezca)
        $fecha = null;
        if (preg_match('/\bFecha:\s*([0-9]{2}\.[0-9]{2}\.[0-9]{4})\b/u', $text, $m)) {
            $fecha = $m[1];
        }

        // N° guía (QC: G.Prod)
        $guia = null;
        if (preg_match('/\bG\.Prod:\s*([0-9]+)\b/u', $text, $m)) {
            $guia = $m[1];
        }

        // Productor (Nombre:)
        $productor = null;
        if (preg_match('/\bNombre:\s*(.+)$/mu', $text, $m)) {
            $productor = trim($m[1]);
        }

        // ✅ Kilos (QC): aparece como "Kilos:" y el número en la(s) línea(s) siguiente(s)
        // ✅ Kilos (QC): soporta "Kilos:" solo, "Kilos: 1.234,56" en la misma línea,
// y tolera que haya "Prom.Band:" u otros rótulos entre medio.
        $kgs = null;

        for ($i = 0; $i < count($lines); $i++) {
            $line = trim($lines[$i]);

            // Si en la misma línea ya viene el número: "Kilos: 1.549,04"
            if (preg_match('/\bKilos\s*:\s*([0-9\.\,]+)/iu', $line, $mSame)) {
                $kgs = (float) str_replace(',', '.', str_replace('.', '', $mSame[1]));
                break;
            }

            // Si aparece "Kilos:" (aunque tenga texto extra), buscar hacia adelante
            if (preg_match('/\bKilos\s*:\b/iu', $line)) {
                for ($k = 1; $k <= 10; $k++) { // más margen que 3
                    $next = $lines[$i + $k] ?? '';
                    $nextTrim = trim($next);

                    if ($nextTrim === '')
                        continue;

                    // Extrae el primer número aunque haya texto/espacios raros alrededor
                    if (preg_match('/([0-9]{1,3}(?:\.[0-9]{3})*,[0-9]+|[0-9]+(?:[.,][0-9]+)?)/u', $nextTrim, $m2)) {
                        $raw = $m2[1];
                        $kgs = (float) str_replace(',', '.', str_replace('.', '', $raw));
                        break 2;
                    }
                }
            }
        }


        // ✅ (Opcional) Kgs netos desde "RESULTADO ANALISIS" (IQF / PULPA)
        $iqfKgNetos = null;
        if (preg_match('/\bIQF\b.*?\b([0-9\.\,]+)\s*Kg\s*Netos\b/iu', $text, $m)) {
            $iqfKgNetos = (float) str_replace(',', '.', str_replace('.', '', $m[1]));
        }

        $pulpaKgNetos = null;
        if (preg_match('/\bPULPA\b.*?\b([0-9\.\,]+)\s*Kg\s*Netos\b/iu', $text, $m)) {
            $pulpaKgNetos = (float) str_replace(',', '.', str_replace('.', '', $m[1]));
        }

        // Materiales / Bandejas
        $bandejas = [];
        foreach ($lines as $line) {
            $line = trim($line);

            if (preg_match('/^Material:\s*([A-Z0-9]+)\s*-\s*(.+?)\s+Cantidad:\s*([0-9\.\,]+)\s*$/u', $line, $m)) {
                $codigo = trim($m[1]);
                $desc = trim($m[2]);
                $cantRaw = trim($m[3]);

                $cant = (float) str_replace(',', '.', str_replace('.', '', $cantRaw));

                $bandejas[] = [
                    'codigo' => $codigo,
                    'descripcion' => $desc,
                    'cantidad' => $cant,
                ];
            }
        }

        $totalBandejas = array_sum(array_map(fn($b) => $b['cantidad'], $bandejas));

        return [
            'guia_no' => $guia,
            'doc_fecha' => $fecha,
            'productor' => $productor,

            // 🔥 ahora QC también aporta kilos al Excel
            'kgs_recibido' => $kgs,

            // opcional (si quieres explotarlo después)
            'iqf_kg_netos' => $iqfKgNetos,
            'pulpa_kg_netos' => $pulpaKgNetos,

            'bandejas' => $bandejas,
            'total_bandejas' => $totalBandejas,
        ];
    }

    private function parseMP(array $lines): array
    {
        // =========================
        // GUIA (G.Despacho)
        // =========================
        $guia = null;

        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];

            if (preg_match('/\bG\.\s*Despacho\b/iu', $line)) {

                // Mismo renglón: G.Despacho: 427
                if (preg_match('/\bG\.\s*Despacho\s*:\s*([0-9]+)\b/iu', $line, $m)) {
                    $guia = $m[1];
                    break;
                }

                // Siguiente renglón:
                $next = $lines[$i + 1] ?? '';
                if (preg_match('/\b([0-9]{1,10})\b/', $next, $m)) {
                    $guia = $m[1];
                    break;
                }
            }
        }

        // =========================
        // FECHA GUIA
        // =========================
        $fecha = null;

        for ($i = 0; $i < count($lines); $i++) {
            if (preg_match('/\bFecha\s*Guia\b/iu', $lines[$i])) {

                // misma línea
                if (preg_match('/([0-9]{2}\/[0-9]{2}\/[0-9]{4})/', $lines[$i], $m)) {
                    $fecha = $this->toMysqlDate($m[1]);
                    break;
                }

                // línea siguiente
                $next = $lines[$i + 1] ?? '';
                if (preg_match('/([0-9]{2}\/[0-9]{2}\/[0-9]{4})/', $next, $m)) {
                    $fecha = $this->toMysqlDate($m[1]);
                    break;
                }
            }
        }

        // =========================
        // PROVEEDOR (1 o 2 líneas)
        // =========================
        $proveedor = null;

        for ($i = 0; $i < count($lines); $i++) {
            if (preg_match('/^Proveedor\s*:/iu', $lines[$i])) {

                $p1 = trim($lines[$i + 1] ?? '');
                $p2 = trim($lines[$i + 2] ?? '');

                // líneas que NO son parte del nombre
                $stopRe = '/^(RUT\s*Proveedor|Orden|Fecha\s*recepci[oó]n|G\.\s*Despacho|Fecha\s*Guia|N[°ºo]\s*Palet|Kgs\s*Recibido)\b/iu';

                $parts = [];

                if ($p1 !== '' && !preg_match($stopRe, $p1)) {
                    $parts[] = $p1;
                }

                if ($p2 !== '' && !preg_match($stopRe, $p2)) {
                    $parts[] = $p2;
                }

                if (!empty($parts)) {
                    $proveedor = trim(implode(' ', $parts));
                }

                break;
            }
        }

        // =========================
        // KGS RECIBIDOS
        // =========================
        $kgs = null;

        for ($i = 0; $i < count($lines); $i++) {
            if (preg_match('/\bKgs\s*Recibido\b/iu', $lines[$i])) {

                // misma línea
                if (preg_match('/([0-9\.,]+)\s*Kg\b/iu', $lines[$i], $m)) {
                    $kgs = (float) str_replace(',', '.', str_replace('.', '', $m[1]));
                    break;
                }

                // línea siguiente
                $next = $lines[$i + 1] ?? '';
                if (preg_match('/([0-9\.,]+)\s*Kg\b/iu', $next, $m)) {
                    $kgs = (float) str_replace(',', '.', str_replace('.', '', $m[1]));
                    break;
                }
            }
        }

        // =========================
        // BANDEJAS / BANDEJONES
        // =========================
        $bandejas = [];

        foreach ($lines as $line) {
            if (
                preg_match(
                    '/^((?:Bandej[oó]n|Bandeja).+?)\s+([0-9]+(?:\.[0-9]+)?)\s*Un\b/iu',
                    $line,
                    $m
                )
            ) {
                $bandejas[] = [
                    'descripcion' => trim($m[1]),
                    'cantidad' => (float) $m[2],
                ];
            }
        }

        // =========================
        // RESULTADO FINAL
        // =========================
        return [
            'guia_no' => $guia,
            'doc_fecha' => $fecha,
            'productor' => $proveedor,
            'kgs_recibido' => $kgs,
            'bandejas' => $bandejas,
        ];
    }

    private function toMysqlDate(string $dmy): ?string
    {
        $dmy = trim($dmy);

        // Espera dd/mm/yyyy
        if (!preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dmy, $m)) {
            return null;
        }

        [$all, $dd, $mm, $yyyy] = $m;

        // valida fecha real
        if (!checkdate((int) $mm, (int) $dd, (int) $yyyy)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', (int) $yyyy, (int) $mm, (int) $dd);
    }


    public function showJson(int $id)
    {
        $import = PdfImport::with('lines')->findOrFail($id);

        return response()->json([
            'id' => $import->id,
            'template' => $import->template,
            'lines' => $import->lines->map(fn($l) => [$l->line_no, $l->content]),
        ]);
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $model = trim((string) $request->get('model', ''));

        $orderBy = $request->get('order_by', 'doc_fecha');
        $dir = $request->get('dir', 'desc');

        // ✅ Query base (AQUÍ estaba el problema antes)
        $query = PdfImport::with('lines');

        // ===== Filtro por modelo =====
        if ($model !== '') {
            if ($model === '—') {
                $query->whereNull('template');
            } else {
                $query->where('template', $model);
            }
        }

        // ===== Búsqueda global =====
        if ($q !== '') {
            $query->where(function ($w) use ($q) {

                // 👇 si es número puro → guía exacta
                if (ctype_digit($q)) {
                    $w->where('guia_no', $q)
                        ->orWhere('id', $q);
                }
                // 👇 si es texto → búsqueda amplia
                else {
                    $w->where('original_name', 'like', "%{$q}%")
                        ->orWhere('doc_fecha', 'like', "%{$q}%")
                        ->orWhere('template', 'like', "%{$q}%");
                }
            });
        }


        // ===== Orden =====
        if ($orderBy === 'doc_fecha') {
            $query->orderByRaw('doc_fecha IS NULL ASC')
                ->orderBy('doc_fecha', $dir);
        } elseif ($orderBy === 'import_date') {
            $query->orderBy('created_at', $dir);
        } else {
            $query->latest('id');
        }

        // fallback seguro
        $query->orderBy('id', 'desc');

        $imports = $query->paginate(15)->withQueryString();

        return view('pdf.index', compact('imports', 'q', 'model', 'orderBy', 'dir'));
    }




    public function show(int $id)
    {
        $import = PdfImport::with('lines')->findOrFail($id);

        $xmlTotals = null;

        if ($import->template === 'XML_SII_46') {
            $xmlTotals = $this->calculateXml46Totals($import);
        }

        return view('pdf.show', compact('import', 'xmlTotals'));
    }


    private function calculateXml46Totals(PdfImport $import): array
    {
        // 👉 EL XML ESTÁ EN DISCO LOCAL
        if (!Storage::disk('local')->exists($import->stored_path)) {
            return ['bandejas' => 0, 'kilos' => 0];
        }

        $xmlContent = Storage::disk('local')->get($import->stored_path);

        libxml_use_internal_errors(true);

        $xml = simplexml_load_string($xmlContent);
        if (!$xml) {
            return ['bandejas' => 0, 'kilos' => 0];
        }

        $totalBandejas = 0;
        $totalKilos = 0;

        // ✔ XPath robusto para XML SII
        foreach ($xml->xpath('//*[local-name()="Detalle"]') as $det) {

            $qty = (float) ($det->xpath('./*[local-name()="QtyItem"]')[0] ?? 0);
            $unm = strtoupper((string) ($det->xpath('./*[local-name()="UnmdItem"]')[0] ?? ''));

            if ($unm === 'KG') {
                $totalKilos += $qty;
            }

            if (in_array($unm, ['UN', 'UND', 'BANDEJA', 'CAJA'], true)) {
                $totalBandejas += $qty;
            }
        }

        return [
            'bandejas' => $totalBandejas,
            'kilos' => $totalKilos,
        ];
    }

    private function extractText(string $pdfPath): string
    {
        $process = new Process([
            'pdftotext',
            '-layout',
            $pdfPath,
            '-'
        ]);

        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(
                'Error ejecutando pdftotext: ' . $process->getErrorOutput()
            );
        }

        return $process->getOutput();
    }


    private function toLines(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        $out = [];
        foreach (explode("\n", $text) as $line) {
            $line = trim($line);
            if ($line === '')
                continue;
            $out[] = $line;
        }

        return $out;
    }

    private function detectTemplate(array $lines): ?string
    {
        $head = mb_strtolower(implode("\n", array_slice($lines, 0, 120)));

        // Modelo QC (Control de Calidad / Resultado de análisis)
        $qcNeedles = [
            'control de calidad',
            'resultado de control de calidad',
            'resultado analisis',
            'detalle control de calidad',
            'datos del productor',
        ];
        $qcHits = 0;
        foreach ($qcNeedles as $n) {
            if (str_contains($head, $n))
                $qcHits++;
        }
        if ($qcHits >= 2)
            return 'QC';

        if (
            str_contains($head, 'reporte recepcion') ||
            str_contains($head, 'detalle de productos recibidos') ||
            str_contains($head, 'reporte mp')
        ) {
            return 'MP';
        }

        // Tus modelos anteriores (ejemplos)
        if (str_contains($head, 'factura'))
            return 'A';
        if (str_contains($head, 'guía de despacho') || str_contains($head, 'guia de despacho'))
            return 'B';
        if (str_contains($head, 'orden de compra'))
            return 'C';
        if (
            str_contains($head, 'guia recepcion de fruta granel') ||
            str_contains($head, 'guía recepcion de fruta granel') ||
            str_contains($head, 'recepcion de fruta') ||
            str_contains($head, 'guia de recepción sanco') ||
            str_contains($head, 'recepción de fruta')
        ) {
            return 'SANCO';
        }

        if (
            str_contains($head, 'liquidación de productores') &&
            str_contains($head, 'compuagro')
        ) {
            return 'LIQ_COMPUAGRO';
        }
        if (

            str_contains($head, 'guía recepción') &&
            str_contains($head, 'guía productor') &&
            str_contains($head, 'total cajas') &&
            str_contains($head, 'total kilos')

        ) {

            return 'GUIA_RECEPCION_RESUMEN';
        }
        // 🔥🔥🔥 ESTE RETURN FALTABA 🔥🔥🔥
        return null;
    }

    public function exportXlsx(Request $request)
    {
        $q = PdfImport::query()->latest('id');

        if ($request->filled('template')) {
            $tpl = $request->input('template');
            if ($tpl === '—')
                $q->whereNull('template');
            else
                $q->where('template', $tpl);
        }

        $q->whereNotNull('guia_no')->where('guia_no', '!=', '');

        $imports = $q->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Bandejas');

        $sheet->fromArray(
            ['Guía', 'Tipo', 'Cantidad (Un)', 'Kgs recibidos'],
            null,
            'A1'
        );

        $row = 2;

        foreach ($imports as $imp) {

            $meta = is_string($imp->meta) ? json_decode($imp->meta, true) : ($imp->meta ?? []);
            if (!is_array($meta))
                $meta = [];

            $template = trim((string) $imp->template);

            // ===== SANCO: exportar "detalles" =====
            if ($template === 'SANCO') {
                $detalles = $meta['detalles'] ?? [];

                if (is_array($detalles) && count($detalles) > 0) {
                    foreach ($detalles as $d) {
                        $tipo = $d['envase'] ?? ($d['folio'] ?? '—');
                        $cant = $d['cantidad'] ?? null;
                        $kgs = $d['kgs'] ?? null;

                        $sheet->fromArray([
                            (string) $imp->guia_no,
                            (string) $tipo,
                            is_null($cant) ? '' : (float) $cant,
                            is_null($kgs) ? '' : (float) $kgs,
                        ], null, "A{$row}");
                        $row++;
                    }
                    continue;
                }

                // si no trae detalles, igual mostrar la guía
                $totalKgs = $meta['total']['kgs'] ?? null;

                $sheet->fromArray([
                    (string) $imp->guia_no,
                    'SANCO (sin detalle)',
                    '',
                    is_null($totalKgs) ? '' : (float) $totalKgs,
                ], null, "A{$row}");
                $row++;
                continue;
            }

            // ===== QC / MP: exportar "bandejas" =====
            $bandejas = $meta['bandejas'] ?? [];
            $kgs = $meta['kgs_recibido'] ?? null;

            if (is_array($bandejas) && count($bandejas) > 0) {
                foreach ($bandejas as $b) {
                    $desc = $b['descripcion'] ?? ($b['codigo'] ?? '—');
                    $cant = $b['cantidad'] ?? null;

                    $sheet->fromArray([
                        (string) $imp->guia_no,
                        (string) $desc,
                        is_null($cant) ? '' : (float) $cant,
                        is_null($kgs) ? '' : (float) $kgs,
                    ], null, "A{$row}");
                    $row++;
                }
            } else {
                // si no trae bandejas, igual mostrar la guía
                $sheet->fromArray([
                    (string) $imp->guia_no,
                    "{$template} (sin bandejas)",
                    '',
                    is_null($kgs) ? '' : (float) $kgs,
                ], null, "A{$row}");
                $row++;
            }
        }

        foreach (range('A', 'D') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        if ($row > 2) {
            $sheet->getStyle("C2:C" . ($row - 1))->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("D2:D" . ($row - 1))->getNumberFormat()->setFormatCode('#,##0.00');
        }

        $writer = new Xlsx($spreadsheet);
        $filename = 'bandejas_kgs_' . now()->format('Ymd_His') . '.xlsx';

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }


    public function ver(int $id)
    {
        $import = PdfImport::findOrFail($id);

        $absolutePath = storage_path('app/private/' . $import->stored_path);

        return response()->file($absolutePath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $import->original_name . '"'
        ]);
    }
















    /**
     * Parse Recepciones desde Liquidación de Productores COMPUAGRO
     * Devuelve datos completos POR GUIA
     */
    private function parseLiquidacionCompuagro(array $lines): array
    {
        $toFloat = function (?string $raw): ?float {
            if ($raw === null) {
                return null;
            }
            $raw = trim($raw);
            $raw = str_replace([' ', "\u{00A0}"], '', $raw);
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
            return is_numeric($raw) ? (float) $raw : null;
        };

        $doc = [
            'liquidacion_no' => null,
            'productor' => null,
            'producto' => null,
            'variedad' => null,
            'periodo_liquidacion' => null,
            'periodo_contrato' => null,
        ];

        $recepciones = [];
        $current = null;
        $skipCurrent = false;

        for ($i = 0; $i < count($lines); $i++) {

            // Limpieza dura PDF
            $raw = $lines[$i];
            $l = preg_replace('/[\x00-\x1F\x7F\xA0]/u', '', $raw);
            $l = trim($l);

            // =======================
            // ENCABEZADO
            // =======================

            if (stripos($l, 'Liquidación de Productores') !== false) {
                for ($j = $i; $j < $i + 5; $j++) {
                    if (preg_match('/N[°º]\s*(\d+)/u', $lines[$j], $m)) {
                        $doc['liquidacion_no'] = (int) $m[1];
                        break;
                    }
                }
            }

            // =======================
// PRODUCTOR
// =======================
            if (
                stripos($l, 'Productor') !== false &&
                preg_match('/Productor\s*:\s*(.+?)\s{2,}/iu', $l, $m)
            ) {
                $doc['productor'] = trim($m[1]);
            }

            // =======================
// PRODUCTO
// =======================
            if (
                stripos($l, 'Producto') !== false &&
                preg_match('/Producto\s*:\s*(.+?)\s{2,}/iu', $l, $m)
            ) {
                $doc['producto'] = trim($m[1]);
            }

            // =======================
// VARIEDAD (línea siguiente)
// =======================
            if (
                $doc['producto'] &&
                !$doc['variedad'] &&
                preg_match('/^[A-Za-zÁÉÍÓÚÑñ]+$/u', trim($l))
            ) {
                $doc['variedad'] = trim($l);
            }

            // =======================
// PERIODO CONTRATO
// =======================
            if (
                stripos($l, 'Periodo Contrato') !== false &&
                preg_match('/Periodo\s+Contrato\s*:\s*(\d{2}-\d{2}-\d{4}\s+al\s+\d{2}-\d{2}-\d{4})/iu', $l, $m)
            ) {
                $doc['periodo_contrato'] = $m[1];
            }

            // =======================
// PERIODO LIQUIDACIÓN
// =======================
            if (
                stripos($l, 'Periodo Liquidación') !== false &&
                preg_match('/Periodo\s+Liquidaci[oó]n\s*:\s*(\d{2}-\d{2}-\d{4}\s+al\s+\d{2}-\d{2}-\d{4})/iu', $l, $m)
            ) {
                $doc['periodo_liquidacion'] = $m[1];
            }



            // =======================
            // NUEVA RECEPCIÓN
            // =======================

            if (preg_match('/^\d{2,3}$/', $l)) {
                $current = [
                    'recepcion_no' => (int) $l,
                    'guia_no' => null,
                    'doc_fecha' => null,
                    'tipo_guia' => null,

                    'exportacion_kgs' => null,
                    'exportacion_pct' => null,
                    'valor_kg_exportacion' => null,
                    'valor_total_exportacion' => null,

                    'mercado_interno_kgs' => null,
                    'mercado_interno_pct' => null,
                    'valor_kg_mercado' => null,
                    'valor_total_mercado' => null,

                    'desecho_kgs' => null,
                    'desecho_pct' => null,
                    'valor_kg_desecho' => null,
                    'valor_total_desecho' => null,

                    'total_kgs' => null,
                    'total_neto' => null,
                ];
                $skipCurrent = false;
                continue;
            }

            // =======================
            // GUIA / FECHA / TIPO
            // =======================

            if ($current) {

                if (
                    $current['guia_no'] === null &&
                    preg_match('/N[°º]\s*Guia\s*:\s*(\d+)/iu', $l, $m)
                ) {
                    $current['guia_no'] = $m[1];

                    // 🔥 VALIDACIÓN BD (solo una vez)
                    if (PdfImport::where('guia_no', $current['guia_no'])->exists()) {
                        $skipCurrent = true;
                    }
                }

                if (
                    $current['doc_fecha'] === null &&
                    preg_match('/Fecha\s*:\s*(\d{2}-\d{2}-\d{4})/iu', $l, $m)
                ) {
                    $current['doc_fecha'] = $m[1];
                }

                if (
                    $current['tipo_guia'] === null &&
                    preg_match('/Tipo\s*Guia\s*:\s*([A-Za-z]+)/iu', $l, $m)
                ) {
                    $current['tipo_guia'] = $m[1];
                }
            }

            // =======================
            // EXPORTACIÓN
            // =======================

            if (
                $current &&
                preg_match(
                    '/Exportaci[oó]n\s*:\s*([0-9\.,]+)\s+([0-9\.,]+)\s+([0-9\.,]+)\s+([0-9\.,]+)/iu',
                    $l,
                    $m
                )
            ) {
                $current['exportacion_kgs'] = $toFloat($m[1]);
                $current['exportacion_pct'] = $toFloat($m[2]);
                $current['valor_kg_exportacion'] = $toFloat($m[3]);
                $current['valor_total_exportacion'] = $toFloat($m[4]);
            }

            // =======================
            // MERCADO INTERNO
            // =======================

            if (
                $current &&
                preg_match(
                    '/Mercado\s+Interno\s*:\s*([0-9\.,]+)\s+([0-9\.,]+)\s+([0-9\.,]+)\s+([0-9\.,]+)/iu',
                    $l,
                    $m
                )
            ) {
                $current['mercado_interno_kgs'] = $toFloat($m[1]);
                $current['mercado_interno_pct'] = $toFloat($m[2]);
                $current['valor_kg_mercado'] = $toFloat($m[3]);
                $current['valor_total_mercado'] = $toFloat($m[4]);
            }

            // =======================
            // DESECHO
            // =======================

            if (
                $current &&
                preg_match(
                    '/Desecho\s*:\s*([0-9\.,]+)\s+([0-9\.,]+)\s+([0-9\.,]+)\s+([0-9\.,]+)/iu',
                    $l,
                    $m
                )
            ) {
                $current['desecho_kgs'] = $toFloat($m[1]);
                $current['desecho_pct'] = $toFloat($m[2]);
                $current['valor_kg_desecho'] = $toFloat($m[3]);
                $current['valor_total_desecho'] = $toFloat($m[4]);
            }

            // =======================
            // CIERRE RECEPCIÓN
            // =======================

            if (
                $current &&
                preg_match(
                    '/Sub\s+Total\s+Recepci[oó]n\s+\d+\s+([0-9\.,]+)\s+([0-9\.,]+)/iu',
                    $l,
                    $m
                )
            ) {
                if (!$skipCurrent) {
                    $current['total_kgs'] = $toFloat($m[1]);
                    $current['total_neto'] = $toFloat($m[2]);
                    $recepciones[] = $current;
                }

                $current = null;
                $skipCurrent = false;
            }
        }

        return [
            'documento' => $doc,
            'recepciones' => $recepciones,
        ];
    }



    private function parseGuiaRecepcionResumen(array $lines): array
    {
        $data = [
            'guia_no' => null,
            'doc_fecha' => null,
            'productor' => null,
            'guia_productor' => null,
            'total_cajas' => null,
            'total_kgs' => null,
        ];

        foreach ($lines as $line) {

            // Normaliza espacios
            $line = preg_replace('/\s+/u', ' ', trim($line));

            /**
             * MATCH PRINCIPAL (toda la fila)
             *
             * 00097682 05-01-2026 AGRÍCOLA EPPLE... 00000636 7200 9625
             */
            if (
                preg_match(
                    '/^
                (\d{5,10})\s+                    # guía recepción
                (\d{2}-\d{2}-\d{4})\s+           # fecha
                (.+?)\s+                         # productor
                (\d{5,10})\s+                    # guía productor
                (\d+)\s+                         # total cajas
                ([0-9\.,]+)                      # total kilos
                $/xu',
                    $line,
                    $m
                )
            ) {
                $pdfGuiaRecepcion = $m[1];
                $pdfGuiaProductor = $m[4];

                $data['guia_productor'] = $pdfGuiaProductor;
                $data['guia_no'] = $pdfGuiaRecepcion;

                $data['productor'] = trim($m[3]);
                $data['doc_fecha'] = $m[2];
                $data['total_cajas'] = (int) $m[5];
                $data['total_kgs'] = (float) str_replace(',', '.', str_replace('.', '', $m[6]));
                break;
            }
        }

        return $data;
    }

}
