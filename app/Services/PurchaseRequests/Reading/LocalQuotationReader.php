<?php

namespace App\Services\PurchaseRequests\Reading;

use App\Support\ChileanMoney;
use App\Support\Rut;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
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
    /**
     * Las palabras con que un documento presenta a quien lo recibe.
     *
     * Sirven para dos cosas: situar el RUT del cliente y descartar su nombre
     * cuando el modelo lo confunde con el del proveedor.
     */
    private const ETIQUETAS_DE_CLIENTE = [
        'cliente', 'señor(es)', 'senor(es)', 'señores',
        'razon social', 'razón social', 'empresa',
    ];

    /**
     * Cuánto texto tras la etiqueta se considera «los datos del cliente».
     *
     * Da para el nombre, la dirección y la comuna, que es lo que ocupa ese
     * bloque; más allá empieza el cuerpo del documento.
     */
    private const LARGO_FRANJA_CLIENTE = 160;

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

            // Si ni siquiera se pudo llegar al modelo, el documento no tiene la
            // culpa y reintentar más tarde sí puede funcionar.
            if ($this->esProblemaDeConexion($e)) {
                return QuotationReading::unreachable(
                    'No se pudo contactar al modelo: '.$e->getMessage(),
                    $modelo,
                    $sourceKind,
                );
            }

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

        // El nombre del proveedor también se contrasta contra el documento.
        // Sin esto, el modelo llegó a nombrar «DERCOMAQ S.P.A.» en una
        // cotización donde esa palabra no aparece ni una vez: la tomó de otro
        // documento visto antes. El RUT sí es fiable, porque se extrae con
        // expresión regular y se valida.
        [$proveedor, $avisoProveedor] = $this->verificarProveedor(
            $this->limpiar($crudo['supplier'] ?? null),
            $referencia,
        );

        if ($avisoProveedor !== null) {
            $avisos[] = $avisoProveedor;
        }

        // Si el modelo no dio un nombre utilizable, lo saca el propio
        // documento. Para eso está el asistente: dejar el campo vacío es
        // devolverle el trabajo a quien pidió la lectura, y el nombre suele
        // estar a la vista, en el encabezado o junto al RUT del emisor.
        if ($proveedor === null) {
            $proveedor = $this->nombreDelEmisorSegunElTexto($referencia, $rutProveedor);

            if ($proveedor !== null) {
                $avisos[] = sprintf(
                    'El proveedor «%s» se tomó del encabezado del documento. Verifícalo.',
                    Str::limit($proveedor, 44),
                );
            }
        }

        return QuotationReading::of(
            items: $items,
            supplier: $proveedor,
            reason: $this->limpiar($crudo['reason'] ?? null),
            taxTreatment: TaxTreatment::infer(
                $items,
                $this->monto($crudo['net_total'] ?? null),
                $this->monto($crudo['tax_total'] ?? null),
                $this->monto($crudo['grand_total'] ?? null),
            ),
            warnings: $avisos,
            model: $modelo,
            sourceKind: $sourceKind,
            supplierTaxId: $rutProveedor,
            customerTaxId: $rutCliente,
        );
    }

    /**
     * @return array{0: string, 1: ?string, 2: ?string} [tipo, texto, imagen en base64]
     */
    /**
     * ¿El problema fue de conexión, y no del documento ni del modelo?
     *
     * El túnel hacia la Mac se cae cada vez que esa máquina se duerme. Eso no
     * es un fallo de lectura: es una ausencia temporal, y merece esperar en
     * vez de dar el documento por ilegible.
     */
    /** Un total leído del documento, en la convención chilena del dinero. */
    private function monto(mixed $valor): ?float
    {
        return blank($valor) ? null : ChileanMoney::parse((string) $valor);
    }

    private function esProblemaDeConexion(Throwable $e): bool
    {
        if ($e instanceof ConnectionException) {
            return true;
        }

        // Un 502/503/504 del otro lado significa lo mismo: el servidor del
        // modelo no está atendiendo ahora mismo.
        if ($e instanceof RequestException && in_array($e->response->status(), [502, 503, 504], true)) {
            return true;
        }

        $mensaje = Str::lower($e->getMessage());

        foreach ([
            'connection refused', 'could not connect', 'failed to connect',
            'timed out', 'timeout', 'connection reset', 'empty reply',
            'no route to host', 'network is unreachable', 'curl error',
        ] as $senal) {
            if (Str::contains($mensaje, $senal)) {
                return true;
            }
        }

        return false;
    }

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
        - "unit_price" es el precio POR UNIDAD, sin puntos de miles ni signo peso: «$ 12.500»
          es "12500". Si la línea sólo muestra el total, divídelo NO: deja "unit_price" vacío.
          Si la línea no trae precio, déjalo vacío. Nunca lo deduzcas de otra línea.
        - "net_total", "tax_total" y "grand_total" son el bloque de totales del final:
          el neto (o subtotal), el IVA y el total a pagar, sin puntos ni signo peso.
          Si el documento no los declara, déjalos vacíos. NO los calcules tú.

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
            'required' => ['supplier', 'reason', 'net_total', 'tax_total', 'grand_total', 'items'],
            'properties' => [
                'supplier' => ['type' => 'string', 'description' => 'Proveedor que emite la cotización, o cadena vacía.'],
                'reason' => ['type' => 'string', 'description' => 'Para qué es la compra, en una frase corta, o cadena vacía.'],
                'net_total' => ['type' => 'string', 'description' => 'Neto o subtotal del documento, sólo cifras, o vacío.'],
                'tax_total' => ['type' => 'string', 'description' => 'IVA declarado, sólo cifras, o vacío.'],
                'grand_total' => ['type' => 'string', 'description' => 'Total a pagar, sólo cifras, o vacío.'],
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['product_service', 'specification', 'quantity', 'unit', 'unit_price'],
                        'properties' => [
                            'product_service' => ['type' => 'string'],
                            'specification' => ['type' => 'string'],
                            'quantity' => ['type' => 'string'],
                            'unit' => ['type' => 'string'],
                            'unit_price' => ['type' => 'string'],
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
                // El servidor descarga el modelo tras este silencio, en vez de
                // dejarlo ocupando memoria todo el día.
                ...$this->descargaAutomatica(),
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

    /**
     * Acepta el nombre del proveedor sólo si el documento lo respalda.
     *
     * Basta con que una palabra distintiva aparezca en el texto: un documento
     * que dice «serviciotecnico@bobinadosloncomilla.cl» respalda «BOBINADOS
     * LONCOMILLA S.A.», aunque no lo escriba con ese formato exacto. Se
     * ignoran las palabras vacías y las formas societarias, que aparecen en
     * cualquier documento y no distinguen a nadie.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function verificarProveedor(?string $proveedor, string $referencia): array
    {
        if ($proveedor === null || trim($referencia) === '') {
            return [$proveedor, null];
        }

        // Sin letras y sin espacios: así «BOBINADOS LONCOMILLA» calza con el
        // dominio de un correo, donde va todo junto.
        $plano = preg_replace('/[^a-z0-9]/', '', $this->normalizar($referencia)) ?? '';

        // El error más repetido del modelo es devolver a quien recibe la
        // cotización en vez de a quien la emite. El nombre pasaba el control
        // porque sí está escrito en el documento —como cliente—, así que hay
        // que mirar *dónde* está escrito, no sólo si está.
        if ($this->esElCliente($proveedor, $referencia)) {
            return [
                null,
                sprintf(
                    'No se registró «%s» como proveedor: en el documento aparece como el cliente, no como quien emite. Se conserva el RUT.',
                    Str::limit($proveedor, 44),
                ),
            ];
        }

        $genericas = ['sa', 'spa', 'ltda', 'limitada', 'eirl', 'sociedad', 'comercial',
            'servicios', 'industrial', 'de', 'del', 'la', 'las', 'los', 'y', 'e'];

        foreach (preg_split('/[\s.,]+/u', $this->normalizar($proveedor)) ?: [] as $palabra) {
            $palabra = preg_replace('/[^a-z0-9]/', '', $palabra) ?? '';

            if (mb_strlen($palabra) < 4 || in_array($palabra, $genericas, true)) {
                continue;
            }

            if (str_contains($plano, $palabra)) {
                return [$proveedor, null];
            }
        }

        return [
            null,
            sprintf(
                'No se registró «%s» como proveedor: ese nombre no aparece en el documento. Se conserva el RUT.',
                Str::limit($proveedor, 44),
            ),
        ];
    }

    /**
     * Saca del propio documento el nombre de quien lo emite.
     *
     * Es lo que hace una persona al mirar el papel: el emisor encabeza la
     * página y su nombre va pegado a su RUT. Dos reglas bastan para los
     * documentos que llegan aquí:
     *
     *  - Con RUT del proveedor: su nombre está en esa misma línea, o en la
     *    anterior si el RUT va solo. Así sale «IVAN RUDY CANCINO DIAZ», que
     *    el documento escribe justo encima de «RUT 10.855.569-6».
     *  - Sin RUT: la primera línea con contenido. Así sale «Wurth Chile
     *    Ltda.», que es lo único que identifica a ese proveedor.
     *
     * Devuelve null cuando el encabezado es una imagen y el texto no trae
     * ningún nombre: preferible vacío a inventado.
     */
    private function nombreDelEmisorSegunElTexto(string $texto, ?string $rutProveedor): ?string
    {
        $lineas = preg_split('/\R/u', $texto) ?: [];

        $indice = $rutProveedor === null
            ? null
            : $this->lineaDondeEstaElRut($lineas, $rutProveedor);

        // Con RUT: esa línea sin el RUT, y si queda vacía, la de más arriba.
        if ($indice !== null) {
            for ($i = $indice; $i >= 0 && $i >= $indice - 3; $i--) {
                $candidato = $this->comoNombreDeEmpresa(
                    $i === $indice ? $this->sinElRut($lineas[$i]) : $lineas[$i],
                );

                if ($candidato !== null && ! $this->esElCliente($candidato, $texto)) {
                    return $candidato;
                }
            }

            return null;
        }

        // Sin RUT: lo primero que el documento escribe.
        foreach ($lineas as $linea) {
            $candidato = $this->comoNombreDeEmpresa($linea);

            if ($candidato !== null && ! $this->esElCliente($candidato, $texto)) {
                return $candidato;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $lineas
     */
    private function lineaDondeEstaElRut(array $lineas, string $rut): ?int
    {
        foreach ($lineas as $i => $linea) {
            foreach (Rut::findAll($linea) as $encontrado) {
                if ($encontrado['rut'] === $rut) {
                    return $i;
                }
            }
        }

        return null;
    }

    /** La línea sin el RUT ni la etiqueta que lo anuncia. */
    private function sinElRut(string $linea): string
    {
        $limpia = preg_replace('/\b[rR]\.?\s?[uU]\.?\s?[tT]\.?\s*:?/u', ' ', $linea) ?? $linea;

        return preg_replace('/\d[\d.\-]*[\dkK]/u', ' ', $limpia) ?? $limpia;
    }

    /**
     * ¿Esta línea sirve como nombre de empresa?
     *
     * Se descartan los rótulos que encabezan cualquier documento y los
     * códigos internos que acompañan al nombre («CL-9720232 Santiago»), que
     * no forman parte de él.
     */
    private function comoNombreDeEmpresa(string $linea): ?string
    {
        $texto = trim(preg_replace('/\s+/u', ' ', $linea) ?? '');

        // Se corta en el primer código: letras con números o un número largo.
        $palabras = [];

        foreach (explode(' ', $texto) as $palabra) {
            if (preg_match('/^[A-Za-z]{1,3}-?\d{4,}$/u', $palabra) || preg_match('/^\d{4,}$/u', $palabra)) {
                break;
            }

            $palabras[] = $palabra;
        }

        $texto = trim(implode(' ', $palabras), " \t.,:;-–—|");

        if (mb_strlen($texto) < 4 || mb_strlen($texto) > 90) {
            return null;
        }

        // Tiene que haber al menos una palabra que no sea un rótulo.
        $rotulos = ['cotizacion', 'cotización', 'factura', 'electronica', 'electrónica',
            'presupuesto', 'orden', 'compra', 'guia', 'guía', 'despacho', 'nota',
            'venta', 'documento', 'fecha', 'senor', 'señor', 'pagina', 'página'];

        $tieneAlgoPropio = false;

        foreach (explode(' ', $this->normalizar($texto)) as $palabra) {
            $palabra = preg_replace('/[^a-z0-9áéíóúñ]/u', '', $palabra) ?? '';

            if (mb_strlen($palabra) >= 4 && ! in_array($palabra, $rotulos, true)) {
                $tieneAlgoPropio = true;

                break;
            }
        }

        return $tieneAlgoPropio ? $texto : null;
    }

    /**
     * ¿El nombre propuesto es en realidad el destinatario de la cotización?
     *
     * Se mira la franja de texto que sigue a la etiqueta que nombra al cliente
     * («Cliente:», «SEÑOR(ES):», «Empresa»). Si todas las palabras
     * distintivas del nombre caen ahí dentro y ninguna aparece fuera, lo que
     * el modelo devolvió es quien recibe, no quien vende.
     *
     * La condición de «ninguna fuera» es la que evita el falso positivo: un
     * proveedor cuyo nombre también encabeza la página aparece en las dos
     * partes, y entonces no se descarta.
     */
    private function esElCliente(string $proveedor, string $referencia): bool
    {
        // Se busca sobre el texto ya normalizado y se mide sobre ése mismo:
        // posicionDeLaEtiquetaDeCliente() informa posiciones del texto crudo,
        // que la normalización desplaza al colapsar los espacios.
        $plano = $this->normalizar($referencia);
        $posicion = null;

        foreach (self::ETIQUETAS_DE_CLIENTE as $etiqueta) {
            $encontrada = mb_strpos($plano, $etiqueta);

            if ($encontrada !== false) {
                $posicion = $encontrada;

                break;
            }
        }

        if ($posicion === null) {
            return false;
        }

        $franja = mb_substr($plano, $posicion, self::LARGO_FRANJA_CLIENTE);
        $fuera = mb_substr($plano, 0, $posicion).' '.mb_substr($plano, $posicion + self::LARGO_FRANJA_CLIENTE);

        $distintivas = $this->palabrasDistintivas($proveedor);

        if ($distintivas === []) {
            return false;
        }

        foreach ($distintivas as $palabra) {
            if (! str_contains($franja, $palabra) || str_contains($fuera, $palabra)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Las palabras de un nombre que sirven para reconocerlo.
     *
     * @return list<string>
     */
    private function palabrasDistintivas(string $nombre): array
    {
        $genericas = ['sa', 'spa', 'ltda', 'limitada', 'eirl', 'sociedad', 'comercial',
            'servicios', 'industrial', 'empresa', 'de', 'del', 'la', 'las', 'los', 'y', 'e'];

        $palabras = [];

        foreach (preg_split('/[\s.,]+/u', $this->normalizar($nombre)) ?: [] as $palabra) {
            $palabra = preg_replace('/[^a-z0-9]/', '', $palabra) ?? '';

            if (mb_strlen($palabra) >= 4 && ! in_array($palabra, $genericas, true)) {
                $palabras[] = $palabra;
            }
        }

        return array_values(array_unique($palabras));
    }

    private function posicionDeLaEtiquetaDeCliente(string $texto): ?int
    {
        $plano = mb_strtolower($texto);

        foreach (self::ETIQUETAS_DE_CLIENTE as $etiqueta) {
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

    /**
     * Pide al servidor que suelte el modelo tras un rato sin uso.
     *
     * LM Studio y los servidores compatibles aceptan `ttl` en segundos: cargan
     * el modelo cuando llega la petición y lo descargan pasado ese tiempo. Un
     * servidor que no reconozca el campo simplemente lo ignora.
     *
     * @return array<string, int>
     */
    private function descargaAutomatica(): array
    {
        $minutos = (int) config('purchase_requests.reader.keep_loaded_minutes', 10);

        return $minutos > 0 ? ['ttl' => $minutos * 60] : [];
    }
}
