<?php

namespace App\Services\PurchaseRequests\Odoo;

use App\Enums\PurchaseRequestStatus;
use App\Models\OdooProduct;
use App\Models\PurchaseRequest;
use App\Models\PurchaseSupplier;
use App\Models\UnitOfMeasure;
use App\Services\PurchaseRequests\Products\ProductMatcher;
use App\Services\PurchaseRequests\Products\ProductSimilarity;
use App\Support\Rut;
use Illuminate\Support\Facades\Log;
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

                return PurchaseRequestExportResult::needsSupplier(
                    $candidatos === []
                        ? 'Odoo no tiene ningún proveedor que se parezca. Búscalo aquí abajo; si tampoco aparece, hay que darlo de alta en Odoo.'
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

        return PurchaseRequestExportResult::created(
            $referencia,
            'Se creó la cotización '.$referencia.' en Odoo, en borrador y sin confirmar.',
        );
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

    /** @return array{0: int, 1: string} */
    private function crearRfq(PurchaseRequest $purchaseRequest, int $proveedor): array
    {
        $id = (int) $this->client->execute('purchase.order', 'create', [[
            'partner_id' => $proveedor,
            'picking_type_id' => (int) config('purchase_requests.odoo.picking_type_id'),
            // De dónde viene: deja el rastro visible dentro de Odoo.
            'origin' => (string) $purchaseRequest->folio,
            'date_order' => now()->format('Y-m-d H:i:s'),
            'order_line' => $purchaseRequest->items
                ->map(fn ($item): array => [0, 0, $this->linea($item, $purchaseRequest, $proveedor)])
                ->values()
                ->all(),
        ]]);

        $leido = $this->client->execute('purchase.order', 'read', [[$id]], ['fields' => ['name']]);
        $referencia = (string) ($leido[0]['name'] ?? $id);

        return [$id, $referencia];
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
    private function linea(mixed $item, PurchaseRequest $purchaseRequest, ?int $proveedorOdoo): array
    {
        $descripcion = trim((string) $item->product_service);

        if (filled($item->specification)) {
            $descripcion .= ' · '.$item->specification;
        }

        $unidad = $this->unidadOdoo($item);

        // La cantidad y la unidad ya van en sus propias columnas de Odoo:
        // repetirlas en la descripción sólo ensucia la línea. Se conserva la
        // unidad escrita únicamente cuando no tiene equivalente allá —Sacos,
        // Rollos, Cada medida—, porque si no se perdería del todo.
        if ($unidad === $this->unidadPorDefecto() && ! $this->esUnidadNeutra($item)) {
            $descripcion .= ' ('.$item->unit.')';
        }

        // Sólo va el producto que alguien confirmó o que calzó exacto. Una
        // sugerencia no basta: sin producto la línea entra igual, y eso es
        // mejor que entrar con el producto equivocado, que mueve stock real
        // del artículo que no era.
        $emparejado = $this->emparejador->match(
            (string) $item->product_service,
            $proveedorOdoo,
            $item->specification,
        );

        $linea = [
            'name' => $descripcion,
            'product_qty' => (float) $item->quantity,
            // Odoo lo exige. Sin cotizar, la RFQ nace en cero y Compras lo
            // completa allá: es preferible a inventar un precio.
            'price_unit' => (float) ($item->unit_price ?? 0),
            'product_uom' => $unidad,
        ];

        if ($emparejado->resolved()) {
            $linea['product_id'] = $emparejado->odooProductId;

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
                ->where('odoo_id', $emparejado->odooProductId)
                ->value('uom_id');

            if (filled($unidadDelProducto)) {
                $linea['product_uom'] = (int) $unidadDelProducto;
            }
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
