<?php

namespace App\Services\PurchaseRequests\Quotes;

use App\Models\PurchaseRequest;
use App\Services\PurchaseRequests\Products\ProductSimilarity;

/**
 * Compara lo que se pidió con lo que el proveedor cotizó.
 *
 * El modelo lee el documento; esta clase compara. La distinción importa: una
 * cantidad que no calza o un precio que subió son aritmética, y pedirle a una
 * IA que haga aritmética es meter un error donde no hacía falta ninguno. Lo
 * único difícil aquí es emparejar los textos —«CANDADO GRIPPLE» con «Candado
 * tipo gripple 20mm»— y para eso ya existe el comparador difuso del módulo.
 *
 * No modifica nada. Devuelve una tabla para que una persona mire y decida.
 */
class QuotationComparison
{
    /** Bajo esto, dos textos no son el mismo producto aunque se parezcan. */
    private const UMBRAL = 0.55;

    public function __construct(private readonly ProductSimilarity $similitud) {}

    /**
     * @param  list<array<string, mixed>>  $lineasDelDocumento
     */
    public function comparar(PurchaseRequest $solicitud, array $lineasDelDocumento): QuotationComparisonResult
    {
        $pedidas = $solicitud->items()->orderBy('sort_order')->get();
        $usadas = [];
        $filas = [];

        foreach ($pedidas as $item) {
            [$indice, $puntaje] = $this->mejorPareja($item, $lineasDelDocumento, $usadas);

            if ($indice === null) {
                $filas[] = QuotationComparisonRow::sinCotizar($item);

                continue;
            }

            $usadas[$indice] = true;
            $filas[] = QuotationComparisonRow::emparejada($item, $lineasDelDocumento[$indice], $puntaje);
        }

        // Lo que el proveedor agregó por su cuenta: fletes, insumos, o una
        // partida que alguien olvidó pedir. Es tan importante como lo que falta.
        $sobrantes = [];

        foreach ($lineasDelDocumento as $i => $linea) {
            if (! isset($usadas[$i])) {
                $sobrantes[] = QuotationComparisonRow::noPedida($linea);
            }
        }

        return new QuotationComparisonResult($filas, $sobrantes);
    }

    /**
     * La línea del documento que mejor calza con la partida, si alguna calza.
     *
     * Cada línea del documento se usa una sola vez: si dos partidas pidieran
     * lo mismo, emparejar ambas contra el mismo renglón diría que todo está
     * bien cuando el proveedor cotizó la mitad.
     *
     * @param  list<array<string, mixed>>  $lineas
     * @param  array<int, bool>  $usadas
     * @return array{0: ?int, 1: float}
     */
    private function mejorPareja($item, array $lineas, array $usadas): array
    {
        $mejor = null;
        $mejorPuntaje = 0.0;

        foreach ($lineas as $i => $linea) {
            if (isset($usadas[$i])) {
                continue;
            }

            $puntaje = $this->parecido($item, $linea);

            if ($puntaje > $mejorPuntaje) {
                $mejor = $i;
                $mejorPuntaje = $puntaje;
            }
        }

        return $mejorPuntaje >= self::UMBRAL ? [$mejor, $mejorPuntaje] : [null, 0.0];
    }

    /**
     * Cuánto se parecen una partida y una línea del documento.
     *
     * El código del proveedor manda sobre el nombre: si la solicitud anotó
     * «KU0214-014047» y el documento trae ese mismo código, es la misma cosa
     * aunque se llamen distinto en cada papel.
     *
     * @param  array<string, mixed>  $linea
     */
    private function parecido($item, array $linea): float
    {
        $codigoPedido = $this->limpiar($item->specification);
        $codigoOfrecido = $this->limpiar($linea['specification'] ?? null);

        if ($codigoPedido !== null && $codigoOfrecido !== null
            && $this->similitud->normalize($codigoPedido) === $this->similitud->normalize($codigoOfrecido)) {
            return 1.0;
        }

        $nombre = (string) ($linea['product_service'] ?? '');

        if ($nombre === '') {
            return 0.0;
        }

        $directo = $this->similitud->score((string) $item->product_service, $nombre);

        // El nombre a veces vive en la especificación de un lado y en el
        // nombre del otro, así que se prueban las dos combinaciones.
        $conEspecificacion = $codigoPedido === null
            ? 0.0
            : $this->similitud->score($item->product_service.' '.$codigoPedido, $nombre);

        return max($directo, $conEspecificacion);
    }

    private function limpiar(mixed $valor): ?string
    {
        if (! is_string($valor)) {
            return null;
        }

        $limpio = trim($valor);

        return $limpio === '' ? null : $limpio;
    }
}
