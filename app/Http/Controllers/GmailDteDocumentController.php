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

        return [$document, $lines];
    }
}
