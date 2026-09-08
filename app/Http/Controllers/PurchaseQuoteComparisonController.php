<?php

namespace App\Http\Controllers;

use App\Jobs\ReadQuotationDocument;
use App\Models\PurchaseProductLink;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestIngestion;
use App\Models\PurchaseRequestItem;
use App\Models\PurchaseSupplier;
use App\Models\UnitOfMeasure;
use App\Services\PurchaseRequests\Drafting\PurchaseRequestDrafter;
use App\Services\PurchaseRequests\Odoo\OdooPurchaseRequestExporter;
use App\Services\PurchaseRequests\Odoo\PurchaseRequestExporter;
use App\Services\PurchaseRequests\Quotes\QuotationComparison;
use App\Services\PurchaseRequests\Reading\PurchaseRequestSourceKind;
use App\Services\PurchaseRequests\Reading\QuotationReader;
use App\Support\Rut;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
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

    /**
     * La cotización dictada, en vez de subida.
     *
     * A veces la compra ya está hecha y la factura está en la mano: los
     * precios se saben, y escanear un papel para anotar cuatro números que ya
     * se conocen es trabajo inventado. Se escribe en prosa —«3 correas a
     * 12.500 cada una, 2 filtros a 8.900»— y el asistente las ordena en
     * partidas con su cantidad y su precio.
     *
     * Lo que se guarda es exactamente lo que la persona escribió, como archivo
     * y con su huella, igual que se guarda el PDF de una cotización subida: si
     * mañana un precio no cuadra, se puede ir a ver qué se dictó.
     */
    public function compose(Request $request, PurchaseRequest $purchaseRequest, PurchaseRequestDrafter $drafter): RedirectResponse
    {
        Gate::authorize('view', $purchaseRequest);

        $datos = $request->validate([
            'text' => ['required', 'string', 'min:3', 'max:4000'],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'document_number' => ['nullable', 'string', 'max:60'],
            'kind' => ['nullable', 'in:cotizacion,factura'],
        ], [], [
            'text' => 'lo que escribiste',
            'supplier_name' => 'el proveedor',
            'document_number' => 'el número de documento',
        ]);

        if (! $drafter->isEnabled()) {
            return back()->with('error', 'El asistente de lectura no está habilitado en este entorno.');
        }

        $sugerencia = $drafter->draftFromText(
            $datos['text'],
            UnitOfMeasure::query()->forCompany()->active()->ordered()->pluck('name')->all(),
        );

        if (! $sugerencia->available) {
            return back()->with('error', $sugerencia->error ?: 'El asistente no pudo leer lo que escribiste.');
        }

        $partidas = array_values(array_filter(
            $sugerencia->items,
            fn (array $item): bool => trim((string) ($item['product_service'] ?? '')) !== '',
        ));

        if ($partidas === []) {
            return back()->with(
                'error',
                'No reconocí ninguna partida en lo que escribiste. Prueba nombrando el producto, '
                    .'la cantidad y el precio: «3 correas a 12.500 cada una».',
            );
        }

        $esFactura = ($datos['kind'] ?? 'cotizacion') === 'factura';
        $proveedor = trim((string) ($datos['supplier_name'] ?? '')) ?: $sugerencia->supplier;
        $documento = trim((string) ($datos['document_number'] ?? ''));

        $texto = $datos['text'];
        $hash = hash('sha256', $texto);

        $previo = PurchaseRequestIngestion::query()
            ->where('company_code', 'EHE')->where('sha256', $hash)->first();

        if ($previo !== null) {
            return $this->reutilizar($previo, $purchaseRequest);
        }

        $path = 'purchase-requests/ingestions/'.$request->user()->getKey().'/'.Str::uuid().'.txt';
        Storage::disk('local')->put($path, $texto);

        $nombre = ($esFactura ? 'Factura' : 'Cotización').' escrita a mano'
            .($documento === '' ? '' : ' · '.$documento).'.txt';

        $ingestion = PurchaseRequestIngestion::query()->create([
            'user_id' => $request->user()->getKey(),
            'uploader_name_snapshot' => $request->user()->name,
            'compared_request_id' => $purchaseRequest->getKey(),
            'disk' => 'local',
            'path' => $path,
            'original_name' => $nombre,
            'mime_type' => 'text/plain',
            'size' => strlen($texto),
            'sha256' => $hash,
            'status' => PurchaseRequestIngestion::COMPLETED,
            'source_kind' => PurchaseRequestSourceKind::TEXT,
            'supplier_name' => $proveedor,
            'extracted' => [
                'items' => $partidas,
                'supplier' => $proveedor,
                'source_kind' => PurchaseRequestSourceKind::TEXT,
                'document_number' => $documento === '' ? null : $documento,
                'already_purchased' => $esFactura,
                'successful' => true,
            ],
            'warnings' => $sugerencia->warnings,
        ]);

        return to_route('purchase_requests.show', $purchaseRequest)->with(
            'success',
            sprintf(
                'Anoté %d %s de %s. Revisa abajo que los precios sean los que dijiste.',
                count($partidas),
                Str::plural('partida', count($partidas)),
                $proveedor ?: 'ese proveedor',
            ),
        )->with('ver_cotizacion', $ingestion->getKey());
    }

    /**
     * Enseña que una línea del proveedor y una partida son lo mismo.
     *
     * Se guarda contra la partida y, si la partida ya tiene producto de Odoo,
     * también contra ese producto. Así el alias sirve para emparejar aquí,
     * para exportar sin crear un producto nuevo, y para la próxima cotización
     * del mismo proveedor, todo con un solo aprendizaje.
     */
    public function link(Request $request, PurchaseRequest $purchaseRequest, PurchaseRequestIngestion $ingestion): RedirectResponse
    {
        Gate::authorize('view', $purchaseRequest);
        abort_unless($ingestion->compared_request_id === $purchaseRequest->getKey(), 404);

        $datos = $request->validate([
            'quote_line' => ['required', 'string', 'max:500'],
            'item_id' => ['required', 'integer'],
            'line_index' => ['nullable', 'integer', 'min:0'],
        ], [], ['quote_line' => 'la línea de la cotización', 'item_id' => 'la partida']);

        $partida = $purchaseRequest->items()->whereKey($datos['item_id'])->first();

        abort_if($partida === null, 404);

        $partnerId = $this->partnerDeOdoo($ingestion);

        $this->anotarPareja($ingestion, $datos['line_index'] ?? null, $partida);
        $this->aprender($request, $ingestion, $datos['quote_line'], $partida, $partnerId);

        return to_route('purchase_requests.show', $purchaseRequest)->with(
            'success',
            'Anotado: «'.Str::limit($datos['quote_line'], 34).'» es «'
                .Str::limit((string) $partida->product_service, 34).'». La próxima vez se cruza solo.',
        );
    }

    /**
     * Confirma de una vez todas las parejas que el programa propuso.
     *
     * Las propuestas no se leen del formulario sino que se vuelven a calcular
     * aquí: son deterministas, y así lo que se aprende es exactamente lo que la
     * pantalla mostraba, sin fiarse de lo que llegue por la red.
     */
    public function confirm(Request $request, PurchaseRequest $purchaseRequest, PurchaseRequestIngestion $ingestion): RedirectResponse
    {
        Gate::authorize('view', $purchaseRequest);
        abort_unless($ingestion->compared_request_id === $purchaseRequest->getKey(), 404);

        $partnerId = $this->partnerDeOdoo($ingestion);
        $lineas = $ingestion->extracted['items'] ?? [];

        $comparacion = app(QuotationComparison::class)->comparar(
            $purchaseRequest,
            is_array($lineas) ? array_values($lineas) : [],
            $partnerId,
            $ingestion->parejasConfirmadas(),
        );

        $aprendidas = 0;

        foreach ($comparacion->filas as $fila) {
            $texto = trim((string) ($fila->cotizada['product_service'] ?? ''));

            if (! $fila->esPropuesta() || $fila->pedida === null || $texto === '') {
                continue;
            }

            $this->anotarPareja($ingestion, $fila->renglon, $fila->pedida);
            $this->aprender($request, $ingestion, $texto, $fila->pedida, $partnerId);
            $aprendidas++;
        }

        return to_route('purchase_requests.show', $purchaseRequest)->with(
            $aprendidas > 0 ? 'success' : 'info',
            $aprendidas > 0
                ? sprintf(
                    'Confirmadas %d %s. La próxima cotización de este proveedor cruza sola.',
                    $aprendidas,
                    Str::plural('pareja', $aprendidas),
                )
                : 'No había ninguna propuesta pendiente de confirmar.',
        );
    }

    /**
     * Deshace un emparejado que alguien enseñó mal.
     *
     * Un clic equivocado aquí no se queda en esta pantalla: el alias vale para
     * todas las cotizaciones futuras de ese proveedor, así que tiene que poder
     * borrarse con la misma facilidad con que se creó.
     */
    public function unlink(Request $request, PurchaseRequest $purchaseRequest, PurchaseRequestIngestion $ingestion): RedirectResponse
    {
        Gate::authorize('view', $purchaseRequest);
        abort_unless($ingestion->compared_request_id === $purchaseRequest->getKey(), 404);

        $datos = $request->validate([
            'quote_line' => ['required', 'string', 'max:500'],
            'line_index' => ['nullable', 'integer', 'min:0'],
        ], [], ['quote_line' => 'la línea de la cotización']);

        $partnerId = $this->partnerDeOdoo($ingestion);
        $olvidada = $this->olvidarPareja($ingestion, $datos['line_index'] ?? null);

        // Se borra el del proveedor y también el general: el que estorba puede
        // ser cualquiera de los dos, y dejar uno vivo haría que la pantalla
        // siguiera mostrando lo mismo después de apretar el botón.
        $borrados = PurchaseProductLink::query()
            ->where('company_code', 'EHE')
            ->where('normalized_text', PurchaseProductLink::normalizar($datos['quote_line']))
            ->where(fn ($q) => $q->whereNull('odoo_partner_id')->orWhere('odoo_partner_id', $partnerId))
            ->delete();

        return to_route('purchase_requests.show', $purchaseRequest)->with(
            $borrados > 0 || $olvidada ? 'success' : 'info',
            $borrados > 0 || $olvidada
                ? 'Deshecho el emparejado de «'.Str::limit($datos['quote_line'], 40).'».'
                : '«'.Str::limit($datos['quote_line'], 40).'» no estaba emparejado a mano: cruzó sola por parecido.',
        );
    }

    /**
     * Deja escrito que este renglón de este documento es esta partida.
     *
     * Va pegado al documento y no al texto porque el texto a veces no
     * identifica nada: el formulario de MAX SERVICE corta los nombres a
     * veintiocho caracteres y deja cuatro overoles de tallas distintas
     * llamados igual. El alias de texto los pisaba unos a otros y confirmar
     * trece parejas dejaba nueve sin confirmar.
     */
    private function anotarPareja(PurchaseRequestIngestion $ingestion, ?int $renglon, PurchaseRequestItem $partida): void
    {
        if ($renglon === null) {
            return;
        }

        $parejas = $ingestion->parejasConfirmadas();

        // Un renglón contesta a una sola partida y una partida se contesta con
        // un solo renglón: si la partida ya estaba apuntada a otro sitio, se
        // muda, no se duplica.
        $parejas = array_filter($parejas, fn (int $id): bool => $id !== (int) $partida->getKey());
        $parejas[$renglon] = (int) $partida->getKey();

        $ingestion->forceFill(['confirmed_pairings' => $parejas])->save();
    }

    /** Olvida la confirmación de un renglón. Devuelve si había alguna. */
    private function olvidarPareja(PurchaseRequestIngestion $ingestion, ?int $renglon): bool
    {
        $parejas = $ingestion->parejasConfirmadas();

        if ($renglon === null || ! array_key_exists($renglon, $parejas)) {
            return false;
        }

        unset($parejas[$renglon]);
        $ingestion->forceFill(['confirmed_pairings' => $parejas === [] ? null : $parejas])->save();

        return true;
    }

    /**
     * Anota que el texto del proveedor y la partida son la misma cosa.
     *
     * Si la partida ya tiene producto de Odoo se guarda también ese, porque es
     * la equivalencia fuerte: sirve para exportar sin crear un producto nuevo.
     * Y si no lo tiene, se guarda igual apuntando al texto de la partida, que
     * es lo que hace falta para que la comparación cruce. Antes esto se
     * rechazaba, y dejaba sin enseñar precisamente las solicitudes nuevas.
     */
    private function aprender(
        Request $request,
        PurchaseRequestIngestion $ingestion,
        string $textoDelProveedor,
        PurchaseRequestItem $partida,
        ?int $partnerId,
    ): void {
        // Si ese mismo nombre aparece en varios renglones del documento, no
        // significa un producto: enseñarlo sería enseñar una mentira que
        // además valdría para todas las cotizaciones futuras del proveedor.
        if ($this->apareceVariasVeces($ingestion, $textoDelProveedor)) {
            return;
        }

        $delaPartida = PurchaseProductLink::para((string) $partida->product_service, $partnerId);

        PurchaseProductLink::query()->updateOrCreate(
            [
                'company_code' => 'EHE',
                'odoo_partner_id' => $partnerId,
                'normalized_text' => PurchaseProductLink::normalizar($textoDelProveedor),
            ],
            [
                'source_text' => $textoDelProveedor,
                'partner_name' => $ingestion->supplier_name,
                'odoo_product_id' => $delaPartida?->odoo_product_id,
                'odoo_product_name' => $delaPartida?->odoo_product_name,
                'canonical_text' => PurchaseProductLink::normalizar((string) $partida->product_service),
                'source' => 'confirmed',
                'confirmed_by' => $request->user()->getKey(),
                'confirmed_by_name' => $request->user()->name,
                'confirmed_at' => now(),
            ],
        );
    }

    /**
     * Lleva a Odoo los precios que cotizó el proveedor.
     *
     * Se toman de la comparación ya cruzada: sólo de las partidas que tienen
     * pareja, y sólo si esa pareja trae precio. Lo que no cruzó no se adivina.
     */
    public function prices(
        Request $request,
        PurchaseRequest $purchaseRequest,
        PurchaseRequestIngestion $ingestion,
        PurchaseRequestExporter $exporter,
    ): RedirectResponse {
        Gate::authorize('exportToOdoo', $purchaseRequest);
        abort_unless($ingestion->compared_request_id === $purchaseRequest->getKey(), 404);

        if (! $exporter instanceof OdooPurchaseRequestExporter) {
            return back()->with('error', 'La integración con Odoo está apagada en este entorno.');
        }

        $partnerId = $this->partnerDeOdoo($ingestion);
        $lineas = $ingestion->extracted['items'] ?? [];

        $comparacion = app(QuotationComparison::class)->comparar(
            $purchaseRequest,
            is_array($lineas) ? array_values($lineas) : [],
            $partnerId,
            $ingestion->parejasConfirmadas(),
        );

        $precios = [];

        foreach ($comparacion->filas as $fila) {
            $precio = $fila->cotizada['unit_price'] ?? null;

            if ($fila->pedida === null || ! is_numeric($precio)) {
                continue;
            }

            // Una pareja que nadie confirmó no escribe precios en Odoo. Es el
            // punto exacto donde una corazonada del programa se volvería un
            // número en una orden de compra.
            if ($fila->esPropuesta()) {
                continue;
            }

            // La partida y la línea de Odoo se encuentran por el mismo
            // producto, resuelto igual que cuando se creó la orden. Mirar sólo
            // los alias aprendidos dejaba fuera todo lo que había cruzado por
            // nombre idéntico o por código, que es la mayoría: la pantalla
            // decía «ninguna partida tiene precio que llevar» sobre una
            // cotización con precios en todas.
            $producto = $exporter->productoDe($fila->pedida, $partnerId);

            if ($producto !== null) {
                $precios[(int) $producto] = (float) $precio;
            }
        }

        [$actualizadas, $motivo] = $exporter->actualizarPrecios($purchaseRequest, $precios);

        if ($motivo !== null) {
            return back()->with('error', $motivo);
        }

        return back()->with('success', $actualizadas > 0
            ? sprintf(
                'Se actualizaron %d %s en %s con los precios del proveedor.',
                $actualizadas,
                Str::plural('línea', $actualizadas),
                $purchaseRequest->odoo_reference,
            )
            : 'Los precios de Odoo ya coincidían con los de la cotización: no había nada que cambiar.');
    }

    /** ¿Ese nombre se repite en el documento, y por tanto no identifica nada? */
    private function apareceVariasVeces(PurchaseRequestIngestion $ingestion, string $texto): bool
    {
        $lineas = $ingestion->extracted['items'] ?? [];

        if (! is_array($lineas)) {
            return false;
        }

        $buscado = PurchaseProductLink::normalizar($texto);
        $veces = 0;

        foreach ($lineas as $linea) {
            if (PurchaseProductLink::normalizar((string) ($linea['product_service'] ?? '')) === $buscado) {
                $veces++;
            }
        }

        return $veces > 1;
    }

    /** El proveedor en Odoo de una cotización, si se le conoce el RUT. */
    private function partnerDeOdoo(PurchaseRequestIngestion $ingestion): ?int
    {
        $rut = Rut::normalize($ingestion->supplier_tax_id);

        if ($rut === null) {
            return null;
        }

        $partner = PurchaseSupplier::query()->forCompany()->where('tax_id', $rut)->value('odoo_partner_id');

        return $partner === null ? null : (int) $partner;
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
