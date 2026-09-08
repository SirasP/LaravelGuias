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
        /** Distingue un problema de una nota informativa: el precio que llega. */
        public readonly bool $hayProblema = false,
        /** El cruce lo enseñó una persona, así que una persona puede deshacerlo. */
        public readonly bool $aprendida = false,
        /** En qué renglón del documento va: lo único que distingue dos líneas de igual nombre. */
        public readonly ?int $renglon = null,
    ) {}

    /** @param array<string, mixed> $linea */
    public static function emparejada(
        PurchaseRequestItem $item,
        array $linea,
        float $confianza,
        bool $aprendida = false,
        ?int $renglon = null,
    ): self {
        return self::cruzada($item, $linea, $confianza, false, $aprendida, $renglon);
    }

    /**
     * Una pareja que el programa cree, pero no afirma.
     *
     * Aparece cruzada en pantalla, con sus diferencias calculadas y un botón
     * para confirmarla. Es lo único honesto cuando el proveedor imprime el
     * nombre cortado a veintiocho caracteres y lo único que respalda la pareja
     * es la cantidad y el orden de los renglones.
     *
     * @param  array<string, mixed>  $linea
     */
    public static function propuesta(PurchaseRequestItem $item, array $linea, float $confianza, ?int $renglon = null): self
    {
        return self::cruzada($item, $linea, $confianza, true, false, $renglon);
    }

    /** @param array<string, mixed> $linea */
    private static function cruzada(
        PurchaseRequestItem $item,
        array $linea,
        float $confianza,
        bool $propuesta,
        bool $aprendida = false,
        ?int $renglon = null,
    ): self {
        $diferencias = [];
        $notas = [];

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
            && self::unidad($unidadPedida) !== self::unidad($unidadOfrecida)) {
            $diferencias[] = sprintf('La unidad no coincide: %s contra %s.', $unidadPedida, $unidadOfrecida);
        }

        $precioPedido = self::numero($item->unit_price);
        $precioOfrecido = self::numero($linea['unit_price'] ?? null);

        // Que traiga precio cuando la solicitud no tenía ninguno no es una
        // diferencia: es la cotización haciendo su trabajo. Contarlo como tal
        // pintaba de ámbar catorce partidas correctas y anunciaba «24
        // diferencias» en una cotización que había cruzado casi entera.
        if ($precioOfrecido !== null && $precioPedido === null) {
            $notas[] = 'Cotizado en '.self::dinero($precioOfrecido).'.';
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

        if ($propuesta) {
            array_unshift($notas, 'Propuesta: nadie ha confirmado todavía que sean lo mismo.');
        }

        return new self(
            $propuesta ? 'propuesta' : ($diferencias === [] ? 'igual' : 'difiere'),
            $item,
            $linea,
            [...$diferencias, ...$notas],
            $confianza,
            $diferencias !== [],
            $aprendida,
            $renglon,
        );
    }

    public static function sinCotizar(PurchaseRequestItem $item): self
    {
        return new self('sin_cotizar', $item, null, ['No aparece en la cotización del proveedor.']);
    }

    /** @param array<string, mixed> $linea */
    public static function noPedida(array $linea, ?int $renglon = null): self
    {
        return new self(
            'no_pedida', null, $linea, ['El proveedor la agregó: no estaba en tu solicitud.'],
            0.0, false, false, $renglon,
        );
    }

    public function estaBien(): bool
    {
        return $this->estado === 'igual';
    }

    /** ¿Está cruzada con una línea del proveedor, sea firme o propuesta? */
    public function cruzo(): bool
    {
        return $this->cotizada !== null && $this->pedida !== null;
    }

    /** ¿Falta que una persona diga que sí? */
    public function esPropuesta(): bool
    {
        return $this->estado === 'propuesta';
    }

    /**
     * La unidad reducida a lo que significa.
     *
     * Cada proveedor la abrevia a su manera —«UN», «UND», «Unidades», «C/U»—
     * y anunciar una diferencia por eso llenaba la comparación de ámbar en
     * partidas donde no pasaba absolutamente nada.
     */
    private static function unidad(string $valor): string
    {
        $limpio = trim(preg_replace('/[^a-z0-9]+/', ' ', \Illuminate\Support\Str::of($valor)->ascii()->lower()->value()) ?? '');

        return match ($limpio) {
            'un', 'und', 'unid', 'unids', 'unidad', 'unidades', 'uds', 'u', 'c u', 'cu', 'ea' => 'un',
            'par', 'pares', 'paa', 'pr' => 'par',
            'kg', 'kgs', 'kilo', 'kilos' => 'kg',
            'lt', 'lts', 'litro', 'litros', 'l' => 'lt',
            'mt', 'mts', 'm', 'metro', 'metros' => 'mt',
            'cja', 'caja', 'cajas', 'cj' => 'caja',
            'pza', 'pzas', 'pieza', 'piezas' => 'pza',
            'rll', 'rollo', 'rollos' => 'rollo',
            default => $limpio,
        };
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
