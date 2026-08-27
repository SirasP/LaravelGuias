<?php

namespace App\Services\PurchaseRequests\Products;

use App\Models\OdooProduct;
use App\Models\OdooSupplierProduct;
use App\Models\PurchaseProductLink;

/**
 * A qué producto de Odoo corresponde lo que dice una partida.
 *
 * Cascada de lo cierto a lo dudoso. Los tres primeros pasos afirman; los dos
 * últimos sólo proponen, y ahí siempre decide una persona.
 *
 * La diferencia con el módulo de facturas es deliberada: allá el parecido
 * decide, con 69 candidatos y umbral 0,88. Aquí hay 2.347, y entre tantos
 * siempre habrá alguno que puntúe alto sin ser el correcto. Un producto
 * equivocado en una recepción mueve stock real del artículo que no era.
 */
class ProductMatcher
{
    /** Por debajo de esto ni se ofrece: es ruido, no una sugerencia. */
    private const MINIMO_PARA_SUGERIR = 0.45;

    private const CUANTAS_SUGERENCIAS = 5;

    public function __construct(private readonly ProductSimilarity $similitud) {}

    public function match(string $texto, ?int $odooPartnerId, ?string $codigo = null): ProductMatch
    {
        $texto = trim($texto);

        if ($texto === '') {
            return ProductMatch::sinIdea('La partida no tiene descripción.');
        }

        // 1. Lo que alguien ya resolvió. Es lo único que no se vuelve a preguntar.
        if ($cierto = $this->porEnlaceAprendido($texto, $odooPartnerId)) {
            return $cierto;
        }

        // 2. El código, cuando la cotización lo trae y Odoo lo reconoce.
        if ($codigo !== null && $cierto = $this->porCodigo($codigo)) {
            return $cierto;
        }

        // 3. El nombre idéntico. Raro, pero cuando pasa no hay duda.
        if ($cierto = $this->porNombreExacto($texto)) {
            return $cierto;
        }

        // 4 y 5. A partir de aquí sólo se propone.
        $candidatos = $this->sugerencias($texto, $odooPartnerId);

        return $candidatos === []
            ? ProductMatch::sinIdea()
            : ProductMatch::sugerencia($candidatos);
    }

    private function porEnlaceAprendido(string $texto, ?int $odooPartnerId): ?ProductMatch
    {
        $enlace = PurchaseProductLink::para($texto, $odooPartnerId);

        if ($enlace === null || $enlace->odoo_product_id === null) {
            return null;
        }

        // Alguien pudo archivar o fusionar ese producto en Odoo desde que se
        // emparejó. Mandar el id igualmente haría fallar la cotización sin
        // explicar por qué.
        if (! $enlace->productoVigente()) {
            return ProductMatch::sinIdea(sprintf(
                'Estaba emparejado con «%s», pero ese producto ya no está disponible en Odoo. Hay que elegir otro.',
                $enlace->odoo_product_name ?: $enlace->odoo_product_id,
            ));
        }

        return ProductMatch::cierto(
            $enlace->odoo_product_id,
            (string) $enlace->odoo_product_name,
            $enlace->odoo_partner_id !== null
                ? 'Ya se había emparejado para este proveedor.'
                : 'Ya se había emparejado antes.',
        );
    }

    private function porCodigo(string $codigo): ?ProductMatch
    {
        $codigo = trim($codigo);

        if ($codigo === '') {
            return null;
        }

        $producto = OdooProduct::query()->usable()
            ->where(fn ($q) => $q->where('default_code', $codigo)->orWhere('barcode', $codigo))
            ->first();

        return $producto === null
            ? null
            : ProductMatch::cierto($producto->odoo_id, $producto->name, 'El código «'.$codigo.'» calza exacto.');
    }

    private function porNombreExacto(string $texto): ?ProductMatch
    {
        $normalizado = $this->similitud->normalize($texto);

        $producto = OdooProduct::query()->usable()->get(['odoo_id', 'name'])
            ->first(fn (OdooProduct $p): bool => $this->similitud->normalize($p->name) === $normalizado);

        return $producto === null
            ? null
            : ProductMatch::cierto($producto->odoo_id, $producto->name, 'El nombre coincide exactamente.');
    }

    /**
     * Los parecidos, mejor primero.
     *
     * Se busca antes entre lo que ese proveedor ya vendió. No es una
     * optimización: RODASERVIC tiene ocho productos en su historial, y acertar
     * entre ocho es otra cosa que acertar entre dos mil trescientos.
     *
     * @return list<array{odoo_id: int, name: string, score: float, reason: string}>
     */
    public function sugerencias(string $texto, ?int $odooPartnerId): array
    {
        $delProveedor = $this->puntuar($texto, $this->productosDe($odooPartnerId), 'Se lo has comprado antes a este proveedor');

        // Si entre los suyos ya hay algo bueno, no hace falta remover el
        // catálogo entero y llenar la lista de ruido.
        if ($delProveedor !== [] && $delProveedor[0]['score'] >= 0.7) {
            return array_slice($delProveedor, 0, self::CUANTAS_SUGERENCIAS);
        }

        $todos = $this->puntuar($texto, OdooProduct::query()->usable()->get(['odoo_id', 'name']), 'Del catálogo');

        $mezcla = [];

        foreach ([...$delProveedor, ...$todos] as $c) {
            $mezcla[$c['odoo_id']] ??= $c;
        }

        usort($mezcla, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice(array_values($mezcla), 0, self::CUANTAS_SUGERENCIAS);
    }

    /** @return \Illuminate\Support\Collection<int, OdooProduct> */
    private function productosDe(?int $odooPartnerId)
    {
        if ($odooPartnerId === null) {
            return collect();
        }

        $ids = OdooSupplierProduct::query()
            ->where('partner_id', $odooPartnerId)
            ->whereNotNull('product_id')
            ->pluck('product_id');

        return $ids->isEmpty()
            ? collect()
            : OdooProduct::query()->usable()->whereIn('odoo_id', $ids)->get(['odoo_id', 'name']);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, OdooProduct>  $productos
     * @return list<array{odoo_id: int, name: string, score: float, reason: string}>
     */
    private function puntuar(string $texto, $productos, string $motivo): array
    {
        $puntuados = [];

        foreach ($productos as $producto) {
            $score = $this->similitud->score($texto, (string) $producto->name);

            if ($score < self::MINIMO_PARA_SUGERIR) {
                continue;
            }

            $puntuados[] = [
                'odoo_id' => (int) $producto->odoo_id,
                'name' => (string) $producto->name,
                'score' => round($score, 3),
                'reason' => $motivo,
            ];
        }

        usort($puntuados, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return $puntuados;
    }
}
