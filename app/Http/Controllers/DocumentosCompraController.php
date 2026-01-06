<?php

namespace App\Http\Controllers;

use App\Services\ServicioPosteoDocumentoCompra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DocumentosCompraController extends Controller
{
    public function crear(Request $request)
    {
        $data = $request->validate([
            'proveedor_id' => ['required', 'integer', 'exists:proveedores,id'],
            'bodega_id' => ['required', 'integer', 'exists:bodegas,id'],
            'tipo_documento' => ['required', Rule::in(['FACTURA', 'GUIA'])],
            'numero_documento' => ['required', 'string', 'max:255'],
            'fecha_documento' => ['required', 'date'],
            'tasa_iva' => ['required', 'numeric'],
            'iva_recuperable' => ['required', 'boolean'],
            'precios_incluyen_iva' => ['required', 'boolean'],
            'documento_relacionado_id' => ['nullable', 'integer', 'exists:documentos_compra,id'],
            'notas' => ['nullable', 'string'],
        ]);

        $id = DB::table('documentos_compra')->insertGetId([
            'proveedor_id' => $data['proveedor_id'],
            'bodega_id' => $data['bodega_id'],
            'tipo_documento' => $data['tipo_documento'],
            'numero_documento' => $data['numero_documento'],
            'fecha_documento' => $data['fecha_documento'],
            'estado' => 'BORRADOR',
            'tasa_iva' => $data['tasa_iva'],
            'iva_recuperable' => $data['iva_recuperable'],
            'precios_incluyen_iva' => $data['precios_incluyen_iva'],
            'documento_relacionado_id' => $data['documento_relacionado_id'] ?? null,
            'notas' => $data['notas'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['id' => $id], 201);
    }

    public function agregarLinea(int $id, Request $request)
    {
        $doc = DB::table('documentos_compra')->where('id', $id)->first();
        if (!$doc)
            return response()->json(['message' => 'Documento no existe'], 404);
        if ($doc->estado !== 'BORRADOR')
            return response()->json(['message' => 'Documento no está en BORRADOR'], 409);

        $data = $request->validate([
            'producto_id' => ['required', 'integer', 'exists:productos,id'],
            'cantidad' => ['required', 'numeric', 'gt:0'],
            'unidad_medida_id' => ['required', 'integer', 'exists:unidades_medida,id'],
            'precio_unitario' => ['nullable', 'numeric', 'gte:0'], // guía puede ser null
            'descuento' => ['nullable', 'numeric', 'gte:0'],
            'codigo_lote' => ['nullable', 'string', 'max:255'],
            'vence_el' => ['nullable', 'date'],
        ]);

        DB::table('lineas_documento_compra')->insert([
            'documento_compra_id' => $id,
            'producto_id' => $data['producto_id'],
            'cantidad' => $data['cantidad'],
            'unidad_medida_id' => $data['unidad_medida_id'],
            'precio_unitario' => $data['precio_unitario'] ?? null,
            'descuento' => $data['descuento'] ?? 0,
            'codigo_lote' => $data['codigo_lote'] ?? null,
            'vence_el' => $data['vence_el'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true], 201);
    }

    public function contabilizar(int $id, ServicioPosteoDocumentoCompra $servicio)
    {
        try {
            $servicio->contabilizar($id);
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'No se pudo contabilizar',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function ver(int $id)
    {
        $doc = DB::table('documentos_compra')->where('id', $id)->first();
        if (!$doc)
            return response()->json(['message' => 'No existe'], 404);

        $lineas = DB::table('lineas_documento_compra')->where('documento_compra_id', $id)->get();

        return response()->json([
            'documento' => $doc,
            'lineas' => $lineas,
        ]);
    }
}
