<?php

namespace App\Http\Controllers;

use App\Jobs\ReadQuotationDocument;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestIngestion;
use App\Services\PurchaseRequests\Reading\QuotationReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Cotizaciones que manda el proveedor, para contrastarlas con la solicitud.
 *
 * Es la lectura de siempre apuntada a otra pregunta: en vez de «qué pidieron
 * aquí», responde «coincide esto con lo que pedimos». Por eso no crea ningún
 * borrador ni toca la solicitud: deja el documento leído y la comparación se
 * arma en pantalla, para que una persona mire las diferencias y decida.
 */
class PurchaseQuoteComparisonController extends Controller
{
    public function store(Request $request, PurchaseRequest $purchaseRequest, QuotationReader $reader): RedirectResponse
    {
        Gate::authorize('view', $purchaseRequest);

        if (! $reader->isEnabled()) {
            return back()->with('error', 'El asistente de lectura no está habilitado en este entorno.');
        }

        $data = $request->validate([
            'quote' => [
                'required', 'file', 'max:15360',
                'mimes:pdf,jpg,jpeg,png',
                'mimetypes:application/pdf,image/jpeg,image/png',
            ],
        ], [], ['quote' => 'la cotización']);

        $file = $data['quote'];
        $hash = hash_file('sha256', $file->getRealPath());

        // Leer cuesta minutos de CPU y un mismo archivo sólo puede tener una
        // fila: si ya se leyó, se aprovecha esa lectura en vez de rechazarla.
        $previo = PurchaseRequestIngestion::query()
            ->where('company_code', 'EHE')
            ->where('sha256', $hash)
            ->first();

        if ($previo !== null) {
            return $this->reutilizar($previo, $purchaseRequest);
        }

        $path = $file->storeAs(
            'purchase-requests/ingestions/'.$request->user()->getKey(),
            (string) Str::uuid().'.'.($file->guessExtension() ?: 'bin'),
            'local',
        );

        if ($path === false) {
            throw new RuntimeException('No fue posible almacenar la cotización.');
        }

        $ingestion = PurchaseRequestIngestion::query()->create([
            'user_id' => $request->user()->getKey(),
            'uploader_name_snapshot' => $request->user()->name,
            'compared_request_id' => $purchaseRequest->getKey(),
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize(),
            'sha256' => $hash,
            'status' => PurchaseRequestIngestion::PENDING,
        ]);

        ReadQuotationDocument::dispatch($ingestion);

        return to_route('purchase_requests.show', $purchaseRequest)->with(
            'success',
            'Cotización recibida. La estamos leyendo y en un momento verás la comparación aquí mismo.',
        );
    }

    /** Quita la comparación sin borrar el documento ni su lectura. */
    public function destroy(Request $request, PurchaseRequest $purchaseRequest, PurchaseRequestIngestion $ingestion): RedirectResponse
    {
        Gate::authorize('view', $purchaseRequest);
        abort_unless($ingestion->compared_request_id === $purchaseRequest->getKey(), 404);

        $ingestion->forceFill(['compared_request_id' => null])->save();

        return to_route('purchase_requests.show', $purchaseRequest)
            ->with('success', 'Se quitó esa cotización de la comparación.');
    }

    /**
     * Un documento que ya se leyó antes.
     *
     * Si no está comprometido con otra solicitud se adopta tal cual, con su
     * lectura hecha: la comparación aparece al instante y no se gasta CPU.
     */
    private function reutilizar(PurchaseRequestIngestion $previo, PurchaseRequest $solicitud): RedirectResponse
    {
        if ($previo->compared_request_id === $solicitud->getKey()) {
            return to_route('purchase_requests.show', $solicitud)
                ->with('info', 'Esa cotización ya estaba comparada con esta solicitud.');
        }

        if ($previo->compared_request_id !== null) {
            return back()->with(
                'error',
                'Ese mismo archivo ya está comparado con la solicitud '
                    .($previo->comparedRequest?->folio ?? 'otra').'. Quítalo de allá si lo quieres aquí.',
            );
        }

        if ($previo->purchase_request_id !== null) {
            return back()->with(
                'error',
                'Ese archivo es del que nació la solicitud '
                    .($previo->purchaseRequest?->folio ?? 'otra').', así que compararlo consigo mismo no diría nada.',
            );
        }

        $previo->forceFill(['compared_request_id' => $solicitud->getKey()])->save();

        return to_route('purchase_requests.show', $solicitud)
            ->with('success', 'Ese documento ya estaba leído, así que la comparación está lista abajo.');
    }
}
