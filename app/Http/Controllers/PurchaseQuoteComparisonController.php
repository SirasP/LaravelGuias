<?php

namespace App\Http\Controllers;

use App\Jobs\ReadQuotationDocument;
use App\Models\PurchaseProductLink;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestEvent;
use App\Models\PurchaseRequestIngestion;
use App\Models\PurchaseRequestItem;
use App\Models\PurchaseSupplier;
use App\Models\UnitOfMeasure;
use App\Services\PurchaseRequests\Drafting\PurchaseRequestDrafter;
use App\Services\PurchaseRequests\Odoo\ConfirmOdooSupplier;
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
            'supplier_tax_id' => ['nullable', 'string', 'max:32'],
            'document_number' => ['nullable', 'string', 'max:60'],
            'kind' => ['nullable', 'in:cotizacion,factura'],
        ], [], [
            'text' => 'lo que escribiste',
            'supplier_name' => 'el proveedor',
            'supplier_tax_id' => 'el RUT del proveedor',
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
            // El RUT es lo que después permite crearle su orden en Odoo a
            // nombre de quien corresponde. Sin él, una cotización dictada se
            // quedaba sin poder comprarse.
            'supplier_tax_id' => Rut::normalize((string) ($datos['supplier_tax_id'] ?? '')),
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
            $ingestion->parejasRechazadas(),
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
     * Busca en Odoo el proveedor de ESTA cotización.
     *
     * El buscador de la tarjeta de Odoo resuelve el proveedor de la solicitud,
     * que es otra cosa: una solicitud puede comprarse a dos. Y una cotización
     * dictada a mano no trae RUT, así que sin esto no había forma de decir
     * quién la firma —y sin eso no se le puede crear su orden—.
     */
    public function supplierSearch(
        Request $request,
        PurchaseRequest $purchaseRequest,
        PurchaseRequestIngestion $ingestion,
        PurchaseRequestExporter $exporter,
    ): RedirectResponse {
        Gate::authorize('exportToOdoo', $purchaseRequest);
        abort_unless($ingestion->compared_request_id === $purchaseRequest->getKey(), 404);

        $datos = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:120'],
        ], [], ['q' => 'la búsqueda']);

        if (! $exporter instanceof OdooPurchaseRequestExporter) {
            return back()->with('error', 'La integración con Odoo está apagada en este entorno.');
        }

        $encontrados = $exporter->buscarProveedores($datos['q']);

        return back()
            ->with('cot_candidatos', [$ingestion->getKey() => $encontrados])
            ->with('cot_busqueda', [$ingestion->getKey() => $datos['q']])
            ->with($encontrados === [] ? 'warning' : 'info', $encontrados === []
                ? 'Odoo no tiene ningún proveedor que coincida con «'.$datos['q'].'».'
                : 'Odoo encontró '.count($encontrados).' '.Str::plural('proveedor', count($encontrados)).'.');
    }

    /**
     * Anota quién es, y lo deja aprendido para las próximas cotizaciones suyas.
     */
    public function supplierAssign(
        Request $request,
        PurchaseRequest $purchaseRequest,
        PurchaseRequestIngestion $ingestion,
        ConfirmOdooSupplier $confirmar,
    ): RedirectResponse {
        Gate::authorize('exportToOdoo', $purchaseRequest);
        abort_unless($ingestion->compared_request_id === $purchaseRequest->getKey(), 404);

        $datos = $request->validate([
            'odoo_partner_id' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
            'vat' => ['nullable', 'string', 'max:32'],
        ]);

        $proveedor = $confirmar($purchaseRequest, (int) $datos['odoo_partner_id'], $datos['name'], $datos['vat'] ?? null);

        // El RUT queda en la cotización: es por donde se la reconoce después,
        // y lo que permite crearle su orden a nombre de quien corresponde.
        $ingestion->forceFill([
            'supplier_name' => $datos['name'],
            'supplier_tax_id' => $proveedor->tax_id,
        ])->save();

        return to_route('purchase_requests.show', $purchaseRequest)->with(
            'success',
            'Anotado: esta cotización es de '.$datos['name'].'. Ya se le puede crear su orden en Odoo.',
        );
    }

    /**
     * «Ésa no es»: descarta una propuesta y no la vuelve a ofrecer.
     *
     * Las propuestas se recalculan en cada carga de la página, así que sin
     * dejarlo escrito el rechazo no duraba nada: «CINTA PELIGRO» volvía a
     * aparecer emparejada con «ESCOBILLON DOMESTICO MANGO MADERA VIRUTEX» a la
     * siguiente visita. Con el rechazo guardado, la partida queda sin cotizar
     * y el renglón baja al bloque de lo que el proveedor trajo de más, donde
     * se le puede asignar la partida correcta.
     */
    public function reject(Request $request, PurchaseRequest $purchaseRequest, PurchaseRequestIngestion $ingestion): RedirectResponse
    {
        Gate::authorize('view', $purchaseRequest);
        abort_unless($ingestion->compared_request_id === $purchaseRequest->getKey(), 404);

        $datos = $request->validate([
            'item_id' => ['required', 'integer'],
            'line_index' => ['required', 'integer', 'min:0'],
        ], [], ['item_id' => 'la partida', 'line_index' => 'la línea de la cotización']);

        abort_if($purchaseRequest->items()->whereKey($datos['item_id'])->doesntExist(), 404);

        $rechazos = $ingestion->parejasRechazadas();
        $rechazos[] = [(int) $datos['line_index'], (int) $datos['item_id']];

        // Y si además estaba confirmada, deja de estarlo: rechazar es la forma
        // de deshacer una confirmación equivocada.
        $parejas = $ingestion->parejasConfirmadas();
        unset($parejas[(int) $datos['line_index']]);

        $ingestion->guardarDecisiones($parejas, $rechazos);

        return to_route('purchase_requests.show', $purchaseRequest)
            ->with('success', 'Descartado. Esa pareja no se vuelve a proponer, y el renglón queda '
                .'abajo para que le digas cuál era.');
    }

    /**
     * Suelta la solicitud de su orden de Odoo, para poder rehacerla.
     *
     * No toca Odoo. La orden de allá se queda donde está —si hay que anularla,
     * se anula allá, que es donde cuelgan sus recepciones y facturas—; lo que
     * se suelta es el vínculo, para que la solicitud pueda repartirse bien
     * entre sus proveedores con una orden nueva.
     */
    public function detach(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        Gate::authorize('exportToOdoo', $purchaseRequest);

        $anterior = (string) ($purchaseRequest->odoo_reference ?: $purchaseRequest->odoo_order_id);

        if ($anterior === '') {
            return back()->with('info', 'Esta solicitud no está vinculada a ninguna orden de Odoo.');
        }

        $purchaseRequest->items()->update(['odoo_order_id' => null, 'odoo_line_id' => null]);
        $purchaseRequest->forceFill([
            'odoo_order_id' => null,
            'odoo_reference' => null,
            'odoo_exported_at' => null,
        ])->save();

        $purchaseRequest->events()->create([
            'actor_id' => $request->user()->getKey(),
            'actor_name_snapshot' => $request->user()->name,
            'actor_role_snapshot' => $request->user()->role,
            'event_type' => PurchaseRequestEvent::EXPORTED,
            'from_status' => $purchaseRequest->status,
            'to_status' => $purchaseRequest->status,
            'revision_number' => $purchaseRequest->revision_number,
            'comment' => 'Se soltó el vínculo con '.$anterior.'. La orden sigue en Odoo.',
            'ip_address' => $request->ip(),
        ]);

        return to_route('purchase_requests.show', $purchaseRequest)->with('success', sprintf(
            'La solicitud se soltó de %s y sus partidas vuelven a estar en espera. '
                .'Ojo: %s sigue existiendo en Odoo — si hay que anularla, se anula allá.',
            $anterior,
            $anterior,
        ));
    }

    /**
     * Manda a Odoo lo que se le compra a este proveedor, y sólo eso.
     *
     * Una compra se reparte: la SC-2026-000024 se hizo en dos sitios, y Odoo
     * tenía sus nueve partidas en una sola orden a nombre de uno solo. Como
     * allá la recepción cuelga de la orden, ese dato falso llegaba al stock.
     *
     * Si la solicitud aún no tiene orden, se crea con las partidas de este
     * proveedor. Si ya tiene una, se le quitan las que no son suyas y se crea
     * otra orden para éste, enlazada a la primera como alternativa —el
     * mecanismo de Odoo que esa instancia ya usa—. Lo que nadie cotizó no va a
     * ninguna: se queda en espera, a la vista.
     */
    public function split(
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

        if ($partnerId === null) {
            return back()->with('error', 'Todavía no se sabe quién es este proveedor en Odoo. '
                .'Búscalo en la tarjeta de Odoo y elígelo primero.');
        }

        $suyas = $this->partidasDe($purchaseRequest, $ingestion, $partnerId);

        if ($suyas->isEmpty()) {
            return back()->with('error', 'Ninguna partida cruzó con esta cotización, así que no hay '
                .'nada que comprarle a este proveedor.');
        }

        // La orden original se vacía: lo de este proveedor se muda a la suya y
        // lo que nadie cotizó queda en espera. Antes se quedaba con lo del
        // proveedor y la orden nueva nacía con eso mismo, o sea la misma
        // partida en dos órdenes y el stock contado dos veces.
        //
        // Se hace antes de crear la nueva: si Odoo rechaza el recorte, no
        // queremos una segunda orden creada sobre una primera que sigue
        // mintiendo.
        $anterior = (int) $purchaseRequest->odoo_order_id;
        $referenciaAnterior = (string) $purchaseRequest->odoo_reference;

        // Quitar exige saber qué línea de allá es cada partida, y eso sólo se
        // guarda desde que existe la columna. Si ninguna partida dice vivir en
        // esa orden no sabemos nada de ella y no se toca: puede ser anterior a
        // la columna, o la de otro proveedor de un reparto anterior.
        $viviendoAlla = $anterior === 0
            ? $purchaseRequest->items()->whereRaw('1 = 0')->get()
            : $purchaseRequest->items()->where('odoo_order_id', $anterior)->get();

        $seSabeQueHayAlla = $viviendoAlla->isNotEmpty();

        if ($seSabeQueHayAlla) {
            $sonSuyas = $suyas->pluck('id')->all();

            // Las que estaban allá y este proveedor no cotizó: salen también y
            // se quedan esperando a quien sí las venda.
            $sobran = $viviendoAlla->reject(fn ($partida) => in_array($partida->getKey(), $sonSuyas, true));

            [, $motivo, $sigueExistiendo] = $exporter->quitarLineas(
                $anterior,
                $viviendoAlla->pluck('odoo_line_id')->filter()->map(fn ($v) => (int) $v)->values()->all(),
            );

            if ($motivo !== null) {
                return back()->with('error', $motivo);
            }

            // Borrada en Odoo. Se limpia el vínculo muerto y se sigue: sin
            // esto la solicitud quedaba atrapada, protegiendo una orden que ya
            // no existía y sin poder repartirse nunca.
            if (! $sigueExistiendo) {
                $purchaseRequest->forceFill([
                    'odoo_order_id' => null, 'odoo_reference' => null, 'odoo_exported_at' => null,
                ])->save();
                $anterior = 0;
            }

            foreach ($sobran as $partida) {
                $partida->forceFill(['odoo_order_id' => null, 'odoo_line_id' => null])->save();
            }
        }

        // La orden nace ya con lo que dice la cotización que tenemos delante:
        // su nombre, su precio y su cantidad. Crearla con lo de la solicitud y
        // obligar a corregirla después era hacer dos veces el mismo trabajo.
        [$id, $referencia, $motivo] = $exporter->ordenParaProveedor(
            $purchaseRequest,
            $suyas,
            $partnerId,
            $this->loCotizado($purchaseRequest, $ingestion, $partnerId),
        );

        if ($motivo !== null) {
            return back()->with('error', $motivo);
        }

        // Alternativas sólo cuando las dos órdenes se pelean lo mismo.
        //
        // En Odoo, confirmar una alternativa te ofrece anular las otras. Eso es
        // lo que quieres con dos proveedores cotizando el mismo casco, y es lo
        // contrario de lo que quieres en un reparto: anularías la compra del
        // otro. Se pelean lo mismo justo cuando no se pudo vaciar la orden
        // anterior, porque entonces allá sigue estando lo que acabamos de
        // pedirle a este proveedor.
        if ($anterior !== 0 && ! $seSabeQueHayAlla
            && ($exporter->cuantasLineasTiene($anterior) ?? 0) > 0) {
            $exporter->enlazarComoAlternativas([$anterior, (int) $id]);
        }

        if ($anterior === 0) {
            $purchaseRequest->forceFill([
                'odoo_order_id' => $id,
                'odoo_reference' => $referencia,
                'odoo_exported_at' => now(),
            ])->save();
        }

        $enEspera = $purchaseRequest->items()->whereNull('odoo_order_id')->count();

        return to_route('purchase_requests.show', $purchaseRequest)->with('success', sprintf(
            'Se creó %s en Odoo con %d %s de %s.%s%s',
            $referencia,
            $suyas->count(),
            Str::plural('partida', $suyas->count()),
            $ingestion->supplier_name ?: 'este proveedor',
            $enEspera > 0
                ? sprintf(' Quedan %d en espera de otro proveedor.', $enEspera)
                : '',
            $anterior === 0 ? '' : ($seSabeQueHayAlla
                ? sprintf(' %s quedó vacía: la puedes borrar en Odoo.',
                    $referenciaAnterior !== '' ? $referenciaAnterior : 'La orden anterior')
                : sprintf(' %s quedó igual porque no sabemos qué línea es cuál allá: en Odoo las dos '
                    .'quedan como alternativas, confirma la que compres y anula la otra.',
                    $referenciaAnterior !== '' ? $referenciaAnterior : 'La orden anterior')),
        ));
    }

    /**
     * Lo que la cotización dice de cada partida: nombre, precio y cantidad.
     *
     * Es lo mismo que después llevaría el botón de «precios y nombres», pero
     * puesto ya al crear la orden: no tiene sentido nacer en cero y con el
     * nombre genérico teniendo la cotización delante.
     *
     * @return array<int, array{nombre: ?string, precio: ?float, cantidad: ?float}>
     */
    private function loCotizado(PurchaseRequest $purchaseRequest, PurchaseRequestIngestion $ingestion, ?int $partnerId): array
    {
        $lineas = $ingestion->extracted['items'] ?? [];

        $comparacion = app(QuotationComparison::class)->comparar(
            $purchaseRequest,
            is_array($lineas) ? array_values($lineas) : [],
            $partnerId,
            $ingestion->parejasConfirmadas(),
            $ingestion->parejasRechazadas(),
        );

        $numero = fn (mixed $v): ?float => is_string($v)
            ? \App\Support\ChileanMoney::parse($v)
            : (is_numeric($v) ? (float) $v : null);

        $cotizado = [];

        foreach ($comparacion->filas as $fila) {
            if ($fila->pedida === null || $fila->cotizada === null || $fila->esPropuesta()) {
                continue;
            }

            $cotizado[(int) $fila->pedida->getKey()] = [
                'nombre' => trim((string) ($fila->cotizada['product_service'] ?? '')) ?: null,
                'precio' => $numero($fila->cotizada['unit_price'] ?? null),
                'cantidad' => $numero($fila->cotizada['quantity'] ?? null),
            ];
        }

        return $cotizado;
    }

    /**
     * Las partidas que este proveedor cotizó y alguien dio por buenas.
     *
     * Una propuesta sin confirmar no entra: de aquí sale una orden de compra.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\PurchaseRequestItem>
     */
    private function partidasDe(PurchaseRequest $purchaseRequest, PurchaseRequestIngestion $ingestion, ?int $partnerId)
    {
        $lineas = $ingestion->extracted['items'] ?? [];

        $comparacion = app(QuotationComparison::class)->comparar(
            $purchaseRequest,
            is_array($lineas) ? array_values($lineas) : [],
            $partnerId,
            $ingestion->parejasConfirmadas(),
            $ingestion->parejasRechazadas(),
        );

        return collect($comparacion->filas)
            ->filter(fn ($fila): bool => $fila->cruzo() && ! $fila->esPropuesta())
            ->map(fn ($fila) => $fila->pedida)
            ->values();
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
            $ingestion->parejasRechazadas(),
        );

        $cambios = [];
        $repetidos = [];

        foreach ($comparacion->filas as $fila) {
            // Con la coma decimal chilena: «12.500,50» no es is_numeric.
            $precio = is_string($fila->cotizada['unit_price'] ?? null)
                ? \App\Support\ChileanMoney::parse($fila->cotizada['unit_price'])
                : ($fila->cotizada['unit_price'] ?? null);

            if ($fila->pedida === null || $fila->cotizada === null) {
                continue;
            }

            // Una pareja que nadie confirmó no escribe nada en Odoo. Es el
            // punto exacto donde una corazonada del programa se volvería un
            // número —o un nombre— en una orden de compra.
            if ($fila->esPropuesta()) {
                continue;
            }

            $nombre = trim((string) ($fila->cotizada['product_service'] ?? '')) ?: null;

            // Tres formas de reconocer la línea en Odoo, porque a lo largo de
            // su vida se llama de tres maneras: como la escribiste tú, como
            // viajó —con la especificación pegada, «TUB CUAD NEG. · ECU202»— y
            // como quedó cuando le pusimos el nombre real del proveedor.
            // Faltando cualquiera de las tres, la orden se vuelve inalcanzable.
            $textos = array_values(array_unique(array_filter([
                PurchaseProductLink::normalizar((string) $fila->pedida->product_service),
                PurchaseProductLink::normalizar($exporter->descripcionDe($fila->pedida)),
                $nombre === null ? '' : PurchaseProductLink::normalizar($nombre),
            ])));

            // Un texto repetido entre partidas no identifica ninguna línea.
            foreach ($textos as $t) {
                $repetidos[$t] = ($repetidos[$t] ?? 0) + 1;
            }

            $cambios[] = [
                // La partida y la línea de Odoo se encuentran por el mismo
                // producto, resuelto igual que cuando se creó la orden.
                'producto' => $exporter->productoDe($fila->pedida, $partnerId),
                'textos' => $textos,
                'precio' => is_numeric($precio) ? (float) $precio : null,
                // La cantidad que va a Odoo es la COTIZADA, no la pedida: la
                // orden de compra dice lo que se compra, y eso es lo que se va
                // a recibir y lo que van a facturar. La solicitud guarda lo que
                // se quería, y la pantalla muestra la diferencia —«pediste 10 y
                // cotizaron 5»— para que se mire antes de llevarla.
                'cantidad' => is_string($fila->cotizada['quantity'] ?? null)
                    ? \App\Support\ChileanMoney::parse($fila->cotizada['quantity'])
                    : (is_numeric($fila->cotizada['quantity'] ?? null) ? (float) $fila->cotizada['quantity'] : null),
                // El nombre de verdad, el que trae la cotización oficial.
                'nombre' => $nombre,
                // Y en qué orden vive esta partida: desde que una solicitud
                // puede repartirse entre dos proveedores, ya no hay una sola.
                'orden' => $fila->pedida->odoo_order_id,
            ];
        }

        $cambios = array_values(array_map(
            fn (array $c): array => [...$c, 'textos' => array_values(array_filter(
                $c['textos'],
                fn (string $t): bool => ($repetidos[$t] ?? 0) === 1,
            ))],
            array_filter($cambios, fn (array $c): bool => $c['precio'] !== null || $c['nombre'] !== null),
        ));

        // Un lote por orden. Corregir los precios de la segunda orden
        // escribiéndolos en la primera sería mover la compra de otro.
        $porOrden = collect($cambios)->groupBy(
            fn (array $c) => (int) ($c['orden'] ?? $purchaseRequest->odoo_order_id),
        );

        $actualizadas = 0;
        $creados = 0;
        $motivo = null;

        foreach ($porOrden as $orden => $lote) {
            if ((int) $orden === 0) {
                continue;
            }

            [$n, $porQue, $nuevos] = $exporter->actualizarLineas(
                $purchaseRequest, $lote->values()->all(), $partnerId, (int) $orden,
            );

            $actualizadas += $n;
            $creados += $nuevos;
            $motivo ??= $porQue;
        }

        if ($motivo !== null && $actualizadas === 0) {
            return back()->with('error', $motivo);
        }

        return back()->with('success', $actualizadas > 0
            ? sprintf(
                'Se actualizaron %d %s en %s con el precio, el nombre y el producto del proveedor.%s',
                $actualizadas,
                Str::plural('línea', $actualizadas),
                $purchaseRequest->odoo_reference,
                // Crear en Odoo es lo único de aquí que deja algo permanente
                // allá: se dice siempre, aunque nadie lo haya preguntado.
                $creados > 0
                    ? sprintf(
                        ' Se %s %d %s nuevos en Odoo, porque no existían.',
                        $creados === 1 ? 'dio de alta' : 'dieron de alta',
                        $creados,
                        Str::plural('producto', $creados),
                    )
                    : '',
            )
            : 'Odoo ya decía lo mismo que la cotización: no había nada que cambiar.');
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
