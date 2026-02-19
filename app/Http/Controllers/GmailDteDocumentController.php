<?php

namespace App\Http\Controllers;

use App\Services\GmailDteInventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GmailDteDocumentController extends Controller
{
    private const FACTURA_TYPES = [33, 34, 56];
    private const BOLETA_TYPES = [39, 41];
    private const EXCLUDED_WORKFLOW_STATUSES = ['anulado', 'rechazado'];

    public function index()
    {
        [$summaryFacturas, $agingFacturas] = $this->buildSummaryByTypes(self::FACTURA_TYPES);
        [$summaryBoletas, $agingBoletas] = $this->buildSummaryByTypes(self::BOLETA_TYPES);

        return view('gmail.dtes.index', compact('summaryFacturas', 'agingFacturas', 'summaryBoletas', 'agingBoletas'));
    }

    public function list(Request $request)
    {
        return redirect()->route('gmail.dtes.facturas.list', $request->only('q'));
    }

    public function facturasIndex()
    {
        return redirect()->route('gmail.dtes.index');
    }

    public function boletasIndex()
    {
        return redirect()->route('gmail.dtes.index');
    }

    public function facturasList(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $tipo = 'facturas';
        $documents = $this->buildDocumentsListQuery($q, self::FACTURA_TYPES)
            ->orderByDesc('fecha_factura')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('gmail.dtes.facturas.list', compact('documents', 'q', 'tipo'));
    }

    public function boletasList(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $tipo = 'boletas';
        $documents = $this->buildDocumentsListQuery($q, self::BOLETA_TYPES)
            ->orderByDesc('fecha_factura')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('gmail.dtes.boletas.list', compact('documents', 'q', 'tipo'));
    }

    private function buildSummaryByTypes(array $types): array
    {
        $docsQuery = DB::connection('fuelcontrol')
            ->table('gmail_dte_documents')
            ->select(['tipo_dte', 'payment_status', 'workflow_status', 'fecha_vencimiento', 'monto_total'])
            ->whereIn('tipo_dte', $types)
            ->where(function ($query) {
                $query->whereNull('workflow_status')
                    ->orWhereNotIn(DB::raw('LOWER(workflow_status)'), self::EXCLUDED_WORKFLOW_STATUSES);
            });

        if (Schema::connection('fuelcontrol')->hasColumn('gmail_dte_documents', 'saldo_pendiente')) {
            $docsQuery->addSelect('saldo_pendiente');
        }

        $docs = $docsQuery->get();

        $today = now()->startOfDay();
        $summary = [
            'total_docs' => 0,
            'por_validar_count' => 0,
            'por_validar_monto' => 0.0,
            'por_pagar_count' => 0,
            'por_pagar_monto' => 0.0,
            'atrasado_count' => 0,
            'atrasado_monto' => 0.0,
        ];

        $aging = [
            'vencido' => 0,
            'd1_7' => 0,
            'd8_15' => 0,
            'd16_30' => 0,
            'd31_plus' => 0,
            'no_adeudado' => 0,
        ];

        foreach ($docs as $doc) {
            $sign = ((int) ($doc->tipo_dte ?? 0) === 61) ? -1.0 : 1.0;
            $montoTotal = (float) ($doc->monto_total ?? 0) * $sign;
            $isPaid = (string) ($doc->payment_status ?? 'sin_pagar') === 'pagado';
            $isDraft = (string) ($doc->workflow_status ?? 'aceptado') === 'borrador';
            $saldoPendienteRaw = property_exists($doc, 'saldo_pendiente')
                ? (float) ($doc->saldo_pendiente ?? 0)
                : ($isPaid ? 0.0 : (float) ($doc->monto_total ?? 0));
            $saldoPendiente = $saldoPendienteRaw * $sign;

            $summary['total_docs']++;

            if ($isDraft) {
                $summary['por_validar_count']++;
                $summary['por_validar_monto'] += $saldoPendiente;
            }

            if (!$isPaid) {
                $venc = $doc->fecha_vencimiento ? \Carbon\Carbon::parse($doc->fecha_vencimiento)->startOfDay() : null;
                if (!$venc) {
                    $summary['por_pagar_count']++;
                    $summary['por_pagar_monto'] += $saldoPendiente;
                    $aging['no_adeudado']++;
                    continue;
                }

                $days = $venc->diffInDays($today, false);
                if ($days > 30) {
                    $aging['d31_plus']++;
                } elseif ($days > 15) {
                    $aging['d16_30']++;
                } elseif ($days > 7) {
                    $aging['d8_15']++;
                } elseif ($days > 0) {
                    $aging['d1_7']++;
                } elseif ($days === 0) {
                    $aging['vencido']++;
                } else {
                    $aging['no_adeudado']++;
                }

                if ($days > 0) {
                    $summary['atrasado_count']++;
                    $summary['atrasado_monto'] += $saldoPendiente;
                } else {
                    // Por pagar sin doble contar vencidos.
                    $summary['por_pagar_count']++;
                    $summary['por_pagar_monto'] += $saldoPendiente;
                }
            } else {
                $aging['no_adeudado']++;
            }
        }

        return [$summary, $aging];
    }

    private function buildDocumentsListQuery(string $q, array $types)
    {
        return DB::connection('fuelcontrol')
            ->table('gmail_dte_documents')
            ->whereIn('tipo_dte', $types)
            ->where(function ($query) {
                $query->whereNull('workflow_status')
                    ->orWhereNotIn(DB::raw('LOWER(workflow_status)'), self::EXCLUDED_WORKFLOW_STATUSES);
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('folio', 'like', "%{$q}%")
                        ->orWhere('proveedor_nombre', 'like', "%{$q}%")
                        ->orWhere('proveedor_rut', 'like', "%{$q}%")
                        ->orWhere('referencia', 'like', "%{$q}%")
                        ->orWhere('xml_filename', 'like', "%{$q}%");
                });
            });
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

    public function markUnpaid(int $id)
    {
        DB::connection('fuelcontrol')
            ->table('gmail_dte_documents')
            ->where('id', $id)
            ->update([
                'payment_status' => 'sin_pagar',
                'paid_at' => null,
                'updated_at' => now(),
            ]);

        return back()->with('success', 'Pago cancelado.');
    }

    public function markDraft(int $id)
    {
        DB::connection('fuelcontrol')
            ->table('gmail_dte_documents')
            ->where('id', $id)
            ->update([
                'workflow_status' => 'borrador',
                'updated_at' => now(),
            ]);

        return back()->with('success', 'Documento marcado como borrador.');
    }

    public function updateLine(Request $request, int $id, int $lineId)
    {
        $validated = $request->validate([
            'cantidad' => ['required', 'numeric', 'min:0'],
            'precio_unitario' => ['required', 'numeric', 'min:0'],
        ]);

        $db = DB::connection('fuelcontrol');
        $document = $db->table('gmail_dte_documents')->where('id', $id)->first();
        if (!$document) {
            return back()->with('warning', 'Documento no encontrado.');
        }

        if ((string) ($document->workflow_status ?? '') !== 'borrador') {
            return back()->with('warning', 'Para editar líneas, primero deja el documento en estado borrador.');
        }

        $cantidad = round((float) $validated['cantidad'], 4);
        $precioUnitario = round((float) $validated['precio_unitario'], 4);
        $montoItem = round($cantidad * $precioUnitario, 2);

        $updated = $db->table('gmail_dte_document_lines')
            ->where('id', $lineId)
            ->where('document_id', $id)
            ->update([
                'cantidad' => $cantidad,
                'precio_unitario' => $precioUnitario,
                'monto_item' => $montoItem,
                'updated_at' => now(),
            ]);

        if (!$updated) {
            return back()->with('warning', 'No se pudo actualizar la línea.');
        }

        $montoNetoNuevo = (float) $db->table('gmail_dte_document_lines')
            ->where('document_id', $id)
            ->sum('monto_item');

        $otrosImpuestos = max(0.0, (float) ($document->monto_total ?? 0) - (float) ($document->monto_neto ?? 0));
        $montoTotalNuevo = round($montoNetoNuevo + $otrosImpuestos, 2);

        $db->table('gmail_dte_documents')
            ->where('id', $id)
            ->update([
                'monto_neto' => $montoNetoNuevo,
                'monto_total' => $montoTotalNuevo,
                'updated_at' => now(),
            ]);

        return back()->with('success', 'Línea actualizada correctamente.');
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
        $products = DB::connection('fuelcontrol')
            ->table('gmail_inventory_products')
            ->select(['is_active', 'stock_actual', 'costo_promedio', 'unidad'])
            ->get();

        $totalProducts = $products->count();
        $totalActivos = $products->where('is_active', 1)->count();
        $totalInactivos = $products->where('is_active', 0)->count();
        $totalConStock = $products->filter(fn($p) => (float) ($p->stock_actual ?? 0) > 0)->count();
        $totalSinStock = $products->filter(fn($p) => (float) ($p->stock_actual ?? 0) <= 0)->count();
        $stockTotalUnidades = $products->sum(fn($p) => (float) ($p->stock_actual ?? 0));
        $valorInventario = $products->sum(function ($p) {
            return (float) ($p->stock_actual ?? 0) * (float) ($p->costo_promedio ?? 0);
        });

        $unidadResumen = $products
            ->groupBy(fn($p) => strtoupper(trim((string) ($p->unidad ?? 'SIN UNIDAD'))))
            ->map(fn($items, $unidad) => ['unidad' => $unidad, 'cantidad' => $items->count()])
            ->sortByDesc('cantidad')
            ->take(6)
            ->values();

        return view('gmail.inventory.index', compact(
            'totalProducts',
            'totalActivos',
            'totalInactivos',
            'totalConStock',
            'totalSinStock',
            'stockTotalUnidades',
            'valorInventario',
            'unidadResumen'
        ));
    }

    public function inventoryList(Request $request)
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

        return view('gmail.inventory.list', compact(
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
        $resolveImpAdicLabel = function (?string $code, ?string $fallback = null): string {
            $code = trim((string) $code);
            if ($code === '28') {
                return 'IEC Diesel';
            }
            if ($code === '35') {
                return 'IEC Gasolina 93';
            }
            if ($code === '52') {
                return 'IEC Gasolina 97';
            }

            if ($fallback && trim($fallback) !== '') {
                return trim(preg_replace('/^Imp\\. adic\\./i', 'Impuesto específico', $fallback));
            }

            return $code !== '' ? ('Impuesto específico ' . $code) : 'Impuesto específico';
        };

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
                    $label = $resolveImpAdicLabel((string) ($tax->codigo ?? ''), $label);
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
                        $impAdicCodes = collect($lines)
                            ->flatMap(function ($line) {
                                return collect($line->taxes ?? [])->filter(function ($tax) {
                                    return strtoupper((string) ($tax->tax_type ?? '')) === 'IMP_ADIC';
                                });
                            })
                            ->pluck('codigo')
                            ->filter(fn ($c) => trim((string) $c) !== '')
                            ->map(fn ($c) => trim((string) $c))
                            ->unique()
                            ->values();

                        $mntImpLabel = 'Impuesto específico';
                        if ($impAdicCodes->count() === 1) {
                            $mntImpLabel = $resolveImpAdicLabel((string) $impAdicCodes->first());
                        } elseif ($impAdicCodes->contains('28')) {
                            $mntImpLabel = 'Impuestos específicos (incluye IEC Diesel)';
                        }

                        $add('MntImp|' . $mntImpLabel, $mntImpLabel, $mntImp, true);
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
