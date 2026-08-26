<?php

namespace App\Http\Controllers;

use App\Models\OdooAccountMove;
use App\Models\OdooAccountMoveLine;
use App\Models\OdooAnalyticAccount;
use App\Services\FcmNotificationService;
use App\Services\GmailDteInventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class GmailDteDocumentController extends Controller
{
    private const FACTURA_TYPES = [33, 34, 56, 61];

    private const BOLETA_TYPES = [39, 41];

    private const GUIA_TYPES = [52];

    private const EXCLUDED_WORKFLOW_STATUSES = ['anulado', 'rechazado'];

    private const BODEGA_TALLER_MECANICO = 2;

    public function index()
    {
        [$summaryFacturas, $agingFacturas] = $this->buildSummaryByTypes(self::FACTURA_TYPES);
        [$summaryBoletas, $agingBoletas] = $this->buildSummaryByTypes(self::BOLETA_TYPES);
        [$summaryGuias, $agingGuias] = $this->buildSummaryByTypes(self::GUIA_TYPES);

        return view('gmail.dtes.index', compact(
            'summaryFacturas',
            'agingFacturas',
            'summaryBoletas',
            'agingBoletas',
            'summaryGuias',
            'agingGuias'
        ));
    }

    public function list(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $tipo = trim((string) $request->query('tipo', 'facturas'));

        return match ($tipo) {
            'boletas' => redirect()->route('gmail.dtes.boletas.list', array_filter(['q' => $q])),
            'guias' => redirect()->route('gmail.dtes.guias.list', array_filter(['q' => $q])),
            default => redirect()->route('gmail.dtes.facturas.list', array_filter(['q' => $q])),
        };
    }

    public function facturasIndex()
    {
        return redirect()->route('gmail.dtes.index');
    }

    public function boletasIndex()
    {
        return redirect()->route('gmail.dtes.index');
    }

    public function guiasIndex()
    {
        return redirect()->route('gmail.dtes.index');
    }

    public function facturasList(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $tipo = 'facturas';
        $estado = (string) $request->query('estado', 'todos');

        if (! in_array($estado, ['todos', 'sinpagar', 'pendiente', 'pagado'], true)) {
            $estado = 'todos';
        }

        $documentsQuery = $this->buildDocumentsListQuery($q, self::FACTURA_TYPES);

        if ($estado === 'pagado') {
            $documentsQuery->where('payment_status', 'pagado');
        } elseif ($estado === 'sinpagar') {
            $documentsQuery
                ->where(function ($query) {
                    $query->whereNull('payment_status')
                        ->orWhere('payment_status', '<>', 'pagado');
                })
                ->whereNotNull('fecha_vencimiento')
                ->whereDate('fecha_vencimiento', '<=', today()->toDateString());
        } elseif ($estado === 'pendiente') {
            $documentsQuery
                ->where(function ($query) {
                    $query->whereNull('payment_status')
                        ->orWhere('payment_status', '<>', 'pagado');
                })
                ->where(function ($query) {
                    $query->whereNull('fecha_vencimiento')
                        ->orWhereDate('fecha_vencimiento', '>', today()->toDateString());
                });
        }

        $documents = $documentsQuery
            ->orderByDesc('fecha_factura')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $canSeeValues = auth()->user()?->canSeeValues() ?? true;

        return view('gmail.dtes.facturas.list', compact('documents', 'q', 'tipo', 'estado', 'canSeeValues'));
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

        $canSeeValues = auth()->user()?->canSeeValues() ?? true;

        return view('gmail.dtes.boletas.list', compact('documents', 'q', 'tipo', 'canSeeValues'));
    }

    public function guiasList(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $tipo = 'guias';
        $documents = $this->buildDocumentsListQuery($q, self::GUIA_TYPES)
            ->orderByDesc('fecha_factura')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $canSeeValues = auth()->user()?->canSeeValues() ?? true;

        return view('gmail.dtes.list', compact('documents', 'q', 'tipo', 'canSeeValues'));
    }

    private function buildSummaryByTypes(array $types): array
    {
        $docsQuery = DB::connection('fuelcontrol')
            ->table('gmail_dte_documents')
            ->select(['tipo_dte', 'payment_status', 'workflow_status', 'fecha_vencimiento', 'monto_neto', 'monto_iva', 'monto_total'])
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
            // Tablero con IVA: usa monto_total; si viene vacío, cae a neto + IVA.
            $grossWithIva = (float) ($doc->monto_total ?? 0);
            if ($grossWithIva <= 0) {
                $grossWithIva = (float) ($doc->monto_neto ?? 0) + (float) ($doc->monto_iva ?? 0);
            }
            $montoConIva = $grossWithIva * $sign;
            $isPaid = (string) ($doc->payment_status ?? 'sin_pagar') === 'pagado';
            $isDraft = (string) ($doc->workflow_status ?? 'aceptado') === 'borrador';
            $saldoPendienteRaw = property_exists($doc, 'saldo_pendiente')
                ? (float) ($doc->saldo_pendiente ?? 0)
                : ($isPaid ? 0.0 : $grossWithIva);
            $saldoPendiente = $saldoPendienteRaw * $sign;

            $summary['total_docs']++;

            if ($isDraft) {
                $summary['por_validar_count']++;
                $summary['por_validar_monto'] += $montoConIva;
            }

            if (! $isPaid) {
                $venc = $doc->fecha_vencimiento ? \Carbon\Carbon::parse($doc->fecha_vencimiento)->startOfDay() : null;
                if (! $venc) {
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

    /**
     * Encuentra la factura contable de Odoo que corresponde a un DTE de proveedor.
     *
     * Los folios de proveedores distintos pueden coincidir, por lo que el folio por
     * sí solo no es una clave suficiente para relacionar los apuntes contables.
     */
    private function findAccountingMoveForDocument(object $document, string $folio): ?OdooAccountMove
    {
        $folioInt = (int) $folio;

        $candidates = OdooAccountMove::query()
            ->where(function ($query) use ($folioInt, $folio) {
                $query->where('folio', $folioInt)
                    ->orWhere('ref', $folio);
            })
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        $providerRut = $this->normalizeRut($document->proveedor_rut ?? null);

        if ($providerRut !== '') {
            $rutMatches = $candidates->filter(fn (OdooAccountMove $candidate) => $this->normalizeRut($candidate->partner_vat) === $providerRut
            )->values();

            if ($rutMatches->count() === 1) {
                return $rutMatches->first();
            }

            if ($rutMatches->isNotEmpty()) {
                $candidates = $rutMatches;
            }
        }

        $invoiceDate = (string) ($document->fecha_factura ?? '');
        $amountTotal = (float) ($document->monto_total ?? 0);

        $dateAndAmountMatches = $candidates->filter(function (OdooAccountMove $candidate) use ($invoiceDate, $amountTotal) {
            $sameDate = $invoiceDate !== '' && (string) $candidate->invoice_date === $invoiceDate;
            $sameAmount = abs((float) $candidate->amount_total - $amountTotal) < 0.01;

            return $sameDate && $sameAmount;
        })->values();

        if ($dateAndAmountMatches->count() === 1) {
            return $dateAndAmountMatches->first();
        }

        return $candidates->count() === 1 ? $candidates->first() : null;
    }

    private function normalizeRut(?string $rut): string
    {
        return strtoupper((string) preg_replace('/[^0-9k]/i', '', (string) $rut));
    }

    public function show(int $id)
    {
        [$document, $lines] = $this->getDocumentWithLines($id);

        $canSeeValues = auth()->user()?->canSeeValues() ?? true;

        $bodegas = DB::table('bodegas')
            ->orderByDesc('es_principal')
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre', 'es_principal']);

        return view('gmail.dtes.show', compact('document', 'lines', 'canSeeValues', 'bodegas'));
    }

    public function print(int $id)
    {
        [$document, $lines] = $this->getDocumentWithLines($id);

        return view('gmail.dtes.print', compact('document', 'lines'));
    }

    /**
     * API: retorna los apuntes contables del DTE leyendo desde MySQL local.
     * Los datos fueron sincronizados previamente desde Odoo con el comando odoo:sync-moves.
     */
    public function apuntesContables(int $id)
    {
        $document = DB::connection('fuelcontrol')
            ->table('gmail_dte_documents')
            ->where('id', $id)
            ->firstOrFail();

        $folio = trim((string) ($document->folio ?? ''));

        if ($folio === '') {
            return response()->json(['found' => false, 'lines' => [], 'move' => null,
                'message' => 'El documento no tiene folio registrado.']);
        }

        // Un folio no es único entre proveedores. Primero se buscan los candidatos por
        // folio y luego se identifica la factura por RUT; si el DTE no tiene RUT, se
        // exige una coincidencia única por fecha y total. Nunca se debe tomar el
        // primer resultado, porque podría pertenecer a otro proveedor.
        $move = $this->findAccountingMoveForDocument($document, $folio);

        if (! $move) {
            return response()->json(['found' => false, 'lines' => [], 'move' => null,
                'message' => "No se encontró una factura única para el folio «{$folio}» en la base de datos local. Verifica el proveedor, la fecha y el total en Odoo."]);
        }

        $rawLines = OdooAccountMoveLine::where('move_odoo_id', $move->odoo_id)
            ->orderBy('id')
            ->get();

        // Resolver nombres de cuentas analíticas
        // La clave en analytic_distribution puede ser "148,65" (varios account IDs separados por coma)
        // o "1" (un solo account ID). Cada parte es un odoo_id de OdooAnalyticAccount.
        $analyticIds = $rawLines
            ->whereNotNull('analytic_distribution')
            ->flatMap(fn ($l) => collect(array_keys($l->analytic_distribution ?? []))
                ->flatMap(fn ($k) => array_map('intval', explode(',', $k)))
            )->unique()->values()->toArray();

        $analyticNames = OdooAnalyticAccount::whereIn('odoo_id', $analyticIds)
            ->get(['odoo_id', 'name_es', 'name', 'plan_name'])
            ->keyBy('odoo_id');

        $isDraft = (string) ($document->workflow_status ?? '') === 'borrador';

        $lines = $rawLines->map(function ($l) use ($analyticNames, $isDraft) {
            // Distribución analítica: expandir cada clave "148,65" en múltiples badges
            $analytic = collect($l->analytic_distribution ?? [])
                ->flatMap(function ($pct, $key) use ($analyticNames) {
                    // Cada parte de la clave es un account_odoo_id independiente
                    return collect(array_map('intval', explode(',', $key)))
                        ->map(function ($accId) use ($pct, $analyticNames) {
                            $acc = $analyticNames->get($accId);
                            $name = $acc ? ($acc->name_es ?: $acc->name) : null;
                            $plan = $acc ? $acc->plan_name : null;

                            return $name ? ['id' => $accId, 'name' => $name, 'plan' => $plan, 'pct' => (int) $pct] : null;
                        })
                        ->filter();
                })
                ->values()
                ->toArray();

            return [
                'line_id' => $l->id,
                'account_odoo_id' => $l->account_odoo_id,
                'account_code' => $l->account_code,
                'account_name_es' => $l->account_name_es ?: $l->account_name,
                'account_name_en' => $l->account_name,
                'description' => $l->name ?: '—',
                'debit' => (float) $l->debit,
                'credit' => (float) $l->credit,
                'analytic' => $analytic,
                'taxes' => $l->taxes ?? [],
                'editable' => $isDraft,
            ];
        })->values();

        return response()->json([
            'found' => true,
            'move' => [
                'name' => $move->name,
                'ref' => $move->ref,
                'state' => $move->state,
                'partner' => $move->partner_name,
                'invoice_date' => $move->invoice_date,
                'amount_total' => (float) $move->amount_total,
            ],
            'lines' => $lines,
            'is_draft' => $isDraft,
        ]);
    }

    /**
     * API: catálogos para el modal de edición (cuentas contables + analíticas).
     */
    public function apuntesEditCatalog()
    {
        $accounts = \App\Models\OdooAccount::orderBy('code')
            ->get(['odoo_id', 'code', 'name_es', 'name', 'account_type'])
            ->map(fn ($a) => [
                'odoo_id' => $a->odoo_id,
                'code' => $a->code,
                'label' => $a->code.' '.($a->name_es ?: $a->name),
            ]);

        $analytic = OdooAnalyticAccount::orderBy('plan_complete_name')->orderBy('name_es')
            ->get(['odoo_id', 'name_es', 'name', 'plan_name', 'plan_complete_name'])
            ->map(fn ($a) => [
                'odoo_id' => $a->odoo_id,
                'name' => $a->name_es ?: $a->name,
                'plan_name' => $a->plan_name,
            ]);

        return response()->json(compact('accounts', 'analytic'));
    }

    /**
     * API: actualizar cuenta contable y/o distribución analítica de una línea (solo en borrador).
     */
    public function updateApunte(Request $request, int $id, int $lineId)
    {
        $document = DB::connection('fuelcontrol')
            ->table('gmail_dte_documents')
            ->where('id', $id)
            ->firstOrFail();

        if ((string) ($document->workflow_status ?? '') !== 'borrador') {
            return response()->json(['error' => 'El documento debe estar en borrador para editar apuntes.'], 403);
        }

        $validated = $request->validate([
            'account_odoo_id' => 'nullable|integer|exists:odoo_accounts,odoo_id',
            'analytic_distribution' => 'nullable|array',
        ]);

        $line = OdooAccountMoveLine::findOrFail($lineId);

        $updates = [];

        if (array_key_exists('account_odoo_id', $validated) && $validated['account_odoo_id']) {
            $acc = \App\Models\OdooAccount::where('odoo_id', $validated['account_odoo_id'])->first();
            $updates['account_odoo_id'] = $acc->odoo_id;
            $updates['account_code'] = $acc->code;
            $updates['account_name'] = $acc->name;
            $updates['account_name_es'] = $acc->name_es ?: $acc->name;
        }

        if (array_key_exists('analytic_distribution', $validated)) {
            $updates['analytic_distribution'] = $validated['analytic_distribution'] ?: null;
        }

        if (! empty($updates)) {
            $line->update($updates);
        }

        return response()->json(['ok' => true]);
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
        if (! $document) {
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

        if (! $updated) {
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

    public function addToStock(Request $request, int $id, GmailDteInventoryService $inventoryService, FcmNotificationService $fcm)
    {
        $bodegaId = $request->input('bodega_id') ? (int) $request->input('bodega_id') : null;

        $result = $inventoryService->addDocumentToStock($id, auth()->id(), [], true, [], $bodegaId);

        if ($result['already_posted']) {
            return back()->with('warning', 'Este documento ya fue ingresado a inventario.');
        }

        $this->notifyMantencionIfTaller($fcm, $bodegaId, $result['movement_id'], $id);

        return back()->with('success', "Inventario actualizado. Movimiento #{$result['movement_id']}.");
    }

    public function rollbackStock(int $id, GmailDteInventoryService $inventoryService)
    {
        try {
            $result = $inventoryService->rollbackDocumentStock($id);
        } catch (\RuntimeException $e) {
            return back()->with('warning', $e->getMessage());
        }

        return back()->with('success', "Ingreso de stock anulado (movimiento #{$result['movement_id']}).");
    }

    public function reviewStockMatching(int $id, GmailDteInventoryService $inventoryService)
    {
        $result = $inventoryService->reviewDocumentStockMatching($id);

        return response()->json([
            'ok' => true,
            'already_posted' => (bool) ($result['already_posted'] ?? false),
            'movement_id' => $result['movement_id'] ?? null,
            'unresolved' => $result['unresolved'] ?? [],
        ]);
    }

    public function stockProductsApi(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $limit = min(300, max(20, (int) $request->query('limit', 150)));

        $products = DB::connection('fuelcontrol')
            ->table('gmail_inventory_products')
            ->where('is_active', 1)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('nombre', 'like', "%{$q}%")
                        ->orWhere('codigo', 'like', "%{$q}%");
                });
            })
            ->orderBy('nombre')
            ->limit($limit)
            ->get(['id', 'nombre', 'codigo', 'unidad', 'stock_actual']);

        return response()->json($products);
    }

    public function addToStockWithMapping(Request $request, int $id, GmailDteInventoryService $inventoryService)
    {
        $validated = $request->validate([
            'mappings' => ['required', 'array', 'min:1'],
            'mappings.*.line_id' => ['required', 'integer'],
            'mappings.*.product_id' => ['required', 'integer'],
            'skipped_lines' => ['nullable', 'array'],
            'skipped_lines.*' => ['integer'],
            'bodega_id' => ['nullable', 'integer'],
        ]);

        $lineProductMap = [];
        foreach ($validated['mappings'] as $row) {
            $lineId = (int) $row['line_id'];
            $productId = (int) $row['product_id'];
            if ($lineId > 0 && $productId > 0) {
                $lineProductMap[$lineId] = $productId;
            }
        }

        $skipLineIds = array_map('intval', $validated['skipped_lines'] ?? []);
        $bodegaId = isset($validated['bodega_id']) ? (int) $validated['bodega_id'] : null;

        $result = $inventoryService->addDocumentToStock($id, auth()->id(), $lineProductMap, true, $skipLineIds, $bodegaId);

        if ($result['already_posted']) {
            return back()->with('warning', 'Este documento ya fue ingresado a inventario.');
        }

        $this->notifyMantencionIfTaller(app(FcmNotificationService::class), $bodegaId, $result['movement_id'], $id);

        return back()->with('success', "Inventario actualizado. Movimiento #{$result['movement_id']}.");
    }

    /**
     * Envía push FCM a la app de mantención si el ingreso fue a Taller Mecánico.
     */
    private function notifyMantencionIfTaller(FcmNotificationService $fcm, ?int $bodegaId, int $movementId, int $documentId): void
    {
        if ($bodegaId !== self::BODEGA_TALLER_MECANICO) {
            return;
        }

        try {
            $fcm->send(
                appType: 'mantencion',
                title: '📦 Stock actualizado — Taller Mecánico',
                body: 'Se ingresó nuevo stock. Toca para ver el inventario actualizado.',
                data: [
                    'tipo' => 'stock_ingreso',
                    'bodega_id' => (string) $bodegaId,
                    'movement_id' => (string) $movementId,
                    'document_id' => (string) $documentId,
                    'timestamp' => now()->toIso8601String(),
                ],
            );
        } catch (\Throwable $e) {
            Log::warning('[FCM Mantención] Error enviando push: '.$e->getMessage());
        }
    }

    public function inventoryIndex(Request $request)
    {
        $bodegaId = $request->query('bodega_id') !== null
            ? (int) $request->query('bodega_id')
            : null;

        $bodegas = DB::table('bodegas')->orderByDesc('es_principal')->orderBy('nombre')->get();

        if ($bodegaId !== null) {
            // Todos los productos con cualquier lote en esa bodega (con o sin stock)
            $productIds = DB::connection('fuelcontrol')
                ->table('gmail_inventory_lots')
                ->where('bodega_id', $bodegaId)
                ->distinct()->pluck('product_id');

            $products = DB::connection('fuelcontrol')
                ->table('gmail_inventory_products')
                ->whereIn('id', $productIds)
                ->select(['id', 'is_active', 'costo_promedio', 'unidad'])
                ->get();

            // Stock disponible: solo lotes ABIERTOS
            $stockPorProducto = DB::connection('fuelcontrol')
                ->table('gmail_inventory_lots')
                ->where('bodega_id', $bodegaId)
                ->where('estado', 'ABIERTO')
                ->selectRaw('product_id, SUM(cantidad_disponible) as stock')
                ->groupBy('product_id')
                ->pluck('stock', 'product_id');

            $totalProducts = $products->count();
            $totalActivos = $products->where('is_active', 1)->count();
            $totalInactivos = $products->where('is_active', 0)->count();
            $totalConStock = $stockPorProducto->filter(fn ($s) => (float) $s > 0)->count();
            $totalSinStock = max(0, $totalProducts - $totalConStock);
            $stockTotalUnidades = $stockPorProducto->sum(fn ($s) => (float) $s);
            $valorInventario = $products->sum(function ($p) use ($stockPorProducto) {
                return (float) ($stockPorProducto->get($p->id) ?? 0) * (float) ($p->costo_promedio ?? 0);
            });

            $unidadResumen = $products
                ->groupBy(fn ($p) => strtoupper(trim((string) ($p->unidad ?? 'SIN UNIDAD'))))
                ->map(fn ($items, $unidad) => ['unidad' => $unidad, 'cantidad' => $items->count()])
                ->sortByDesc('cantidad')
                ->take(6)
                ->values();
        } else {
            // Stats globales
            $products = DB::connection('fuelcontrol')
                ->table('gmail_inventory_products')
                ->select(['is_active', 'stock_actual', 'costo_promedio', 'unidad'])
                ->get();

            $totalProducts = $products->count();
            $totalActivos = $products->where('is_active', 1)->count();
            $totalInactivos = $products->where('is_active', 0)->count();
            $totalConStock = $products->filter(fn ($p) => (float) ($p->stock_actual ?? 0) > 0)->count();
            $totalSinStock = $products->filter(fn ($p) => (float) ($p->stock_actual ?? 0) <= 0)->count();
            $stockTotalUnidades = $products->sum(fn ($p) => (float) ($p->stock_actual ?? 0));
            $valorInventario = $products->sum(function ($p) {
                return (float) ($p->stock_actual ?? 0) * (float) ($p->costo_promedio ?? 0);
            });

            $unidadResumen = $products
                ->groupBy(fn ($p) => strtoupper(trim((string) ($p->unidad ?? 'SIN UNIDAD'))))
                ->map(fn ($items, $unidad) => ['unidad' => $unidad, 'cantidad' => $items->count()])
                ->sortByDesc('cantidad')
                ->take(6)
                ->values();
        }

        return view('gmail.inventory.index', compact(
            'totalProducts', 'totalActivos', 'totalInactivos',
            'totalConStock', 'totalSinStock', 'stockTotalUnidades',
            'valorInventario', 'unidadResumen', 'bodegas', 'bodegaId'
        ));
    }

    public function inventoryList(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $estado = trim((string) $request->query('estado', ''));
        $stock = trim((string) $request->query('stock', ''));
        $bodegaId = $request->query('bodega_id') !== null
            ? (int) $request->query('bodega_id')
            : null;   // null = todas las bodegas

        // Bodegas disponibles (DB principal)
        $bodegas = DB::table('bodegas')->orderByDesc('es_principal')->orderBy('nombre')->get();
        $bodegaNames = $bodegas->pluck('nombre', 'id');

        // Cantidad de productos con stock en cada bodega (para los chips)
        $productosPorBodega = DB::connection('fuelcontrol')
            ->table('gmail_inventory_lots')
            ->where('estado', 'ABIERTO')
            ->where('cantidad_disponible', '>', 0)
            ->whereNotNull('bodega_id')
            ->selectRaw('bodega_id, COUNT(DISTINCT product_id) as total_productos')
            ->groupBy('bodega_id')
            ->pluck('total_productos', 'bodega_id');

        // Query base de productos
        $baseQuery = DB::connection('fuelcontrol')
            ->table('gmail_inventory_products')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('nombre', 'like', "%{$q}%")
                        ->orWhere('codigo', 'like', "%{$q}%")
                        ->orWhere('unidad', 'like', "%{$q}%");
                });
            })
            ->when($estado === 'activos', fn ($query) => $query->where('is_active', 1))
            ->when($estado === 'inactivos', fn ($query) => $query->where('is_active', 0))
            // Filtro por bodega: productos que tienen CUALQUIER lote en esa bodega (con o sin stock)
            ->when($bodegaId !== null, function ($query) use ($bodegaId) {
                $query->whereExists(function ($sub) use ($bodegaId) {
                    $sub->selectRaw('1')
                        ->from('gmail_inventory_lots')
                        ->whereColumn('product_id', 'gmail_inventory_products.id')
                        ->where('bodega_id', $bodegaId);
                });
            });

        // Filtros de stock se aplican sobre el stock de la bodega seleccionada o global
        if ($bodegaId !== null) {
            // Stock en bodega: subquery de suma por bodega
            $baseQuery = $baseQuery
                ->when($stock === 'con_stock', function ($query) use ($bodegaId) {
                    $query->whereExists(function ($sub) use ($bodegaId) {
                        $sub->selectRaw('1')->from('gmail_inventory_lots')
                            ->whereColumn('product_id', 'gmail_inventory_products.id')
                            ->where('bodega_id', $bodegaId)->where('estado', 'ABIERTO')
                            ->where('cantidad_disponible', '>', 0);
                    });
                })
                ->when($stock === 'sin_stock', function ($query) use ($bodegaId) {
                    $query->whereNotExists(function ($sub) use ($bodegaId) {
                        $sub->selectRaw('1')->from('gmail_inventory_lots')
                            ->whereColumn('product_id', 'gmail_inventory_products.id')
                            ->where('bodega_id', $bodegaId)->where('estado', 'ABIERTO')
                            ->where('cantidad_disponible', '>', 0);
                    });
                });
        } else {
            $baseQuery = $baseQuery
                ->when($stock === 'con_stock', fn ($q) => $q->where('stock_actual', '>', 0))
                ->when($stock === 'sin_stock', fn ($q) => $q->where('stock_actual', '<=', 0))
                ->when($stock === 'bajo_minimo', fn ($q) => $q->whereNotNull('stock_minimo')->whereRaw('stock_actual < stock_minimo'));
        }

        $products = (clone $baseQuery)->orderBy('nombre')->paginate(30)->withQueryString();

        // KPI chips — aplicar filtro de bodega si está seleccionada
        $kpiBase = DB::connection('fuelcontrol')->table('gmail_inventory_products');
        if ($bodegaId !== null) {
            // Todos los productos con cualquier lote en esa bodega
            $kpiBase = $kpiBase->whereExists(function ($sub) use ($bodegaId) {
                $sub->selectRaw('1')
                    ->from('gmail_inventory_lots')
                    ->whereColumn('product_id', 'gmail_inventory_products.id')
                    ->where('bodega_id', $bodegaId);
            });
            $totalActivos = (clone $kpiBase)->where('is_active', 1)->count();
            $totalInactivos = (clone $kpiBase)->where('is_active', 0)->count();
            // Con stock = tiene lote ABIERTO con cantidad > 0 en esa bodega
            $totalConStock = (clone $kpiBase)->whereExists(function ($sub) use ($bodegaId) {
                $sub->selectRaw('1')->from('gmail_inventory_lots')
                    ->whereColumn('product_id', 'gmail_inventory_products.id')
                    ->where('bodega_id', $bodegaId)
                    ->where('estado', 'ABIERTO')
                    ->where('cantidad_disponible', '>', 0);
            })->count();
            // Sin stock = tiene lotes en bodega pero ninguno abierto con cantidad > 0
            $totalSinStock = (clone $kpiBase)->whereNotExists(function ($sub) use ($bodegaId) {
                $sub->selectRaw('1')->from('gmail_inventory_lots')
                    ->whereColumn('product_id', 'gmail_inventory_products.id')
                    ->where('bodega_id', $bodegaId)
                    ->where('estado', 'ABIERTO')
                    ->where('cantidad_disponible', '>', 0);
            })->count();
            $totalBajoMinimo = 0;
        } else {
            $totalActivos = (clone $kpiBase)->where('is_active', 1)->count();
            $totalInactivos = (clone $kpiBase)->where('is_active', 0)->count();
            $totalConStock = (clone $kpiBase)->where('stock_actual', '>', 0)->count();
            $totalSinStock = (clone $kpiBase)->where('stock_actual', '<=', 0)->count();
            $totalBajoMinimo = (clone $kpiBase)->whereNotNull('stock_minimo')->whereRaw('stock_actual < stock_minimo')->count();
        }

        // Stock por bodega para los productos visibles en esta página
        $productIds = $products->pluck('id')->all();
        $stockPorBodega = DB::connection('fuelcontrol')
            ->table('gmail_inventory_lots')
            ->whereIn('product_id', $productIds)
            ->where('estado', 'ABIERTO')
            ->whereNotNull('bodega_id')
            ->selectRaw('product_id, bodega_id, SUM(cantidad_disponible) as total')
            ->groupBy('product_id', 'bodega_id')
            ->get()
            ->groupBy('product_id');

        return view('gmail.inventory.list', compact(
            'products', 'q', 'estado', 'stock', 'bodegaId',
            'totalActivos', 'totalInactivos', 'totalConStock', 'totalSinStock', 'totalBajoMinimo',
            'stockPorBodega', 'bodegaNames', 'bodegas', 'productosPorBodega'
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

            return $code !== '' ? ('Impuesto específico '.$code) : 'Impuesto específico';
        };

        $add = function (string $key, string $label, ?float $monto, bool $informado = true) use (&$summary): void {
            if (! isset($summary[$key])) {
                $summary[$key] = [
                    'label' => $label,
                    'monto' => 0.0,
                    'informado' => false,
                ];
            }

            if (! is_null($monto)) {
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
                $label = trim((string) ($tax->descripcion ?? '')) ?: ('Impuesto '.($tax->codigo ?? ''));
                if ($type === 'IMP_ADIC') {
                    $label = $resolveImpAdicLabel((string) ($tax->codigo ?? ''), $label);
                }
                $monto = is_null($tax->monto) ? null : (float) $tax->monto;
                $add($type.'|'.$label, $label, $monto, ! is_null($monto));
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
                        $summary['IVA']['label'] = 'IVA '.rtrim(rtrim($tasaIva, '0'), '.').'%';
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

                        $add('MntImp|'.$mntImpLabel, $mntImpLabel, $mntImp, true);
                    }

                    if (isset($tot->ImptoReten)) {
                        foreach ($tot->ImptoReten as $ret) {
                            $tipo = trim((string) ($ret->TipoImp ?? ''));
                            $tasa = trim((string) ($ret->TasaImp ?? ''));
                            $monto = is_numeric((string) ($ret->MontoImp ?? null)) ? (float) $ret->MontoImp : null;

                            $label = 'Impuesto retenido';
                            if ($tipo !== '') {
                                $label .= ' '.$tipo;
                            }
                            if ($tasa !== '') {
                                $label .= ' ('.rtrim(rtrim($tasa, '0'), '.').'%)';
                            }

                            $add('RET|'.$label, $label, $monto, ! is_null($monto));
                        }
                    }

                    foreach ($tot->children() as $child) {
                        $name = $child->getName();
                        if (! str_starts_with($name, 'Mnt')) {
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
                        $add('TOT|'.$name, $label, $value, true);
                    }
                }
            }
            libxml_clear_errors();
        }

        return array_values($summary);
    }
}
