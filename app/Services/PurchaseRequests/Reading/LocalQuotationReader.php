<?php

namespace App\Services\PurchaseRequests\Reading;

use App\Support\Rut;
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

        [$items, $avisos] = (new LineVerifier)->verificarContraElDocumento(
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

        // Los RUT se sacan del texto con expresión regular y se validan por su
        // dígito verificador. No se le preguntan al modelo: es un patrón fijo
        // con comprobación matemática, y ahí una IA sólo puede inventar.
        [$rutProveedor, $rutCliente, $avisosRut] = $this->identificarPartes($referencia);
        $avisos = array_values(array_unique([...$avisos, ...$avisosRut]));

        return QuotationReading::of(
            items: $items,
            supplier: $this->limpiar($crudo['supplier'] ?? null),
            reason: $this->limpiar($crudo['reason'] ?? null),
            warnings: $avisos,
            model: $modelo,
            sourceKind: $sourceKind,
            supplierTaxId: $rutProveedor,
            customerTaxId: $rutCliente,
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

        PROVEEDOR:
        - "supplier" es la EMPRESA que emite el documento, la que aparece en el encabezado
          junto a su RUT (por ejemplo "DERCOMAQ S.P.A." o "MOTORMAN S.A").
        - NO es el vendedor, ejecutivo o contacto que atiende. Un campo "Vendedor:",
          "Ejecutivo:" o "Atendido por:" contiene el nombre de una PERSONA, y esa persona
          nunca es el proveedor.
        - Tampoco es el cliente ni quien recibe la cotización.
        - Si no aparece con claridad el nombre de la empresa emisora, deja "supplier" vacío:
          el RUT se extrae aparte y basta para identificarla.

        COLUMNAS:
        - "product_service" es el NOMBRE del producto (por ejemplo "ANILLO PISTON STD"),
          nunca su código interno. Si el documento tiene una columna de código o SKU
          (por ejemplo "KU0214-014047"), ese código va en "specification", no en el nombre.

        MOTIVO:
        - "reason" es el propósito de la compra, y sólo si el documento lo declara
          explícitamente (un campo "Motivo", "Obra", "Destino" o similar).
        - El giro, rubro o actividad económica del proveedor NO es el motivo. Tampoco
          lo es su razón social ni su dirección. Si el documento no declara un motivo,
          deja "reason" vacío.

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
     * Distingue el RUT del proveedor del de la empresa que recibe.
     *
     * En una cotización chilena el emisor va en el encabezado y el cliente en
     * su propio bloque, precedido por «Cliente», «Señor(es)» o «Razón social».
     * Se usa esa cercanía para separarlos, y si igual queda ambiguo, se
     * reconoce el nuestro por el RUT configurado de la empresa.
     *
     * @return array{0: ?string, 1: ?string, 2: list<string>}
     */
    private function identificarPartes(string $texto): array
    {
        if (trim($texto) === '') {
            return [null, null, []];
        }

        $encontrados = Rut::findAll($texto);

        if ($encontrados === []) {
            return [null, null, ['No se encontró ningún RUT en el documento; el proveedor queda sin identificar.']];
        }

        $nuestro = Rut::normalize(config('purchase_requests.company.tax_id'));
        $avisos = [];

        // Si uno de los RUT es el nuestro, no hay nada que adivinar.
        $rutCliente = null;
        foreach ($encontrados as $item) {
            if ($nuestro !== null && $item['rut'] === $nuestro) {
                $rutCliente = $item['rut'];

                break;
            }
        }

        // Si no, el cliente es el RUT que sigue a la etiqueta que lo nombra.
        if ($rutCliente === null) {
            $posicionEtiqueta = $this->posicionDeLaEtiquetaDeCliente($texto);

            if ($posicionEtiqueta !== null) {
                foreach ($encontrados as $item) {
                    if ($item['posicion'] > $posicionEtiqueta) {
                        $rutCliente = $item['rut'];

                        break;
                    }
                }
            }
        }

        // El proveedor es el primer RUT que no sea el del cliente: en estos
        // documentos el emisor encabeza la página.
        $rutProveedor = null;
        foreach ($encontrados as $item) {
            if ($item['rut'] !== $rutCliente) {
                $rutProveedor = $item['rut'];

                break;
            }
        }

        if ($rutProveedor === null) {
            $avisos[] = 'No se pudo distinguir el RUT del proveedor en el documento.';
        }

        if ($rutCliente === null) {
            $avisos[] = 'No se reconoció a qué empresa va dirigida la cotización.';
        } elseif ($nuestro !== null && $rutCliente !== $nuestro) {
            $avisos[] = sprintf(
                'Ojo: la cotización va dirigida al RUT %s, que no es el de %s. Verifica que corresponda.',
                Rut::format($rutCliente),
                config('purchase_requests.company.name'),
            );
        }

        return [$rutProveedor, $rutCliente, $avisos];
    }

    private function posicionDeLaEtiquetaDeCliente(string $texto): ?int
    {
        $plano = mb_strtolower($texto);

        foreach (['cliente', 'señor(es)', 'senor(es)', 'señores', 'razon social', 'razón social'] as $etiqueta) {
            $posicion = mb_strpos($plano, $etiqueta);

            if ($posicion !== false) {
                return $posicion;
            }
        }

        return null;
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
