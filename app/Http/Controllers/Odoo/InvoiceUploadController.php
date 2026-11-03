<?php

namespace App\Http\Controllers\Odoo;

use App\Http\Controllers\Controller;
use App\Jobs\ReadSupplierInvoice;
use App\Models\SupplierInvoiceUpload;
use App\Services\SupplierInvoices\InvoiceToOdoo;
use App\Support\Rut;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * «Subir factura»: las facturas que no llegaron a Odoo por el SII. Se sube el
 * PDF o la foto, la IA local la lee, una persona la revisa y se crea en Odoo
 * como borrador.
 */
class InvoiceUploadController extends Controller
{
    public function index(InvoiceToOdoo $odoo): View
    {
        return view('odoo.invoice_uploads.index', [
            'facturas' => SupplierInvoiceUpload::query()->latest()->limit(60)->get(),
            'destino' => $odoo->destino(),
            'lectorEncendido' => (bool) config('purchase_requests.reader.enabled'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'documento' => ['required', 'file', 'max:15360', 'mimes:pdf,jpg,jpeg,png'],
        ], [
            'documento.required' => 'Elige el PDF o la foto de la factura.',
            'documento.mimes' => 'Sólo PDF, JPG o PNG.',
            'documento.max' => 'El archivo pesa más de 15 MB.',
        ]);

        $archivo = $request->file('documento');
        $sha = hash_file('sha256', $archivo->getRealPath());

        // El mismo archivo dos veces es la misma factura: se abre la que ya está.
        if ($ya = SupplierInvoiceUpload::query()->where('sha256', $sha)->first()) {
            return to_route('odoo.invoice_uploads.show', $ya)->with('success', 'Ese archivo ya se había subido: es esta.');
        }

        $factura = SupplierInvoiceUpload::query()->create([
            'user_id' => $request->user()->getKey(),
            'uploader_name_snapshot' => $request->user()->name,
            'disk' => 'local',
            'path' => $archivo->storeAs('facturas-subidas', Str::ulid().'.'.strtolower($archivo->getClientOriginalExtension() ?: 'pdf'), 'local'),
            'original_name' => $archivo->getClientOriginalName(),
            'mime_type' => (string) $archivo->getMimeType(),
            'size' => (int) $archivo->getSize(),
            'sha256' => $sha,
            'status' => SupplierInvoiceUpload::QUEUED,
        ]);

        ReadSupplierInvoice::dispatch($factura);

        return to_route('odoo.invoice_uploads.show', $factura);
    }

    public function show(SupplierInvoiceUpload $factura, InvoiceToOdoo $odoo): View
    {
        $factura->refresh();

        return view('odoo.invoice_uploads.show', [
            'factura' => $factura,
            'revision' => $factura->isEditable() && $factura->data !== null ? $odoo->revisar($factura) : null,
            'destino' => $odoo->destino(),
        ]);
    }

    /** Guarda lo corregido y, si se pidió, la crea en Odoo. */
    public function update(Request $request, SupplierInvoiceUpload $factura, InvoiceToOdoo $odoo): RedirectResponse
    {
        abort_unless($factura->isEditable(), 409, 'Esta factura ya no se puede cambiar.');

        $datos = $request->validate([
            'tipo' => ['required', 'in:'.implode(',', array_keys(SupplierInvoiceUpload::TIPOS))],
            'rut' => ['nullable', 'string', 'max:20'],
            'razon_social' => ['nullable', 'string', 'max:200'],
            'folio' => ['nullable', 'regex:/^\d{1,10}$/'],
            'fecha' => ['nullable', 'date_format:Y-m-d'],
            'vencimiento' => ['nullable', 'date_format:Y-m-d'],
            'neto' => ['nullable', 'numeric', 'min:0'],
            'exento' => ['nullable', 'numeric', 'min:0'],
            'iva' => ['nullable', 'numeric', 'min:0'],
            'total' => ['nullable', 'numeric', 'min:0'],
            'lineas' => ['array', 'max:200'],
            'lineas.*.detalle' => ['nullable', 'string', 'max:500'],
            'lineas.*.cantidad' => ['nullable', 'numeric', 'min:0'],
            'lineas.*.precio' => ['nullable', 'numeric'],
            'accion' => ['nullable', 'in:guardar,crear'],
        ], ['folio.regex' => 'El folio son sólo números.']);

        $factura->forceFill(['data' => [
            'tipo' => $datos['tipo'],
            'rut' => Rut::normalize($datos['rut'] ?? null) ?? ($datos['rut'] ?? null),
            'razon_social' => $datos['razon_social'] ?? null,
            'folio' => filled($datos['folio'] ?? null) ? (ltrim($datos['folio'], '0') ?: '0') : null,
            'fecha' => $datos['fecha'] ?? null,
            'vencimiento' => $datos['vencimiento'] ?? null,
            ...collect(['neto', 'exento', 'iva', 'total'])->mapWithKeys(fn (string $k): array => [$k => isset($datos[$k]) ? (float) $datos[$k] : null])->all(),
            'lineas' => collect($datos['lineas'] ?? [])
                ->filter(fn (array $l): bool => filled($l['detalle'] ?? null))
                ->map(fn (array $l): array => [
                    'detalle' => trim((string) $l['detalle']),
                    'cantidad' => isset($l['cantidad']) ? (float) $l['cantidad'] : null,
                    'precio' => isset($l['precio']) ? (float) $l['precio'] : null,
                ])->values()->all(),
        ]])->save();

        if (($datos['accion'] ?? 'guardar') !== 'crear') {
            return to_route('odoo.invoice_uploads.show', $factura)->with('success', 'Cambios guardados.');
        }

        // Un solo clic crea una sola factura, aunque el botón se apriete dos veces.
        $tomada = SupplierInvoiceUpload::query()->whereKey($factura->getKey())
            ->whereIn('status', [SupplierInvoiceUpload::READ, SupplierInvoiceUpload::FAILED])
            ->update(['status' => SupplierInvoiceUpload::SENDING]);

        if ($tomada === 0) {
            return to_route('odoo.invoice_uploads.show', $factura)->with('error', 'Ya se está creando en Odoo.');
        }

        $estadoAntes = $factura->status;

        try {
            $creada = $odoo->crear($factura->refresh());
        } catch (Throwable $e) {
            $factura->forceFill(['status' => $estadoAntes])->save();

            return to_route('odoo.invoice_uploads.show', $factura)->with('error', 'No se creó en Odoo: '.$e->getMessage());
        }

        $factura->forceFill([
            'status' => SupplierInvoiceUpload::SENT,
            'odoo_move_id' => $creada['id'],
            'odoo_move_name' => $creada['name'],
            'odoo_amount_total' => $creada['total'],
            // En qué Odoo quedó: el enlace no puede depender de a dónde apunte
            // la configuración hoy. Las primeras se crearon en el de pruebas.
            'data' => [...$factura->data, 'odoo_url' => rtrim((string) config('supplier_invoices.odoo.url'), '/')],
            'sent_by_name' => $request->user()->name,
            'sent_at' => now(),
        ])->save();

        $total = (float) $factura->dato('total');
        $mensaje = sprintf('Creada en Odoo como borrador: %s.', $creada['name'] ?: '#'.$creada['id']);

        if (abs($creada['total'] - $total) > 2) {
            $mensaje .= sprintf(' Ojo: Odoo calculó $%s y la factura dice $%s. Revísala allá antes de confirmarla.',
                number_format($creada['total'], 0, ',', '.'), number_format($total, 0, ',', '.'));
        }

        return to_route('odoo.invoice_uploads.show', $factura)->with('success', $mensaje);
    }

    /** Vuelve a pasarla por la IA, por ejemplo cuando el modelo no estaba. */
    public function reread(SupplierInvoiceUpload $factura): RedirectResponse
    {
        abort_unless($factura->isEditable(), 409);

        $factura->forceFill(['status' => SupplierInvoiceUpload::QUEUED, 'error_message' => null])->save();
        ReadSupplierInvoice::dispatch($factura);

        return to_route('odoo.invoice_uploads.show', $factura);
    }

    public function file(SupplierInvoiceUpload $factura): StreamedResponse
    {
        abort_unless(Storage::disk($factura->disk)->exists($factura->path), 404);

        return Storage::disk($factura->disk)->response($factura->path, $factura->original_name, [
            'Content-Type' => $factura->mime_type,
        ]);
    }

    public function destroy(SupplierInvoiceUpload $factura): RedirectResponse
    {
        abort_if($factura->isSent(), 409, 'Ya está en Odoo: se borra allá.');

        Storage::disk($factura->disk)->delete($factura->path);
        $factura->delete();

        return to_route('odoo.invoice_uploads.index')->with('success', 'Se quitó la factura subida.');
    }
}
