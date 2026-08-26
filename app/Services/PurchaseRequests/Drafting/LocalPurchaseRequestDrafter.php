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

        return DraftSuggestion::of(
            reason: $this->limpiar($crudo['reason'] ?? null),
            requestedForName: $paraQuien,
            items: $items,
            warnings: $avisos,
            supplier: $proveedor,
        );
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
        - "reason" es el motivo, sólo si lo dice ("para el riego", "porque no queda stock").
          Si no lo dice, déjalo vacío.
        - "requested_for" es para quién es, sólo si lo menciona ("lo pide Marco"). Si no, vacío.
        - "supplier" es el proveedor, SÓLO si la persona lo nombra ("en Sodimac", "a Motorman").
          No es el fabricante ni la marca del producto: «guantes 3M» no significa comprarle a 3M.
          Si no nombra un proveedor, déjalo vacío.
        EJEMPLOS:
        - «pañuelos desechables 2, confort 2» → dos partidas, cantidad "2" cada una, unit vacío.
        - «5 litros de cloro» → UNA partida: product_service "cloro", quantity "5", unit "Litros".
          La cantidad va SIEMPRE en quantity aunque esté escrita antes de la unidad.
        - «295 metros de PVC 200mm» → product_service "PVC 200mm", quantity "295", unit "Metros".
        - «1,5 cubos de bolones» → product_service "bolones", quantity "1,5", unit "Cubos".
        - «cloro en Sodimac» → supplier "Sodimac", sin la preposición.

        Responde SOLO el JSON pedido.
        TXT;

        $esquema = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['reason', 'requested_for', 'supplier', 'items'],
            'properties' => [
                'reason' => ['type' => 'string'],
                'requested_for' => ['type' => 'string'],
                'supplier' => ['type' => 'string'],
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

        $modelo = config('purchase_requests.reader.text_model')
            ?: config('purchase_requests.reader.vision_model');

        $respuesta = Http::withToken((string) config('purchase_requests.reader.api_key'))
            ->timeout($this->timeout())
            ->acceptJson()
            ->post(config('purchase_requests.reader.base_url').'/chat/completions', [
                'model' => $modelo,
                'temperature' => 0,
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
}
