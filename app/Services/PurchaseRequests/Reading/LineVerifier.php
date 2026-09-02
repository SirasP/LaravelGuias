<?php

namespace App\Services\PurchaseRequests\Reading;

use App\Support\ChileanMoney;
use Illuminate\Support\Str;

/**
 * Contrasta lo que propone un modelo contra el texto que lo originó.
 *
 * La usan tanto la lectura de documentos como el asistente de texto libre: en
 * ambos casos hay un texto de referencia —el documento o lo que la persona
 * escribió— y una propuesta que no puede tomarse por cierta.
 *
 * Todo lo que hace es determinista. Ninguna de estas comprobaciones vuelve a
 * consultar al modelo: si la verificación dependiera de él, no verificaría nada.
 */
class LineVerifier
{
    /**
     * Unidad que se asume cuando el documento no declara ninguna.
     *
     * Es una regla de la empresa: sin otra indicación, se cuentan unidades.
     * Evita que una solicitud quede trabada por un dato que el papel nunca
     * trajo, y siempre se avisa para que la persona pueda cambiarlo.
     */
    public const UNIDAD_POR_DEFECTO = 'Unidades';

    /**
     * Red de seguridad: contrasta lo que dijo el modelo contra el documento.
     *
     * Un modelo pequeño puede inventar una cantidad o traducir una unidad. Aquí
     * se descarta todo dato que no aparezca en el texto original y se marca
     * cada unidad que no esté en el catálogo. Con un PDF escaneado no hay texto
     * contra el cual verificar, así que la lectura entera se marca como dudosa.
     *
     * @param  list<array<string, mixed>>  $items
     * @param  list<string>  $knownUnits
     * @return array{0: list<array<string, string|null>>, 1: list<string>}
     */
    /**
     * @param  bool  $referenciaEsUnaFrase  cuando el texto es una frase corta
     *                                      escrita a mano —«cloro 5 litros»—, la unidad puede estar en cualquier
     *                                      parte de ella y no necesariamente pegada al producto. En un documento
     *                                      con tabla, en cambio, cada línea responde por lo suyo.
     */
    public function verificarContraElDocumento(array $items, string $referencia, array $knownUnits, bool $esImagen, bool $referenciaEsUnaFrase = false): array
    {
        $limpios = [];
        $avisos = [];
        $sinUnidad = [];
        // «dos correas» tiene que valer lo mismo que «2 correas»: nadie escribe
        // pensando en lo que este verificador espera encontrar.
        $refNormalizada = $this->conNumerosEnCifra($this->normalizar($referencia));
        $unidadesConocidas = array_map(fn (string $u): string => $this->normalizar($u), $knownUnits);

        if ($esImagen) {
            $avisos[] = 'El documento es una imagen o un PDF escaneado: no se pudo contrastar contra su texto. Revisa cada línea.';
        }

        foreach ($items as $posicion => $item) {
            if (! is_array($item)) {
                continue;
            }

            $producto = $this->limpiar($item['product_service'] ?? null);

            if (blank($producto)) {
                continue;
            }

            $numero = count($limpios) + 1;
            $cantidad = $this->limpiar($item['quantity'] ?? null);
            $unidad = $this->limpiar($item['unit'] ?? null);

            // El texto tal como vino: muchos documentos escriben la unidad
            // dentro de la propia cantidad («295 mtrs»), así que también
            // cuenta como respaldo al verificarla.
            $cantidadOriginal = (string) $cantidad;

            // Los documentos escriben «295 mtrs» en la columna de cantidad. Se
            // separan aquí con código, no pidiéndoselo al modelo: partir un
            // texto es determinista y no admite invenciones.
            [$cantidad, $unidadPegada] = $this->separarCantidadYUnidad($cantidad);

            if (blank($unidad) && filled($unidadPegada)) {
                $unidad = $unidadPegada;
            }

            // Una abreviatura como «mtrs» o «un» se lleva a su nombre del
            // catálogo. Si no calza con ninguno, se deja vacía.
            $unidad = $this->unidadDelCatalogo($unidad, $knownUnits);

            // La cantidad tiene que aparecer en el documento. Si no aparece, se
            // vacía: es preferible que una persona la escriba a que el
            // asistente la haya imaginado.
            if (filled($cantidad) && ! $esImagen && ! $this->apareceEnElTexto($cantidad, $refNormalizada)) {
                $avisos[] = sprintf('Partida N° %d: la cantidad «%s» no aparece en el documento y se dejó vacía.', $numero, $cantidad);
                $cantidad = null;
            }

            // El precio se mide con la misma vara que la cantidad: si no está
            // escrito en el documento, no entra. Un precio inventado no se nota
            // al mirarlo —parece razonable— y termina en una aprobación.
            //
            // Se le pasa la cantidad porque muchos presupuestos imprimen sólo
            // el total de la línea: «9 PULIR CIGÜEÑAL $63.000». Ahí el unitario
            // correcto —7.000— no está escrito en ninguna parte, y exigirlo al
            // pie de la letra borraba precios que sí eran ciertos.
            $precio = $this->limpiar($item['unit_price'] ?? null);

            if (filled($precio) && ! $esImagen && ! $this->precioApareceEnElTexto($precio, $referencia, $cantidad)) {
                $avisos[] = sprintf(
                    'Partida N° %d: el precio «%s» no aparece en el documento y se dejó vacío.',
                    $numero,
                    $precio,
                );
                $precio = null;
            }

            // La unidad tiene que estar en el catálogo. Si no está, se vacía.
            if (filled($unidad) && $unidadesConocidas !== [] && ! in_array($this->normalizar($unidad), $unidadesConocidas, true)) {
                $avisos[] = sprintf('Partida N° %d: la unidad «%s» no está en el catálogo y se dejó vacía.', $numero, $unidad);
                $unidad = null;
            }

            // La unidad debe estar respaldada por el texto de su propia línea,
            // venga el documento como imagen o como texto. Estar en el catálogo
            // no basta: leyendo una cotización de rodamientos, el modelo puso
            // «Cada medida» a dos líneas que no mencionaban ninguna medida, y
            // como esa unidad sí existe en el catálogo, pasaba el filtro.
            // «Unidades» es la unidad neutra: cuando un documento no declara
            // ninguna, contar piezas es lo razonable y no constituye una
            // invención. Se exige respaldo sólo a las unidades específicas
            // —cajas, litros, cada talla—, que sí afirman algo sobre el
            // producto y por tanto pueden estar equivocadas.
            $esNeutra = $this->normalizar($unidad ?? '') === 'unidades';

            $textoDeLaLinea = $producto.' '.($item['specification'] ?? '').' '.$cantidadOriginal;

            if ($referenciaEsUnaFrase) {
                $textoDeLaLinea .= ' '.$referencia;
            }

            if (filled($unidad) && ! $esNeutra && ! $this->unidadRespaldadaPorLaLinea($unidad, $textoDeLaLinea)) {
                $avisos[] = sprintf(
                    'Partida N° %d: la unidad «%s» no aparece en la línea del documento y se dejó vacía.',
                    $numero,
                    $unidad,
                );
                $unidad = null;
            }

            if (blank($cantidad)) {
                $avisos[] = sprintf('Partida N° %d («%s»): falta la cantidad.', $numero, Str::limit($producto, 40));
            }

            // Regla de la empresa: si el documento no indica unidad, se cuentan
            // unidades. Se deja constancia para que la persona pueda cambiarla,
            // pero la solicitud queda lista para enviar sin trabajo extra.
            if (blank($unidad)) {
                $unidad = self::UNIDAD_POR_DEFECTO;
                $sinUnidad[] = $numero;
            }

            $especificacion = $this->limpiar($item['specification'] ?? null);

            // Cuando el documento trae una columna de código, el modelo tiende
            // a dejarlo pegado al nombre además de en su propia columna:
            // «KU0214-014047 ANILLO PISTON STD» con especificación
            // «KU0214-014047». Se limpia con código, que es determinista.
            [$producto, $especificacion] = $this->separarCodigoDelNombre($producto, $especificacion);

            $limpios[] = [
                'product_service' => Str::limit($producto, 990, ''),
                'specification' => $especificacion,
                'quantity' => $cantidad,
                'unit_price' => filled($precio) ? $this->aNumeroCanonico($precio) : null,
                'unit' => $unidad,
            ];
        }

        // Se resume en un solo aviso: veintitrés líneas repitiendo lo mismo no
        // ayudarían a nadie. No es un error, es una suposición razonable de la
        // que conviene dejar constancia.
        if ($sinUnidad !== []) {
            $avisos[] = count($sinUnidad) === count($limpios)
                ? sprintf(
                    'El documento no indica unidades: se asumió «%s» en todas las partidas. Cámbialas si corresponde.',
                    self::UNIDAD_POR_DEFECTO,
                )
                : sprintf(
                    '%s %d %s sin unidad en el documento (N° %s): se asumió «%s».',
                    count($sinUnidad) === 1 ? 'Quedó' : 'Quedaron',
                    count($sinUnidad),
                    count($sinUnidad) === 1 ? 'partida' : 'partidas',
                    implode(', ', array_slice($sinUnidad, 0, 12)).(count($sinUnidad) > 12 ? '…' : ''),
                    self::UNIDAD_POR_DEFECTO,
                );
        }

        return [$limpios, array_values(array_unique($avisos))];
    }

    /**
     * Separa «295 mtrs» en cantidad y unidad. Si no hay número al principio,
     * devuelve la cantidad tal cual y sin unidad.
     *
     * @return array{0: ?string, 1: ?string}
     */
    public function separarCantidadYUnidad(?string $valor): array
    {
        if (blank($valor)) {
            return [null, null];
        }

        if (preg_match('/^\s*([0-9]+(?:[.,][0-9]+)?)\s*(.*)$/u', $valor, $m) !== 1) {
            return [$valor, null];
        }

        $resto = trim($m[2]);

        return [$m[1], $resto === '' ? null : $resto];
    }

    /**
     * Lleva la unidad leída a su nombre del catálogo.
     *
     * Acepta la forma exacta, el singular y las abreviaturas más frecuentes de
     * los documentos. Lo que no calce se descarta: es preferible una unidad
     * vacía a una inventada.
     *
     * @param  list<string>  $knownUnits
     */
    public function unidadDelCatalogo(?string $unidad, array $knownUnits): ?string
    {
        if (blank($unidad) || $knownUnits === []) {
            return null;
        }

        $buscada = $this->normalizar($unidad);

        // Abreviaturas vistas en los documentos reales de EHE.
        $sinonimos = [
            'mtrs' => 'metros', 'mtr' => 'metros', 'mts' => 'metros', 'mt' => 'metros', 'm' => 'metros',
            'un' => 'unidades', 'uni' => 'unidades', 'unid' => 'unidades', 'u' => 'unidades', 'c/u' => 'unidades',
            'kg' => 'kilos', 'kls' => 'kilos', 'kl' => 'kilos',
            'lt' => 'litros', 'lts' => 'litros', 'l' => 'litros',
            'paq' => 'paquetes', 'pack' => 'paquetes',
            'cja' => 'cajas', 'cj' => 'cajas',
            'm3' => 'cubos', 'cubo' => 'cubos',
            'saco' => 'sacos', 'rollo' => 'rollos', 'caja' => 'cajas', 'paquete' => 'paquetes',
            'unidad' => 'unidades', 'metro' => 'metros', 'kilo' => 'kilos', 'litro' => 'litros',
        ];

        $buscada = $sinonimos[$buscada] ?? $buscada;

        foreach ($knownUnits as $conocida) {
            if ($this->normalizar($conocida) === $buscada) {
                return $conocida;
            }
        }

        return null;
    }

    /**
     * Quita del nombre el código que ya viaja en la especificación.
     *
     * Deja «ANILLO PISTON STD» como producto y «KU0214-014047» como
     * especificación, en vez de repetir el código en ambos. Sólo actúa si el
     * nombre realmente empieza o termina con esa misma especificación y queda
     * algo legible después: ante la duda, no toca nada.
     *
     * @return array{0: string, 1: ?string}
     */
    public function separarCodigoDelNombre(string $producto, ?string $especificacion): array
    {
        if (blank($especificacion)) {
            return [$producto, $especificacion];
        }

        $normalizadoProducto = $this->normalizar($producto);
        $normalizadoEspec = $this->normalizar($especificacion);

        if ($normalizadoProducto === $normalizadoEspec) {
            // Nombre y especificación idénticos: la especificación no aporta.
            return [$producto, null];
        }

        foreach (['inicio', 'fin'] as $extremo) {
            $coincide = $extremo === 'inicio'
                ? str_starts_with($normalizadoProducto, $normalizadoEspec)
                : str_ends_with($normalizadoProducto, $normalizadoEspec);

            if (! $coincide) {
                continue;
            }

            $recortado = $extremo === 'inicio'
                ? mb_substr($producto, mb_strlen($especificacion))
                : mb_substr($producto, 0, mb_strlen($producto) - mb_strlen($especificacion));

            $recortado = trim($recortado, " \t-–—:·|");

            // Sólo se acepta si lo que queda sigue siendo un nombre legible.
            if (mb_strlen($recortado) >= 3) {
                return [$recortado, $especificacion];
            }
        }

        return [$producto, $especificacion];
    }

    /**
     * ¿La unidad aparece nombrada en el propio texto de la partida?
     *
     * Es la única verificación posible cuando el documento es una imagen y no
     * hay texto extraíble. Imperfecta, pero atrapa el caso frecuente: una
     * unidad plausible pegada a una línea que nunca la mencionó.
     */
    public function unidadRespaldadaPorLaLinea(string $unidad, string $textoDeLaLinea): bool
    {
        $texto = $this->normalizar($textoDeLaLinea);
        $normalizada = $this->normalizar($unidad);

        // Raíces y abreviaturas con que cada unidad aparece escrita.
        $raices = [
            'unidades' => ['unidad', 'unid', 'c/u', ' un ', 'c/ u'],
            'metros' => ['metro', 'mtr', 'mts', ' mt'],
            'cubos' => ['cubo', 'm3'],
            'kilos' => ['kilo', ' kg', 'kls'],
            'litros' => ['litro', ' lt', 'lts', ' l '],
            'paquetes' => ['paquete', 'pack'],
            'cajas' => ['caja'],
            'sacos' => ['saco'],
            'rollos' => ['rollo'],
            'cada medida' => ['medida', 'c/ medida'],
            'cada talla' => ['talla'],
            'global / servicio' => ['global', 'servicio'],
        ];

        // Sin raíces conocidas se acepta el singular como respaldo mínimo.
        $candidatos = $raices[$normalizada] ?? [rtrim($normalizada, 's')];

        foreach ($candidatos as $candidato) {
            if (str_contains(' '.$texto.' ', $candidato)) {
                return true;
            }
        }

        return false;
    }

    /** Compara ignorando separadores decimales y ceros de relleno. */
    public function apareceEnElTexto(string $cantidad, string $refNormalizada): bool
    {
        $conPunto = str_replace(',', '.', $cantidad);

        $candidatos = [$cantidad, $conPunto, str_replace('.', ',', $cantidad)];

        // «1,50» y «1,5» son la misma cantidad, pero sólo cuando hay decimales:
        // recortar el cero de «30» lo convertía en «3», y entonces un documento
        // que decía 35 respaldaba un 30 inventado.
        if (str_contains($conPunto, '.')) {
            $sinRelleno = rtrim(rtrim($conPunto, '0'), '.');
            $candidatos[] = $sinRelleno;
            $candidatos[] = str_replace('.', ',', $sinRelleno);
        }

        foreach (array_unique($candidatos) as $candidato) {
            if ($candidato === '') {
                continue;
            }

            // Pegado a otro número no cuenta: el 5 de «15» no respalda un 5
            // suelto, ni el 35 respalda un 3.
            $patron = '/(?<![\d.,])'.preg_quote($this->normalizar($candidato), '/').'(?![\d.,])/u';

            if (preg_match($patron, $refNormalizada) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Agrega al texto la versión en cifra de los números escritos con letra.
     *
     * No reemplaza: añade. Así «dos correas y 3 poleas» respalda tanto el «2»
     * como el «3», y la comprobación de que la cantidad estaba escrita sigue
     * siendo la misma. Lo que no aparezca de ninguna de las dos formas se
     * sigue descartando igual que antes.
     */
    private function conNumerosEnCifra(string $textoNormalizado): string
    {
        $encontrados = [];

        // Se va tachando lo ya reconocido sobre una copia: si «treinta y cinco»
        // se lleva la frase entera, «treinta» no puede volver a aparecer detrás
        // y colar un 30 donde iba un 35.
        $pendiente = $textoNormalizado;

        foreach (self::numerosEnPalabras() as $palabra => $cifra) {
            $patron = '/(?<![\p{L}\p{N}])'.preg_quote($palabra, '/').'(?![\p{L}\p{N}])/u';

            if (preg_match($patron, $pendiente) === 1) {
                $encontrados[] = (string) $cifra;
                $pendiente = preg_replace($patron, ' ', $pendiente) ?? $pendiente;
            }
        }

        return $encontrados === []
            ? $textoNormalizado
            : $textoNormalizado.' '.implode(' ', array_unique($encontrados));
    }

    /**
     * Números en castellano, ya normalizados (minúsculas), ordenados de la
     * expresión más larga a la más corta.
     *
     * @return array<string, int>
     */
    private static function numerosEnPalabras(): array
    {
        static $mapa = null;

        if ($mapa !== null) {
            return $mapa;
        }

        $unidades = [
            'uno' => 1, 'una' => 1, 'un' => 1, 'dos' => 2, 'tres' => 3, 'cuatro' => 4,
            'cinco' => 5, 'seis' => 6, 'siete' => 7, 'ocho' => 8, 'nueve' => 9,
        ];

        $mapa = [
            ...$unidades,
            'diez' => 10, 'once' => 11, 'doce' => 12, 'trece' => 13, 'catorce' => 14,
            'quince' => 15, 'dieciseis' => 16, 'dieciséis' => 16, 'diecisiete' => 17,
            'dieciocho' => 18, 'diecinueve' => 19, 'veinte' => 20, 'veintiuno' => 21,
            'veintiun' => 21, 'veintiún' => 21, 'veintidos' => 22, 'veintidós' => 22,
            'veintitres' => 23, 'veintitrés' => 23, 'veinticuatro' => 24, 'veinticinco' => 25,
            'veintiseis' => 26, 'veintiséis' => 26, 'veintisiete' => 27, 'veintiocho' => 28,
            'veintinueve' => 29,
            'cien' => 100, 'ciento' => 100, 'doscientos' => 200, 'trescientos' => 300,
            'cuatrocientos' => 400, 'quinientos' => 500, 'mil' => 1000,
            // Como se pide en una ferretería, no en un examen de aritmética.
            'un par' => 2, 'par' => 2, 'media docena' => 6, 'docena' => 12,
        ];

        // Treinta y cinco, cuarenta y dos… se generan en vez de escribirlas.
        foreach (['treinta' => 30, 'cuarenta' => 40, 'cincuenta' => 50, 'sesenta' => 60,
            'setenta' => 70, 'ochenta' => 80, 'noventa' => 90] as $decena => $valor) {
            $mapa[$decena] = $valor;

            foreach ($unidades as $palabra => $suma) {
                if ($palabra === 'una' || $palabra === 'un') {
                    continue;
                }

                $mapa[$decena.' y '.$palabra] = $valor + $suma;
            }
        }

        // La expresión más larga primero, para que gane sobre sus pedazos.
        uksort($mapa, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        return $mapa;
    }

    /**
     * ¿El precio propuesto está respaldado por el documento?
     *
     * No sirve buscar el texto tal cual: el documento dice «$ 12.500» y el
     * modelo devuelve «12500». Se comparan los números ya normalizados, que es
     * lo único que significa lo mismo en ambos lados.
     *
     * Vale de dos maneras. La directa: el unitario está impreso. Y la
     * aritmética: el documento sólo trae el total de la línea, y unitario por
     * cantidad da exactamente ese total. Lo segundo sigue siendo verificación
     * —la cifra tiene que estar en el papel—, no confianza en el modelo: un
     * precio inventado no cuadra con ningún total por casualidad.
     */
    public function precioApareceEnElTexto(string $precio, string $referencia, ?string $cantidad = null): bool
    {
        $buscado = $this->aNumeroCanonico($precio);

        if ($buscado === null) {
            return false;
        }

        // Todo lo que parezca una cifra en el documento, con o sin separadores.
        preg_match_all('/\d[\d.,]*/u', $referencia, $coincidencias);

        $cifras = [];

        foreach ($coincidencias[0] ?? [] as $candidato) {
            $valor = $this->aNumeroCanonico($candidato);

            if ($valor === null) {
                continue;
            }

            if (abs($valor - $buscado) < 0.005) {
                return true;
            }

            $cifras[] = $valor;
        }

        return $this->cuadraConAlgunTotal($buscado, $cantidad, $cifras);
    }

    /**
     * ¿Unitario por cantidad da algún total impreso en el documento?
     *
     * Con cantidad 1 no se comprueba nada: el total sería el propio unitario,
     * que ya se buscó arriba, y aceptarlo aquí sería dar la vuelta al control.
     *
     * @param  list<float>  $cifras
     */
    private function cuadraConAlgunTotal(float $unitario, ?string $cantidad, array $cifras): bool
    {
        if ($cantidad === null || $unitario <= 0) {
            return false;
        }

        $veces = $this->aNumeroCanonico($cantidad);

        if ($veces === null || $veces <= 1) {
            return false;
        }

        $total = $unitario * $veces;

        foreach ($cifras as $cifra) {
            // Un peso de margen: los documentos redondean el total impreso.
            if (abs($cifra - $total) <= 1.0) {
                return true;
            }
        }

        return false;
    }

    /** @see ChileanMoney para por qué «12.500» son doce mil quinientos. */
    public function aNumeroCanonico(string $valor): ?float
    {
        return ChileanMoney::parse($valor);
    }

    private function normalizar(string $valor): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($valor)) ?? '');
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
