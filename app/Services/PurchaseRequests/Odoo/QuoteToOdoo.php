<?php

namespace App\Services\PurchaseRequests\Odoo;

use App\Models\PurchaseProductLink;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestIngestion;
use App\Services\PurchaseRequests\Quotes\QuotationComparison;
use App\Services\PurchaseRequests\Quotes\QuoteMeasures;
use App\Support\ChileanMoney;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Lleva una cotización ya leída a la orden de su proveedor en Odoo.
 *
 * Primero arma lo que haría —{@see plan()}— para que una persona lo mire, y
 * sólo después lo escribe —{@see aplicar()}—. Las dos cosas salen del mismo
 * cálculo, así que lo que se ve es lo que se escribe.
 *
 * Tres reglas, todas aprendidas con la SC-2026-000103:
 *
 *  - La orden es la del proveedor de la cotización. Luis cotiza en Odoo a
 *    cuatro proveedores con las mismas líneas; escribir los precios de Entre
 *    Riegos en la orden de Cooprinsem sería peor que no escribir nada.
 *  - La línea se ubica por la que guardó la partida al viajar, o por su
 *    producto, que es el mismo en las órdenes alternativas. El nombre no
 *    sirve: allá se lee «Válvula de pie con canastillo de 10"» y aquí
 *    «válvula de pie con canastillo 10"».
 *  - La unidad de la orden se respeta. Si se pidieron 2 tubos y cotizan 12
 *    metros, la orden sigue en 2 tubos con el precio de 6 metros cada uno:
 *    escribir 12 en una línea que mide en unidades serían 12 tubos.
 */
class QuoteToOdoo
{
    /** Hasta aquí se puede escribir: después hay recepciones y facturas detrás. */
    private const EDITABLES = ['draft', 'sent'];

    private const CAMPOS_ORDEN = ['name', 'state', 'partner_id', 'purchase_group_id', 'order_line', 'origin'];

    private const CAMPOS_LINEA = ['id', 'order_id', 'name', 'product_id', 'product_qty', 'product_uom', 'price_unit', 'taxes_id', 'display_type'];

    private OdooClient $client;

    public function __construct(
        private readonly OdooPurchaseRequestExporter $exporter,
        private readonly QuotationComparison $comparacion,
    ) {
        $this->client = $exporter->client();
    }

    /**
     * Lo que haría, sin escribir nada.
     *
     * @return array{
     *     orden: ?array{id: int, name: string, state: string, editable: bool},
     *     motivo: ?string,
     *     filas: list<array<string, mixed>>,
     *     piezas: list<array<string, mixed>>,
     *     sobrantes: list<array<string, mixed>>,
     * }
     */
    public function plan(PurchaseRequest $solicitud, PurchaseRequestIngestion $lectura, ?int $partnerId): array
    {
        $vacio = ['orden' => null, 'motivo' => null, 'filas' => [], 'piezas' => [], 'sobrantes' => []];

        if ($partnerId === null) {
            return [...$vacio, 'motivo' => 'Todavía no se sabe quién es este proveedor en Odoo. Búscalo en la tarjeta de la cotización y elígelo primero.'];
        }

        $ordenes = $this->ordenesDeLaSolicitud($solicitud);
        $orden = $this->ordenDelProveedor($solicitud, $ordenes, $partnerId);

        if ($orden === null) {
            return [...$vacio, 'motivo' => sprintf(
                '%s no tiene orden en Odoo para esta solicitud. Usa «Comprarle a…» para crearle una con lo que cotizó.',
                $lectura->supplier_name ?: 'Este proveedor',
            )];
        }

        $editable = in_array($orden['state'], self::EDITABLES, true);
        $lineas = $this->lineasDe([$orden['id'], ...array_map('intval', $solicitud->items()->whereNotNull('odoo_order_id')->pluck('odoo_order_id')->unique()->all())], $ordenes);
        $enLaOrden = array_filter($lineas, fn (array $l): bool => (int) ($l['order_id'][0] ?? 0) === $orden['id'] && empty($l['display_type'] ?? null));

        $resultado = $this->comparacion->comparar(
            $solicitud,
            array_values(is_array($lectura->extracted['items'] ?? null) ? $lectura->extracted['items'] : []),
            $partnerId,
            $lectura->parejasConfirmadas(),
            $lectura->parejasRechazadas(),
            $lectura->piezasConfirmadas(),
        );

        $usadas = [];
        $filas = [];

        foreach ($resultado->filas as $fila) {
            if ($fila->pedida === null || $fila->cotizada === null) {
                continue;
            }

            $item = $fila->pedida;
            [$linea, $como] = $this->lineaDe($item, $fila->cotizada, $enLaOrden, $lineas, $usadas, $orden['id'], $partnerId);

            if ($linea !== null) {
                $usadas[(int) $linea['id']] = true;
            }

            $filas[] = $this->fila($item, $fila->cotizada, $linea, $como, $fila->esPropuesta());
        }

        // Las piezas van a la misma orden, cada una en su línea: así las factura
        // el proveedor. Si ya está —se llevó antes—, se actualiza esa.
        $piezas = [];

        foreach ($resultado->piezas as $pieza) {
            $nombre = trim((string) ($pieza['linea']['product_service'] ?? ''));
            $texto = PurchaseProductLink::normalizar($nombre);
            $ya = array_filter($enLaOrden, fn (array $l): bool => ! isset($usadas[(int) $l['id']])
                && $texto !== '' && str_starts_with(PurchaseProductLink::normalizar((string) ($l['name'] ?? '')), $texto));
            $linea = count($ya) === 1 ? reset($ya) : null;

            if ($linea !== null) {
                $usadas[(int) $linea['id']] = true;
            }

            $nueva = ['nombre' => $nombre, 'cantidad' => $this->numero($pieza['linea']['quantity'] ?? null), 'precio' => $this->numero($pieza['linea']['unit_price'] ?? null)];
            $actual = $linea === null ? null : ['id' => (int) $linea['id'], 'nombre' => (string) ($linea['name'] ?? ''),
                'precio' => (float) ($linea['price_unit'] ?? 0), 'cantidad' => (float) ($linea['product_qty'] ?? 0)];

            $piezas[] = [
                'renglon' => (int) $pieza['renglon'],
                'item_id' => (int) $pieza['partida']->getKey(),
                'orden_partida' => (int) $pieza['partida']->sort_order,
                'pedida' => (string) $pieza['partida']->product_service,
                'nombre' => $nombre,
                'unidad' => (string) ($pieza['linea']['unit'] ?? ''),
                'propuesto' => $nueva,
                'linea' => $actual,
                'cambia' => $actual === null || $this->diferencias($actual, $nueva) !== [],
            ];
        }

        $sobrantes = [];

        foreach ($resultado->sobrantes as $sobra) {
            $sobrantes[] = [
                'renglon' => $sobra->renglon,
                'nombre' => trim((string) ($sobra->cotizada['product_service'] ?? '')),
                'cantidad' => $this->numero($sobra->cotizada['quantity'] ?? null),
                'unidad' => (string) ($sobra->cotizada['unit'] ?? ''),
                'precio' => $this->numero($sobra->cotizada['unit_price'] ?? null),
            ];
        }

        return [
            'orden' => ['id' => $orden['id'], 'name' => $orden['name'], 'state' => $orden['state'], 'editable' => $editable],
            'motivo' => $editable ? null : sprintf(
                '%s ya no es una cotización en Odoo: tiene una compra confirmada detrás y sus líneas no se tocan desde aquí.',
                $orden['name'],
            ),
            'filas' => $filas,
            'piezas' => $piezas,
            'sobrantes' => $sobrantes,
        ];
    }

    /**
     * Escribe lo que la persona dejó marcado.
     *
     * El plan se vuelve a calcular aquí, no se recibe del navegador: lo único
     * que llega del formulario es qué filas sí, cuáles sin convertir y qué
     * renglones sobrantes agregar.
     *
     * @param  list<int>|null  $filas  ids de partida; null = todas las que se pueden
     * @param  list<int>  $sinConvertir  ids de partida que van con la cantidad cotizada tal cual
     * @param  list<int>  $agregar  renglones del documento que entran como líneas nuevas
     * @param  list<int>|null  $piezas  renglones de las piezas que van; null = todas
     * @return array{actualizadas: int, agregadas: int, productos: int, motivo: ?string, orden: ?string}
     */
    public function aplicar(
        PurchaseRequest $solicitud,
        PurchaseRequestIngestion $lectura,
        ?int $partnerId,
        ?array $filas = null,
        array $sinConvertir = [],
        array $agregar = [],
        ?array $piezas = null,
    ): array {
        $plan = $this->plan($solicitud, $lectura, $partnerId);
        $nada = ['actualizadas' => 0, 'agregadas' => 0, 'productos' => 0, 'motivo' => $plan['motivo'], 'orden' => $plan['orden']['name'] ?? null];

        if ($plan['motivo'] !== null || $plan['orden'] === null) {
            return $nada;
        }

        $ordenId = $plan['orden']['id'];

        try {
            // Volver a mirar el estado justo antes: entre la vista previa y el
            // clic alguien pudo confirmarla en Odoo.
            $estado = (string) ($this->client->execute('purchase.order', 'read', [[$ordenId]], ['fields' => ['state']])[0]['state'] ?? '');

            if (! in_array($estado, self::EDITABLES, true)) {
                return [...$nada, 'motivo' => $plan['orden']['name'].' se confirmó en Odoo mientras tanto: no se tocó nada.'];
            }

            // Sin vista previa no se escribe lo que tiene las medidas cruzadas:
            // ésas las tiene que marcar una persona.
            $elegidas = array_filter($plan['filas'], fn (array $f): bool => $f['escribible']
                && ($filas === null ? ! $f['medidas_chocan'] : in_array($f['item_id'], $filas, true)));

            // Las líneas que llegaron sin producto lo reciben ahora, con el
            // nombre real del proveedor: es el único momento en que se puede
            // hacer bien.
            $sinProducto = array_values(array_unique(array_map(
                fn (array $f): string => $f['cotizada'],
                array_filter($elegidas, fn (array $f): bool => $f['linea']['product_id'] === null && $f['cotizada'] !== ''),
            )));
            $productos = $sinProducto === [] ? [] : $this->exporter->productosParaNombres($sinProducto, $partnerId, true);
            $creados = count(array_filter($productos, fn (array $p): bool => $p['creado']));

            $actualizadas = 0;

            foreach ($elegidas as $f) {
                $valores = in_array($f['item_id'], $sinConvertir, true) ? $f['sin_convertir'] : $f['propuesto'];
                $escribir = $this->diferencias($f['linea'], $valores);

                if ($f['linea']['product_id'] === null && isset($productos[$f['cotizada']])) {
                    $escribir['product_id'] = $productos[$f['cotizada']]['id'];

                    if ($productos[$f['cotizada']]['uom'] !== null) {
                        $escribir['product_uom'] = $productos[$f['cotizada']]['uom'];
                    }
                }

                if ($escribir === []) {
                    continue;
                }

                $this->client->execute('purchase.order.line', 'write', [[$f['linea']['id']], $escribir]);
                $actualizadas++;
            }

            $lasPiezas = array_filter($plan['piezas'], fn (array $p): bool => $p['cambia']
                && ($piezas === null || in_array($p['renglon'], $piezas, true)));

            foreach ($lasPiezas as $p) {
                if ($p['linea'] !== null && ($escribir = $this->diferencias($p['linea'], $p['propuesto'])) !== []) {
                    $this->client->execute('purchase.order.line', 'write', [[$p['linea']['id']], $escribir]);
                    $actualizadas++;
                }
            }

            $nuevas = [
                ...array_values(array_map(fn (array $p): array => $p['propuesto'], array_filter($lasPiezas, fn (array $p): bool => $p['linea'] === null))),
                ...array_values(array_filter($plan['sobrantes'], fn (array $s): bool => $s['renglon'] !== null && in_array((int) $s['renglon'], $agregar, true))),
            ];

            [$agregadas, $nuevos] = $this->agregarLineas($ordenId, $nuevas, $partnerId);

            return [
                'actualizadas' => $actualizadas,
                'agregadas' => $agregadas,
                'productos' => $creados + $nuevos,
                'motivo' => null,
                'orden' => $plan['orden']['name'],
            ];
        } catch (Throwable $e) {
            Log::warning('No se pudo llevar la cotización a Odoo.', [
                'folio' => $solicitud->folio,
                'orden' => $ordenId,
                'motivo' => $e->getMessage(),
            ]);

            return [...$nada, 'motivo' => 'Odoo no aceptó el cambio: '.$e->getMessage()];
        }
    }

    /**
     * Las órdenes de Odoo que son de esta solicitud: la que creó Laravel, las
     * de sus partidas, sus alternativas y las que alguien hizo allá con el
     * folio en el origen.
     *
     * @return array<int, array<string, mixed>> por id
     */
    private function ordenesDeLaSolicitud(PurchaseRequest $solicitud): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', [
            (int) $solicitud->odoo_order_id,
            ...$solicitud->items()->whereNotNull('odoo_order_id')->pluck('odoo_order_id')->all(),
        ]))));

        $criterio = [['origin', 'ilike', (string) $solicitud->folio]];

        if ($ids !== []) {
            $criterio = ['|', ['id', 'in', $ids], ...$criterio];
        }

        $ordenes = $this->porId($this->client->execute('purchase.order', 'search_read', [$criterio], ['fields' => self::CAMPOS_ORDEN]));

        $grupos = array_values(array_unique(array_filter(array_map(
            fn (array $o): int => is_array($o['purchase_group_id'] ?? null) ? (int) $o['purchase_group_id'][0] : 0,
            $ordenes,
        ))));

        if ($grupos !== []) {
            $ordenes += $this->porId($this->client->execute(
                'purchase.order', 'search_read', [[['purchase_group_id', 'in', $grupos]]], ['fields' => self::CAMPOS_ORDEN],
            ));
        }

        return $ordenes;
    }

    /**
     * La orden del proveedor de la cotización.
     *
     * Si tiene varias, gana la que todavía es cotización, y entre ésas la que
     * ya lleva las partidas de esta solicitud.
     *
     * @param  array<int, array<string, mixed>>  $ordenes
     * @return array{id: int, name: string, state: string}|null
     */
    private function ordenDelProveedor(PurchaseRequest $solicitud, array $ordenes, int $partnerId): ?array
    {
        $dondeViven = array_map('intval', $solicitud->items()->whereNotNull('odoo_order_id')->pluck('odoo_order_id')->all());

        $suyas = array_filter($ordenes, fn (array $o): bool => is_array($o['partner_id'] ?? null)
            && (int) $o['partner_id'][0] === $partnerId
            && ($o['state'] ?? '') !== 'cancel');

        if ($suyas === []) {
            return null;
        }

        uasort($suyas, fn (array $a, array $b): int => [
            in_array($b['state'], self::EDITABLES, true), in_array((int) $b['id'], $dondeViven, true), -(int) $b['id'],
        ] <=> [
            in_array($a['state'], self::EDITABLES, true), in_array((int) $a['id'], $dondeViven, true), -(int) $a['id'],
        ]);

        $orden = reset($suyas);

        return ['id' => (int) $orden['id'], 'name' => (string) $orden['name'], 'state' => (string) $orden['state']];
    }

    /**
     * Las líneas de esas órdenes, en una sola lectura.
     *
     * @param  list<int>  $ids
     * @param  array<int, array<string, mixed>>  $ordenes
     * @return array<int, array<string, mixed>> por id de línea
     */
    private function lineasDe(array $ids, array $ordenes): array
    {
        $lineas = [];

        foreach (array_unique($ids) as $id) {
            foreach ((array) ($ordenes[$id]['order_line'] ?? []) as $linea) {
                $lineas[] = (int) $linea;
            }
        }

        if ($lineas === []) {
            return [];
        }

        return $this->porId($this->client->execute('purchase.order.line', 'read', [array_values(array_unique($lineas))], ['fields' => self::CAMPOS_LINEA]));
    }

    /**
     * Qué línea de la orden le toca a esta partida, y cómo se supo.
     *
     * @param  array<int, array<string, mixed>>  $enLaOrden
     * @param  array<int, array<string, mixed>>  $todas
     * @param  array<int, true>  $usadas
     * @return array{0: ?array<string, mixed>, 1: ?string}
     */
    private function lineaDe(mixed $item, array $cotizada, array $enLaOrden, array $todas, array $usadas, int $orden, int $partnerId): array
    {
        $libres = array_filter($enLaOrden, fn (array $l): bool => ! isset($usadas[(int) $l['id']]));
        $guardada = (int) $item->odoo_line_id;

        // 1. La línea que la partida guardó cuando viajó a esta misma orden.
        if ((int) $item->odoo_order_id === $orden && isset($libres[$guardada])) {
            return [$libres[$guardada], 'guardada'];
        }

        // 2. El producto de su línea en otra orden: las alternativas de Odoo
        //    copian las líneas con el mismo producto.
        $producto = $this->productoDe($todas[$guardada] ?? null)
            ?? $this->exporter->productoDe($item, $partnerId);

        if ($producto !== null) {
            $conEse = array_filter($libres, fn (array $l): bool => $this->productoDe($l) === $producto);

            if (count($conEse) === 1) {
                return [reset($conEse), 'producto'];
            }
        }

        // 3. Por el texto, sólo si es idéntico y no se repite.
        $textos = array_filter([
            PurchaseProductLink::normalizar((string) $item->product_service),
            PurchaseProductLink::normalizar($this->exporter->descripcionDe($item)),
            PurchaseProductLink::normalizar((string) ($cotizada['product_service'] ?? '')),
        ]);
        $conEseTexto = array_filter($libres, fn (array $l): bool => in_array(PurchaseProductLink::normalizar((string) ($l['name'] ?? '')), $textos, true));

        return count($conEseTexto) === 1 ? [reset($conEseTexto), 'texto'] : [null, null];
    }

    /**
     * Una fila de la vista previa: lo pedido, lo cotizado, la línea de Odoo
     * como está y como quedaría.
     *
     * @param  array<string, mixed>  $cotizada
     * @param  array<string, mixed>|null  $linea
     * @return array<string, mixed>
     */
    private function fila(mixed $item, array $cotizada, ?array $linea, ?string $como, bool $propuesta): array
    {
        $nombre = trim((string) ($cotizada['product_service'] ?? ''));
        $precio = $this->numero($cotizada['unit_price'] ?? null);
        $cantidad = $this->numero($cotizada['quantity'] ?? null);
        $pedida = (float) $item->quantity;
        $unidad = trim((string) ($cotizada['unit'] ?? ''));

        $porMedida = $this->sePideEnUnidadesYSeCotizaPorMedida($item, $nombre, $unidad)
            && $cantidad !== null && $pedida > 0 && abs($cantidad - $pedida) > 0.0001;

        $sinConvertir = ['nombre' => $nombre ?: null, 'precio' => $precio, 'cantidad' => $cantidad];

        // Mismo total, en la unidad de la orden: 2 tubos de 6 m a 56.446 el
        // metro son 2 tubos a 338.676.
        $propuesto = $porMedida && $precio !== null
            ? [
                'nombre' => $nombre.' · '.$this->cantidadLegible($cantidad).' '.$this->medida($nombre, $unidad).' cotizados',
                'precio' => round($precio * $cantidad / $pedida, 2),
                'cantidad' => $pedida,
            ]
            : $sinConvertir;

        $actual = $linea === null ? null : [
            'id' => (int) $linea['id'],
            'nombre' => (string) ($linea['name'] ?? ''),
            'precio' => (float) ($linea['price_unit'] ?? 0),
            'cantidad' => (float) ($linea['product_qty'] ?? 0),
            'unidad' => is_array($linea['product_uom'] ?? null) ? (string) $linea['product_uom'][1] : '',
            'product_id' => $this->productoDe($linea),
        ];

        return [
            'item_id' => (int) $item->getKey(),
            'orden_partida' => (int) $item->sort_order,
            'pedida' => (string) $item->product_service,
            'cantidad_pedida' => $pedida,
            'unidad_pedida' => (string) $item->unit,
            'cotizada' => $nombre,
            'cantidad_cotizada' => $cantidad,
            'unidad_cotizada' => $unidad,
            'precio_cotizado' => $precio,
            'linea' => $actual,
            'como' => $como,
            'propuesta' => $propuesta,
            'medidas_chocan' => QuoteMeasures::chocan((string) $item->product_service.' '.(string) $item->specification, $nombre),
            'por_medida' => $porMedida,
            'propuesto' => $propuesto,
            'sin_convertir' => $sinConvertir,
            'cambia' => $actual !== null && $this->diferencias($actual, $propuesto) !== [],
            // Una pareja que nadie confirmó no escribe nada en Odoo.
            'escribible' => $actual !== null && ! $propuesta,
        ];
    }

    /**
     * Lo que hay que escribir para que la línea diga eso. Escribir el mismo
     * valor ensucia el historial de Odoo sin cambiar nada.
     *
     * @param  array<string, mixed>  $actual
     * @param  array{nombre: ?string, precio: ?float, cantidad: ?float}  $nuevo
     * @return array<string, mixed>
     */
    private function diferencias(array $actual, array $nuevo): array
    {
        $escribir = [];

        if ($nuevo['precio'] !== null && abs((float) $actual['precio'] - $nuevo['precio']) >= 0.005) {
            $escribir['price_unit'] = $nuevo['precio'];
        }

        if (filled($nuevo['nombre']) && trim((string) $actual['nombre']) !== trim((string) $nuevo['nombre'])) {
            $escribir['name'] = trim((string) $nuevo['nombre']);
        }

        if ($nuevo['cantidad'] !== null && abs((float) $actual['cantidad'] - $nuevo['cantidad']) >= 0.0001) {
            $escribir['product_qty'] = $nuevo['cantidad'];
        }

        return $escribir;
    }

    /**
     * Agrega a la orden líneas nuevas: las piezas que el proveedor cotizó
     * aparte —el canastillo de la válvula de pie— y lo que agregó por su
     * cuenta y la persona marcó.
     *
     * @param  list<array{nombre: string, cantidad: ?float, precio: ?float}>  $nuevas
     * @return array{0: int, 1: int} líneas agregadas y productos creados
     */
    private function agregarLineas(int $orden, array $nuevas, int $partnerId): array
    {
        $elegidos = array_values(array_filter($nuevas, fn (array $s): bool => $s['nombre'] !== ''));

        if ($elegidos === []) {
            return [0, 0];
        }

        $productos = $this->exporter->productosParaNombres(array_column($elegidos, 'nombre'), $partnerId, true);

        // El impuesto de sus hermanas: una línea nueva sin IVA dejaría la
        // orden un 19% más barata de lo que es.
        $hermana = $this->client->execute('purchase.order.line', 'search_read', [[['order_id', '=', $orden], ['display_type', '=', false]]], ['fields' => ['taxes_id', 'date_planned'], 'limit' => 1]);
        $impuestos = (array) ($hermana[0]['taxes_id'] ?? []);

        $agregadas = 0;

        foreach ($elegidos as $s) {
            $producto = $productos[$s['nombre']] ?? null;

            if ($producto === null) {
                continue;
            }

            $linea = [
                'order_id' => $orden,
                'product_id' => $producto['id'],
                'name' => $s['nombre'],
                'product_qty' => $s['cantidad'] ?? 1.0,
                'price_unit' => $s['precio'] ?? 0.0,
                'taxes_id' => [[6, 0, array_map('intval', $impuestos)]],
            ];

            if ($producto['uom'] !== null) {
                $linea['product_uom'] = $producto['uom'];
            }

            if (! empty($hermana[0]['date_planned'] ?? null)) {
                $linea['date_planned'] = $hermana[0]['date_planned'];
            }

            $this->client->execute('purchase.order.line', 'create', [$linea]);
            $agregadas++;
        }

        return [$agregadas, count(array_filter($productos, fn (array $p): bool => $p['creado']))];
    }

    /**
     * ¿Se pidió por unidades y se cotizó por metro o litro? «CAÑERIA ACERO 10"
     * 5 MM - MT SOLDAR, 12 mts» contra «2 tubos». A veces la unidad no viene
     * en su columna y sólo la dice el nombre: «PERFIL 50x50x3MM - MT, u. 6».
     */
    private function sePideEnUnidadesYSeCotizaPorMedida(mixed $item, string $nombre, string $unidad): bool
    {
        $pedida = Str::lower(Str::ascii(trim((string) $item->unit)));

        if (! in_array($pedida, ['', 'unidades', 'unidad', 'un', 'u', 'units'], true)) {
            return false;
        }

        return $this->medida($nombre, $unidad) !== null;
    }

    /** «m» o «L» si la cotización es por medida, null si es por unidad. */
    private function medida(string $nombre, string $unidad): ?string
    {
        $u = Str::lower(Str::ascii(trim($unidad)));

        if (in_array($u, ['metros', 'metro', 'mts', 'mt', 'm'], true)) {
            return 'm';
        }

        if (in_array($u, ['litros', 'litro', 'lts', 'lt', 'l'], true)) {
            return 'L';
        }

        if (preg_match('/(?<![a-z0-9])(mts?|metros?)(?![a-z0-9])/i', Str::ascii($nombre))) {
            return 'm';
        }

        if (preg_match('/(?<![a-z0-9])(lts?|litros?)(?![a-z0-9])/i', Str::ascii($nombre))) {
            return 'L';
        }

        return null;
    }

    /** @param array<string, mixed>|null $linea */
    private function productoDe(?array $linea): ?int
    {
        return is_array($linea['product_id'] ?? null) ? (int) $linea['product_id'][0] : (isset($linea['product_id']) && is_int($linea['product_id']) ? $linea['product_id'] : null);
    }

    private function numero(mixed $valor): ?float
    {
        if (is_string($valor)) {
            return ChileanMoney::parse($valor);
        }

        return is_numeric($valor) ? (float) $valor : null;
    }

    private function cantidadLegible(float $cantidad): string
    {
        return rtrim(rtrim(number_format($cantidad, 2, ',', '.'), '0'), ',');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function porId(mixed $filas): array
    {
        $porId = [];

        foreach (is_array($filas) ? $filas : [] as $fila) {
            $porId[(int) $fila['id']] = $fila;
        }

        return $porId;
    }
}
