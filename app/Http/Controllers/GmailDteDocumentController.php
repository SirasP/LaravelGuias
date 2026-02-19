<?php

namespace App\Http\Controllers;

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

        return view('gmail.dtes.show', compact('document', 'lines'));
    }
}

