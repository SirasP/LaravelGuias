<?php

namespace App\Http\Controllers;

use App\Services\ServicioPosteoDocumentoCompra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ComprasController extends Controller
{
    public function formCrear()
    {
        $proveedores = DB::table('proveedores')->orderBy('nombre')->get();
        $bodegas = DB::table('bodegas')->orderBy('nombre')->get();

        return view('compras.documento_crear', compact('proveedores', 'bodegas'));
    }

    public function crear(Request $request)
    {
        $data = $request->validate([
            'proveedor_id' => ['required', 'integer', 'exists:proveedores,id'],
            'bodega_id' => ['required', 'integer', 'exists:bodegas,id'],
            'tipo_documento' => ['required', Rule::in(['FACTURA', 'GUIA'])],
            'numero_documento' => ['required', 'string', 'max:255'],
            'fecha_documento' => ['required', 'date'],

            'tasa_iva' => ['required', 'numeric'],
            'iva_recuperable' => ['required'],
            'precios_incluyen_iva' => ['required'],

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
            'iva_recuperable' => filter_var($data['iva_recuperable'], FILTER_VALIDATE_BOOLEAN),
            'precios_incluyen_iva' => filter_var($data['precios_incluyen_iva'], FILTER_VALIDATE_BOOLEAN),

            'documento_relacionado_id' => $data['documento_relacionado_id'] ?? null,
            'notas' => $data['notas'] ?? null,

            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('compras.documentos.ver', $id)->with('ok', 'Documento creado. Ahora agrega líneas.');
    }

    public function ver(int $id)
    {
        $doc = DB::table('documentos_compra')->where('id', $id)->first();
        abort_if(!$doc, 404);

        $proveedor = DB::table('proveedores')->where('id', $doc->proveedor_id)->first();
        $bodega = DB::table('bodegas')->where('id', $doc->bodega_id)->first();

        $lineas = DB::table('lineas_documento_compra')->where('documento_compra_id', $id)->get();

        $productos = DB::table('productos')->where('activo', true)->orderBy('nombre')->get();
        $unidades = DB::table('unidades_medida')->orderBy('codigo')->get();

        return view('compras.documento_ver', compact('doc', 'proveedor', 'bodega', 'lineas', 'productos', 'unidades'));
    }

    public function agregarLinea(int $id, Request $request)
    {
        $doc = DB::table('documentos_compra')->where('id', $id)->first();
        abort_if(!$doc, 404);

        if ($doc->estado !== 'BORRADOR') {
            return back()->withErrors(['estado' => 'El documento no está en BORRADOR.']);
        }

        $data = $request->validate([
            'producto_id' => ['required', 'integer', 'exists:productos,id'],
            'cantidad' => ['required', 'numeric', 'gt:0'],
            'unidad_medida_id' => ['required', 'integer', 'exists:unidades_medida,id'],
            'precio_unitario' => ['nullable', 'numeric', 'gte:0'],
            'descuento' => ['nullable', 'numeric', 'gte:0'],
            'codigo_lote' => ['nullable', 'string', 'max:255'],
            'vence_el' => ['nullable', 'date'],
        ]);

        // Si es guía, permitir precio null. Si es factura, exigir precio.
        if ($doc->tipo_documento === 'FACTURA' && $data['precio_unitario'] === null) {
            return back()->withErrors(['precio_unitario' => 'En FACTURA el precio unitario es obligatorio.']);
        }

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

        return redirect()->route('compras.documentos.ver', $id)->with('ok', 'Línea agregada.');
    }

    public function contabilizar(int $id, ServicioPosteoDocumentoCompra $servicio)
    {
        try {
            $servicio->contabilizar($id);
            return redirect()->route('compras.documentos.ver', $id)->with('ok', 'Documento contabilizado. Inventario actualizado.');
        } catch (\Throwable $e) {
            return back()->withErrors(['contabilizar' => $e->getMessage()]);
        }
    }
}
