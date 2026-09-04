<?php

namespace App\Http\Controllers;

use App\Jobs\ReadQuotationDocument;
use App\Models\Department;
use App\Models\PurchaseRequestIngestion;
use App\Models\UnitOfMeasure;
use App\Services\PurchaseRequests\DraftFromIngestionService;
use App\Services\PurchaseRequests\Drafting\PurchaseRequestDrafter;
use App\Services\PurchaseRequests\Reading\QuotationReader;
use Illuminate\Http\JsonResponse;
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

        // Un documento en espera es normal unos segundos. Si lleva minutos,
        // casi siempre es que nadie está procesando la cola: hay que decirlo,
        // no dejar a la persona mirando «En espera» sin saber qué pasa.
        $enProceso = $ingestions->getCollection()
            ->whereIn('status', [PurchaseRequestIngestion::PENDING, PurchaseRequestIngestion::PROCESSING]);

        $atascado = $enProceso
            ->filter(fn (PurchaseRequestIngestion $i): bool => $i->created_at?->lt(now()->subMinutes(2)) ?? false)
            ->isNotEmpty();

        return response()->view('purchase_requests.ingestions', [
            'ingestions' => $ingestions,
            'readerEnabled' => $reader->isEnabled(),
            'readerDescription' => $reader->describe(),
            'hayEnProceso' => $enProceso->isNotEmpty(),
            'procesadorDetenido' => $atascado,
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
            // No se rechaza en silencio: se lleva a la lectura que ya existe,
            // donde además se puede pedir leerlo de nuevo.
            return to_route('purchase_requests.ingestions.show', $previo)
                ->with('info', 'Ese documento ya se había leído. Aquí está el resultado; puedes usarlo o volver a leerlo.');
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

    /**
     * Convierte una frase escrita a mano en partidas, para el formulario.
     *
     * Responde JSON y no guarda nada: lo que devuelve entra en el formulario
     * que la persona está llenando, y ella decide qué queda. Es una sugerencia
     * en pantalla, no una solicitud.
     */
    public function draft(Request $request, PurchaseRequestDrafter $drafter): JsonResponse
    {
        abort_unless($request->user()->canCreatePurchaseRequests(), 403);

        $data = $request->validate([
            'text' => ['required', 'string', 'min:3', 'max:4000'],
        ], [], ['text' => 'el texto']);

        if (! $drafter->isEnabled()) {
            return response()->json([
                'available' => false,
                'error' => 'El asistente no está habilitado en este entorno.',
            ], 200);
        }

        $sugerencia = $drafter->draftFromText(
            $data['text'],
            UnitOfMeasure::query()->forCompany()->active()->ordered()->pluck('name')->all(),
        );

        return response()->json($sugerencia->toArray());
    }

    /**
     * La lectura, en una tabla que se puede corregir antes de crear nada.
     */
    public function show(Request $request, PurchaseRequestIngestion $ingestion): Response
    {
        abort_unless($this->puedeVer($request, $ingestion), 403);

        $ingestion->load('purchaseRequest');

        return response()->view('purchase_requests.ingestion_review', [
            'ingestion' => $ingestion,
            'items' => $ingestion->extracted['items'] ?? [],
            'units' => UnitOfMeasure::query()->forCompany()->active()->ordered()->get(),
            'departments' => Department::query()->forCompany()->active()->ordered()->get(),
        ]);
    }

    /**
     * Crea la solicitud con lo que quedó en pantalla.
     *
     * Es el único punto donde una lectura se convierte en solicitud, y siempre
     * lo dispara una persona.
     */
    public function confirm(
        Request $request,
        PurchaseRequestIngestion $ingestion,
        DraftFromIngestionService $servicio,
    ): RedirectResponse {
        abort_unless($ingestion->user_id === $request->user()->getKey(), 403);
        abort_unless($request->user()->canCreatePurchaseRequests(), 403);

        if ($ingestion->purchase_request_id !== null) {
            return to_route('purchase_requests.show', $ingestion->purchaseRequest)
                ->with('info', 'Esta lectura ya había creado una solicitud.');
        }

        $data = $request->validate([
            'department' => ['nullable', 'string', 'max:120'],
            'reason' => ['nullable', 'string', 'max:10000'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_service' => ['nullable', 'string', 'max:1000'],
            'items.*.specification' => ['nullable', 'string', 'max:5000'],
            'items.*.quantity' => ['nullable', 'string', 'max:40'],
            'items.*.unit' => ['nullable', 'string', 'max:80'],
            // Sin esta regla el precio no llega: validate() devuelve sólo lo
            // declarado, así que omitir un campo lo descarta en silencio.
            'items.*.unit_price' => ['nullable', 'string', 'max:40'],
        ], [
            'items.required' => 'No quedó ninguna partida que traspasar.',
        ]);

        // Las líneas que quedaron sin producto se descartan: quien revisa pudo
        // haber vaciado una que el asistente leyó de más.
        $items = array_values(array_filter(
            $data['items'],
            fn (array $item): bool => filled($item['product_service'] ?? null),
        ));

        if ($items === []) {
            return back()->with('error', 'Deja al menos una partida con producto antes de crear la solicitud.');
        }

        $solicitud = $servicio->create(
            $ingestion,
            $request->user(),
            $items,
            $data['reason'] ?? null,
            $data['department'] ?? null,
        );

        return to_route('purchase_requests.edit', $solicitud)
            ->with('success', 'Solicitud creada desde el documento. Revísala y envíala cuando esté lista.');
    }

    /** Vuelve a leer el mismo documento, por si la primera vez salió mal. */
    public function reread(Request $request, PurchaseRequestIngestion $ingestion, QuotationReader $reader): RedirectResponse
    {
        abort_unless($ingestion->user_id === $request->user()->getKey(), 403);

        if (! $reader->isEnabled()) {
            return back()->with('error', 'El asistente no está habilitado en este entorno.');
        }

        if ($ingestion->purchase_request_id !== null) {
            return back()->with('error', 'Esta lectura ya creó una solicitud: no se puede volver a leer.');
        }

        $ingestion->forceFill([
            'status' => PurchaseRequestIngestion::PENDING,
            'error_message' => null,
            'started_at' => null,
            'finished_at' => null,
            'duration_ms' => null,
        ])->save();

        ReadQuotationDocument::dispatch($ingestion);

        return to_route('purchase_requests.ingestions.show', $ingestion)
            ->with('success', 'Estamos leyendo el documento otra vez. Te avisamos al terminar.');
    }

    private function puedeVer(Request $request, PurchaseRequestIngestion $ingestion): bool
    {
        $user = $request->user();

        return $ingestion->user_id === $user->getKey() || $user->canSeeAllPurchaseRequests();
    }

    /** El documento original, sólo para quien lo subió. */
    public function download(Request $request, PurchaseRequestIngestion $ingestion): StreamedResponse
    {
        // Quien lo subió siempre; y además cualquiera que pueda ver la
        // solicitud con la que se contrastó. Sin esto, la cotización que
        // justifica el precio sólo la abre quien la subió, justo cuando
        // quien revisa es otra persona.
        $puedeVerla = $ingestion->comparedRequest !== null
            && $request->user()->can('view', $ingestion->comparedRequest);

        abort_unless($ingestion->user_id === $request->user()->getKey() || $puedeVerla, 403);
        abort_unless(Storage::disk($ingestion->disk)->exists($ingestion->path), 404);

        return Storage::disk($ingestion->disk)->download($ingestion->path, $ingestion->original_name);
    }
}
