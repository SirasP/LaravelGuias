<?php

namespace App\Http\Controllers;

use App\Models\PdfImport;
use App\Models\PdfLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use SimpleXMLElement;

class XmlImportController extends Controller
{
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
            if (PdfImport::where('guia_no', $guia)->exists()) {
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

            $nmb = str_replace(["\xC2\xB0", "\xC2\xBA", 'Â°', 'Âº', 'º'], '°', $nmb);
            $nmb = preg_replace('/\s+/u', ' ', $nmb);

            if (preg_match('/\bGD\s*(?:N\s*°)?\s*0*(\d+)\b/ui', $nmb, $m)) {
                return (string) ((int) $m[1]); 
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
        
        $guia = $this->extractGuiaFromDetalles($xml);
        $items = [];
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

                if ($child->children()->count() === 0) {
                    $value = trim((string) $child);
                    if ($value !== '') {
                        $value = str_replace(
                            ["\xC2\xB0", "\xC2\xBA", 'Â°', 'Âº', 'Ã ', 'Ã‰', 'Ã ', 'Ã“', 'Ãš', 'Ã¡', 'Ã©', 'Ã­', 'Ã³', 'Ãº'],
                            ['°', '°', '°', '°', 'Á', 'É', 'Í', 'Ó', 'Ú', 'á', 'é', 'í', 'ó', 'ú'],
                            $value
                        );
                        $value = preg_replace('/\s+/u', ' ', $value);
                        $lines[] = "{$currentPath}: {$value}";
                    }
                }

                if ($child->children()->count() > 0) {
                    $walker($child, $currentPath);
                }
            }
        };

        $walker($xml);

        return $lines;
    }
}
