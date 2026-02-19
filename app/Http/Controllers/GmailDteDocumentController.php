<?php

namespace App\Http\Controllers;

use App\Services\GmailDteInventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GmailDteDocumentController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $documents = DB::connection('fuelcontrol')
            ->table('gmail_dte_documents')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('folio', 'like', "%{$q}%")
                        ->orWhere('proveedor_nombre', 'like', "%{$q}%")
                        ->orWhere('proveedor_rut', 'like', "%{$q}%")
                        ->orWhere('referencia', 'like', "%{$q}%")
                        ->orWhere('xml_filename', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('fecha_factura')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('gmail.dtes.index', compact('documents', 'q'));
    }

    public function show(int $id)
    {
        [$document, $lines] = $this->getDocumentWithLines($id);

        return view('gmail.dtes.show', compact('document', 'lines'));
    }

    public function print(int $id)
    {
        [$document, $lines] = $this->getDocumentWithLines($id);

        return view('gmail.dtes.print', compact('document', 'lines'));
    }

    public function markPaid(int $id)
    {
        DB::connection('fuelcontrol')
            ->table('gmail_dte_documents')
            ->where('id', $id)
            ->update([
                'payment_status' => 'pagado',
                'paid_at' => now(),
                'updated_at' => now(),
            ]);

        return back()->with('success', 'Documento marcado como pagado.');
    }

    public function markCreditNote(int $id)
    {
        DB::connection('fuelcontrol')
            ->table('gmail_dte_documents')
            ->where('id', $id)
            ->update([
                'workflow_status' => 'nota_credito',
                'updated_at' => now(),
            ]);

        return back()->with('success', 'Documento marcado como nota de credito.');
    }

    public function markAccepted(int $id)
    {
        DB::connection('fuelcontrol')
            ->table('gmail_dte_documents')
            ->where('id', $id)
            ->update([
                'workflow_status' => 'aceptado',
                'updated_at' => now(),
            ]);

        return back()->with('success', 'Documento aceptado.');
    }

    public function addToStock(int $id, GmailDteInventoryService $inventoryService)
    {
        $result = $inventoryService->addDocumentToStock($id, auth()->id());

        if ($result['already_posted']) {
            return back()->with('warning', 'Este documento ya fue ingresado a inventario.');
        }

        return back()->with('success', "Inventario actualizado. Movimiento #{$result['movement_id']}.");
    }

    public function inventoryIndex(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $estado = trim((string) $request->query('estado', ''));
        $stock = trim((string) $request->query('stock', ''));

        $baseQuery = DB::connection('fuelcontrol')
            ->table('gmail_inventory_products')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('nombre', 'like', "%{$q}%")
                        ->orWhere('codigo', 'like', "%{$q}%")
                        ->orWhere('unidad', 'like', "%{$q}%");
                });
            })
            ->when($estado === 'activos', fn($query) => $query->where('is_active', 1))
            ->when($estado === 'inactivos', fn($query) => $query->where('is_active', 0))
            ->when($stock === 'con_stock', fn($query) => $query->where('stock_actual', '>', 0))
            ->when($stock === 'sin_stock', fn($query) => $query->where('stock_actual', '<=', 0));

        $products = (clone $baseQuery)
            ->orderBy('nombre')
            ->paginate(30)
            ->withQueryString();

        $totalActivos = DB::connection('fuelcontrol')->table('gmail_inventory_products')->where('is_active', 1)->count();
        $totalInactivos = DB::connection('fuelcontrol')->table('gmail_inventory_products')->where('is_active', 0)->count();
        $totalConStock = DB::connection('fuelcontrol')->table('gmail_inventory_products')->where('stock_actual', '>', 0)->count();
        $totalSinStock = DB::connection('fuelcontrol')->table('gmail_inventory_products')->where('stock_actual', '<=', 0)->count();

        return view('gmail.inventory.index', compact(
            'products',
            'q',
            'estado',
            'stock',
            'totalActivos',
            'totalInactivos',
            'totalConStock',
            'totalSinStock'
        ));
    }

    private function getDocumentWithLines(int $id): array
    {
        $document = DB::connection('fuelcontrol')
            ->table('gmail_dte_documents')
            ->where('id', $id)
            ->firstOrFail();

        $lines = DB::connection('fuelcontrol')
            ->table('gmail_dte_document_lines')
            ->where('document_id', $id)
            ->orderBy('nro_linea')
            ->orderBy('id')
            ->get();

        $taxesByLine = DB::connection('fuelcontrol')
            ->table('gmail_dte_document_line_taxes')
            ->where('document_id', $id)
            ->orderBy('id')
            ->get()
            ->groupBy('dte_line_id');

        foreach ($lines as $line) {
            $line->taxes = $taxesByLine->get($line->id, collect());
        }

        $document->tax_summary = $this->buildTaxSummary($document, $lines);

        return [$document, $lines];
    }

    private function buildTaxSummary($document, $lines): array
    {
        $summary = [];

        $add = function (string $key, string $label, ?float $monto, bool $informado = true) use (&$summary): void {
            if (!isset($summary[$key])) {
                $summary[$key] = [
                    'label' => $label,
                    'monto' => 0.0,
                    'informado' => false,
                ];
            }

            if (!is_null($monto)) {
                $summary[$key]['monto'] += (float) $monto;
                $summary[$key]['informado'] = true;
            } elseif ($informado) {
                $summary[$key]['informado'] = $summary[$key]['informado'] || $informado;
            }
        };

        if ((float) ($document->monto_iva ?? 0) > 0) {
            $add('IVA', 'IVA', (float) $document->monto_iva, true);
        }

        // Montos provenientes de impuestos por línea (si vienen explícitos).
        foreach ($lines as $line) {
            foreach (($line->taxes ?? collect()) as $tax) {
                $type = strtoupper((string) ($tax->tax_type ?? 'TAX'));
                $label = trim((string) ($tax->descripcion ?? '')) ?: ('Impuesto ' . ($tax->codigo ?? ''));
                if ($type === 'IMP_ADIC') {
                    $label = 'Impuesto específico';
                }
                $monto = is_null($tax->monto) ? null : (float) $tax->monto;
                $add($type . '|' . $label, $label, $monto, !is_null($monto));
            }
        }

        // Busca montos en el XML crudo (Totales), p.ej. MntImp / ImptoReten.
        $xmlRaw = (string) ($document->xml_raw ?? '');
        if (trim($xmlRaw) !== '') {
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($xmlRaw);
            if ($xml) {
                $xml->registerXPathNamespace('sii', 'http://www.sii.cl/SiiDte');
                $tot = $xml->xpath('//sii:Encabezado/sii:Totales')[0] ?? null;

                if ($tot) {
                    $tasaIva = trim((string) ($tot->TasaIVA ?? ''));
                    if ($tasaIva !== '' && isset($summary['IVA'])) {
                        $summary['IVA']['label'] = 'IVA ' . rtrim(rtrim($tasaIva, '0'), '.') . '%';
                    }

                    $mntImp = (float) ((string) ($tot->MntImp ?? 0));
                    if ($mntImp > 0) {
                        $add('MntImp', 'Impuesto específico', $mntImp, true);
                    }

                    if (isset($tot->ImptoReten)) {
                        foreach ($tot->ImptoReten as $ret) {
                            $tipo = trim((string) ($ret->TipoImp ?? ''));
                            $tasa = trim((string) ($ret->TasaImp ?? ''));
                            $monto = is_numeric((string) ($ret->MontoImp ?? null)) ? (float) $ret->MontoImp : null;

                            $label = 'Impuesto retenido';
                            if ($tipo !== '') {
                                $label .= ' ' . $tipo;
                            }
                            if ($tasa !== '') {
                                $label .= ' (' . rtrim(rtrim($tasa, '0'), '.') . '%)';
                            }

                            $add('RET|' . $label, $label, $monto, !is_null($monto));
                        }
                    }

                    foreach ($tot->children() as $child) {
                        $name = $child->getName();
                        if (!str_starts_with($name, 'Mnt')) {
                            continue;
                        }
                        if (in_array($name, ['MntNeto', 'MntTotal', 'MntImp'], true)) {
                            continue;
                        }

                        $value = (float) ((string) $child);
                        if ($value <= 0) {
                            continue;
                        }

                        $label = match ($name) {
                            'MntExe' => 'Monto exento',
                            default => $name,
                        };
                        $add('TOT|' . $name, $label, $value, true);
                    }
                }
            }
            libxml_clear_errors();
        }

        return array_values($summary);
    }
}
