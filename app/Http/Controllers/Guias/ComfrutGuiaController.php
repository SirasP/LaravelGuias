<?php

namespace App\Http\Controllers\Guias;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ComfrutGuia;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;

class ComfrutGuiaController extends Controller
{

    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        $guias = ComfrutGuia::query()
            ->when($q, function ($query) use ($q) {
                $query->where('guia_numero', 'like', "%{$q}%")
                    ->orWhere('productor', 'like', "%{$q}%")
                    ->orWhere('patente', 'like', "%{$q}%");
            })
            ->orderByDesc('fecha_guia')
            ->paginate(20)
            ->withQueryString();

        return view('guias.comfrut.index', [
            'guias' => $guias,
            'q' => $q,
            'total' => $guias->total(),
        ]);
    }
    public function importForm()
    {
        return view('guias.comfrut.import');
    }
    public function show(ComfrutGuia $guia)
    {
        $guia->load('detalles');

        return view('guias.comfrut.show', compact('guia'));
    }
    public function import(Request $request)
    {
        $request->validate([
            'xml' => ['required', 'array'],
            'xml.*' => ['file', 'mimes:xml', 'max:10240'],
        ]);

        $importadas = 0;
        $omitidas = 0;

        foreach ($request->file('xml') as $file) {

            $xmlContent = file_get_contents($file->getRealPath());
            $hash = hash('sha256', $xmlContent);

            // 🔒 XML duplicado exacto
            if (ComfrutGuia::where('xml_hash', $hash)->exists()) {
                $omitidas++;
                continue;
            }

            $xml = simplexml_load_string($xmlContent, 'SimpleXMLElement', LIBXML_NOCDATA);
            if (!$xml) {
                $omitidas++;
                continue;
            }

            $xml->registerXPathNamespace('sii', 'http://www.sii.cl/SiiDte');
            $doc = $xml->xpath('//sii:DTE/sii:Documento')[0] ?? null;
            if (!$doc) {
                $omitidas++;
                continue;
            }

            $idDoc = $doc->Encabezado->IdDoc;
            $emisor = $doc->Encabezado->Emisor;

            $folio = (string) $idDoc->Folio;

            // 🔒 Guía duplicada por número
            if (ComfrutGuia::where('guia_numero', $folio)->exists()) {
                $omitidas++;
                continue;
            }

            $fecha = (string) $idDoc->FchEmis;
            $tipoDte = (string) $idDoc->TipoDTE;

            // 🔒 Solo importar tipo DTE 52
            if ($tipoDte !== '52') {
                $omitidas++;
                continue;
            }

            $razonSocial = (string) $emisor->RznSoc;
            $rutEmisor = (string) $emisor->RUTEmisor;

            // Transporte seguro
            $transporte = $doc->Encabezado->Transporte ?? null;

            $patente = $transporte?->Patente ? (string) $transporte->Patente : null;

            $rutChofer = $transporte?->Chofer?->RUTChofer ? (string) $transporte->Chofer->RUTChofer : null;
            $nombreChofer = $transporte?->Chofer?->NombreChofer ? (string) $transporte->Chofer->NombreChofer : null;

            $montoTotal = (int) ($doc->Encabezado->Totales->MntTotal ?? 0);

            // guardar XML
            $path = $file->store('comfrut/xml', 'public');

            // crear guía
            $cantidadTotal = 0;

            $guia = ComfrutGuia::create([
                'guia_numero' => $folio,
                'fecha_guia' => $fecha,
                'tipo_dte' => $tipoDte,
                'productor' => $razonSocial,
                'rut_productor' => $rutEmisor,
                'patente' => $patente,
                'monto_total' => $montoTotal,
                'xml_path' => $path,
                'xml_hash' => $hash,
            ]);

            foreach ($doc->Detalle as $det) {
                $qty = (float) $det->QtyItem;
                $cantidadTotal += $qty;

                $guia->detalles()->create([
                    'linea' => (string) $det->NroLinDet,
                    'codigo_tipo' => (string) ($det->CdgItem->TpoCodigo ?? null),
                    'codigo_valor' => (string) ($det->CdgItem->VlrCodigo ?? null),
                    'nombre_item' => (string) $det->NmbItem,
                    'cantidad' => $qty,
                    'unidad' => (string) $det->UnmdItem,
                    'precio' => (float) $det->PrcItem,
                    'monto' => (float) $det->MontoItem,
                    'rut_chofer' => $rutChofer,
                    'nombre_chofer' => $nombreChofer,
                ]);
            }

            $guia->update([
                'cantidad_total' => $cantidadTotal,
            ]);

            $importadas++;
        }

        return redirect()
            ->route('guias.comfrut.index')
            ->with('ok', "Importadas: $importadas · Omitidas: $omitidas");
    }


    public function exportExcelPhpSpreadsheet(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        $guias = ComfrutGuia::with('detalles')
            ->when($q, fn($query) => $query->where('guia_numero', 'like', "%{$q}%")
                ->orWhere('productor', 'like', "%{$q}%")
                ->orWhere('patente', 'like', "%{$q}%"))
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Cabeceras
        $sheet->fromArray([
            [
                'Guía',
                'Fecha',
                'Productor',
                'Patente',
                'Monto Total',

                'Bandeja',
                'Cantidad Bandeja',
                'Pallet',
                'Cantidad Pallet'
            ]
        ], null, 'A1');

        $row = 2;
        foreach ($guias as $guia) {
            // Separar detalles en bandejas y pallets y reindexar
            $bandejas = $guia->detalles->filter(function ($d) {
                $nombre = strtolower(trim($d->nombre_item));
                return Str::contains($nombre, ['bandej', 'bdja']); // agregar variantes
            })->values();

            $pallets = $guia->detalles->filter(function ($d) {
                $nombre = strtolower(trim($d->nombre_item));
                return Str::contains($nombre, ['pallet', 'palet', 'esquinero']); // variantes
            })->values();

            $maxRows = max($bandejas->count(), $pallets->count(), 1);

            for ($i = 0; $i < $maxRows; $i++) {
                $sheet->setCellValue("A{$row}", $guia->guia_numero);
                $sheet->setCellValue("B{$row}", $guia->fecha_guia);
                $sheet->setCellValue("C{$row}", $guia->productor);
                $sheet->setCellValue("D{$row}", $guia->patente);
                $sheet->setCellValue("E{$row}", $guia->monto_total);

                // Bandeja
                if (isset($bandejas[$i])) {
                    $sheet->setCellValue("F{$row}", $bandejas[$i]->nombre_item);
                    $sheet->setCellValue("G{$row}", $bandejas[$i]->cantidad);
                }

                // Pallet
                if (isset($pallets[$i])) {
                    $sheet->setCellValue("H{$row}", $pallets[$i]->nombre_item);
                    $sheet->setCellValue("I{$row}", $pallets[$i]->cantidad);
                }

                $row++;
            }
        }

        // Ajustar ancho de columnas automáticamente
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = 'comfrut_detalles.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"{$fileName}\"");
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }





}
