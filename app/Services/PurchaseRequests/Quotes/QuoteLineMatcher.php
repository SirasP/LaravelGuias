<?php

namespace App\Services\PurchaseRequests\Quotes;

/**
 * Decide qué línea del proveedor responde a qué partida de la solicitud.
 *
 * Una cotización no es una lista suelta: es la respuesta a un papel que
 * nosotros escribimos, y eso deja pistas que el parecido entre textos no ve.
 * Las cantidades vienen copiadas de la solicitud. El orden suele conservarse.
 * Cuando el proveedor imprime «OVEROL PILOTO MS POP C/C, NA» cuatro veces
 * porque su formulario corta a veintiocho caracteres, el texto ya no distingue
 * nada y sólo quedan esas dos pistas.
 *
 * Por eso hay dos clases de pareja. Las seguras se dan por hechas. Las
 * probables se proponen en pantalla con la propuesta ya escrita, y una persona
 * las confirma de un clic —confirmarlas además enseña el alias, así que la
 * próxima cotización de ese proveedor cruza sola.
 *
 * Nada se empareja a la fuerza: lo que no calza queda sin cotizar, que es
 * información y no un fallo.
 */
final class QuoteLineMatcher
{
    /**
     * Con esto, el texto solo basta.
     *
     * Medido sobre la SC-2026-000031: «CASCO MSA V-GARD - (BLANCO)» da 0,5487
     * contra la partida, y los dos trajes de agua 0,51 y 0,52. Las confusiones
     * de talla y de medida no llegan aquí: valen exactamente 0,0000 porque el
     * comparador las descalifica antes de puntuarlas.
     */
    private const SEGURO = 0.50;

    /**
     * Con la cantidad idéntica de respaldo, basta bastante menos.
     *
     * «Bloqueador solar 1000 ml» contra «BLOQUEADOR SOLAR 1 KG C/VAL FPS 50»
     * da 0,4758, y «Lente Oscuro Con Protector UV basic» contra «ANTEOJO
     * POLICARB. BASIC GRIS» apenas 0,3354. Las dos son la misma cosa y las dos
     * traen la cantidad exacta que se pidió.
     */
    private const CON_CANTIDAD = 0.33;

    /** Debajo de esto no se propone nada, ni siquiera para confirmar. */
    private const PISO = 0.18;

    /**
     * @param  list<mixed>  $items  Las partidas, en el orden de la solicitud.
     * @param  list<array<string, mixed>>  $lineas  Lo que el documento traía.
     * @param  callable(mixed, array<string, mixed>): float  $parecido
     * @param  array<int, int>  $yaDichas  Lo que una persona ya confirmó: partida => renglón.
     * @param  array<string, bool>  $vetadas  Lo que una persona dijo que NO era.
     */
    public function emparejar(
        array $items,
        array $lineas,
        callable $parecido,
        array $yaDichas = [],
        array $vetadas = [],
    ): QuoteMatching {
        $puntajes = $this->puntuar($items, $lineas, $parecido, $vetadas);

        [$seguras, $conApoyo] = $this->porConfianza($puntajes, $yaDichas);

        // Las apoyadas en la cantidad también sirven de ancla: son parejas
        // buenas, sólo que sin la firma de nadie todavía.
        $probables = $conApoyo;
        $probables += $this->porOrden($items, $lineas, $seguras + $conApoyo, $puntajes);
        $probables += $this->porCantidad($items, $lineas, $seguras + $probables, $puntajes);

        return new QuoteMatching($seguras, $probables, $puntajes);
    }

    /**
     * La matriz completa: cuánto se parece cada partida a cada línea.
     *
     * Se calcula entera antes de decidir nada. Emparejar partida por partida
     * —la primera se queda con lo que más se le parezca y las demás con lo que
     * sobre— hace que una partida vaga se lleve el renglón que otra necesitaba.
     *
     * @param  list<mixed>  $items
     * @param  list<array<string, mixed>>  $lineas
     * @param  callable(mixed, array<string, mixed>): float  $parecido
     * @return array<string, float>
     */
    private function puntuar(array $items, array $lineas, callable $parecido, array $vetadas = []): array
    {
        $puntajes = [];

        foreach ($items as $i => $item) {
            foreach ($lineas as $j => $linea) {
                // Lo que una persona ya descartó no vuelve a puntuar: si dijo
                // que «CINTA PELIGRO» no es «ESCOBILLON DOMESTICO», no hay que
                // proponérselo cada vez que abra la página.
                if (isset($vetadas[$i.':'.$j])) {
                    continue;
                }

                $puntaje = $parecido($item, $linea);

                if ($puntaje > 0.0) {
                    $puntajes[$i.':'.$j] = $puntaje;
                }
            }
        }

        return $puntajes;
    }

    /**
     * Las parejas que sostiene el parecido, con o sin ayuda de la cantidad.
     *
     * Se toman las mejores primero, sean de la partida que sean, y cada lado
     * se usa una sola vez. Así el mejor par disponible siempre gana, en vez de
     * ganar el que aparecía antes en la lista.
     *
     * Vuelven separadas. Las que sostiene el texto por sí solo se dan por
     * hechas; las que necesitaron la cantidad de respaldo se proponen, porque
     * de estas parejas salen los precios que después se escriben en Odoo y un
     * emparejado silencioso equivocado ahí cuesta caro.
     *
     * Lo que una persona ya confirmó entra primero y no se discute: ocupa su
     * partida y su renglón antes de que nadie los reclame.
     *
     * @param  array<string, float>  $puntajes
     * @param  array<int, int>  $yaDichas
     * @return array{0: array<int, int>, 1: array<int, int>}
     */
    private function porConfianza(array $puntajes, array $yaDichas = []): array
    {
        $candidatas = [];

        foreach ($puntajes as $clave => $puntaje) {
            [$i, $j] = array_map('intval', explode(':', $clave));

            if ($puntaje >= self::SEGURO || ($puntaje >= self::CON_CANTIDAD && $this->cantidadCalza($i, $j))) {
                $candidatas[] = [$i, $j, $puntaje];
            }
        }

        // Entre dos parejas que el texto puntúa igual —el mismo overol impreso
        // cuatro veces porque el formulario del proveedor corta a veintiocho
        // caracteres— decide la cantidad, y sólo después la posición. Al revés
        // se cruzaban: el overol S se llevaba el renglón de cinco unidades
        // porque su renglón quedaba un lugar más cerca.
        usort($candidatas, fn (array $a, array $b): int => round($b[2], 4) <=> round($a[2], 4)
            ?: $this->cantidadCalza($b[0], $b[1]) <=> $this->cantidadCalza($a[0], $a[1])
            ?: abs($a[0] - $a[1]) <=> abs($b[0] - $b[1])
            ?: $a[0] <=> $b[0]);

        $tomadas = $this->tomar($candidatas, $yaDichas);
        $seguras = $yaDichas;
        $conApoyo = [];

        foreach ($tomadas as $i => $j) {
            if (($puntajes[$i.':'.$j] ?? 0.0) >= self::SEGURO) {
                $seguras[$i] = $j;
            } else {
                $conApoyo[$i] = $j;
            }
        }

        return [$seguras, $conApoyo];
    }

    /**
     * Lo que queda encajonado entre dos parejas seguras.
     *
     * Si entre la partida 5 y la 12 —ya emparejadas con los renglones 2 y 9—
     * quedan seis partidas sueltas y exactamente seis renglones sueltos, el
     * orden los aparea uno a uno. Es el mismo razonamiento con el que una
     * persona lee las dos hojas en paralelo, y es lo único que resuelve seis
     * overoles cuyo nombre el proveedor imprimió idéntico.
     *
     * Exige que el hueco calce exacto por los dos lados: si sobra o falta un
     * renglón, el orden ya no prueba nada y no se propone nada.
     *
     * @param  list<mixed>  $items
     * @param  list<array<string, mixed>>  $lineas
     * @param  array<int, int>  $seguras
     * @param  array<string, float>  $puntajes
     * @return array<int, int>
     */
    private function porOrden(array $items, array $lineas, array $seguras, array $puntajes): array
    {
        if ($seguras === []) {
            return [];
        }

        $anclas = $seguras;
        ksort($anclas);

        // Los bordes se tratan como anclas imaginarias para que el primer y el
        // último hueco se examinen igual que los del medio.
        $bordes = [[-1, -1], ...array_map(null, array_keys($anclas), array_values($anclas)), [count($items), count($lineas)]];
        $propuestas = [];

        for ($k = 0; $k < count($bordes) - 1; $k++) {
            [$desdeItem, $desdeLinea] = $bordes[$k];
            [$hastaItem, $hastaLinea] = $bordes[$k + 1];

            // Un hueco de cero por un lado no es un hueco: range() daría la
            // vuelta y propondría parejas al revés.
            if ($hastaItem - $desdeItem <= 1 || $hastaLinea - $desdeLinea <= 1) {
                continue;
            }

            if ($hastaItem - $desdeItem !== $hastaLinea - $desdeLinea) {
                continue;
            }

            $itemsDelHueco = range($desdeItem + 1, $hastaItem - 1);
            $lineasDelHueco = range($desdeLinea + 1, $hastaLinea - 1);

            foreach ($itemsDelHueco as $n => $i) {
                $j = $lineasDelHueco[$n];

                if (($puntajes[$i.':'.$j] ?? 0.0) >= self::PISO) {
                    $propuestas[$i] = $j;
                }
            }
        }

        return $propuestas;
    }

    /**
     * Lo que sobra, cuando la cantidad respalda al texto flojo.
     *
     * El proveedor puede reordenar su cotización entera —contestar al final lo
     * que se pidió primero— y ahí el orden no sirve. Queda la cantidad: si el
     * mejor renglón libre pide exactamente lo mismo, se propone.
     *
     * @param  list<mixed>  $items
     * @param  list<array<string, mixed>>  $lineas
     * @param  array<int, int>  $tomadas
     * @param  array<string, float>  $puntajes
     * @return array<int, int>
     */
    private function porCantidad(array $items, array $lineas, array $tomadas, array $puntajes): array
    {
        // Sólo cuando el documento ya se ganó el derecho. Si casi todo cruzó,
        // lo que queda suelto probablemente también se corresponde. Si no cruzó
        // nada, una cantidad igual no dice nada: pedir una unidad de algo y que
        // el proveedor cobre un flete de una unidad no los hace lo mismo.
        $minimo = min(count($items), count($lineas));

        if (count($tomadas) < 2 || count($tomadas) * 2 < $minimo) {
            return [];
        }

        $candidatas = [];
        $ocupadas = array_flip($tomadas);

        foreach ($items as $i => $item) {
            if (isset($tomadas[$i])) {
                continue;
            }

            foreach ($lineas as $j => $linea) {
                if (isset($ocupadas[$j])) {
                    continue;
                }

                $puntaje = $puntajes[$i.':'.$j] ?? 0.0;

                if ($puntaje >= self::PISO && $this->cantidadCalza($i, $j)) {
                    $candidatas[] = [$i, $j, $puntaje];
                }
            }
        }

        usort($candidatas, fn (array $a, array $b): int => $b[2] <=> $a[2] ?: $a[0] <=> $b[0]);

        return $this->tomar($candidatas, $tomadas);
    }

    /**
     * Recorre las candidatas ya ordenadas y se queda con las que no chocan.
     *
     * @param  list<array{0: int, 1: int, 2: float}>  $candidatas
     * @param  array<int, int>  $yaTomadas
     * @return array<int, int>
     */
    private function tomar(array $candidatas, array $yaTomadas = []): array
    {
        $porItem = [];
        $porLinea = array_flip($yaTomadas);

        foreach ($candidatas as [$i, $j]) {
            if (isset($porItem[$i]) || isset($porLinea[$j]) || isset($yaTomadas[$i])) {
                continue;
            }

            $porItem[$i] = $j;
            $porLinea[$j] = $i;
        }

        return $porItem;
    }

    /** @var array<string, bool> */
    private array $cantidades = [];

    /** ¿La partida y el renglón piden exactamente lo mismo? */
    private function cantidadCalza(int $i, int $j): bool
    {
        return $this->cantidades[$i.':'.$j] ?? false;
    }

    /**
     * Se calcula una vez, fuera del bucle de puntajes, y se consulta después.
     *
     * @param  list<mixed>  $items
     * @param  list<array<string, mixed>>  $lineas
     */
    public function conCantidades(array $items, array $lineas): self
    {
        $this->cantidades = [];

        foreach ($items as $i => $item) {
            $pedida = $this->numero($item->quantity ?? null);

            if ($pedida === null) {
                continue;
            }

            foreach ($lineas as $j => $linea) {
                $ofrecida = $this->numero($linea['quantity'] ?? null);

                if ($ofrecida !== null && abs($pedida - $ofrecida) < 0.0001) {
                    $this->cantidades[$i.':'.$j] = true;
                }
            }
        }

        return $this;
    }

    /** Con la coma decimal chilena: sin esto la cantidad no respaldaba nada. */
    private function numero(mixed $valor): ?float
    {
        if (is_int($valor) || is_float($valor)) {
            return (float) $valor;
        }

        return is_string($valor) ? \App\Support\ChileanMoney::parse($valor) : null;
    }
}
