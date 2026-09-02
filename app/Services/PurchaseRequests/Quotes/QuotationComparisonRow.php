<?php

namespace App\Services\PurchaseRequests\Quotes;

use App\Models\PurchaseRequestItem;

/**
 * Una línea de la comparación: qué se pidió, qué cotizaron y en qué difieren.
 *
 * Las diferencias se calculan aquí, con aritmética, y se nombran en palabras
 * para que la pantalla no tenga que interpretarlas.
 */
class QuotationComparisonRow
{
    /**
     * @param  list<string>  $diferencias
     */
    private function __construct(
        public readonly string $estado,
        public readonly ?PurchaseRequestItem $pedida,
        public readonly ?array $cotizada,
        public readonly array $diferencias,
        public readonly float $confianza = 0.0,
    ) {}

    /** @param array<string, mixed> $linea */
    public static function emparejada(PurchaseRequestItem $item, array $linea, float $confianza): self
    {
        $diferencias = [];

        $pedida = self::numero($item->quantity);
        $ofrecida = self::numero($linea['quantity'] ?? null);

        if ($pedida !== null && $ofrecida !== null && abs($pedida - $ofrecida) > 0.0001) {
            $diferencias[] = sprintf(
                'Pediste %s y cotizaron %s.',
                self::cantidad($pedida),
                self::cantidad($ofrecida),
            );
        }

        $unidadPedida = trim((string) $item->unit);
        $unidadOfrecida = trim((string) ($linea['unit'] ?? ''));

        if ($unidadPedida !== '' && $unidadOfrecida !== ''
            && mb_strtolower($unidadPedida) !== mb_strtolower($unidadOfrecida)) {
            $diferencias[] = sprintf('La unidad no coincide: %s contra %s.', $unidadPedida, $unidadOfrecida);
        }

        $precioPedido = self::numero($item->unit_price);
        $precioOfrecido = self::numero($linea['unit_price'] ?? null);

        if ($precioOfrecido !== null && $precioPedido === null) {
            $diferencias[] = 'Trae precio y tu solicitud no tenía ninguno.';
        } elseif ($precioOfrecido !== null && $precioPedido !== null && abs($precioOfrecido - $precioPedido) > 0.5) {
            $subeOBaja = $precioOfrecido > $precioPedido ? 'subió' : 'bajó';
            $variacion = $precioPedido > 0
                ? sprintf(' (%s%%)', number_format((($precioOfrecido - $precioPedido) / $precioPedido) * 100, 1, ',', '.'))
                : '';
            $diferencias[] = sprintf(
                'El precio %s: tenías %s y cotizaron %s.%s',
                $subeOBaja,
                self::dinero($precioPedido),
                self::dinero($precioOfrecido),
                $variacion,
            );
        } elseif ($precioOfrecido === null) {
            $diferencias[] = 'El documento no trae precio para esta partida.';
        }

        return new self($diferencias === [] ? 'igual' : 'difiere', $item, $linea, $diferencias, $confianza);
    }

    public static function sinCotizar(PurchaseRequestItem $item): self
    {
        return new self('sin_cotizar', $item, null, ['No aparece en la cotización del proveedor.']);
    }

    /** @param array<string, mixed> $linea */
    public static function noPedida(array $linea): self
    {
        return new self('no_pedida', null, $linea, ['El proveedor la agregó: no estaba en tu solicitud.']);
    }

    public function estaBien(): bool
    {
        return $this->estado === 'igual';
    }

    private static function numero(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return is_numeric($valor) ? (float) $valor : null;
    }

    private static function cantidad(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 2, ',', '.'), '0'), ',');
    }

    private static function dinero(float $valor): string
    {
        return '$ '.number_format($valor, 0, ',', '.');
    }
}
