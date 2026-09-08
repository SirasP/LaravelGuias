<?php

namespace App\Services\PurchaseRequests\Quotes;

use App\Models\PurchaseProductLink;
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
    /** El proveedor de la cotización que se está comparando, si se sabe. */
    private ?int $partnerId = null;

    public function __construct(
        private readonly ProductSimilarity $similitud,
        private readonly QuoteLineMatcher $emparejador,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $lineasDelDocumento
     */
    public function comparar(
        PurchaseRequest $solicitud,
        array $lineasDelDocumento,
        ?int $odooPartnerId = null,
    ): QuotationComparisonResult {
        $pedidas = $solicitud->items()->orderBy('sort_order')->get()->all();
        $this->partnerId = $odooPartnerId;

        $emparejado = $this->emparejador
            ->conCantidades($pedidas, $lineasDelDocumento)
            ->emparejar($pedidas, $lineasDelDocumento, fn ($item, array $linea): float => $this->parecido($item, $linea));

        $filas = [];

        foreach ($pedidas as $i => $item) {
            $j = $emparejado->lineaDe($i);

            if ($j === null) {
                $filas[] = QuotationComparisonRow::sinCotizar($item);

                continue;
            }

            $filas[] = $emparejado->esProbable($i)
                ? QuotationComparisonRow::propuesta($item, $lineasDelDocumento[$j], $emparejado->confianza($i, $j))
                : QuotationComparisonRow::emparejada(
                    $item,
                    $lineasDelDocumento[$j],
                    $emparejado->confianza($i, $j),
                    $this->loEnsenoAlguien((string) ($lineasDelDocumento[$j]['product_service'] ?? '')),
                );
        }

        // Lo que el proveedor agregó por su cuenta: fletes, insumos, o una
        // partida que alguien olvidó pedir. Es tan importante como lo que falta,
        // pero va aparte: sumarlo a la tabla convertía una solicitud de
        // diecinueve partidas en treinta y tres filas que nadie pidió leer.
        $sobrantes = [];

        foreach ($emparejado->lineasLibres(count($lineasDelDocumento)) as $j) {
            $sobrantes[] = QuotationComparisonRow::noPedida($lineasDelDocumento[$j]);
        }

        return new QuotationComparisonResult($filas, $sobrantes);
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
        // Dos textos son la misma partida si alguien ya enseñó que apuntan al
        // mismo producto de Odoo. Eso es exacto: no compite con el parecido,
        // lo reemplaza. «valvula mariposa» y «VALVULA MARIPOSA 8" 200MM
        // C/PALANCA» se parecen un 52%, por debajo de cualquier umbral
        // razonable, y son lo mismo.
        if (PurchaseProductLink::equivalentes(
            (string) $item->product_service,
            (string) ($linea['product_service'] ?? ''),
            $this->partnerId,
        )) {
            return 1.0;
        }

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

    /**
     * ¿Este texto del proveedor cruza porque alguien lo dijo?
     *
     * Importa para la pantalla: un cruce que salió de un clic humano tiene que
     * poder deshacerse con otro clic. Uno que salió del parecido no: ahí no hay
     * nada guardado que borrar.
     */
    private function loEnsenoAlguien(string $textoDelProveedor): bool
    {
        if (trim($textoDelProveedor) === '') {
            return false;
        }

        return PurchaseProductLink::para($textoDelProveedor, $this->partnerId)?->source === PurchaseProductLink::CONFIRMADO;
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
