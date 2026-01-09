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

                // ✅ guardar PDF una sola vez
                $path = $file->store('imports/pdfs', $disk);

                foreach ($recepciones as $r) {

                    $guia = $r['guia_no'] ?? null;
                    if (!$guia) {
                        $skippedNoGuia++;
                        continue;
                    }

                    if (
                        PdfImport::where('template', 'LIQ_COMPUAGRO')
                            ->where('guia_no', $guia)
                            ->exists()
                    ) {
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
            } elseif ($template === 'GUIA_RECEPCION_PINOCHET') {

                $parsed = $this->parseGuiaRecepcionPinochet($lines);

                // normalización mínima para el store
                $data = [
                    'guia_no' => $parsed['guia_no'] ?? null,
                    'doc_fecha' => $parsed['doc_fecha'] ?? null,
                    'productor' => $parsed['productor'] ?? null,

                    'source' => 'pdf',
                    'tipo_documento' => 'guia_recepcion',
                    'emisor' => 'Agroindustria Pinochet Fuenzalida Ltda.',
                    'guia_productor' => $parsed['guia_productor'] ?? null,
                    'total_cajas' => $parsed['total_cajas'] ?? null,
                    'recepcion' => [
                        'total_kgs' => $parsed['total_kgs'] ?? null,
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

            // 4) DEDUPE por (template + guia_no)
            $existing = PdfImport::where('template', $template)
                ->where('guia_no', $guia)
                ->first();

            if ($existing) {
                $duplicates++;
                $report[] = [
                    'file' => $originalName,
                    'status' => 'duplicate',
                    'reason' => "Duplicado (ya existe template={$template} guía={$guia} en ID={$existing->id}).",
                    'template' => $template,
                    'guia' => $guia,
                    'existing_id' => $existing->id,
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
        $pdftotext = '/opt/homebrew/bin/pdftotext';
        //$pdftotext = '/usr/bin/pdftotext';

        $process = new Process([$pdftotext, '-layout', $pdfPath, '-']);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException('Error ejecutando pdftotext: ' . $process->getErrorOutput());
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
            str_contains($head, 'Agroindustria Pinochet') &&
            str_contains($head, 'Guía de Recepción')
        ) {
            return 'GUIA_RECEPCION_PINOCHET';
        }
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

    public function excelForm()
    {
        return view('excel.import');
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

        // Guardar Excel para stored_path NOT NULL
        $storedPath = $file->store('imports/excel', 'public');

        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();

        $rows = $sheet->toArray(null, true, true, true);
        if (count($rows) < 2) {
            return back()->withErrors(['excel' => 'El Excel no tiene filas de datos.']);
        }

        // Headers -> columna
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

        // parse number robusto (si viene numérico de Excel, no lo rompe)
        $parseNumber = function ($v): ?float {
            if ($v === null)
                return null;
            if (is_int($v) || is_float($v))
                return (float) $v;

            $s = trim((string) $v);
            if ($s === '')
                return null;

            $s = str_replace("\xc2\xa0", '', $s); // NBSP

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
            if ($v === null)
                return null;
            $s = trim((string) $v);
            if ($s === '')
                return null;

            if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $s, $m)) {
                return "{$m[3]}-{$m[2]}-{$m[1]}";
            }
            if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $s, $m)) {
                return "{$m[3]}-{$m[2]}-{$m[1]}";
            }
            return null;
        };

        $created = 0;
        $duplicates = 0;
        $skippedNoGuia = 0;

        // ✅ Reporte para la vista (igual que PDF)
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

            // ✅ Kilos reales: vienen en "Cantidad Recepcionada"
            $kgs = $parseNumber($get($r, 'Cantidad Recepcionada'));
            $unidad = trim((string) $get($r, 'Kilos')); // "KG"

            $exists = PdfImport::where('template', $template)
                ->where('guia_no', $guia)
                ->exists();

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

            // ✅ Igual que PDF: crear PdfImport + guardar "detalle" en PdfLine
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

                // ============================
                // ✅✅✅ DETALLE (PdfLine) ✅✅✅
                // ============================

                // 1) líneas “bonitas” (resumen)
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

                // 2) además: guarda TODA la fila completa como detalle (todas las columnas del excel)
                //    para que “no falte nada”
                $detailLines[] = "— DETALLE COMPLETO FILA —";
                foreach ($r as $col => $val) {
                    if ($val === null)
                        continue;
                    $valStr = is_string($val) ? trim($val) : (string) $val;
                    if ($valStr === '')
                        continue;

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
     * Parse PDF Guía Recepción Fruta Granel (R xxx)
     * Devuelve: numero_guia, productor, especie, variedad, destino, totales, detalle[]
     */
    private function parseGuiaRecepcion(array $lines): array
    {
        // Normaliza líneas
        $normLines = array_values(array_filter(array_map(function ($l) {
            $l = trim(preg_replace('/\s+/u', ' ', (string) $l));
            return $l;
        }, $lines), fn($l) => $l !== ''));

        $text = implode("\n", $normLines);

        // Helpers
        $toFloat = function (?string $raw): ?float {
            if (!$raw)
                return null;
            // "1.598,00" -> "1598.00"
            $raw = trim($raw);
            $raw = str_replace([' ', "\u{00A0}"], '', $raw);
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
            return is_numeric($raw) ? (float) $raw : null;
        };

        $findFirstDate = function (array $lines, int $startIdx = 0): ?string {
            for ($i = $startIdx; $i < count($lines); $i++) {
                if (preg_match('/\b([0-3]?\d-[01]?\d-\d{4})\b/u', $lines[$i], $m)) {
                    return $m[1];
                }
            }
            return null;
        };

        // 1) Numero de guía (lo más importante)
        // Soporta: "Numero 559", "Nº: 336", "N° 336", "GUIA ... Nº: 336"
        $numero = null;
        if (preg_match('/\bNumero\b\s*[:\-]?\s*(\d{2,6})\b/iu', $text, $m)) {
            $numero = (int) $m[1];
        } elseif (preg_match('/\bN[°ºo]\s*[:\-]?\s*(\d{2,6})\b/iu', $text, $m)) {
            $numero = (int) $m[1];
        } elseif (preg_match('/\bN[°ºo]\s*[:\-]?\s*(\d{2,6})\b/iu', $text, $m)) {
            $numero = (int) $m[1];
        }

        // 2) Productor / Especie / Variedad  (línea con " / ")
        $productor = $especie = $variedad = null;
        foreach ($normLines as $ln) {
            // Ej: "Agrícola Epple y Heinrich Ltda. / Frambuesa Org. / Wakefield"
            if (substr_count($ln, '/') >= 2) {
                $parts = array_map('trim', explode('/', $ln));
                if (count($parts) >= 3) {
                    $productor = $parts[0] ?: $productor;
                    $especie = $parts[1] ?: $especie;
                    $variedad = $parts[2] ?: $variedad;
                    break;
                }
            }
        }

        // 3) Destino / Categoría (ej: "A Proceso Purranque")
        $destino = null;
        foreach ($normLines as $ln) {
            if (preg_match('/\bA\s+Proceso\s+([A-Za-zÁÉÍÓÚÑáéíóúñ ]+)\b/u', $ln, $m)) {
                $destino = 'A Proceso ' . trim($m[1]);
                break;
            }
        }

        // 4) Fecha principal (si aparece, toma la primera del doc)
        $fecha_doc = null;
        // A veces viene con "Fecha:" o solo suelta
        if (preg_match('/\bFecha\s*:\s*([0-3]?\d-[01]?\d-\d{4})\b/iu', $text, $m)) {
            $fecha_doc = $m[1];
        } else {
            $fecha_doc = $findFirstDate($normLines, 0);
        }

        // 5) Totales (sub total / total general)
        $subtotal_cant = $subtotal_kgs = null;
        $total_cant = $total_kgs = null;

        for ($i = 0; $i < count($normLines); $i++) {
            $ln = $normLines[$i];

            // Sub Total ... luego una línea con 2 números
            if (preg_match('/^Sub\s*Total\b/iu', $ln)) {
                // Busca en las siguientes 1-3 líneas los 2 números
                for ($k = 1; $k <= 3; $k++) {
                    $next = $normLines[$i + $k] ?? '';
                    if (preg_match('/([0-9]{1,3}(?:\.[0-9]{3})*,[0-9]{2})\s+([0-9]{1,3}(?:\.[0-9]{3})*,[0-9]{2})/u', $next, $m2)) {
                        $subtotal_cant = $toFloat($m2[1]);
                        $subtotal_kgs = $toFloat($m2[2]);
                        break;
                    }
                }
            }

            // Total general
            if (preg_match('/^Total\s+general\s*:/iu', $ln) || preg_match('/^Total\s+general$/iu', $ln)) {
                // Busca en la misma o siguientes líneas 2 números
                if (preg_match('/([0-9]{1,3}(?:\.[0-9]{3})*,[0-9]{2})\s+([0-9]{1,3}(?:\.[0-9]{3})*,[0-9]{2})/u', $ln, $m2)) {
                    $total_cant = $toFloat($m2[1]);
                    $total_kgs = $toFloat($m2[2]);
                } else {
                    for ($k = 1; $k <= 3; $k++) {
                        $next = $normLines[$i + $k] ?? '';
                        if (preg_match('/([0-9]{1,3}(?:\.[0-9]{3})*,[0-9]{2})\s+([0-9]{1,3}(?:\.[0-9]{3})*,[0-9]{2})/u', $next, $m2)) {
                            $total_cant = $toFloat($m2[1]);
                            $total_kgs = $toFloat($m2[2]);
                            break;
                        }
                    }
                }
            }
        }

        // 6) Detalle: líneas tipo "305001R Sin Calibre Bandeja ... 240 284,00"
        // Ojo: a veces la fecha está en la línea anterior o siguiente; guardamos la más cercana.
        $detalles = [];
        for ($i = 0; $i < count($normLines); $i++) {
            $ln = $normLines[$i];

            // Match folio: 305001R / 336001R etc.
            // Formato: FOLIO + "Sin Calibre" + ENVASE (texto) + CANT + PESO
            // Ej: "305006R Sin Calibre Bandeja Berries 50x30 AZUL 248 324,00"
            if (
                preg_match(
                    '/^(\d{3,6}\d{3}R)\s+(Sin\s+Calibre|[A-Za-zÁÉÍÓÚÑáéíóúñ ]+)\s+(.+?)\s+(\d+)\s+([0-9]{1,3}(?:\.[0-9]{3})*,[0-9]{2})$/u',
                    $ln,
                    $m
                )
            ) {
                $folio = $m[1];
                $calibre = trim($m[2]);
                $envase = trim($m[3]);
                $cant = (int) $m[4];
                $kgs = $toFloat($m[5]);

                // Fecha asociada (busca cerca: línea anterior o siguiente)
                $fecha = null;
                if ($i > 0 && preg_match('/\b([0-3]?\d-[01]?\d-\d{4})\b/u', $normLines[$i - 1], $md)) {
                    $fecha = $md[1];
                } elseif (preg_match('/\b([0-3]?\d-[01]?\d-\d{4})\b/u', $ln, $md)) {
                    $fecha = $md[1];
                } elseif (isset($normLines[$i + 1]) && preg_match('/\b([0-3]?\d-[01]?\d-\d{4})\b/u', $normLines[$i + 1], $md)) {
                    $fecha = $md[1];
                } else {
                    $fecha = $fecha_doc; // fallback
                }

                $detalles[] = [
                    'folio' => $folio,
                    'fecha' => $fecha,
                    'calibre' => $calibre,
                    'envase' => $envase,
                    'cantidad' => $cant,
                    'kgs' => $kgs,
                ];
            }
        }

        return [
            'numero_guia' => $numero,
            'fecha' => $fecha_doc,
            'productor' => $productor,
            'especie' => $especie,
            'variedad' => $variedad,
            'destino' => $destino,
            'subtotal' => [
                'cantidad' => $subtotal_cant,
                'kgs' => $subtotal_kgs,
            ],
            'total' => [
                'cantidad' => $total_cant,
                'kgs' => $total_kgs,
            ],
            'detalles' => $detalles,
        ];
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

        // =========================================================
        // 1) Elegir hoja correcta (la que contenga "bin" en alguna fila)
        // =========================================================
        $sheet = $spreadsheet->getActiveSheet();
        $found = false;

        foreach ($spreadsheet->getWorksheetIterator() as $ws) {
            $tmp = $ws->toArray(null, true, true, true);

            // buscar "bin" en las primeras 30 filas de esa hoja
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

        // =========================================================
        // 2) Normalizador de headers (quita NBSP, dobles espacios, etc.)
        // =========================================================
        $norm = function ($s): string {
            $s = (string) $s;
            $s = str_replace("\xc2\xa0", ' ', $s);      // NBSP
            $s = preg_replace('/\s+/u', ' ', $s);      // colapsa espacios
            $s = trim($s);
            $s = mb_strtolower($s);

            // opcional: quitar acentos para comparar (código => codigo)
            $noAcc = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
            if ($noAcc !== false && $noAcc !== '')
                $s = mb_strtolower($noAcc);

            return $s;
        };

        // =========================================================
        // 3) Detectar la fila real de headers (no asumir fila 1)
        // =========================================================
        $headerRowIndex = null;

        for ($r = 1; $r <= min(30, count($rows)); $r++) {
            $row = $rows[$r] ?? [];
            $keys = array_map(fn($v) => $norm($v), $row);

            // condiciones típicas de tu archivo
            $hasBin = false;
            $hasFecha = false;

            foreach ($keys as $k) {
                if ($k === '')
                    continue;
                if (str_contains($k, 'bin'))
                    $hasBin = true;
                if (str_contains($k, 'fecha') && str_contains($k, 'registro'))
                    $hasFecha = true;
            }

            if ($hasBin && $hasFecha) {
                $headerRowIndex = $r;
                break;
            }
        }

        if (!$headerRowIndex) {
            // debug útil
            return back()->withErrors([
                'excel' => 'No pude detectar la fila de encabezados (busqué "bin" y "fecha registro" en las primeras 30 filas).'
            ]);
        }

        $headerRow = $rows[$headerRowIndex];

        // =========================================================
        // 4) Armar mapa header => columna con normalización
        // =========================================================
        $headerToCol = [];
        foreach ($headerRow as $colLetter => $headerName) {
            $k = $norm($headerName);
            if ($k !== '')
                $headerToCol[$k] = $colLetter;
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
                if ($n !== '' && str_contains($n, $needle))
                    $cols[] = $col;
            }
            return $cols;
        };

        $exportadoraCols = $findColsByContains('exportadora');
        $selloCols = $findColsByContains('sello'); // más flexible que "número de sello"
        $binCols = $findColsByContains('bin');
        $binCol = $binCols[0] ?? null;

        // =========================================================
        // Helpers
        // =========================================================
        $parseDate = function ($v): ?string {
            if ($v === null)
                return null;
            $s = trim((string) $v);
            if ($s === '')
                return null;

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s))
                return $s;
            if (preg_match('/^(\d{2})[\/\-](\d{2})[\/\-](\d{4})$/', $s, $m))
                return "{$m[3]}-{$m[2]}-{$m[1]}";
            return null;
        };

        $parseTime = function ($v): ?string {
            if ($v === null)
                return null;
            $s = trim((string) $v);
            if ($s === '')
                return null;

            if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $s))
                return strlen($s) === 5 ? ($s . ':00') : $s;
            return null;
        };

        $parseInt = function ($v): ?int {
            if ($v === null)
                return null;
            if (is_int($v))
                return $v;
            if (is_float($v))
                return (int) round($v);

            $s = trim((string) $v);
            if ($s === '')
                return null;
            $s = str_replace(['.', ' '], '', $s);
            return ctype_digit($s) ? (int) $s : null;
        };

        $normalizeBin = function ($v): ?string {
            if ($v === null)
                return null;
            $s = trim((string) $v);
            if ($s === '')
                return null;

            // "bin;AAAA041805" => "AAAA041805"
            if (str_contains($s, ';')) {
                $parts = explode(';', $s, 2);
                $s = trim($parts[1] ?? $s);
            }
            return $s !== '' ? $s : null;
        };

        // =========================================================
        // 5) Procesar filas: parten DESPUÉS del header real
        // =========================================================
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

        // =========================
        // Normalizador fuerte
        // =========================
        $norm = function ($s): string {
            $s = (string) $s;
            $s = str_replace(["\xc2\xa0", 'í', 'Í'], [' ', 'i', 'i'], $s);
            $s = preg_replace('/\s+/u', ' ', $s);
            $s = trim($s);
            return mb_strtolower($s);
        };

        // =========================
        // HEADER FIJO = FILA 1
        // =========================
        $headerRow = $rows[1];
        $headerToCol = [];

        foreach ($headerRow as $col => $name) {
            $k = $norm($name);
            if ($k !== '') {
                $headerToCol[$k] = $col;
            }
        }

        // helper seguro (alias)
        $get = function (array $row, array $aliases) use ($headerToCol) {
            foreach ($aliases as $a) {
                if (isset($headerToCol[$a])) {
                    return $row[$headerToCol[$a]] ?? null;
                }
            }
            return null;
        };

        // =========================
        // Helpers
        // =========================
        $parseNumber = function ($v): ?float {
            if ($v === null)
                return null;
            if (is_int($v) || is_float($v))
                return (float) $v;

            $s = str_replace(['.', ','], ['', '.'], trim((string) $v));
            return is_numeric($s) ? (float) $s : null;
        };

        $parseDate = function ($v): ?string {
            if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', (string) $v, $m)) {
                return "{$m[3]}-{$m[2]}-{$m[1]}";
            }
            return null;
        };

        // =========================
        // PROCESO + REPORTE
        // =========================
        $template = 'RFP';
        $created = $duplicates = $skipped = 0;
        $report = [];

        for ($i = 2; $i <= count($rows); $i++) {
            $r = $rows[$i];

            $guia = trim((string) $get($r, [
                'guia despacho',
                'guia',
            ]));

            if ($guia === '') {
                $skipped++;
                $report[] = [
                    'row' => $i,
                    'status' => 'skip',
                    'reason' => 'Guía vacía / no detectada',
                ];
                continue;
            }

            if (
                PdfImport::where('template', $template)
                    ->where('guia_no', $guia)
                    ->exists()
            ) {
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

            DB::transaction(function () use ($file, $storedPath, $template, $guia, $fecha,        // ✅
                $productor,    // ✅
                $meta, &$created) {

                $import = PdfImport::create([
                    'original_name' => 'EXCEL RFP: ' . $file->getClientOriginalName(),
                    'stored_path' => $storedPath,
                    'template' => $template,
                    'guia_no' => $guia,
                    'doc_fecha' => $fecha,              // ✅
                    'productor' => $productor ?: null,  // ✅
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

    public function storeXml(Request $request)
    {
        $request->validate([
            'xmls' => ['required', 'array', 'min:1'],
            'xmls.*' => ['file', 'mimes:xml', 'max:10240'],
        ]);

        $disk = config('filesystems.default');

        $created = 0;
        $duplicates = 0;
        $skipped = 0;

        foreach ($request->file('xmls') as $file) {

            $originalName = $file->getClientOriginalName();

            // ===== 1️⃣ Leer XML correctamente (EnvioDTE firmado) =====
            $xmlRaw = file_get_contents($file->getRealPath());

            // eliminar BOM si existe
            $xmlRaw = preg_replace('/^\xEF\xBB\xBF/', '', $xmlRaw);

            // convertir a UTF-8 (desde ISO-8859-1)
            $xmlString = mb_convert_encoding(
                $xmlRaw,
                'UTF-8',
                'ISO-8859-1,UTF-8'
            );

            libxml_use_internal_errors(true);

            $xml = simplexml_load_string(
                $xmlString,
                SimpleXMLElement::class,
                LIBXML_NOCDATA | LIBXML_NOBLANKS | LIBXML_COMPACT
            );

            if ($xml === false) {
                $skipped++;
                continue;
            }

            // ===== 2️⃣ Detectar template =====
            $template = $this->detectXmlTemplate($xml);
            if (!$template) {
                $skipped++;
                continue;
            }

            // ===== 3️⃣ Parsear XML =====
            $parsed = $this->parseXmlSii46($xml);

            $guia = $parsed['guia_no'] ?? null;
            if (!$guia) {
                $skipped++;
                continue;
            }

            // ===== 4️⃣ Dedupe por guía real =====
            if (
                PdfImport::where('template', $template)
                    ->where('guia_no', $guia)
                    ->exists()
            ) {
                $duplicates++;
                continue;
            }

            // ===== 5️⃣ Guardar archivo =====
            $path = $file->store('imports/xml', $disk);

            // ===== 6️⃣ Guardar DB =====
            DB::transaction(function () use ($originalName, $path, $template, $parsed, &$created) {

                $import = PdfImport::create([
                    'original_name' => $originalName,
                    'stored_path' => $path,
                    'template' => $template,
                    'guia_no' => $parsed['guia_no'],   // ✅ GD 580
                    'doc_fecha' => $parsed['doc_fecha'],
                    'productor' => $parsed['productor'],
                    'meta' => json_encode($parsed['meta'], JSON_UNESCAPED_UNICODE),
                ]);

                // ===== 7️⃣ Todas las líneas del XML =====
                $lineNo = 1;
                foreach ($parsed['lines'] as $line) {
                    PdfLine::create([
                        'pdf_import_id' => $import->id,
                        'line_no' => $lineNo++,
                        'content' => $line,
                    ]);
                }

                $created++;
            });
        }

        return redirect()
            ->route('pdf.index')
            ->with(
                'ok',
                "XML importados ✅ | {$created} creados | {$duplicates} duplicados | {$skipped} saltados"
            );
    }

    private function detectXmlTemplate(SimpleXMLElement $xml): ?string
    {
        $tipo = (string) ($xml->xpath('//*[local-name()="TipoDTE"]')[0] ?? '');

        return $tipo === '46' ? 'XML_SII_46' : null;
    }
    private function extractGuiaFromDetalles(SimpleXMLElement $xml): ?string
    {
        foreach ($xml->xpath('//*[local-name()="Detalle"]') as $det) {

            $nmbNode = $det->xpath('./*[local-name()="NmbItem"]');

            if (!$nmbNode || !isset($nmbNode[0])) {
                continue;
            }

            $nmb = trim((string) $nmbNode[0]);

            if ($nmb === '') {
                continue;
            }

            /**
             * 🔥 NORMALIZACIÓN DE ENCODING SII
             * Convierte:
             *  GD NÂ° 547
             *  GD NÂº 547
             *  GD N° 547
             *  GD Nº547
             * → GD N° 547
             */
            $nmb = str_replace(
                ["\xC2\xB0", "\xC2\xBA", 'Â°', 'Âº', 'º'],
                '°',
                $nmb
            );

            // Limpieza extra de espacios raros
            $nmb = preg_replace('/\s+/u', ' ', $nmb);

            // 🔎 MATCH FINAL (robusto)
            if (preg_match('/\bGD\s*(?:N\s*°)?\s*0*(\d+)\b/ui', $nmb, $m)) {
                return (string) ((int) $m[1]); // 👉 "547"
            }
        }

        return null;
    }


    private function parseXmlSii46(SimpleXMLElement $xml): array
    {
        $get = fn(string $name) =>
            (string) ($xml->xpath('//*[local-name()="' . $name . '"]')[0] ?? null);
        $totalKilos = 0;

        foreach ($xml->xpath('//*[local-name()="Detalle"]') as $det) {
            $qty = (float) ($det->xpath('./*[local-name()="QtyItem"]')[0] ?? 0);
            $unm = strtoupper((string) ($det->xpath('./*[local-name()="UnmdItem"]')[0] ?? ''));

            if ($unm === 'KG') {
                $totalKilos += $qty;
            }
        }
        // 🔥 GD REAL
        $guia = $this->extractGuiaFromDetalles($xml);

        $items = [];
        $lines = [];

        $lines = $this->extractAllXmlLines($xml);
        $emisorRut = $get('RUTEmisor');
        $emisorRzn = $get('RznSoc');

        $receptorRut = $get('RUTRecep');
        $receptorRzn = $get('RznSocRecep');

        return [
            'guia_no' => $guia,
            'doc_fecha' => $get('FchEmis'),
            'productor' => $emisorRzn,
            'lines' => $lines,

            'meta' => [
                'source' => 'xml',
                'tipo_dte' => 46,
                'folio_sii' => $get('Folio'),

                // 🔥🔥🔥 ESTO FALTABA
                'kgs_recibido' => $totalKilos > 0 ? $totalKilos : null,

                'emisor' => [
                    'rut' => $emisorRut,
                    'razon_social' => $emisorRzn,
                ],
                'receptor' => [
                    'rut' => $receptorRut,
                    'razon_social' => $receptorRzn,
                ],
                'items' => $items,
            ],
        ];


    }
    private function extractAllXmlLines(SimpleXMLElement $xml): array
    {
        $lines = [];

        $walker = function ($node, string $path = '') use (&$walker, &$lines) {

            foreach ($node->children() as $name => $child) {

                $currentPath = $path === '' ? $name : $path . '/' . $name;

                // Nodo hoja (solo texto)
                if ($child->children()->count() === 0) {

                    $value = trim((string) $child);

                    if ($value !== '') {

                        // 🔥 Normalización encoding SII
                        $value = str_replace(
                            [
                                "\xC2\xB0",
                                "\xC2\xBA",
                                'Â°',
                                'Âº',
                                'Ã',
                                'Ã',
                                'Ã',
                                'Ã',
                                'Ã',
                                'Ã¡',
                                'Ã©',
                                'Ã­',
                                'Ã³',
                                'Ãº',
                            ],
                            [
                                '°',
                                '°',
                                '°',
                                '°',
                                'Á',
                                'É',
                                'Í',
                                'Ó',
                                'Ú',
                                'á',
                                'é',
                                'í',
                                'ó',
                                'ú',
                            ],
                            $value
                        );

                        $value = preg_replace('/\s+/u', ' ', $value);

                        $lines[] = "{$currentPath}: {$value}";
                    }
                }

                // seguir bajando
                if ($child->children()->count() > 0) {
                    $walker($child, $currentPath);
                }
            }
        };

        $walker($xml);

        return $lines;
    }


    public function ver(int $id)
    {
        $import = PdfImport::findOrFail($id);

        if (!Storage::disk(config('filesystems.default'))->exists($import->stored_path)) {
            abort(404, 'Archivo no encontrado');
        }

        return response()->file(
            Storage::disk(config('filesystems.default'))->path($import->stored_path),
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $import->original_name . '"'
            ]
        );
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



    private function parseGuiaRecepcionPinochet(array $lines): array
    {
        $toFloat = function (?string $v): ?float {
            if (!$v)
                return null;
            $v = str_replace(['.', ','], ['', '.'], trim($v));
            return is_numeric($v) ? (float) $v : null;
        };

        $data = [
            'guia_no' => null,
            'doc_fecha' => null,
            'productor' => null,
            'guia_productor' => null,
            'total_cajas' => null,
            'total_kgs' => null,
        ];

        foreach ($lines as $l) {
            $l = trim(preg_replace('/\s+/', ' ', $l));

            if (preg_match('/Gu[ií]a\s*N[°º]?\s*:? (\d+)/i', $l, $m)) {
                $data['guia_no'] = $m[1];
            }

            if (preg_match('/Fecha\s*:? (\d{2}-\d{2}-\d{4})/i', $l, $m)) {
                $data['doc_fecha'] = $m[1];
            }

            if (preg_match('/Productor\s*:? (.+)$/i', $l, $m)) {
                $data['productor'] = trim($m[1]);
            }

            if (preg_match('/Gu[ií]a\s*Productor\s*:? (\d+)/i', $l, $m)) {
                $data['guia_productor'] = $m[1];
            }

            if (preg_match('/Total\s+Cajas\s*:? (\d+)/i', $l, $m)) {
                $data['total_cajas'] = (int) $m[1];
            }

            if (preg_match('/Total\s+Kilos\s*:? ([0-9\.,]+)/i', $l, $m)) {
                $data['total_kgs'] = $toFloat($m[1]);
            }
        }

        return $data;
    }




}
