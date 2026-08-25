<?php

namespace App\Services\PurchaseRequests\Reading;

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
    public function verificarContraElDocumento(array $items, string $referencia, array $knownUnits, bool $esImagen): array
    {
        $limpios = [];
        $avisos = [];
        $sinUnidad = [];
        $refNormalizada = $this->normalizar($referencia);
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

            // La unidad tiene que estar en el catálogo. Si no está, se vacía.
            if (filled($unidad) && $unidadesConocidas !== [] && ! in_array($this->normalizar($unidad), $unidadesConocidas, true)) {
                $avisos[] = sprintf('Partida N° %d: la unidad «%s» no está en el catálogo y se dejó vacía.', $numero, $unidad);
                $unidad = null;
            }

            // Con una imagen no hay texto del documento contra el cual
            // contrastar, y el modelo tiende a rellenar con una unidad
            // plausible: en una prueba real puso «Cajas» a unos guantes cuyo
            // documento decía «20 C/ TALLA». Se exige entonces que la unidad
            // esté respaldada por el texto de su propia línea.
            if ($esImagen && filled($unidad) && ! $this->unidadRespaldadaPorLaLinea($unidad, $producto.' '.($item['specification'] ?? ''))) {
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

            if (blank($unidad)) {
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
                'unit' => $unidad,
            ];
        }

        // Las unidades faltantes se resumen en un solo aviso: veintitrés
        // líneas repitiendo lo mismo no ayudarían a nadie. Sin este aviso, la
        // lectura aparecía «con dudas» sin explicar por qué.
        if ($sinUnidad !== []) {
            $avisos[] = count($sinUnidad) === count($limpios)
                ? 'Ninguna partida trae unidad. Complétalas antes de enviar.'
                : sprintf(
                    '%s %d %s sin unidad (N° %s). Complétalas antes de enviar.',
                    count($sinUnidad) === 1 ? 'Quedó' : 'Quedaron',
                    count($sinUnidad),
                    count($sinUnidad) === 1 ? 'partida' : 'partidas',
                    implode(', ', array_slice($sinUnidad, 0, 12)).(count($sinUnidad) > 12 ? '…' : ''),
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
        $candidatos = array_unique([
            $cantidad,
            str_replace(',', '.', $cantidad),
            str_replace('.', ',', $cantidad),
            rtrim(rtrim(str_replace(',', '.', $cantidad), '0'), '.'),
        ]);

        foreach ($candidatos as $candidato) {
            if ($candidato !== '' && str_contains($refNormalizada, $this->normalizar($candidato))) {
                return true;
            }
        }

        return false;
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
