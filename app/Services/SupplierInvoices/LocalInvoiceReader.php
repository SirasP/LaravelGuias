<?php

namespace App\Services\SupplierInvoices;

use App\Services\PurchaseRequests\Reading\PurchaseRequestSourceKind;
use App\Support\ChileanMoney;
use App\Support\Rut;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Lee una factura de proveedor —PDF o foto— con la misma IA local que lee las
 * cotizaciones (LM Studio, configurado en `purchase_requests.reader`).
 *
 * Igual que allá, al modelo no se le cree lo que se puede comprobar: el RUT
 * se saca del texto con su dígito verificador, y el folio y los totales se
 * buscan en el documento. Lo que no aparece, se avisa.
 *
 * Devuelve un arreglo:
 *  - `ok`, `unreachable` (el lector no estaba), `error`
 *  - `data`: tipo, rut, razon_social, folio, fecha, neto, exento, iva, total, lineas
 *  - `warnings`, `raw` (respuesta del modelo), `model`, `source_kind`
 */
class LocalInvoiceReader
{
    public function isEnabled(): bool
    {
        return (bool) config('purchase_requests.reader.enabled');
    }

    public function describe(): string
    {
        return 'modelo local en '.config('purchase_requests.reader.base_url');
    }

    /** @return array<string, mixed> */
    public function read(string $ruta, string $mimeType): array
    {
        if (! $this->isEnabled()) {
            return $this->fallo('La IA local está desactivada (PURCHASE_REQUESTS_READER).');
        }

        try {
            [$tipoFuente, $texto, $imagen] = $this->prepararEntrada($ruta, $mimeType);
        } catch (Throwable $e) {
            return $this->fallo('No se pudo preparar el documento: '.$e->getMessage());
        }

        if ($texto === null && $imagen === null) {
            return $this->fallo('El documento no tiene texto legible ni pudo convertirse a imagen.');
        }

        $modelo = $imagen !== null
            ? (string) config('purchase_requests.reader.vision_model')
            : (string) (config('purchase_requests.reader.text_model') ?: config('purchase_requests.reader.vision_model'));

        try {
            $crudo = $this->preguntarAlModelo($modelo, $texto, $imagen);
        } catch (Throwable $e) {
            return [...$this->fallo('El modelo no respondió: '.$e->getMessage()),
                'unreachable' => $this->esProblemaDeConexion($e), 'model' => $modelo, 'source_kind' => $tipoFuente];
        }

        [$data, $avisos] = $this->verificar($crudo, $texto);

        return [
            'ok' => true, 'unreachable' => false, 'error' => null,
            'data' => $data, 'warnings' => $avisos, 'raw' => $crudo,
            'model' => $modelo, 'source_kind' => $tipoFuente,
        ];
    }

    /**
     * Lo que dijo el modelo, contrastado con el texto del documento.
     *
     * @param  array<string, mixed>  $crudo
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    public function verificar(array $crudo, ?string $texto): array
    {
        $avisos = [];
        $texto ??= '';

        // El RUT del emisor: el primero válido que no sea el nuestro. Sin
        // texto (una foto) no hay contra qué compararlo, y vale el del modelo.
        $nuestro = Rut::normalize((string) config('purchase_requests.company.tax_id'));
        $delTexto = collect(Rut::findAll($texto))->pluck('rut')->first(fn (string $rut): bool => $rut !== $nuestro);
        $delModelo = Rut::normalize((string) ($crudo['rut'] ?? ''));
        $rut = $delTexto ?? ($delModelo !== $nuestro ? $delModelo : null);

        if ($rut === null) {
            $avisos[] = filled($crudo['rut'] ?? null)
                ? sprintf('El RUT «%s» que leyó la IA no es válido. Escríbelo a mano.', $crudo['rut'])
                : 'No se encontró el RUT del proveedor.';
        }

        $folio = preg_replace('/\D/', '', (string) ($crudo['folio'] ?? '')) ?: null;
        $folioDelTexto = preg_match('/(?:N[°ºo]\.?|Nro\.?|N\s?°|FOLIO)\s*:?\s*0*(\d{1,10})\b/iu', $texto, $m) ? $m[1] : null;

        if ($texto !== '' && $folio !== null && ! preg_match('/\b0*'.preg_quote(ltrim($folio, '0'), '/').'\b/', $texto)) {
            $avisos[] = sprintf('El folio %s no aparece en el documento. Revísalo.', $folio);
        }

        $folio = $folio !== null ? (ltrim($folio, '0') ?: '0') : $folioDelTexto;

        if ($folio === null) {
            $avisos[] = 'No se encontró el folio de la factura.';
        }

        $fecha = $this->fecha((string) ($crudo['fecha'] ?? ''));

        if ($fecha === null) {
            $avisos[] = 'No se pudo leer la fecha de emisión.';
        }

        $montos = [];

        foreach (['neto', 'exento', 'iva', 'total'] as $clave) {
            $montos[$clave] = blank($crudo[$clave] ?? null) ? null : ChileanMoney::parse((string) $crudo[$clave]);

            if ($texto !== '' && ($montos[$clave] ?? 0) > 0 && ! $this->apareceElMonto($montos[$clave], $texto)) {
                $avisos[] = sprintf('El %s de $%s no aparece en el documento. Revísalo.', $clave, number_format($montos[$clave], 0, ',', '.'));
            }
        }

        $exenta = Str::contains(Str::lower((string) ($crudo['tipo'] ?? '')), 'exent')
            || ($texto !== '' && preg_match('/FACTURA\s+(?:NO\s+AFECTA\s+O\s+)?EXENTA/iu', $texto));

        $lineas = [];

        foreach ((array) ($crudo['lineas'] ?? []) as $linea) {
            $detalle = trim((string) ($linea['detalle'] ?? ''));
            $codigo = trim((string) ($linea['codigo'] ?? ''));

            if ($detalle === '') {
                continue;
            }

            $lineas[] = [
                // Como las escribe Odoo en las facturas de hoy: «TUERCA 1/2-13 HC G-2 · 500034».
                'detalle' => $codigo !== '' && ! Str::contains($detalle, $codigo) ? $detalle.' · '.$codigo : $detalle,
                'cantidad' => $this->cantidad((string) ($linea['cantidad'] ?? '')),
                'precio' => $this->precio((string) ($linea['precio'] ?? '')),
            ];
        }

        if ($lineas === []) {
            $avisos[] = 'No se reconoció ninguna línea de detalle.';
        }

        return [[
            'tipo' => $exenta ? '34' : '33',
            'rut' => $rut,
            'razon_social' => trim((string) ($crudo['proveedor'] ?? '')) ?: null,
            'folio' => $folio,
            'fecha' => $fecha,
            'vencimiento' => $this->fecha((string) ($crudo['vencimiento'] ?? '')),
            ...$montos,
            'lineas' => $lineas,
        ], $avisos];
    }

    /** @return array<string, mixed> */
    private function fallo(string $error): array
    {
        return ['ok' => false, 'unreachable' => false, 'error' => $error, 'data' => null, 'warnings' => [], 'raw' => null, 'model' => null, 'source_kind' => null];
    }

    /** «2026-11-03», o lo que haya escrito el modelo si se parece a una fecha chilena. */
    private function fecha(string $valor): ?string
    {
        $valor = trim($valor);

        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y', 'd.m.Y'] as $formato) {
            try {
                $fecha = Carbon::createFromFormat('!'.$formato, $valor);
            } catch (Throwable) {
                continue;
            }

            if ($fecha !== false && $fecha->format($formato) === $valor && $fecha->year >= 2000) {
                return $fecha->format('Y-m-d');
            }
        }

        return null;
    }

    /** «81,00» → 81; «1.5» → 1.5. Una cantidad no lleva separador de miles casi nunca. */
    private function cantidad(string $valor): ?float
    {
        $limpio = preg_replace('/[^\d.,]/', '', $valor) ?? '';

        if ($limpio === '') {
            return null;
        }

        if (str_contains($limpio, ',')) {
            $limpio = str_replace(',', '.', str_replace('.', '', $limpio));
        }

        return is_numeric($limpio) ? (float) $limpio : null;
    }

    /**
     * El precio unitario. Con coma sola, la coma es decimal: la tuerca de
     * 84,035 que ya está en Odoo cuesta ochenta y cuatro pesos, no ochenta y
     * cuatro mil. Con punto, la regla del dinero.
     */
    private function precio(string $valor): ?float
    {
        if (blank($valor)) {
            return null;
        }

        return str_contains($valor, ',') && ! str_contains($valor, '.')
            ? $this->cantidad($valor)
            : ChileanMoney::parse($valor);
    }

    /** ¿Está ese monto escrito en el documento, con o sin puntos de miles? */
    private function apareceElMonto(float $monto, string $texto): bool
    {
        $entero = (int) round($monto);

        // Con punto de miles, con coma («494,919» en la factura 606 de CDTEC) o sin nada.
        foreach ([number_format($entero, 0, ',', '.'), number_format($entero, 0, '.', ','), (string) $entero] as $forma) {
            if (preg_match('/(?<![\d.,])'.preg_quote($forma, '/').'(?![\d])/', $texto)) {
                return true;
            }
        }

        return false;
    }

    /** @return array{0: string, 1: ?string, 2: ?string} [tipo, texto, imagen en base64] */
    private function prepararEntrada(string $ruta, string $mimeType): array
    {
        if (str_starts_with($mimeType, 'image/')) {
            return [PurchaseRequestSourceKind::IMAGE, null, base64_encode((string) file_get_contents($ruta))];
        }

        $texto = $this->correr([(string) config('purchase_requests.reader.pdftotext'), '-layout', '-f', '1',
            '-l', (string) config('purchase_requests.reader.max_pages'), $ruta, '-']);

        if ($texto !== null && mb_strlen(trim($texto)) >= 80) {
            return [PurchaseRequestSourceKind::PDF_TEXT, $texto, null];
        }

        // Un escaneo: la primera página como imagen.
        $prefijo = sys_get_temp_dir().'/fac-img-'.Str::random(10);
        $this->correr([(string) config('purchase_requests.reader.pdftoppm'), '-png', '-r', '150', '-f', '1', '-l', '1', $ruta, $prefijo]);
        $png = glob($prefijo.'*.png') ?: [];
        $imagen = $png === [] ? null : base64_encode((string) file_get_contents($png[0]));
        array_map('unlink', $png);

        return [PurchaseRequestSourceKind::PDF_SCAN, null, $imagen];
    }

    private function correr(array $comando): ?string
    {
        $proceso = new Process($comando);
        $proceso->setTimeout(120);
        $proceso->run();

        return $proceso->isSuccessful() ? $proceso->getOutput() : null;
    }

    /** @return array<string, mixed> */
    private function preguntarAlModelo(string $modelo, ?string $texto, ?string $imagen): array
    {
        $sistema = <<<'TXT'
        Lees facturas de proveedores chilenos recibidas por una empresa agrícola y extraes sus datos.

        REGLAS:
        - Extrae SOLO lo que aparece en el documento. Lo que no esté, va como cadena vacía.
        - "proveedor" y "rut" son los de quien EMITE la factura (el encabezado), nunca los del
          cliente que la recibe (el bloque «Señor(es)», «Cliente» o «Razón social»).
        - "folio" es el número de la factura: «FACTURA ELECTRÓNICA N° 3748» → "3748".
        - "fecha" es la fecha de emisión, como AAAA-MM-DD: «3 de Noviembre de 2026» → "2026-11-03".
        - "vencimiento" es la fecha de vencimiento, igual que la fecha. Si no aparece, vacía.
        - "tipo" es el nombre del documento tal como aparece: «FACTURA ELECTRÓNICA», «FACTURA EXENTA»…
        - Montos sin puntos de miles ni signo peso: «$ 3.338.500» → "3338500".
          "neto" es el monto neto o afecto, "exento" el monto exento, "iva" el IVA 19%, "total" el total.
          Si el documento no declara uno, déjalo vacío. NO los calcules.
        - Cada línea de detalle: "detalle" es la descripción, "codigo" el código del producto si
          tiene columna propia, "cantidad" tal cual (coma decimal chilena), "precio" el precio
          UNITARIO neto. No sumes ni juntes líneas repetidas.
        - Las pulgadas llevan comillas: escápalas como \" dentro del JSON.

        Responde SOLO este objeto JSON, sin texto alrededor y sin ```:
        {"tipo":"","proveedor":"","rut":"","folio":"","fecha":"","vencimiento":"","neto":"","exento":"","iva":"","total":"",
         "lineas":[{"detalle":"","codigo":"","cantidad":"","precio":""}]}
        TXT;

        $pedido = 'Extrae los datos de esta factura.';
        $usuario = $imagen !== null
            ? [['type' => 'text', 'text' => $pedido], ['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,'.$imagen]]]
            : $pedido."\n\n---\n".mb_substr((string) $texto, 0, 12000);

        $minutos = (int) config('purchase_requests.reader.keep_loaded_minutes', 10);

        $respuesta = Http::withToken((string) config('purchase_requests.reader.api_key'))
            ->timeout((int) config('purchase_requests.reader.timeout'))
            ->acceptJson()
            ->post(config('purchase_requests.reader.base_url').'/chat/completions', [
                'model' => $modelo,
                'temperature' => 0,
                ...($minutos > 0 ? ['ttl' => $minutos * 60] : []),
                'messages' => [['role' => 'system', 'content' => $sistema], ['role' => 'user', 'content' => $usuario]],
            ])
            ->throw();

        $contenido = (string) data_get($respuesta->json(), 'choices.0.message.content');
        $inicio = mb_strpos($contenido, '{');
        $fin = mb_strrpos($contenido, '}');
        $json = $inicio !== false && $fin !== false && $fin > $inicio ? mb_substr($contenido, $inicio, $fin - $inicio + 1) : $contenido;

        $decodificado = json_decode($json, true) ?? json_decode($this->conLasComillasEscapadas($json), true);

        if (! is_array($decodificado)) {
            throw new \RuntimeException(sprintf('El modelo no devolvió un JSON válido (%s). Terminó en: «…%s».',
                data_get($respuesta->json(), 'choices.0.finish_reason') ?: 'sin motivo', mb_substr(trim($contenido), -120)));
        }

        return $decodificado;
    }

    /**
     * Escapa las comillas de pulgada que el modelo dejó sueltas: «CODO 1 1/2"».
     * Una comilla sólo cierra la cadena si la sigue puntuación de JSON. Es la
     * misma reparación que hace el lector de cotizaciones.
     */
    private function conLasComillasEscapadas(string $json): string
    {
        $salida = '';
        $dentro = false;
        $largo = strlen($json);

        for ($i = 0; $i < $largo; $i++) {
            $c = $json[$i];

            if ($dentro && $c === '\\' && $i + 1 < $largo) {
                $salida .= $c.$json[++$i];

                continue;
            }

            if ($c !== '"') {
                $salida .= $c;

                continue;
            }

            if (! $dentro) {
                $dentro = true;
                $salida .= $c;

                continue;
            }

            $siguiente = ltrim(substr($json, $i + 1))[0] ?? null;

            if ($siguiente === null || in_array($siguiente, [',', '}', ']', ':'], true)) {
                $dentro = false;
                $salida .= $c;
            } else {
                $salida .= '\\"';
            }
        }

        return $salida;
    }

    /** La Mac del modelo se durmió, o soltó el modelo: no es culpa del documento. */
    private function esProblemaDeConexion(Throwable $e): bool
    {
        if ($e instanceof ConnectionException) {
            return true;
        }

        if ($e instanceof RequestException && in_array($e->response->status(), [502, 503, 504], true)) {
            return true;
        }

        return Str::contains(Str::lower($e->getMessage()), [
            'connection refused', 'could not connect', 'failed to connect', 'timed out', 'timeout',
            'connection reset', 'empty reply', 'no route to host', 'curl error',
            'model is unloaded', 'model not loaded', 'no model loaded', 'loading model',
        ]);
    }
}
