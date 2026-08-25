<?php

namespace App\Http\Controllers;

use App\Jobs\ReadQuotationDocument;
use App\Models\PurchaseRequestIngestion;
use App\Services\PurchaseRequests\Reading\QuotationReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Subida de cotizaciones para que el asistente las lea.
 *
 * La petición sólo guarda el archivo y encola el trabajo: responde en
 * milisegundos y la lectura ocurre por detrás. Nadie queda esperando frente a
 * una página cargando, que fue justamente lo que hizo que un trabajador
 * pulsara dos veces el botón de anular.
 */
class PurchaseIngestionController extends Controller
{
    public function index(Request $request, QuotationReader $reader): Response
    {
        $ingestions = PurchaseRequestIngestion::query()
            ->where('user_id', $request->user()->getKey())
            ->with('purchaseRequest')
            ->latest('id')
            ->paginate(20);

        return response()->view('purchase_requests.ingestions', [
            'ingestions' => $ingestions,
            'readerEnabled' => $reader->isEnabled(),
            'readerDescription' => $reader->describe(),
        ]);
    }

    public function store(Request $request, QuotationReader $reader): RedirectResponse
    {
        abort_unless($request->user()->canCreatePurchaseRequests(), 403);

        if (! $reader->isEnabled()) {
            return back()->with('error', 'El asistente de lectura no está habilitado en este entorno.');
        }

        $data = $request->validate([
            'document' => [
                'required', 'file', 'max:15360',
                'mimes:pdf,jpg,jpeg,png',
                'mimetypes:application/pdf,image/jpeg,image/png',
            ],
        ], [], ['document' => 'el documento']);

        $file = $data['document'];
        $hash = hash_file('sha256', $file->getRealPath());

        // El mismo archivo no se lee dos veces: leer cuesta minutos de CPU y
        // crearía borradores duplicados.
        $previo = PurchaseRequestIngestion::query()
            ->where('company_code', 'EHE')
            ->where('sha256', $hash)
            ->first();

        if ($previo !== null) {
            return to_route('purchase_requests.ingestions.index')
                ->with('success', 'Ese documento ya se había subido; abajo está su resultado.');
        }

        $path = $file->storeAs(
            'purchase-requests/ingestions/'.$request->user()->getKey(),
            (string) Str::uuid().'.'.($file->guessExtension() ?: 'bin'),
            'local',
        );

        $ingestion = PurchaseRequestIngestion::query()->create([
            'user_id' => $request->user()->getKey(),
            'uploader_name_snapshot' => $request->user()->name,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize(),
            'sha256' => $hash,
            'status' => PurchaseRequestIngestion::PENDING,
        ]);

        ReadQuotationDocument::dispatch($ingestion);

        return to_route('purchase_requests.ingestions.index')->with(
            'success',
            'Documento recibido. Lo estamos leyendo y te avisamos cuando el borrador esté listo; puedes seguir trabajando.',
        );
    }

    /** El documento original, sólo para quien lo subió. */
    public function download(Request $request, PurchaseRequestIngestion $ingestion): StreamedResponse
    {
        abort_unless($ingestion->user_id === $request->user()->getKey(), 403);
        abort_unless(Storage::disk($ingestion->disk)->exists($ingestion->path), 404);

        return Storage::disk($ingestion->disk)->download($ingestion->path, $ingestion->original_name);
    }
}
