<?php

namespace App\Services\PurchaseRequests\Reading;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Lee cotizaciones con un modelo local servido por LM Studio, Ollama o
 * cualquier servidor compatible con la API de OpenAI.
 *
 * Estrategia según el documento:
 *  - PDF con capa de texto: se extrae el texto y se manda a un modelo de
 *    texto. Es lo más preciso y lo más barato.
 *  - PDF escaneado: se convierte la página a imagen y va a un modelo de visión.
 *  - Imagen: directo al modelo de visión.
 *
 * Nada de lo que devuelve el modelo se toma por cierto: todo pasa por
 * `verificarContraElDocumento()` antes de salir de aquí.
 */
class LocalQuotationReader implements QuotationReader
{
    public function isEnabled(): bool
    {
        return (bool) config('purchase_requests.reader.enabled');
    }

    public function describe(): string
    {
        return 'modelo local en '.config('purchase_requests.reader.base_url');
    }

    public function read(string $absolutePath, string $mimeType, array $knownUnits = []): QuotationReading
    {
        if (! $this->isEnabled()) {
            return QuotationReading::failed('El asistente de lectura está desactivado.');
        }

        if (! is_readable($absolutePath)) {
            return QuotationReading::failed('No se pudo abrir el documento.');
        }

        try {
            [$sourceKind, $texto, $imagenBase64] = $this->prepararEntrada($absolutePath, $mimeType);
        } catch (Throwable $e) {
            return QuotationReading::failed('No se pudo preparar el documento: '.$e->getMessage());
        }

        if ($texto === null && $imagenBase64 === null) {
            return QuotationReading::failed('El documento no tiene texto legible ni pudo convertirse a imagen.');
        }

        $modelo = $imagenBase64 !== null
            ? config('purchase_requests.reader.vision_model')
            : (config('purchase_requests.reader.text_model') ?: config('purchase_requests.reader.vision_model'));

        try {
            $crudo = $this->preguntarAlModelo($modelo, $texto, $imagenBase64, $knownUnits);
        } catch (Throwable $e) {
            Log::warning('El asistente no pudo leer una cotización.', [
                'archivo' => basename($absolutePath),
                'motivo' => $e->getMessage(),
            ]);

            return QuotationReading::failed('El modelo no respondió: '.$e->getMessage(), $modelo, $sourceKind);
        }

        // El texto del documento es la única verdad disponible para contrastar.
        $referencia = $texto ?? '';

        [$items, $avisos] = $this->verificarContraElDocumento(
            $crudo['items'] ?? [],
            $referencia,
            $knownUnits,
            $imagenBase64 !== null,
        );

        if ($items === []) {
            return QuotationReading::failed(
                'No se reconoció ninguna partida en el documento.',
                $modelo,
                $sourceKind,
            );
        }

        return QuotationReading::of(
            items: $items,
            supplier: $this->limpiar($crudo['supplier'] ?? null),
            reason: $this->limpiar($crudo['reason'] ?? null),
            warnings: $avisos,
            model: $modelo,
            sourceKind: $sourceKind,
        );
    }

    /**
     * @return array{0: string, 1: ?string, 2: ?string}  [tipo, texto, imagen en base64]
     */
    private function prepararEntrada(string $ruta, string $mimeType): array
    {
        if (str_starts_with($mimeType, 'image/')) {
            return [
                PurchaseRequestSourceKind::IMAGE,
                null,
                base64_encode((string) file_get_contents($ruta)),
            ];
        }

        // PDF: primero se busca su capa de texto.
        $texto = $this->extraerTexto($ruta);

        if ($texto !== null && mb_strlen(trim($texto)) >= 80) {
            return [PurchaseRequestSourceKind::PDF_TEXT, $texto, null];
        }

        // Sin texto útil: es un escaneo, así que se mira como imagen.
        $png = $this->primeraPaginaComoImagen($ruta);

        return [PurchaseRequestSourceKind::PDF_SCAN, null, $png];
    }

    private function extraerTexto(string $ruta): ?string
    {
        $destino = tempnam(sys_get_temp_dir(), 'sc-txt-');

        $proc = new Process([
            (string) config('purchase_requests.reader.pdftotext'),
            '-layout',
            '-f', '1',
            '-l', (string) config('purchase_requests.reader.max_pages'),
            $ruta,
            $destino,
        ]);
        $proc->setTimeout(60);
        $proc->run();

        $texto = $proc->isSuccessful() && is_readable($destino)
            ? (string) file_get_contents($destino)
            : null;

        @unlink($destino);

        return $texto;
    }

    private function primeraPaginaComoImagen(string $ruta): ?string
    {
        $prefijo = sys_get_temp_dir().'/sc-img-'.Str::random(10);

        $proc = new Process([
            (string) config('purchase_requests.reader.pdftoppm'),
            '-png',
            '-r', '150',
            '-f', '1',
            '-l', '1',
            $ruta,
            $prefijo,
        ]);
        $proc->setTimeout(120);
        $proc->run();

        $generados = glob($prefijo.'*.png') ?: [];

        if ($generados === []) {
            return null;
        }

        $contenido = (string) file_get_contents($generados[0]);

        foreach ($generados as $archivo) {
            @unlink($archivo);
        }

        return base64_encode($contenido);
    }

    /**
     * @param  list<string>  $knownUnits
     * @return array<string, mixed>
     */
    private function preguntarAlModelo(string $modelo, ?string $texto, ?string $imagenBase64, array $knownUnits): array
    {
        $unidades = $knownUnits === [] ? 'Unidades' : implode(', ', $knownUnits);

        $sistema = <<<TXT
        Eres un asistente que lee cotizaciones y listas de materiales de una empresa agrícola chilena
        y extrae las partidas para una solicitud de compra interna.

        REGLAS ESTRICTAS:
        - Extrae SOLO lo que aparece literalmente en el documento. No completes ni supongas.
        - Si la cantidad no está clara, deja "quantity" vacío. NUNCA la inventes ni la copies de otra línea.
        - Si la unidad no está clara, deja "unit" vacío.
        - Usa exclusivamente estas unidades cuando calcen: {$unidades}. Si ninguna calza, deja "unit" vacío.
        - Respeta la coma decimal chilena: 1,5 se escribe "1,5".
        - Si dos líneas repiten el mismo producto, devuélvelas como dos partidas separadas. No las sumes.
        - No traduzcas los nombres de productos ni las unidades. Mantén el español del documento.
        - Ignora precios, totales, impuestos y descuentos: esta solicitud no los lleva.
        Responde SOLO el JSON pedido.
        TXT;

        $esquema = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['supplier', 'reason', 'items'],
            'properties' => [
                'supplier' => ['type' => 'string', 'description' => 'Proveedor que emite la cotización, o cadena vacía.'],
                'reason' => ['type' => 'string', 'description' => 'Para qué es la compra, en una frase corta, o cadena vacía.'],
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['product_service', 'specification', 'quantity', 'unit'],
                        'properties' => [
                            'product_service' => ['type' => 'string'],
                            'specification' => ['type' => 'string'],
                            'quantity' => ['type' => 'string'],
                            'unit' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];

        $contenidoUsuario = $imagenBase64 !== null
            ? [
                ['type' => 'text', 'text' => 'Extrae las partidas de esta cotización.'],
                ['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,'.$imagenBase64]],
            ]
            : "Extrae las partidas de esta cotización.\n\n---\n".mb_substr((string) $texto, 0, 12000);

        $respuesta = Http::withToken((string) config('purchase_requests.reader.api_key'))
            ->timeout((int) config('purchase_requests.reader.timeout'))
            ->acceptJson()
            ->post(config('purchase_requests.reader.base_url').'/chat/completions', [
                'model' => $modelo,
                'temperature' => 0,
                'messages' => [
                    ['role' => 'system', 'content' => $sistema],
                    ['role' => 'user', 'content' => $contenidoUsuario],
                ],
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => ['name' => 'cotizacion', 'strict' => true, 'schema' => $esquema],
                ],
            ])
            ->throw();

        $contenido = data_get($respuesta->json(), 'choices.0.message.content');
        $decodificado = json_decode((string) $contenido, true);

        if (! is_array($decodificado)) {
            throw new \RuntimeException('El modelo no devolvió un JSON válido.');
        }

        return $decodificado;
    }

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
    private function verificarContraElDocumento(array $items, string $referencia, array $knownUnits, bool $esImagen): array
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

            $limpios[] = [
                'product_service' => Str::limit($producto, 990, ''),
                'specification' => $this->limpiar($item['specification'] ?? null),
                'quantity' => $cantidad,
                'unit' => $unidad,
            ];
        }

        // Las unidades faltantes se resumen en un solo aviso: veintitrés
        // líneas repitiendo lo mismo no ayudarían a nadie. Sin este aviso, la
        // lectura aparecía «con dudas» sin explicar por qué.
        if ($sinUnidad !== []) {
            $avisos[] = count($sinUnidad) === count($limpios)
                ? 'Ninguna partida traía la unidad en el documento. Complétalas antes de enviar.'
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
    private function separarCantidadYUnidad(?string $valor): array
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
    private function unidadDelCatalogo(?string $unidad, array $knownUnits): ?string
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
     * ¿La unidad aparece nombrada en el propio texto de la partida?
     *
     * Es la única verificación posible cuando el documento es una imagen y no
     * hay texto extraíble. Imperfecta, pero atrapa el caso frecuente: una
     * unidad plausible pegada a una línea que nunca la mencionó.
     */
    private function unidadRespaldadaPorLaLinea(string $unidad, string $textoDeLaLinea): bool
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
    private function apareceEnElTexto(string $cantidad, string $refNormalizada): bool
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
