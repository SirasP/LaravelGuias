<?php

namespace App\Services\PurchaseRequests\Odoo;

use App\Enums\PurchaseRequestStatus;
use App\Models\OdooProduct;
use App\Models\PurchaseProductLink;
use App\Models\PurchaseRequest;
use App\Models\PurchaseSupplier;
use App\Models\UnitOfMeasure;
use App\Services\PurchaseRequests\Products\ProductMatcher;
use App\Services\PurchaseRequests\Products\ProductSimilarity;
use App\Support\Rut;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Exporta una solicitud aprobada como RFQ borrador en Odoo.
 *
 * Las reglas que cumple están escritas en el puerto. Las tres que más importan:
 *
 *  - Crea la cotización en borrador y no la toca más. Confirmar, recibir y
 *    facturar son decisiones que se toman dentro de Odoo, por una persona.
 *  - Es idempotente: si la solicitud ya tiene una RFQ vinculada, no crea otra.
 *    Un segundo clic dejaría dos cotizaciones para la misma compra y nadie
 *    sabría cuál vale.
 *  - No inventa proveedores ni productos. Si el RUT no está en Odoo, se
 *    detiene y lo dice; crear un proveedor a medias ensucia un sistema que la
 *    empresa usa de verdad.
 */
class OdooPurchaseRequestExporter implements PurchaseRequestExporter
{
    public function __construct(
        private readonly OdooClient $client,
        private readonly ProductMatcher $emparejador = new ProductMatcher(new ProductSimilarity),
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('purchase_requests.odoo.enabled')
            && filled(config('purchase_requests.odoo.url'))
            && filled(config('purchase_requests.odoo.db'));
    }

    public function exportApproved(PurchaseRequest $purchaseRequest): PurchaseRequestExportResult
    {
        if (! $this->isEnabled()) {
            return PurchaseRequestExportResult::skipped('La integración con Odoo está apagada en este entorno.');
        }

        if ($purchaseRequest->status !== PurchaseRequestStatus::APPROVED) {
            return PurchaseRequestExportResult::skipped('Sólo se exportan solicitudes aprobadas.');
        }

        if (filled($purchaseRequest->odoo_order_id)) {
            return PurchaseRequestExportResult::alreadyExported(
                (string) ($purchaseRequest->odoo_reference ?: $purchaseRequest->odoo_order_id),
            );
        }

        $purchaseRequest->loadMissing('items');

        if ($purchaseRequest->items->isEmpty()) {
            return PurchaseRequestExportResult::skipped('La solicitud no tiene partidas que exportar.');
        }

        try {
            $proveedor = $this->buscarProveedor($purchaseRequest);

            if ($proveedor === null) {
                // Nadie escribe el nombre legal completo: se pide «Vicat» y en
                // Odoo está «ARIDOS VICAT SUR SPA». Se ofrecen los parecidos
                // para que una persona diga cuál, en vez de acertar solos.
                $candidatos = $this->candidatos($purchaseRequest);

                // Que nadie haya escrito el proveedor y que Odoo no lo
                // reconozca son cosas distintas, y decirlas igual mandaba a
                // dar de alta en Odoo a alguien que sólo tenía que elegir.
                if ((array) ($purchaseRequest->suggested_suppliers ?? []) === []) {
                    return PurchaseRequestExportResult::needsSupplier(
                        'Esta solicitud no dice a quién comprarle. Busca el proveedor aquí abajo y elígelo: '
                            .'con eso se crea la cotización en Odoo.',
                        [],
                    );
                }

                return PurchaseRequestExportResult::needsSupplier(
                    $candidatos === []
                        ? 'Odoo no tiene ningún proveedor que se parezca a «'
                            .Str::limit((string) collect($purchaseRequest->suggested_suppliers)->first(), 40)
                            .'». Búscalo aquí abajo; si tampoco aparece, hay que darlo de alta en Odoo.'
                        : 'Falta decir cuál es el proveedor en Odoo antes de crear la cotización.',
                    $candidatos,
                );
            }

            [$id, $referencia] = $this->crearRfq($purchaseRequest, $proveedor);
        } catch (Throwable $e) {
            Log::warning('No se pudo exportar una solicitud a Odoo.', [
                'folio' => $purchaseRequest->folio,
                'motivo' => $e->getMessage(),
            ]);

            return PurchaseRequestExportResult::failed('Odoo no aceptó la solicitud: '.$e->getMessage());
        }

        $purchaseRequest->forceFill([
            'odoo_order_id' => $id,
            'odoo_reference' => $referencia,
            'odoo_exported_at' => now(),
        ])->save();

        // Los PDF del proveedor viajan con la orden. Va después de guardar el
        // vínculo y aparte del try de arriba a propósito: la cotización ya
        // existe en Odoo, y que falle un adjunto no puede deshacerla ni hacer
        // creer que no se creó. Si falla, queda en el log y se adjunta a mano.
        $adjuntados = $this->adjuntarCotizaciones($purchaseRequest, $id);

        return PurchaseRequestExportResult::created(
            $referencia,
            'Se creó la cotización '.$referencia.' en Odoo, en borrador y sin confirmar.'
                .($adjuntados > 0
                    ? ' Se adjuntaron '.$adjuntados.' '.Str::plural('cotización', $adjuntados).' del proveedor.'
                    : ''),
        );
    }

    /**
     * Cuelga de la orden de Odoo los documentos que mandó el proveedor.
     *
     * Es lo que alguien ya venía haciendo a mano: la orden 235 tiene pegada
     * su cotización. Se escribe un `ir.attachment` sobre la orden que este
     * mismo programa acaba de crear; no se toca ningún producto ni proveedor.
     *
     * @return int cuántos quedaron colgados
     */
    private function adjuntarCotizaciones(PurchaseRequest $purchaseRequest, int $ordenId): int
    {
        $colgados = 0;

        foreach ($purchaseRequest->receivedQuotes()->get() as $cotizacion) {
            try {
                $disco = Storage::disk($cotizacion->disk);

                if (! $disco->exists($cotizacion->path)) {
                    continue;
                }

                $this->client->execute('ir.attachment', 'create', [[
                    'name' => $cotizacion->original_name,
                    'type' => 'binary',
                    'datas' => base64_encode($disco->get($cotizacion->path)),
                    'res_model' => 'purchase.order',
                    'res_id' => $ordenId,
                    'mimetype' => $cotizacion->mime_type,
                ]]);

                $colgados++;
            } catch (Throwable $e) {
                Log::warning('No se pudo adjuntar una cotización a la orden de Odoo.', [
                    'folio' => $purchaseRequest->folio,
                    'orden' => $ordenId,
                    'archivo' => $cotizacion->original_name,
                    'motivo' => $e->getMessage(),
                ]);
            }
        }

        return $colgados;
    }

    /**
     * Busca el proveedor por RUT.
     *
     * Odoo guarda el RUT sin puntos y con guion —«77045469-7»—, que es
     * exactamente como lo normaliza el catálogo. Buscar con puntos no
     * devuelve nada, y de ahí saldría un «no existe» que sí existe.
     */
    private function buscarProveedor(PurchaseRequest $purchaseRequest): ?int
    {
        $rut = $this->rutDelProveedor($purchaseRequest);

        if ($rut === null) {
            return null;
        }

        $encontrados = $this->client->execute(
            'res.partner',
            'search',
            [[['vat', '=', $rut]]],
            ['limit' => 1],
        );

        return is_array($encontrados) && $encontrados !== [] ? (int) $encontrados[0] : null;
    }

    /**
     * Proveedores de Odoo que se parecen a lo escrito en la solicitud.
     *
     * Busca por cada palabra con peso: «Vicat» encuentra «ARIDOS VICAT SUR
     * SPA». Se limita a quienes ya son proveedores para no ofrecer clientes.
     *
     * @return list<array{id: int, name: string, vat: string|null}>
     */
    public function candidatos(PurchaseRequest $purchaseRequest): array
    {
        $encontrados = [];

        foreach ((array) ($purchaseRequest->suggested_suppliers ?? []) as $sugerido) {
            foreach ($this->palabrasConPeso((string) $sugerido) as $palabra) {
                $filas = $this->client->execute(
                    'res.partner',
                    'search_read',
                    [[['name', 'ilike', $palabra], ['supplier_rank', '>', 0]]],
                    ['fields' => ['id', 'name', 'vat'], 'limit' => 5],
                );

                foreach (is_array($filas) ? $filas : [] as $fila) {
                    $encontrados[(int) $fila['id']] = [
                        'id' => (int) $fila['id'],
                        'name' => (string) $fila['name'],
                        'vat' => filled($fila['vat'] ?? null) ? (string) $fila['vat'] : null,
                    ];
                }
            }
        }

        return array_values($encontrados);
    }

    /**
     * Las palabras que sirven para buscar.
     *
     * Fuera las formas societarias y las de menos de cuatro letras: buscar
     * «SPA» devolvería medio Odoo.
     *
     * @return list<string>
     */
    private function palabrasConPeso(string $texto): array
    {
        $ruido = ['spa', 'ltda', 'limitada', 'sociedad', 'comercial', 'servicios', 'rut'];
        $palabras = [];

        foreach (preg_split('/[^\p{L}\p{N}]+/u', Str::lower($texto)) ?: [] as $palabra) {
            if (mb_strlen($palabra) >= 4 && ! in_array($palabra, $ruido, true)) {
                $palabras[$palabra] = true;
            }
        }

        return array_keys($palabras);
    }

    /**
     * Busca proveedores en Odoo por nombre o por RUT.
     *
     * Es la salida cuando lo que el sistema propone no sirve: nombres de
     * fantasía, razones sociales que no se parecen a como se le llama, o un
     * proveedor cargado con otro nombre. Buscar lo hace una persona, que sabe
     * a quién está buscando.
     *
     * @return list<array{id: int, name: string, vat: string|null}>
     */
    public function buscarProveedores(string $texto): array
    {
        $texto = trim($texto);

        if ($texto === '') {
            return [];
        }

        // Si escribió un RUT, se busca por RUT: es exacto y no se presta a
        // confusión con nombres parecidos.
        $rut = Rut::normalize($texto);

        $criterio = $rut !== null && Rut::isValid($rut)
            ? [['vat', '=', $rut]]
            : [['name', 'ilike', $texto], ['supplier_rank', '>', 0]];

        $filas = $this->client->execute(
            'res.partner',
            'search_read',
            [$criterio],
            ['fields' => ['id', 'name', 'vat'], 'limit' => 15, 'order' => 'name'],
        );

        return array_map(fn (array $f): array => [
            'id' => (int) $f['id'],
            'name' => (string) $f['name'],
            'vat' => filled($f['vat'] ?? null) ? (string) $f['vat'] : null,
        ], is_array($filas) ? $filas : []);
    }

    /**
     * Busca productos en Odoo, en vivo.
     *
     * La copia local sirve para proponer candidatos sin llamar a nadie, pero
     * envejece hasta la sincronización de la noche. Cuando una persona busca a
     * mano es porque sabe qué quiere —muchas veces algo que acaba de crear en
     * Odoo—, y responderle «no existe» porque nuestra copia es de anoche es
     * justo lo contrario de ayudar.
     *
     * Lo encontrado se guarda en la copia, así que buscar también la pone al
     * día para el emparejado automático de la próxima vez.
     *
     * @return list<array{odoo_id: int, name: string, score: float, reason: string}>
     */
    public function buscarProductos(string $texto): array
    {
        $texto = trim($texto);

        if ($texto === '') {
            return [];
        }

        $filas = $this->client->execute('product.product', 'search_read', [[
            '|', '|',
            ['name', 'ilike', $texto],
            ['default_code', 'ilike', $texto],
            ['barcode', '=', $texto],
        ]], [
            'fields' => ['id', 'name', 'default_code', 'barcode', 'uom_id',
                'type', 'is_storable', 'purchase_ok', 'active'],
            'limit' => 20,
            'order' => 'name',
        ]);

        $encontrados = [];

        foreach (is_array($filas) ? $filas : [] as $fila) {
            $this->guardarEnLaCopia($fila);

            $encontrados[] = [
                'odoo_id' => (int) $fila['id'],
                'name' => (string) ($fila['name'] ?? ''),
                'score' => 1.0,
                'reason' => 'Encontrado en Odoo ahora mismo',
            ];
        }

        return $encontrados;
    }

    /**
     * Lo recién visto en Odoo entra en la copia local.
     *
     * Así un producto creado allá hace un minuto queda disponible para el
     * emparejado automático sin esperar a la sincronización de la noche.
     *
     * @param  array<string, mixed>  $fila
     */
    private function guardarEnLaCopia(array $fila): void
    {
        OdooProduct::query()->updateOrCreate(
            ['odoo_id' => (int) $fila['id']],
            [
                'name' => (string) ($fila['name'] ?? ''),
                'default_code' => is_string($fila['default_code'] ?? null) ? $fila['default_code'] : null,
                'barcode' => is_string($fila['barcode'] ?? null) ? $fila['barcode'] : null,
                'uom_id' => is_array($fila['uom_id'] ?? null) ? (int) $fila['uom_id'][0] : null,
                'uom_name' => is_array($fila['uom_id'] ?? null) ? (string) $fila['uom_id'][1] : null,
                'type' => is_string($fila['type'] ?? null) ? $fila['type'] : null,
                'is_storable' => (bool) ($fila['is_storable'] ?? false),
                'purchase_ok' => (bool) ($fila['purchase_ok'] ?? true),
                'active_in_odoo' => (bool) ($fila['active'] ?? true),
                // Si estaba marcado como desaparecido y Odoo lo devuelve, ya no lo está.
                'missing_since' => null,
                'synced_at' => now(),
            ],
        );
    }

    /**
     * El proveedor que ya está en el catálogo, sin preguntarle a Odoo.
     *
     * Sirve para que la pantalla sepa si falta elegirlo antes de que nadie
     * apriete nada. Mira sólo la base local, así que se puede llamar al
     * dibujar la página sin gastar una llamada por visita.
     */
    public function proveedorConocido(PurchaseRequest $purchaseRequest): ?PurchaseSupplier
    {
        $rut = $this->rutDelProveedor($purchaseRequest);

        if ($rut === null) {
            return null;
        }

        return PurchaseSupplier::query()
            ->whereNotNull('odoo_partner_id')
            ->where('tax_id', $rut)
            ->first();
    }

    private function rutDelProveedor(PurchaseRequest $purchaseRequest): ?string
    {
        foreach ((array) ($purchaseRequest->suggested_suppliers ?? []) as $sugerido) {
            // findAll devuelve ['rut' => …, 'posicion' => …], ya normalizado.
            $ruts = Rut::findAll((string) $sugerido);

            if ($ruts !== []) {
                return (string) $ruts[0]['rut'];
            }

            // El proveedor pudo escribirse sólo por nombre; el RUT está en el
            // catálogo, que es donde se registró al leer la cotización.
            $escrito = Str::lower(trim((string) $sugerido));

            $delCatalogo = PurchaseSupplier::query()
                ->where(function ($q) use ($escrito): void {
                    // Por su nombre, o por cualquiera de los alias con que ya
                    // se confirmó antes: quien escribió «Vicat» una vez y lo
                    // resolvió, no debería volver a resolverlo nunca.
                    $q->whereRaw('LOWER(name) = ?', [$escrito])
                        ->orWhereJsonContains('aliases', $escrito);
                })
                ->value('tax_id');

            if (filled($delCatalogo)) {
                return Rut::normalize((string) $delCatalogo);
            }
        }

        return null;
    }

    /**
     * Los productos emparejados que Odoo todavía reconoce.
     *
     * La copia local puede estar vieja: si alguien fusionó o archivó un
     * producto allá desde la última sincronización, mandarlo haría que Odoo
     * rechazara la orden entera. Se pregunta en el momento de exportar, que es
     * el único instante en que la respuesta no puede quedarse anticuada.
     *
     * Una sola llamada para todas las partidas, no una por línea.
     *
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function productosQueOdooConfirma(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));

        if ($ids === []) {
            return [];
        }

        $vivos = $this->client->execute(
            'product.product',
            'search',
            [[['id', 'in', $ids]]],
            ['context' => ['active_test' => true]],
        );

        return is_array($vivos) ? array_map('intval', $vivos) : [];
    }

    /**
     * Resuelve los nombres reales del proveedor contra el catálogo de Odoo.
     *
     * Nunca habíamos buscado por este nombre. La solicitud dice «Corchetera» y
     * eso es lo único que se buscaba; el nombre de verdad —«CORCHETERA
     * PLASTICA 20 HJ 24081 AUCA TOR111»— sólo aparece cuando llega la
     * cotización, y con él tres de las ocho partidas de la SC-2026-000029
     * resultaron estar ya en Odoo.
     *
     * El orden importa y va de lo barato a lo caro:
     *
     * 1. El alias que alguien enseñó. Instantáneo, y no se vuelve a preguntar.
     * 2. La copia local del catálogo. Siete milisegundos sobre 2.403 productos.
     * 3. Odoo en vivo, **todos los nombres en una sola consulta**. La copia
     *    local se sincroniza de noche, así que un producto creado hoy allá es
     *    invisible aquí: sin este paso se crearían duplicados de cosas que ya
     *    existen. Medido: pasó con tres de ocho el primer día.
     * 4. Crear, sólo lo que de verdad no está.
     *
     * Lo que se encuentra o se crea se guarda en la copia local y como alias,
     * para que la próxima cotización de ese proveedor cruce sola.
     *
     * @param  list<string>  $nombres
     * @return array<string, array{id: int, uom: ?int, creado: bool}> indexado por el nombre
     */
    public function productosParaNombres(array $nombres, ?int $partnerId = null, bool $crearSiFalta = false): array
    {
        $pendientes = [];
        $resueltos = [];

        foreach ($nombres as $nombre) {
            $nombre = trim($nombre);

            if ($nombre === '' || isset($resueltos[$nombre]) || isset($pendientes[$nombre])) {
                continue;
            }

            // 1. Lo que alguien ya enseñó.
            $enlace = PurchaseProductLink::para($nombre, $partnerId);

            if ($enlace?->odoo_product_id !== null) {
                $resueltos[$nombre] = ['id' => (int) $enlace->odoo_product_id, 'uom' => null, 'creado' => false];

                continue;
            }

            // 2. La copia local, por nombre exacto.
            $local = OdooProduct::query()->usable()
                ->whereRaw('LOWER(name) = ?', [Str::lower($nombre)])
                ->first(['odoo_id', 'uom_id']);

            if ($local !== null) {
                $resueltos[$nombre] = ['id' => (int) $local->odoo_id, 'uom' => $local->uom_id, 'creado' => false];

                continue;
            }

            $pendientes[$nombre] = true;
        }

        if ($pendientes !== []) {
            $resueltos += $this->buscandoEnOdoo(array_keys($pendientes));
        }

        if ($crearSiFalta) {
            foreach (array_keys($pendientes) as $nombre) {
                if (! isset($resueltos[$nombre])) {
                    $creado = $this->crearProducto($nombre);

                    if ($creado !== null) {
                        $resueltos[$nombre] = $creado;
                    }
                }
            }
        }

        return $resueltos;
    }

    /**
     * Los nombres que faltan, preguntados a Odoo en una sola consulta.
     *
     * Ocho consultas seguidas serían dieciséis segundos; una con los ocho
     * nombres juntos son dos. Se exige el nombre exacto: un parecido no basta
     * para dar por hecho que son el mismo producto, y ésa es la regla de todo
     * el módulo.
     *
     * @param  list<string>  $nombres
     * @return array<string, array{id: int, uom: ?int, creado: bool}>
     */
    private function buscandoEnOdoo(array $nombres): array
    {
        $criterio = [];

        for ($i = 0; $i < count($nombres) - 1; $i++) {
            $criterio[] = '|';
        }

        foreach ($nombres as $nombre) {
            $criterio[] = ['name', '=', $nombre];
        }

        $filas = $this->client->execute('product.product', 'search_read', [$criterio], [
            'fields' => ['id', 'name', 'default_code', 'barcode', 'uom_id', 'type', 'is_storable', 'purchase_ok', 'active'],
            'limit' => max(count($nombres) * 2, 20),
        ]);

        $encontrados = [];

        foreach (is_array($filas) ? $filas : [] as $fila) {
            foreach ($nombres as $nombre) {
                if (Str::lower(trim((string) $fila['name'])) !== Str::lower($nombre)) {
                    continue;
                }

                $this->anotarEnLaCopiaLocal($fila);
                $encontrados[$nombre] = [
                    'id' => (int) $fila['id'],
                    'uom' => is_array($fila['uom_id'] ?? null) ? (int) $fila['uom_id'][0] : null,
                    'creado' => false,
                ];
            }
        }

        return $encontrados;
    }

    /**
     * Da de alta el producto en Odoo, con lo que Sebastián pone siempre.
     *
     * Categoría y unidad las deja Odoo por defecto —«All» y «Units», que es
     * donde ya está el 96% de su catálogo—, y el rastreo de inventario va
     * encendido, que es lo que él marca a mano cada vez.
     *
     * @return array{id: int, uom: ?int, creado: bool}|null
     */
    private function crearProducto(string $nombre): ?array
    {
        if (! (bool) config('purchase_requests.odoo.create_missing_products', true)) {
            return null;
        }

        // La unidad va dicha y no heredada del valor por defecto de Odoo: el
        // nombre del producto a veces menciona un peso —«PGAL10300150 (peso
        // …)»— y de ahí a que la ficha naciera en kilos hay un paso. Se compra
        // por unidades, que es donde está el 96% del catálogo.
        $unidad = (int) config('purchase_requests.odoo.default_uom_id', 1);

        $id = $this->client->execute('product.product', 'create', [[
            'name' => $nombre,
            'type' => 'consu',
            'is_storable' => true,
            'purchase_ok' => true,
            'uom_id' => $unidad,
            'uom_po_id' => $unidad,
        ]]);

        if (! is_numeric($id)) {
            return null;
        }

        $leido = $this->client->execute('product.product', 'read', [[(int) $id]], [
            'fields' => ['id', 'name', 'default_code', 'barcode', 'uom_id', 'type', 'is_storable', 'purchase_ok', 'active'],
        ]);

        if (is_array($leido) && isset($leido[0])) {
            $this->anotarEnLaCopiaLocal($leido[0]);
        }

        Log::info('Se creó un producto en Odoo desde una cotización.', ['odoo_id' => (int) $id, 'nombre' => $nombre]);

        return [
            'id' => (int) $id,
            'uom' => is_array($leido[0]['uom_id'] ?? null) ? (int) $leido[0]['uom_id'][0] : null,
            'creado' => true,
        ];
    }

    /**
     * Guarda el producto en la copia local, para no volver a salir a Odoo.
     *
     * @param  array<string, mixed>  $fila
     */
    private function anotarEnLaCopiaLocal(array $fila): void
    {
        OdooProduct::query()->updateOrCreate(
            ['odoo_id' => (int) $fila['id']],
            [
                'name' => (string) $fila['name'],
                'default_code' => filled($fila['default_code'] ?? null) ? (string) $fila['default_code'] : null,
                'barcode' => filled($fila['barcode'] ?? null) ? (string) $fila['barcode'] : null,
                'uom_id' => is_array($fila['uom_id'] ?? null) ? (int) $fila['uom_id'][0] : null,
                'uom_name' => is_array($fila['uom_id'] ?? null) ? (string) $fila['uom_id'][1] : null,
                'type' => (string) ($fila['type'] ?? 'consu'),
                'is_storable' => (bool) ($fila['is_storable'] ?? false),
                'purchase_ok' => (bool) ($fila['purchase_ok'] ?? true),
                'active_in_odoo' => (bool) ($fila['active'] ?? true),
                'missing_since' => null,
                'synced_at' => now(),
            ],
        );
    }

    /**
     * El texto con el que una partida viaja a Odoo.
     *
     * Lleva pegada la especificación —«TUB CUAD NEG. 2,0 X 2,0 MM · ECU202»—,
     * y esa es la forma exacta en que la línea queda llamándose allá. Buscarla
     * después sólo por el nombre de la partida no la encontraba, y una orden
     * entera se quedaba sin poder recibir precios.
     */
    public function descripcionDe(mixed $item): string
    {
        $descripcion = trim((string) $item->product_service);

        if (filled($item->specification)) {
            $descripcion .= ' · '.$item->specification;
        }

        return $descripcion;
    }

    /**
     * El producto de Odoo de una partida, resuelto igual que al exportar.
     *
     * Importa que sea la misma resolución: la línea de Odoo nació de aquí, y
     * si para escribirle el precio se buscara el producto de otra manera, las
     * dos mitades dejarían de hablar de lo mismo.
     *
     * Sólo lo cierto —lo que alguien enseñó, el código del proveedor o el
     * nombre idéntico—. Lo que el parecido apenas sugiere no vale para escribir
     * un número en una orden de compra.
     */
    public function productoDe(mixed $item, ?int $odooPartnerId): ?int
    {
        // Lo que dijo una persona, tal cual. Aquí no se le exige al producto
        // seguir vivo en la copia local del catálogo, como sí se hace al crear
        // una línea: la línea de Odoo ya existe, y es ella la que decide si el
        // producto está o no. Una copia local desfasada no puede impedir
        // corregir un precio en una orden que está ahí, delante.
        $enlace = PurchaseProductLink::para((string) $item->product_service, $odooPartnerId);

        if ($enlace?->odoo_product_id !== null) {
            return (int) $enlace->odoo_product_id;
        }

        $encontrado = $this->emparejador->match(
            (string) $item->product_service,
            $odooPartnerId,
            $item->specification,
        );

        return $encontrado->resolved() ? $encontrado->odooProductId : null;
    }

    /**
     * Escribe en la cotización de Odoo los precios que cotizó el proveedor.
     *
     * Sólo `price_unit`, y sólo mientras la orden siga en borrador: una vez
     * confirmada hay compromisos detrás —recepciones, facturas— y cambiarle el
     * precio por detrás desde aquí sería mover algo que allá ya se dio por
     * cerrado.
     *
     * Las líneas se localizan por producto, no por texto: la línea de Odoo y
     * la partida apuntan al mismo producto aunque se llamen distinto, que es
     * justamente el caso que motivó todo esto.
     *
     * No todas las líneas tienen producto. Odoo acepta una línea con sólo la
     * descripción, y así nacen todas las partidas cuyo producto nadie ha
     * resuelto todavía: la P00243 tiene ocho renglones de texto suelto
     * —«Corchetera», «pilas AA»— sin un `product_id` al que apuntar. A ésas se
     * las encuentra por su texto, que es el que este mismo programa escribió
     * al crearlas. Sin eso no había forma de ponerles precio nunca.
     *
     * @param  array<int, float>  $preciosPorProducto  id de producto de Odoo => precio unitario
     * @param  array<string, float>  $preciosPorTexto  descripción normalizada => precio unitario
     * @return array{0: int, 1: ?string} cuántas líneas se actualizaron y el motivo si no
     */
    public function actualizarPrecios(
        PurchaseRequest $purchaseRequest,
        array $preciosPorProducto,
        array $preciosPorTexto = [],
    ): array {
        $cambios = [];

        foreach ($preciosPorProducto as $producto => $precio) {
            $cambios[] = ['producto' => (int) $producto, 'textos' => [], 'precio' => (float) $precio, 'nombre' => null, 'cantidad' => null];
        }

        foreach ($preciosPorTexto as $texto => $precio) {
            $cambios[] = ['producto' => null, 'textos' => [(string) $texto], 'precio' => (float) $precio, 'nombre' => null, 'cantidad' => null];
        }

        [$actualizadas, $motivo] = $this->actualizarLineas($purchaseRequest, $cambios);

        return [$actualizadas, $motivo];
    }

    /**
     * Lleva a la cotización de Odoo lo que se sabe después de cotizar.
     *
     * Dos cosas: el precio y el nombre. El nombre importa tanto como el
     * precio. La solicitud se escribe con lo que uno tiene en la cabeza
     * —«anotador acrilico PVC»— y la cotización llega con el nombre de verdad
     * —«ANOTADOR ACRILICO TABLHOL002 JM JM111»—. Ese segundo es el que sirve
     * para volver a pedirlo y el que cualquiera reconoce en Odoo.
     *
     * Se escribe sólo sobre la orden, y sólo mientras siga en borrador. Nada
     * de esto toca la ficha de un producto ni crea uno: el catálogo de Odoo se
     * mantiene allá, por quien corresponda.
     *
     * Y si la línea viajó sin producto, se le pone el que corresponde: con el
     * nombre real del proveedor en la mano, el producto o ya está en Odoo o se
     * da de alta. Es el único momento en que se puede hacer bien; al crear la
     * solicitud sólo existe el nombre genérico, y crear con eso sería llenar
     * el catálogo de basura.
     *
     * @param  list<array{producto: ?int, textos: list<string>, precio: ?float, nombre: ?string, cantidad: ?float}>  $cambios
     * @return array{0: int, 1: ?string, 2: int} actualizadas, motivo, productos creados
     */
    public function actualizarLineas(PurchaseRequest $purchaseRequest, array $cambios, ?int $partnerId = null): array
    {
        $orden = (int) $purchaseRequest->odoo_order_id;

        if ($orden === 0) {
            return [0, 'Esta solicitud todavía no está en Odoo.', 0];
        }

        if ($cambios === []) {
            return [0, 'Ninguna partida tiene precio cotizado que llevar.', 0];
        }

        try {
            $cabecera = $this->client->execute('purchase.order', 'read', [[$orden]], ['fields' => ['state', 'order_line']]);
            $estado = (string) ($cabecera[0]['state'] ?? '');

            if ($estado !== 'draft') {
                return [0, $purchaseRequest->odoo_reference.' ya no está en borrador en Odoo, así que se quedó '
                    .'con los nombres y precios que tenía. Cambiar una orden que allá ya se dio por cerrada '
                    .'movería algo con recepciones o facturas detrás.', 0];
            }

            $lineas = $this->client->execute(
                'purchase.order.line',
                'read',
                [$cabecera[0]['order_line'] ?? []],
                ['fields' => ['id', 'product_id', 'price_unit', 'name', 'product_qty']],
            );

            // Los nombres reales de las líneas que viajaron sin producto, en
            // una sola pasada: buscar ocho veces por separado sería ocho veces
            // el viaje a Odoo.
            $sinProducto = [];

            foreach ($lineas ?: [] as $linea) {
                $cambio = $this->cambioDe($linea, $cambios);

                if ($cambio !== null && ! is_array($linea['product_id'] ?? null) && filled($cambio['nombre'])) {
                    $sinProducto[] = (string) $cambio['nombre'];
                }
            }

            $productos = $sinProducto === [] ? [] : $this->productosParaNombres($sinProducto, $partnerId, true);
            $creados = count(array_filter($productos, fn (array $p): bool => $p['creado']));

            $actualizadas = 0;
            $reconocidas = 0;

            foreach ($lineas ?: [] as $linea) {
                $cambio = $this->cambioDe($linea, $cambios);

                if ($cambio === null) {
                    continue;
                }

                $reconocidas++;
                $escribir = [];

                // La línea sin producto recibe el suyo, con su unidad, para que
                // Odoo no quede con una línea que dice una cosa y mide otra.
                if (! is_array($linea['product_id'] ?? null) && filled($cambio['nombre'])) {
                    $producto = $productos[trim((string) $cambio['nombre'])] ?? null;

                    if ($producto !== null) {
                        $escribir['product_id'] = $producto['id'];

                        if ($producto['uom'] !== null) {
                            $escribir['product_uom'] = $producto['uom'];
                        }
                    }
                }

                // Escribir el mismo valor que ya está sería ensuciar el
                // historial de Odoo sin cambiar nada.
                if ($cambio['precio'] !== null && abs((float) $linea['price_unit'] - $cambio['precio']) >= 0.005) {
                    $escribir['price_unit'] = $cambio['precio'];
                }

                if (filled($cambio['nombre']) && trim((string) ($linea['name'] ?? '')) !== trim((string) $cambio['nombre'])) {
                    $escribir['name'] = trim((string) $cambio['nombre']);
                }

                // La cantidad que manda es la COTIZADA.
                //
                // La solicitud dice lo que se quería; la orden de compra dice
                // lo que se compra, y eso es lo que se va a recibir y lo que
                // van a facturar. Si se pidieron diez y el proveedor cotiza
                // cinco, la orden va por cinco: dejarla en diez haría que la
                // recepción y la factura no cuadraran con su propia orden.
                //
                // La diferencia no se pierde: la solicitud conserva las diez y
                // la pantalla la muestra —«pediste 10 y cotizaron 5»— antes de
                // que nadie apriete nada.
                if ($cambio['cantidad'] !== null && abs((float) ($linea['product_qty'] ?? 0) - $cambio['cantidad']) >= 0.0001) {
                    $escribir['product_qty'] = $cambio['cantidad'];
                }

                if ($escribir === []) {
                    continue;
                }

                $this->client->execute('purchase.order.line', 'write', [[(int) $linea['id']], $escribir]);
                $actualizadas++;
            }

            if ($actualizadas === 0 && $reconocidas === 0) {
                return [0, 'No se pudo emparejar ninguna línea de '.$purchaseRequest->odoo_reference
                    .' con las partidas. Puede que las hayan reescrito en Odoo.', 0];
            }

            return [$actualizadas, null, $creados];
        } catch (Throwable $e) {
            Log::warning('No se pudieron actualizar las líneas en Odoo.', [
                'folio' => $purchaseRequest->folio,
                'orden' => $orden,
                'motivo' => $e->getMessage(),
            ]);

            return [0, 'Odoo no aceptó el cambio: '.$e->getMessage(), 0];
        }
    }

    /**
     * Qué cambio le toca a esa línea de Odoo.
     *
     * Por producto cuando lo tiene; por su texto cuando no, que es el que este
     * mismo programa escribió al crearla. Si alguien la reescribió allá, no
     * calza y no se toca: ya no es la línea que salió de aquí.
     *
     * @param  array<string, mixed>  $linea
     * @param  list<array{producto: ?int, textos: list<string>, precio: ?float, nombre: ?string, cantidad: ?float}>  $cambios
     * @return array{producto: ?int, textos: list<string>, precio: ?float, nombre: ?string, cantidad: ?float}|null
     */
    private function cambioDe(array $linea, array $cambios): ?array
    {
        $producto = is_array($linea['product_id'] ?? null) ? (int) $linea['product_id'][0] : null;

        if ($producto !== null) {
            foreach ($cambios as $cambio) {
                if ($cambio['producto'] === $producto) {
                    return $cambio;
                }
            }
        }

        $texto = PurchaseProductLink::normalizar((string) ($linea['name'] ?? ''));

        if ($texto === '') {
            return null;
        }

        // Vale tanto el texto con que la línea nació como el nombre real que se
        // le escribió después. Reconocerla sólo por el primero la volvía
        // irreconocible en cuanto se le ponía el segundo: llevar los nombres
        // una vez dejaba la orden sin poder recibir nada más.
        foreach ($cambios as $cambio) {
            if (in_array($texto, $cambio['textos'] ?? [], true)) {
                return $cambio;
            }
        }

        // Último recurso: la línea empieza por lo que escribimos y alguien le
        // añadió algo detrás. Pasó con «PLUS GALV.G60 1.5 x1000x3000 mm ·
        // PGAL10300150 (peso)», donde el «(peso)» está en Odoo y ya no en la
        // solicitud. Sólo vale si encaja una sola partida: con dos, no se sabe
        // cuál es y quedarse con la primera sería adivinar.
        $candidatas = [];

        foreach ($cambios as $cambio) {
            foreach ($cambio['textos'] ?? [] as $suyo) {
                if ($suyo !== '' && str_starts_with($texto, $suyo)) {
                    $candidatas[] = $cambio;

                    break;
                }
            }
        }

        return count($candidatas) === 1 ? $candidatas[0] : null;
    }

    /** @return array{0: int, 1: string} */
    private function crearRfq(PurchaseRequest $purchaseRequest, int $proveedor): array
    {
        $id = (int) $this->client->execute('purchase.order', 'create', [[
            'partner_id' => $proveedor,
            'picking_type_id' => (int) config('purchase_requests.odoo.picking_type_id'),
            // De dónde viene: deja el rastro visible dentro de Odoo.
            'origin' => (string) $purchaseRequest->folio,
            'date_order' => now()->format('Y-m-d H:i:s'),
            'order_line' => $this->lineasDe($purchaseRequest, $proveedor),
        ]]);

        $leido = $this->client->execute('purchase.order', 'read', [[$id]], ['fields' => ['name']]);
        $referencia = (string) ($leido[0]['name'] ?? $id);

        return [$id, $referencia];
    }

    /**
     * Las líneas, con los productos ya confirmados contra Odoo.
     *
     * @return list<array{0: int, 1: int, 2: array<string, mixed>}>
     */
    private function lineasDe(PurchaseRequest $purchaseRequest, ?int $proveedor): array
    {
        $emparejados = [];

        foreach ($purchaseRequest->items as $item) {
            $m = $this->emparejador->match((string) $item->product_service, $proveedor, $item->specification);
            $emparejados[$item->getKey()] = $m->resolved() ? $m->odooProductId : null;
        }

        $confirmados = $this->productosQueOdooConfirma(array_values($emparejados));

        return $purchaseRequest->items
            ->map(function ($item) use ($purchaseRequest, $emparejados, $confirmados): array {
                $id = $emparejados[$item->getKey()] ?? null;

                return [0, 0, $this->linea(
                    $item,
                    $purchaseRequest,
                    $id !== null && in_array($id, $confirmados, true) ? $id : null,
                )];
            })
            ->values()
            ->all();
    }

    /**
     * Una línea de la cotización.
     *
     * No se manda `product_id`: en Odoo 18 la línea acepta sólo texto, y crear
     * productos a partir de lo que alguien escribió a mano llenaría el
     * catálogo de duplicados con faltas de ortografía.
     *
     * Tampoco `product_uom`: las unidades de Odoo están en inglés y las
     * nuestras en español, y forzar una equivalencia inventa información. La
     * unidad real viaja escrita en la descripción, que es donde se lee.
     *
     * @return array<string, mixed>
     */
    private function linea(mixed $item, PurchaseRequest $purchaseRequest, ?int $productoConfirmado): array
    {
        $descripcion = $this->descripcionDe($item);
        $unidad = $this->unidadOdoo($item);

        $linea = [
            'name' => $descripcion,
            'product_qty' => (float) $item->quantity,
            // Odoo lo exige. Sin cotizar, la RFQ nace en cero y Compras lo
            // completa allá: es preferible a inventar un precio.
            'price_unit' => (float) ($item->unit_price ?? 0),
            'product_uom' => $unidad,
        ];

        if ($productoConfirmado !== null) {
            $linea['product_id'] = $productoConfirmado;

            /*
             * Con producto confirmado, el nombre lo pone Odoo.
             *
             * La línea llegaba llamándose «cemento» —como lo escribió quien
             * pidió— junto al producto «CEMENTO 25 KG». En Odoo ese texto es la
             * descripción del producto comprado, y quien la lee allá espera el
             * nombre del catálogo, no la forma en que alguien lo pidió acá.
             * Lo escrito no se pierde: sigue en la solicitud, que es su sitio.
             */
            $delCatalogo = OdooProduct::query()
                ->where('odoo_id', $productoConfirmado)
                ->value('name');

            if (filled($delCatalogo)) {
                $linea['name'] = (string) $delCatalogo;
            }

            /*
             * Con producto, la unidad la manda el producto.
             *
             * Odoo exige que la unidad de la línea sea de la misma categoría
             * que la del producto, y rechaza la orden entera si no lo es:
             * «m³ no pertenece a la misma categoría que Units». Nuestra
             * equivalencia dice que Cubos es m³, y el producto Bolones está
             * declarado en Units. Las dos afirmaciones son razonables y no se
             * pueden sostener a la vez, así que gana la de Odoo, que es donde
             * la línea va a vivir.
             */
            $unidadDelProducto = OdooProduct::query()
                ->where('odoo_id', $productoConfirmado)
                ->value('uom_id');

            if (filled($unidadDelProducto)) {
                $linea['product_uom'] = (int) $unidadDelProducto;
            }
        }

        /*
         * La unidad escrita se conserva en la descripción cuando la que acaba
         * viajando no la representa.
         *
         * Se decide al final a propósito: la definitiva no se sabe hasta aquí,
         * porque el producto puede haber pisado la nuestra. Decidirlo antes
         * hacía que «5 Cubos de bolones» llegara a Odoo como «bolones · 5
         * Units», sin rastro de los cubos ni en la unidad ni en el texto.
         */
        if ($this->laUnidadSePierde($item, (int) $linea['product_uom'])) {
            $linea['name'] .= ' ('.$item->unit.')';
        }

        // La fecha requerida de la solicitud es la entrega esperada de Odoo:
        // el mismo dato con otro nombre. Va en la línea, que es de donde Odoo
        // deduce la de la cabecera. Sin esto la cotización entra sin fecha y
        // no aparece en ninguna planificación.
        if (filled($purchaseRequest->required_date)) {
            $linea['date_planned'] = $purchaseRequest->required_date->startOfDay()->format('Y-m-d H:i:s');
        }

        // El impuesto no viene del producto —no mandamos producto—, así que sin
        // esto la cotización entraría en cero de IVA. Sólo se pone cuando
        // sabemos que los precios son netos: si ya traen el IVA dentro,
        // sumárselo otra vez inflaría la orden un 19%.
        $impuesto = config('purchase_requests.odoo.tax_id');

        if ($impuesto !== null && $purchaseRequest->prices_include_tax === false) {
            $linea['taxes_id'] = [[6, 0, [(int) $impuesto]]];
        }

        return $linea;
    }

    /**
     * ¿La unidad que se escribió deja de estar representada en Odoo?
     *
     * Ocurre en dos casos: cuando la nuestra no tiene equivalente allá —Sacos,
     * Rollos, Cada medida— y cuando el producto impone otra distinta. En ambos
     * el dato se perdería, y quien mire la cotización leería «5 unidades» de
     * algo que se pidió por metros cúbicos.
     */
    private function laUnidadSePierde(mixed $item, int $unidadQueViaja): bool
    {
        // «Unidades» y «Units» son lo mismo: repetirlo sólo ensucia.
        if ($this->esUnidadNeutra($item)) {
            return false;
        }

        return $unidadQueViaja !== $this->unidadOdoo($item)
            || $unidadQueViaja === $this->unidadPorDefecto();
    }

    private function unidadPorDefecto(): int
    {
        return (int) config('purchase_requests.odoo.default_uom_id', 1);
    }

    /**
     * ¿La unidad es «Unidades», que en Odoo también son «Units»?
     *
     * En ese caso no hay nada que conservar: la columna de Odoo dice lo mismo.
     */
    private function esUnidadNeutra(mixed $item): bool
    {
        return Str::lower(trim((string) $item->unit)) === 'unidades';
    }

    /**
     * La unidad de Odoo que corresponde a la nuestra.
     *
     * Se busca la equivalencia registrada en el catálogo; si no hay, se usa la
     * de por defecto. Traducir «Cubos» a alguna unidad métrica por parecido
     * sería inventar, y la unidad real ya viaja escrita en la descripción.
     */
    private function unidadOdoo(mixed $item): int
    {
        $mapeada = UnitOfMeasure::query()
            ->whereRaw('LOWER(name) = ?', [Str::lower(trim((string) $item->unit))])
            ->value('odoo_uom_id');

        return filled($mapeada)
            ? (int) $mapeada
            : (int) config('purchase_requests.odoo.default_uom_id', 1);
    }
}
