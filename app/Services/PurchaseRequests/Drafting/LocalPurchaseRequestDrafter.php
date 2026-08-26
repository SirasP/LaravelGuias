<?php

namespace App\Services\PurchaseRequests\Drafting;

use App\Services\PurchaseRequests\Reading\LineVerifier;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Convierte una frase escrita a mano en partidas.
 *
 * «pañuelos desechables 2, confort 2» debe quedar como dos líneas listas para
 * revisar. Corre dentro de la petición porque la persona está esperando
 * frente a la pantalla, así que se le da un límite de tiempo corto: si el
 * modelo tarda, se avisa y se sigue escribiendo a mano.
 *
 * Las mismas reglas que rigen la lectura de documentos: no inventa cantidades
 * ni unidades, y lo que propone se contrasta contra el propio texto de quien
 * escribió.
 */
class LocalPurchaseRequestDrafter implements PurchaseRequestDrafter
{
    /**
     * Quien escribe está mirando la pantalla, así que el límite es corto por
     * defecto. Se puede subir por configuración para un modelo lento —un
     * servidor sin GPU tarda minutos—, sabiendo que ahí la persona sí queda
     * esperando.
     */
    private function timeout(): int
    {
        return (int) config('purchase_requests.reader.draft_timeout', 45);
    }

    public function isEnabled(): bool
    {
        return (bool) config('purchase_requests.reader.enabled');
    }

    public function draftFromText(string $text, array $knownUnits = []): DraftSuggestion
    {
        if (! $this->isEnabled()) {
            return DraftSuggestion::unavailable('El asistente no está habilitado en este entorno.');
        }

        $texto = trim($text);

        if (mb_strlen($texto) < 3) {
            return DraftSuggestion::unavailable('Escribe qué necesitas antes de pedir ayuda.');
        }

        try {
            $crudo = $this->preguntar($texto, $knownUnits);
        } catch (Throwable $e) {
            return DraftSuggestion::unavailable('El asistente no respondió: '.$e->getMessage());
        }

        // Misma verificación que para los documentos: aquí el texto contra el
        // cual contrastar es lo que la persona escribió, que es exactamente la
        // referencia correcta.
        [$items, $avisos] = (new LineVerifier)->verificarContraElDocumento(
            $crudo['items'] ?? [],
            $texto,
            $knownUnits,
            false,
            // Es una frase escrita a mano: «cloro 5 litros» declara la unidad
            // aunque no vaya pegada al nombre del producto.
            referenciaEsUnaFrase: true,
        );

        if ($items === []) {
            return DraftSuggestion::unavailable('No se reconoció ningún producto en lo que escribiste.');
        }

        // Aquí el texto de referencia es lo que la persona escribió, así que
        // TODO lo propuesto tiene que estar respaldado por él. Un modelo
        // pequeño puede devolver restos de otra petición: pidiendo «confort 2»
        // llegó a responder «cloro», «Marco» y «Sodimac», ninguno escrito.
        [$items, $avisosProducto] = $this->descartarProductosNoEscritos($items, $texto);
        $avisos = array_values(array_unique([...$avisos, ...$avisosProducto]));

        if ($items === []) {
            return DraftSuggestion::unavailable(
                'Lo que devolvió el asistente no corresponde a lo que escribiste. Inténtalo de nuevo o escribe las partidas a mano.',
            );
        }

        // El destinatario también: si no lo nombraste, no se inventa.
        $paraQuien = $this->limpiar($crudo['requested_for'] ?? null);

        if ($paraQuien !== null && ! $this->apareceEnElTexto($paraQuien, $texto)) {
            $avisos[] = sprintf('No se registró «%s» porque no aparece en lo que escribiste.', $paraQuien);
            $paraQuien = null;
        }

        // El proveedor sólo se acepta si la persona realmente lo escribió: el
        // modelo tiende a confundir la marca del producto con quien lo vende.
        $proveedor = $this->limpiar($crudo['supplier'] ?? null);

        // «en Sodimac» viene con la preposición pegada; se recorta aquí.
        if ($proveedor !== null) {
            $proveedor = $this->limpiar(preg_replace('/^(en|a|al|de|del|con|para)\s+/iu', '', $proveedor));
        }

        if ($proveedor !== null && ! Str::contains(Str::lower($texto), Str::lower($proveedor))) {
            $avisos[] = sprintf('No se registró «%s» como proveedor porque no aparece en lo que escribiste.', $proveedor);
            $proveedor = null;
        }

        // Dónde se entrega tampoco se deduce: o está escrito, o queda vacío.
        $lugar = $this->limpiar($crudo['delivery_location'] ?? null);

        if ($lugar !== null && ! $this->apareceEnElTexto($lugar, $texto)) {
            $avisos[] = sprintf('No se registró «%s» como lugar de entrega porque no aparece en lo que escribiste.', $lugar);
            $lugar = null;
        }

        [$prioridad, $motivoUrgencia, $avisoPrioridad] = $this->prioridadVerificada($crudo, $texto);

        if ($avisoPrioridad !== null) {
            $avisos[] = $avisoPrioridad;
        }

        // Lo que explica la urgencia explica también la compra. El modelo suele
        // escribirlo sólo en uno de los dos campos, y dejar el motivo vacío
        // frena el guardado por un dato que la persona sí había escrito.
        $motivo = $this->limpiar($crudo['reason'] ?? null) ?? $motivoUrgencia;

        return DraftSuggestion::of(
            reason: $motivo,
            requestedForName: $paraQuien,
            items: $items,
            warnings: $avisos,
            supplier: $proveedor,
            priority: $prioridad,
            urgentReason: $motivoUrgencia,
            deliveryLocation: $lugar,
        );
    }

    /**
     * Una solicitud sólo nace urgente si quien la escribe dijo que lo era.
     *
     * El modelo tiende a leer urgencia en cualquier pedido de repuestos. Y una
     * urgencia falsa cuesta cara: si todo llega marcado en rojo, el rojo deja
     * de significar algo y las de verdad se pierden entre las demás.
     *
     * @param  array<string, mixed>  $crudo
     * @return array{0: string, 1: string|null, 2: string|null}
     */
    private function prioridadVerificada(array $crudo, string $texto): array
    {
        $pedida = Str::lower(trim((string) ($crudo['priority'] ?? 'normal')));

        if ($pedida !== 'urgente' && $pedida !== 'urgent') {
            return ['normal', null, null];
        }

        $senales = [
            'urgente', 'urgencia', 'urgen', 'apura', 'apurad', 'prisa', 'priorid',
            'para hoy', 'hoy mismo', 'cuanto antes', 'lo antes posible', 'de inmediato',
            'inmediato', 'emergencia', 'ya mismo', 'se paró', 'se paro', 'parada',
            'detenid', 'no puede esperar', 'para mañana', 'para manana',
        ];

        $textoPlano = Str::lower($texto);

        foreach ($senales as $senal) {
            if (Str::contains($textoPlano, $senal)) {
                $motivo = $this->limpiar($crudo['urgent_reason'] ?? null);

                return ['urgent', $motivo, null];
            }
        }

        return [
            'normal',
            null,
            'Se dejó como prioridad normal: no escribiste que fuera urgente. Puedes cambiarla a mano.',
        ];
    }

    /**
     * @param  list<string>  $knownUnits
     * @return array<string, mixed>
     */
    private function preguntar(string $texto, array $knownUnits): array
    {
        $unidades = $knownUnits === [] ? 'Unidades' : implode(', ', $knownUnits);

        $sistema = <<<TXT
        Conviertes en partidas lo que un trabajador de una empresa agrícola chilena escribe
        cuando necesita comprar algo. Escribe rápido y sin formato: «pañuelos desechables 2,
        confort 2» son dos productos con cantidad 2 cada uno.

        REGLAS ESTRICTAS:
        - Extrae SOLO lo que la persona escribió. No agregues productos que no nombró.
        - Si no escribió la cantidad de un producto, deja "quantity" vacío. NUNCA la inventes
          ni la copies de otro producto.
        - Usa exclusivamente estas unidades cuando calcen: {$unidades}. Si ninguna calza,
          deja "unit" vacío. No inventes unidades.
        - Respeta la coma decimal chilena: «1,5 cubos» es quantity "1,5" y unit "Cubos".
        - Si repite un producto, devuélvelo como dos partidas separadas: pueden ser destinos
          distintos. No los sumes.
        - Mantén el nombre tal como lo escribió, sólo corrigiendo mayúsculas evidentes.
        - "unit_price" es el precio POR UNIDAD, sin puntos de miles ni signo peso: «a 12.500
          cada uno» es "12500". Si no dice precio, déjalo vacío. Nunca lo inventes ni lo
          copies de otra partida.
        - "reason" es el motivo, sólo si lo dice ("para el riego", "porque no queda stock").
          Si no lo dice, déjalo vacío.
        - "requested_for" es para quién es, sólo si lo menciona ("lo pide Marco"). Si no, vacío.
        - "supplier" es el proveedor, SÓLO si la persona lo nombra ("en Sodimac", "a Motorman").
          No es el fabricante ni la marca del producto: «guantes 3M» no significa comprarle a 3M.
          Si no nombra un proveedor, déjalo vacío.
        - "priority" es "urgente" SÓLO si dice que corre prisa ("urgente", "para hoy", "lo antes
          posible", "se paró la máquina"). En cualquier otro caso es "normal". Ante la duda,
          "normal": marcar urgente lo que no lo es hace que nadie crea en las urgencias.
        - "urgent_reason" es por qué no puede esperar, sólo si lo explica. Si no, vacío.
          Puede ser el mismo texto que "reason": «se paró la bomba» explica a la vez para
          qué se compra y por qué corre prisa. En ese caso ponlo en LOS DOS campos.
        - "delivery_location" es dónde se entrega o se usa, sólo si lo dice ("para la casa de
          operarios", "llevar al galpón"). Si no lo dice, déjalo vacío.
        EJEMPLOS:
        - «pañuelos desechables 2, confort 2» → dos partidas, cantidad "2" cada una, unit vacío.
        - «5 litros de cloro» → UNA partida: product_service "cloro", quantity "5", unit "Litros".
          La cantidad va SIEMPRE en quantity aunque esté escrita antes de la unidad.
        - «295 metros de PVC 200mm» → product_service "PVC 200mm", quantity "295", unit "Metros".
        - «1,5 cubos de bolones» → product_service "bolones", quantity "1,5", unit "Cubos".
        - «3 correas a 12.500 cada una» → quantity "3", unit_price "12500".
        - «cemento 10 sacos» → unit_price vacío: no dijo cuánto cuesta.
        - «cloro en Sodimac» → supplier "Sodimac", sin la preposición.
        - «2 correas urgente, se paró la bomba» → priority "urgente",
          urgent_reason "se paró la bomba".
        - «10 sacos de cemento para la casa de operarios» → delivery_location
          "casa de operarios", priority "normal".

        Responde SOLO el JSON pedido.
        TXT;

        $esquema = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['reason', 'requested_for', 'supplier', 'priority', 'urgent_reason', 'delivery_location', 'items'],
            'properties' => [
                'reason' => ['type' => 'string'],
                'requested_for' => ['type' => 'string'],
                'supplier' => ['type' => 'string'],
                'priority' => ['type' => 'string', 'enum' => ['normal', 'urgente']],
                'urgent_reason' => ['type' => 'string'],
                'delivery_location' => ['type' => 'string'],
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

        $modelo = config('purchase_requests.reader.text_model')
            ?: config('purchase_requests.reader.vision_model');

        $respuesta = Http::withToken((string) config('purchase_requests.reader.api_key'))
            ->timeout($this->timeout())
            ->acceptJson()
            ->post(config('purchase_requests.reader.base_url').'/chat/completions', [
                'model' => $modelo,
                'temperature' => 0,
                // El servidor descarga el modelo tras este silencio, en vez de
                // dejarlo ocupando memoria todo el día.
                ...$this->descargaAutomatica(),
                'messages' => [
                    ['role' => 'system', 'content' => $sistema],
                    ['role' => 'user', 'content' => Str::limit($texto, 4000, '')],
                ],
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => ['name' => 'solicitud', 'strict' => true, 'schema' => $esquema],
                ],
            ])
            ->throw();

        $contenido = data_get($respuesta->json(), 'choices.0.message.content');
        $decodificado = json_decode((string) $contenido, true);

        if (! is_array($decodificado)) {
            throw new \RuntimeException('El asistente no devolvió un resultado utilizable.');
        }

        return $decodificado;
    }

    /**
     * Descarta las partidas cuyo producto no figura en el texto escrito.
     *
     * Basta con que alguna palabra significativa del nombre aparezca: quien
     * escribe «confort» acepta «CONFORT», pero no «cloro».
     *
     * @param  list<array<string, string|null>>  $items
     * @return array{0: list<array<string, string|null>>, 1: list<string>}
     */
    private function descartarProductosNoEscritos(array $items, string $texto): array
    {
        $conservados = [];
        $avisos = [];

        foreach ($items as $item) {
            $producto = (string) ($item['product_service'] ?? '');

            if ($producto === '' || $this->apareceEnElTexto($producto, $texto)) {
                $conservados[] = $item;

                continue;
            }

            $avisos[] = sprintf('Se descartó «%s»: no aparece en lo que escribiste.', Str::limit($producto, 40));
        }

        return [$conservados, $avisos];
    }

    /**
     * ¿Alguna palabra con peso del valor aparece en el texto?
     *
     * Se ignoran las palabras de menos de cuatro letras para no dar por bueno
     * un «de» o un «la» sueltos.
     */
    private function apareceEnElTexto(string $valor, string $texto): bool
    {
        $textoPlano = Str::lower($texto);

        if (Str::contains($textoPlano, Str::lower($valor))) {
            return true;
        }

        foreach (preg_split('/\s+/u', Str::lower($valor)) ?: [] as $palabra) {
            $palabra = trim($palabra, " \t.,;:()«»\"'");

            if (mb_strlen($palabra) >= 4 && Str::contains($textoPlano, $palabra)) {
                return true;
            }
        }

        return false;
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
