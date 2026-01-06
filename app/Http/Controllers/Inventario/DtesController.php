<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use Google\Client;
use Google\Service\Gmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use SimpleXMLElement;
class DtesController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $tipo = trim((string) $request->query('tipo', ''));        // ej: 33
        $estado = trim((string) $request->query('estado', ''));      // pendiente|parcial|completo
        $desde = trim((string) $request->query('desde', ''));       // YYYY-MM-DD
        $hasta = trim((string) $request->query('hasta', ''));       // YYYY-MM-DD
        $perPage = (int) $request->query('per_page', 20);
        if (!in_array($perPage, [20, 50, 100], true))
            $perPage = 20;

        // Subquery: totales por DTE desde dte_detalles
        $detAgg = DB::table('dte_detalles as dd')
            ->selectRaw('
        dd.dte_id,
        COALESCE(SUM(dd.qty), 0)            as total_objetivo,
        COALESCE(SUM(dd.qty_ingresada), 0)  as total_ingresado
    ')
            ->groupBy('dd.dte_id');

        $dtes = DB::table('dtes as d')
            ->leftJoinSub($detAgg, 'agg', function ($join) {
                $join->on('agg.dte_id', '=', 'd.id');
            })
            ->selectRaw('
            d.*,
            COALESCE(agg.total_objetivo,0) as total_objetivo,
            COALESCE(agg.total_ingresado,0) as total_ingresado,
            CASE
                WHEN COALESCE(agg.total_objetivo,0) <= 0 THEN "pendiente"
                WHEN COALESCE(agg.total_ingresado,0) <= 0 THEN "pendiente"
                WHEN COALESCE(agg.total_ingresado,0) >= COALESCE(agg.total_objetivo,0) THEN "completo"
                ELSE "parcial"
            END as estado_inventario,
            CASE
                WHEN COALESCE(agg.total_objetivo,0) <= 0 THEN 0
                ELSE ROUND((COALESCE(agg.total_ingresado,0) / NULLIF(COALESCE(agg.total_objetivo,0),0)) * 100, 1)
            END as pct_ingresado
        ')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('d.rut_emisor', 'like', "%{$q}%")
                        ->orWhere('d.rut_receptor', 'like', "%{$q}%")
                        ->orWhere('d.rz_emisor', 'like', "%{$q}%")
                        ->orWhere('d.rz_receptor', 'like', "%{$q}%")
                        ->orWhere('d.folio', 'like', "%{$q}%");
                });
            })
            ->when($tipo !== '', function ($query) use ($tipo) {
                $query->where('d.tipo_dte', (int) $tipo);
            })
            ->when($desde !== '', function ($query) use ($desde) {
                $query->whereDate('d.fch_emis', '>=', $desde);
            })
            ->when($hasta !== '', function ($query) use ($hasta) {
                $query->whereDate('d.fch_emis', '<=', $hasta);
            })
            ->when($estado !== '', function ($query) use ($estado) {
                // Filtra por el CASE (lo repetimos)
                $query->whereRaw('
                CASE
                    WHEN COALESCE(agg.total_objetivo,0) <= 0 THEN "pendiente"
                    WHEN COALESCE(agg.total_ingresado,0) <= 0 THEN "pendiente"
                    WHEN COALESCE(agg.total_ingresado,0) >= COALESCE(agg.total_objetivo,0) THEN "completo"
                    ELSE "parcial"
                END = ?
            ', [$estado]);
            })
            ->orderByDesc('d.id')
            ->paginate($perPage)
            ->withQueryString();

        // Tipos para filtro (si quieres mostrar nombres)
        $tiposDte = [
            33 => 'Factura electrónica',
            34 => 'Factura exenta electrónica',
            46 => 'Factura de compra electrónica',
            52 => 'Guía de despacho electrónica',
            56 => 'Nota de débito electrónica',
            61 => 'Nota de crédito electrónica',
        ];

        return view('inventario.dtes.index', compact(
            'dtes',
            'q',
            'tipo',
            'estado',
            'desde',
            'hasta',
            'perPage',
            'tiposDte'
        ));
    }



    public function show($dteId)
    {
        $dte = DB::table('dtes')->where('id', $dteId)->first();
        abort_unless($dte, 404);

        $detalles = DB::table('dte_detalles')
            ->where('dte_id', $dteId)
            ->orderBy('nro_lin_det')
            ->get();

        // OJO: cambia "bodegas" si tu tabla se llama distinto
        $bodegas = DB::table('bodegas')
            ->select('id', 'nombre')
            ->orderBy('nombre')
            ->get();

        return view('inventario.dtes.show', compact('dte', 'detalles', 'bodegas'));
    }


    /**
     * LISTA Gmail (esto es lo que demoraba).
     * ✅ list (ids) + get (metadata) cacheado
     * ✅ limpia Subject/From/Date a UTF-8 antes de mostrar
     */
    public function gmailIndex(Request $request)
    {
        $isJson = $request->expectsJson() || $request->wantsJson() || $request->header('Accept') === 'application/json';

        $token = Cache::get('gmail_token');
        if (!$token) {
            if ($isJson) {
                return response()->json([
                    'items' => [],
                    'nextPageToken' => null,
                    'error' => 'No hay token Gmail. Conecta Gmail primero.',
                ], 401);
            }

            return view('inventario.dtes.gmail', [
                'items' => [],
                'q' => $request->query('q', ''),
                'error' => 'No hay token Gmail. Conecta Gmail primero.',
                'nextPageToken' => null,
            ]);
        }

        $client = new Client();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setAccessToken($token);

        // ✅ Renovar automáticamente si está expirado y hay refresh_token
        if ($client->isAccessTokenExpired()) {
            $refreshToken = $client->getRefreshToken() ?: ($token['refresh_token'] ?? null);

            if ($refreshToken) {
                $newToken = $client->fetchAccessTokenWithRefreshToken($refreshToken);

                if (!isset($newToken['error'])) {
                    $newToken['refresh_token'] = $token['refresh_token'] ?? $refreshToken;
                    Cache::put('gmail_token', $newToken, now()->addDays(30));
                    $client->setAccessToken($newToken);
                } else {
                    if ($isJson) {
                        return response()->json([
                            'items' => [],
                            'nextPageToken' => null,
                            'error' => 'Token expirado y no se pudo renovar. Reconecta Gmail.',
                        ], 401);
                    }

                    return view('inventario.dtes.gmail', [
                        'items' => [],
                        'q' => $request->query('q', ''),
                        'error' => 'Token expirado y no se pudo renovar. Reconecta Gmail.',
                        'nextPageToken' => null,
                    ]);
                }
            } else {
                if ($isJson) {
                    return response()->json([
                        'items' => [],
                        'nextPageToken' => null,
                        'error' => 'Token expirado. Reconecta Gmail (no hay refresh_token).',
                    ], 401);
                }

                return view('inventario.dtes.gmail', [
                    'items' => [],
                    'q' => $request->query('q', ''),
                    'error' => 'Token expirado. Reconecta Gmail (no hay refresh_token).',
                    'nextPageToken' => null,
                ]);
            }
        }

        $gmail = new Gmail($client);

        $q = trim((string) $request->query('q', ''));
        $pageToken = $request->query('pageToken');
        $perPage = (int) $request->query('perPage', 20);
        $perPage = max(1, min($perPage, 200));

        $gmailQuery = 'filename:xml' . ($q !== '' ? (' ' . $q) : '');

        $listCacheKey = 'gmail_list_' . md5($gmailQuery . '|' . ($pageToken ?? '') . '|' . $perPage);
        $cached = Cache::get($listCacheKey);

        // ✅ SI HAY CACHE: devolver JSON si es AJAX
        if (is_array($cached) && isset($cached['items'])) {
            if ($isJson) {
                return response()->json([
                    'items' => $cached['items'],
                    'nextPageToken' => $cached['nextPageToken'] ?? null,
                    'error' => null,
                ]);
            }

            return view('inventario.dtes.gmail', [
                'items' => array_map(fn($r) => (object) $r, $cached['items']),
                'q' => $q,
                'error' => null,
                'nextPageToken' => $cached['nextPageToken'] ?? null,
                'perPage' => $perPage,
            ]);
        }

        $list = $gmail->users_messages->listUsersMessages('me', array_filter([
            'q' => $gmailQuery,
            'maxResults' => $perPage,
            'pageToken' => $pageToken,
            'includeSpamTrash' => false,
            'fields' => 'messages(id),nextPageToken,resultSizeEstimate',
        ]));

        $msgs = $list->getMessages() ?? [];
        $nextPageToken = $list->getNextPageToken();

        $items = [];
        foreach ($msgs as $m) {
            $id = (string) $m->getId();
            $cacheKey = 'gmail_meta_' . $id;

            $row = Cache::get($cacheKey);
            if (!is_array($row)) {
                $msg = $gmail->users_messages->get('me', $id, [
                    'format' => 'metadata',
                    'metadataHeaders' => ['Subject', 'From', 'Date'],
                    'fields' => 'id,payload(headers)',
                ]);

                $headers = collect($msg->getPayload()?->getHeaders() ?? [])
                    ->mapWithKeys(fn($h) => [$h->getName() => $h->getValue()]);

                $row = [
                    'gmail_id' => $id,
                    'subject' => $this->ensureUtf8((string) $headers->get('Subject', '')),
                    'from' => $this->ensureUtf8((string) $headers->get('From', '')),
                    'date' => $this->ensureUtf8((string) $headers->get('Date', '')),
                ];

                Cache::put($cacheKey, $row, now()->addHours(6));
            }

            $items[] = (object) $row;
        }

        $payload = [
            'items' => array_map(fn($o) => (array) $o, $items),
            'nextPageToken' => $nextPageToken,
            'error' => null,
        ];

        Cache::put($listCacheKey, $payload, now()->addMinutes(2));

        // ✅ JSON consistente
        if ($isJson) {
            return response()->json($payload);
        }

        return view('inventario.dtes.gmail', [
            'items' => $items,
            'q' => $q,
            'error' => null,
            'nextPageToken' => $nextPageToken,
            'perPage' => $perPage,
        ]);
    }



    /**
     * Importa seleccionados
     * ✅ Convierte XML a UTF-8 ANTES de parsear
     * ✅ Limpia strings antes de guardar (evita bytes sueltos en DB)
     * ✅ Guarda totales + xml + detalle
     */
    public function gmailImportSelected(Request $request)
    {
        $ids = $request->input('message_ids', []);
        if (!is_array($ids) || count($ids) === 0) {
            return back()->withErrors(['Debes seleccionar al menos 1 correo.']);
        }
        $token = Cache::get('gmail_token');
        if (!$token) {
            return back()->withErrors(['No hay token Gmail.']);
        }

        $client = new Client();
        $client->setAccessToken($token);

        if ($client->isAccessTokenExpired()) {
            return back()->withErrors(['Token expirado, reconecta.']);
        }

        $gmail = new Gmail($client);

        $nuevos = 0;
        $existian = 0;
        $sinXml = 0;
        $sinParse = 0;

        foreach ($ids as $messageId) {
            $messageId = (string) $messageId;

            $msg = $gmail->users_messages->get('me', $messageId, ['format' => 'full']);
            $payload = $msg->getPayload();

            $xmlContent = $this->firstXmlFromMessage($gmail, $messageId, $payload);
            if (!$xmlContent) {
                $sinXml++;
                continue;
            }

            // ✅ normalizar a UTF-8 ANTES de parsear y guardar
            $xmlContent = $this->ensureUtf8Xml($xmlContent);

            $parsed = $this->parseDteXml($xmlContent);
            $items = $parsed['items'] ?? [];

            if (!($parsed['ok'] ?? false) || !is_array($items) || count($items) === 0) {
                $sinParse++;
                continue;
            }

            // ✅ un correo puede traer varios DTE
            foreach ($items as $datos) {

                $tipo = (int) ($datos['dte']['TipoDTE'] ?? 0);
                $folio = (int) ($datos['dte']['Folio'] ?? 0);
                $fchEmis = $datos['dte']['FchEmis'] ?? null;

                $rutEmisor = (string) ($datos['emisor']['RUTEmisor'] ?? $datos['caratula']['RutEmisor'] ?? '');
                $rzEmisor = $this->ensureUtf8((string) ($datos['emisor']['RznSoc'] ?? ''));

                $rutReceptor = (string) ($datos['receptor']['RUTRecep'] ?? $datos['caratula']['RutReceptor'] ?? '');
                $rzReceptor = $this->ensureUtf8((string) ($datos['receptor']['RznSocRecep'] ?? ''));

                $mntNeto = $this->toIntOrNull($datos['totales']['MntNeto'] ?? null);
                $iva = $this->toIntOrNull($datos['totales']['IVA'] ?? null);
                $mntTotal = $this->toIntOrNull($datos['totales']['MntTotal'] ?? null);

                if ($rutEmisor === '' || $tipo === 0 || $folio === 0) {
                    $sinParse++;
                    continue;
                }

                if ($rutReceptor === '') {
                    $rutReceptor = '0-0';
                }

                DB::beginTransaction();
                try {
                    // 1) DTE master: upsert (por rut_emisor + tipo_dte + folio)
                    $existing = DB::table('dtes')
                        ->where('rut_emisor', $rutEmisor)
                        ->where('tipo_dte', $tipo)
                        ->where('folio', $folio)
                        ->first();

                    if ($existing) {
                        $dteId = (int) $existing->id;
                        $existian++;

                        DB::table('dtes')->where('id', $dteId)->update([
                            'gmail_message_id' => $messageId,
                            'tipo_nombre' => $datos['dte']['TipoNombre'] ?? $existing->tipo_nombre,
                            'fch_emis' => $fchEmis ?: $existing->fch_emis,

                            'rz_emisor' => $rzEmisor ?: $existing->rz_emisor,
                            'rut_receptor' => $rutReceptor ?: $existing->rut_receptor,
                            'rz_receptor' => $rzReceptor ?: $existing->rz_receptor,

                            'mnt_neto' => $mntNeto,
                            'iva' => $iva,
                            'mnt_total' => $mntTotal,

                            'xml' => $xmlContent,
                            'updated_at' => now(),
                        ]);
                    } else {
                        $dteId = (int) DB::table('dtes')->insertGetId([
                            'source' => 'gmail',
                            'gmail_message_id' => $messageId,

                            'tipo_dte' => $tipo,
                            'tipo_nombre' => $datos['dte']['TipoNombre'] ?? $this->tipoDteNombre((string) $tipo),
                            'folio' => $folio,
                            'fch_emis' => $fchEmis,

                            'rut_emisor' => $rutEmisor,
                            'rz_emisor' => $rzEmisor ?: null,

                            'rut_receptor' => $rutReceptor,
                            'rz_receptor' => $rzReceptor ?: null,

                            'mnt_neto' => $mntNeto,
                            'iva' => $iva,
                            'mnt_total' => $mntTotal,

                            'xml' => $xmlContent,

                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $nuevos++;
                    }

                    // 2) Detalles: UPSERT por (dte_id, nro_lin_det)
                    $detalleRows = $datos['detalle'] ?? [];
                    if (!is_array($detalleRows)) {
                        $detalleRows = [];
                    }

                    // Construimos filas para upsert
                    $rows = [];
                    foreach ($detalleRows as $row) {
                        $nro = ($row['NroLinDet'] ?? '') !== '' ? (int) $row['NroLinDet'] : null;
                        if ($nro === null) {
                            // si viene sin nro, lo saltamos (o podrías manejarlo distinto)
                            continue;
                        }

                        $rows[] = [
                            'dte_id' => $dteId,
                            'nro_lin_det' => $nro,

                            // datos “del XML” (sí se actualizan)
                            'nmb_item' => $this->ensureUtf8($row['NmbItem'] ?? null),
                            'qty' => $this->toDecimalOrNull($row['QtyItem'] ?? null, 3),
                            'unmd_item' => $this->ensureUtf8($row['UnmdItem'] ?? null),
                            'prc_item' => $this->toDecimalOrNull($row['PrcItem'] ?? null, 4),
                            'monto_item' => $this->toIntOrNull($row['MontoItem'] ?? null),

                            // ⚠️ campos tuyos NO los tocamos aquí:
                            // seleccionado_inventario, qty_a_ingresar, qty_ingresada, producto_id

                            'updated_at' => now(),

                            // created_at solo para inserts nuevos
                            'created_at' => now(),
                        ];
                    }

                    if (count($rows) > 0) {
                        // Upsert: solo actualiza columnas del XML + updated_at
                        DB::table('dte_detalles')->upsert(
                            $rows,
                            ['dte_id', 'nro_lin_det'], // clave única
                            ['nmb_item', 'qty', 'unmd_item', 'prc_item', 'monto_item', 'updated_at'] // columnas a actualizar
                        );
                    }

                    DB::commit();
                } catch (\Throwable $e) {
                    DB::rollBack();
                    throw $e;
                }
            }
        }

        return redirect()
            ->route('inventario.dtes.index')
            ->with('ok', "Nuevos: {$nuevos} | Ya existían: {$existian} | Sin XML: {$sinXml} | Sin parse: {$sinParse}");
    }

    /**
     * ✅ Extrae el primer XML adjunto del mensaje (si existe)
     */


    private function firstXmlFromMessage(Gmail $gmail, string $messageId, $payload): ?string
    {
        $parts = $payload->getParts() ?? [];
        $stack = $parts;

        while (!empty($stack)) {
            $part = array_shift($stack);

            if ($part->getParts()) {
                foreach ($part->getParts() as $sp) {
                    $stack[] = $sp;
                }
            }

            $filename = $part->getFilename();
            $body = $part->getBody();

            if ($filename && str_ends_with(strtolower($filename), '.xml') && $body?->getAttachmentId()) {
                $att = $gmail->users_messages_attachments->get('me', $messageId, $body->getAttachmentId());
                $data = $att->getData();
                return base64_decode(strtr($data, '-_', '+/'));
            }
        }

        return null;
    }

    /**
     * ✅ Limpia cualquier string a UTF-8 (para Subject/From/Razones sociales, etc.)
     */
    private function ensureUtf8($value): ?string
    {
        if ($value === null)
            return null;

        $s = (string) $value;

        // ya ok
        if (mb_check_encoding($s, 'UTF-8')) {
            // igual elimina bytes inválidos por si acaso
            $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $s);
            return is_string($clean) && $clean !== '' ? $clean : $s;
        }

        // intenta convertir desde ISO-8859-1 / Windows-1252
        $s = @mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1,Windows-1252,UTF-8');

        // limpieza final
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $s);
        return is_string($clean) && $clean !== '' ? $clean : $s;
    }

    /**
     * ✅ Convierte XML a UTF-8 según encoding declarado + limpieza final
     */
    private function ensureUtf8Xml(string $xml): string
    {
        $enc = null;
        if (preg_match('/<\?xml[^>]*encoding=["\']([^"\']+)["\']/i', $xml, $m)) {
            $enc = strtoupper(trim($m[1]));
        }

        if ($enc && $enc !== 'UTF-8') {
            $converted = @mb_convert_encoding($xml, 'UTF-8', $enc);
            if (is_string($converted) && $converted !== '') {
                $xml = $converted;
            }
            $xml = preg_replace('/(<\?xml[^>]*encoding=)["\'][^"\']+["\']/i', '$1"UTF-8"', $xml);
        }

        if (!mb_check_encoding($xml, 'UTF-8')) {
            $xml = @mb_convert_encoding($xml, 'UTF-8', 'ISO-8859-1,Windows-1252,UTF-8');
            $xml = preg_replace('/(<\?xml[^>]*encoding=)["\'][^"\']+["\']/i', '$1"UTF-8"', $xml);
        }

        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $xml);
        if (is_string($clean) && $clean !== '') {
            $xml = $clean;
        }

        return $xml;
    }


    private function parseDteXml(string $xml): array
    {
        libxml_use_internal_errors(true);

        $sxe = simplexml_load_string($xml);
        if (!$sxe) {
            return ['ok' => false, 'items' => []];
        }

        // Helpers sin namespace (local-name)
        $g1 = function (SimpleXMLElement $ctx, string $localName): string {
            $res = $ctx->xpath('.//*[local-name()="' . $localName . '"]');
            if ($res === false)
                return '';
            return (string) (($res[0] ?? '') ?: '');
        };

        $g = function (SimpleXMLElement $ctx, string $pathLocalNames): string {
            // pathLocalNames ejemplo: 'Caratula/RutEmisor'
            $parts = array_values(array_filter(explode('/', $pathLocalNames)));
            $q = '.';
            foreach ($parts as $p) {
                $q .= '/*[local-name()="' . $p . '"]';
            }
            $res = $ctx->xpath($q);
            if ($res === false)
                return '';
            return (string) (($res[0] ?? '') ?: '');
        };

        // Carátula (una por envío)
        $rutEmisorCar = $g($sxe, 'SetDTE/Caratula/RutEmisor');
        if ($rutEmisorCar === '')
            $rutEmisorCar = $g($sxe, 'Caratula/RutEmisor');

        $rutReceptorCar = $g($sxe, 'SetDTE/Caratula/RutReceptor');
        if ($rutReceptorCar === '')
            $rutReceptorCar = $g($sxe, 'Caratula/RutReceptor');

        // Todos los DTE
        $dteNodes = $sxe->xpath('//*[local-name()="DTE"]') ?: [];

        $items = [];

        foreach ($dteNodes as $dte) {
            // OJO: dentro del DTE, todo se extrae por local-name

            $tipoDte = $g($dte, 'Documento/Encabezado/IdDoc/TipoDTE');
            $folio = $g($dte, 'Documento/Encabezado/IdDoc/Folio');
            $fchEmis = $g($dte, 'Documento/Encabezado/IdDoc/FchEmis');

            $rutEmisor = $g($dte, 'Documento/Encabezado/Emisor/RUTEmisor');
            $rzSoc = $this->ensureUtf8($g($dte, 'Documento/Encabezado/Emisor/RznSoc'));
            $giroEmis = $this->ensureUtf8($g($dte, 'Documento/Encabezado/Emisor/GiroEmis'));

            $rutRecep = $g($dte, 'Documento/Encabezado/Receptor/RUTRecep');
            $rznSocRecep = $this->ensureUtf8($g($dte, 'Documento/Encabezado/Receptor/RznSocRecep'));

            $mntNeto = $g($dte, 'Documento/Encabezado/Totales/MntNeto');
            $iva = $g($dte, 'Documento/Encabezado/Totales/IVA');
            $mntTotal = $g($dte, 'Documento/Encabezado/Totales/MntTotal');

            // Detalle SOLO del DTE actual
            $detalle = [];
            $detNodes = $dte->xpath('.//*[local-name()="Documento"]/*[local-name()="Detalle"]') ?: [];

            foreach ($detNodes as $det) {
                $detalle[] = [
                    'NroLinDet' => (string) ($det->xpath('./*[local-name()="NroLinDet"]')[0] ?? ''),
                    'NmbItem' => $this->ensureUtf8((string) ($det->xpath('./*[local-name()="NmbItem"]')[0] ?? '')),
                    'QtyItem' => (string) ($det->xpath('./*[local-name()="QtyItem"]')[0] ?? ''),
                    'UnmdItem' => $this->ensureUtf8((string) ($det->xpath('./*[local-name()="UnmdItem"]')[0] ?? '')),
                    'PrcItem' => (string) ($det->xpath('./*[local-name()="PrcItem"]')[0] ?? ''),
                    'MontoItem' => (string) ($det->xpath('./*[local-name()="MontoItem"]')[0] ?? ''),
                ];
            }

            $items[] = [
                'caratula' => [
                    'RutEmisor' => $rutEmisorCar,
                    'RutReceptor' => $rutReceptorCar,
                ],
                'dte' => [
                    'TipoDTE' => $tipoDte,
                    'TipoNombre' => $this->tipoDteNombre($tipoDte),
                    'Folio' => $folio,
                    'FchEmis' => $fchEmis,
                ],
                'emisor' => [
                    'RUTEmisor' => $rutEmisor ?: $rutEmisorCar,
                    'RznSoc' => $rzSoc,
                    'GiroEmis' => $giroEmis,
                ],
                'receptor' => [
                    'RUTRecep' => $rutRecep ?: $rutReceptorCar,
                    'RznSocRecep' => $rznSocRecep,
                ],
                'totales' => [
                    'MntNeto' => $mntNeto,
                    'IVA' => $iva,
                    'MntTotal' => $mntTotal,
                ],
                'detalle' => $detalle,
            ];
        }

        return ['ok' => true, 'items' => $items];
    }



    private function toIntOrNull($v): ?int
    {
        if ($v === null)
            return null;

        $v = trim((string) $v);
        if ($v === '')
            return null;

        $v = str_replace(["\u{00A0}", ' '], '', $v);
        $v = str_replace('.', '', $v);

        if (!is_numeric($v))
            return null;
        return (int) $v;
    }
    private function toDecimalOrNull($v, int $scale = 4): ?string
    {
        if ($v === null)
            return null;

        $v = trim((string) $v);
        if ($v === '')
            return null;

        // limpia espacios raros
        $v = str_replace(["\u{00A0}", ' '], '', $v);

        // Si viniera con coma decimal (por si acaso), la convertimos a punto
        // y eliminamos separador de miles
        if (str_contains($v, ',')) {
            // formato tipo "1.234,56" -> "1234.56"
            $v = str_replace('.', '', $v);
            $v = str_replace(',', '.', $v);
        }

        if (!is_numeric($v))
            return null;

        // Devolver como string formateado para DECIMAL (evita errores float)
        return number_format((float) $v, $scale, '.', '');
    }
    public function ingresarSeleccionadosInventario(Request $request, $dteId)
    {
        $dte = DB::table('dtes')->where('id', $dteId)->first();
        abort_unless($dte, 404);

        $bodegaId = (int) $request->input('bodega_id', 0);
        if ($bodegaId <= 0) {
            return back()->withErrors(['Debes seleccionar una bodega.']);
        }

        DB::beginTransaction();
        try {
            /**
             * ✅ Traemos SOLO líneas seleccionadas
             * ✅ y que todavía tengan pendiente contra la cantidad real del DTE (qty)
             *    (NO contra qty_a_ingresar)
             */
            $lines = DB::table('dte_detalles')
                ->where('dte_id', $dteId)
                ->where('seleccionado_inventario', 1)
                ->whereRaw('COALESCE(qty_ingresada,0) < COALESCE(qty,0)')
                ->lockForUpdate()
                ->get();

            if ($lines->count() === 0) {
                DB::rollBack();
                return back()->withErrors(['No hay cantidades pendientes para ingresar.']);
            }

            $insertados = 0;

            // líneas del form (si vienen)
            $reqLines = $request->input('lines', []);
            if (!is_array($reqLines))
                $reqLines = [];

            foreach ($lines as $l) {

                // ✅ META TOTAL SIEMPRE = qty del DTE
                $qtyObjetivo = is_numeric($l->qty) ? (float) $l->qty : 0.0;

                // ya ingresado anteriormente
                $qtyIngresada = is_numeric($l->qty_ingresada ?? null) ? (float) $l->qty_ingresada : 0.0;

                // pendiente real
                $pendiente = $qtyObjetivo - $qtyIngresada;
                if ($pendiente <= 0) {
                    continue;
                }

                /**
                 * ✅ Cantidad solicitada "en esta corrida"
                 * - si el input viene, úsalo
                 * - si no viene, ingresa TODO lo pendiente
                 */
                $qtySolicitada = null;
                if (isset($reqLines[$l->id]['qty']) && is_numeric($reqLines[$l->id]['qty'])) {
                    $qtySolicitada = (float) $reqLines[$l->id]['qty'];
                }

                $qtyAIngresar = $qtySolicitada !== null ? $qtySolicitada : $pendiente;

                // sanitizar
                if ($qtyAIngresar <= 0)
                    continue;
                if ($qtyAIngresar > $pendiente)
                    $qtyAIngresar = $pendiente;

                // costo unitario
                if ($l->prc_item !== null && is_numeric($l->prc_item)) {
                    $costoUnit = (float) $l->prc_item;
                } elseif ($l->monto_item !== null && is_numeric($l->monto_item) && $qtyAIngresar > 0) {
                    $costoUnit = ((float) $l->monto_item) / (float) ((float) ($l->qty ?: 1));
                } else {
                    $costoUnit = 0.0;
                }

                $costoTotal = $costoUnit * $qtyAIngresar;

                // producto_id automático
                $productoId = $this->findOrCreateProductoFromDetalle($l);

                // 1) MOVIMIENTO
                DB::table('movimientos_inventario')->insert([
                    'tipo' => 'ENTRADA',
                    'producto_id' => $productoId,
                    'bodega_id' => $bodegaId,
                    'cantidad' => number_format($qtyAIngresar, 6, '.', ''),
                    'costo_unitario' => number_format($costoUnit, 6, '.', ''),
                    'costo_total' => number_format($costoTotal, 6, '.', ''),
                    'ocurrio_el' => $dte->fch_emis ? $dte->fch_emis : now(),
                    'documento_tipo' => 'dtes',
                    'documento_id' => $dteId,
                    'usuario_id' => auth()->id(),
                    'notas' => 'Entrada por DTE ' . ($dte->tipo_nombre ?? $dte->tipo_dte) . ' #' . $dte->folio,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // 2) LOTE
                $ingresadoEl = $dte->fch_emis ? $dte->fch_emis : now();

                DB::table('lotes_inventario')->insert([
                    'producto_id' => $productoId,
                    'bodega_id' => $bodegaId,
                    'codigo_lote' => null,
                    'ingresado_el' => $ingresadoEl,
                    'vence_el' => null,
                    'costo_unitario' => number_format($costoUnit, 6, '.', ''),
                    'cantidad_ingresada' => number_format($qtyAIngresar, 6, '.', ''),
                    'cantidad_salida' => number_format(0, 6, '.', ''),
                    'cantidad_disponible' => number_format($qtyAIngresar, 6, '.', ''),
                    'origen_tipo' => 'dtes',
                    'origen_id' => $dteId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // 3) Actualiza qty_ingresada
                DB::table('dte_detalles')->where('id', $l->id)->update([
                    'qty_ingresada' => number_format($qtyIngresada + $qtyAIngresar, 3, '.', ''),
                    'producto_id' => $productoId,
                    'updated_at' => now(),
                ]);

                $insertados++;
            }

            DB::commit();

            if ($insertados <= 0) {
                return back()->withErrors(['No había cantidades pendientes reales (todo estaba completo).']);
            }

            return back()->with('ok', "Ingresado a inventario. Líneas procesadas: {$insertados}");

        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }




    private function findOrCreateProductoFromDetalle($l): int
    {
        $nombre = trim((string) ($l->nmb_item ?? ''));
        if ($nombre === '') {
            throw new \RuntimeException('Detalle sin nombre de item (nmb_item).');
        }

        // Buscar producto existente por nombre
        $p = DB::table('productos')
            ->where('nombre', $nombre)
            ->orderByDesc('id')
            ->first();

        if ($p) {
            return (int) $p->id;
        }

        // ⚠️ AJUSTA estos defaults a tus IDs reales (si tus FK lo exigen)
        $defaultUnidadStockId = 1;
        $defaultUnidadCompraId = 1;
        $defaultUnidadVentaId = 1;
        $defaultCategoriaUnidadId = 1;
        $defaultPerfilImpuestoId = 1;

        // ✅ SKU automático único
        $sku = $this->generarSkuDesdeNombre($nombre);

        return (int) DB::table('productos')->insertGetId([
            'unidad_stock_id' => $defaultUnidadStockId,
            'sku' => $sku, // <-- aquí
            'nombre' => $nombre,
            'descripcion' => null,
            'activo' => 1,
            'unidad_compra_id' => $defaultUnidadCompraId,
            'unidad_venta_id' => $defaultUnidadVentaId,
            'categoria_unidad_id' => $defaultCategoriaUnidadId,
            'perfil_impuesto_id' => $defaultPerfilImpuestoId,
            'permite_fraccion' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function generarSkuDesdeNombre(string $nombre): string
    {
        $nombreUpper = mb_strtoupper($nombre, 'UTF-8');

        // 1️⃣ Prefijo (primeras 3 letras útiles)
        $prefijo = substr(preg_replace('/[^A-Z]/', '', $nombreUpper), 0, 3);
        if ($prefijo === '') {
            $prefijo = 'PRO';
        }

        // 2️⃣ Detectar medida tipo 50 X 60 / 50x60 / 50*60
        $medida = 'STD';
        if (preg_match('/(\d{2,3})\s*[Xx\*]\s*(\d{2,3})/', $nombreUpper, $m)) {
            $medida = $m[1] . $m[2]; // 50x60 → 5060
        }

        // 3️⃣ Hash corto estable (no aleatorio)
        $hash = strtoupper(substr(md5($nombreUpper), 0, 4));

        $sku = "{$prefijo}-{$medida}-{$hash}";

        // 4️⃣ Seguridad extra: evitar colisión real
        $i = 1;
        $skuFinal = $sku;
        while (DB::table('productos')->where('sku', $skuFinal)->exists()) {
            $skuFinal = "{$sku}-{$i}";
            $i++;
        }

        return $skuFinal;
    }


    public function updateDetallesSelection(Request $request, $dteId)
    {
        $dte = DB::table('dtes')->where('id', $dteId)->first();
        abort_unless($dte, 404);

        $lines = $request->input('lines', []);
        if (!is_array($lines)) {
            $lines = [];
        }

        $detalles = DB::table('dte_detalles')
            ->where('dte_id', $dteId)
            ->get()
            ->keyBy('id');

        $selectedCount = 0;

        DB::beginTransaction();
        try {
            // reset: todo queda "no seleccionado" si no viene en el post
            DB::table('dte_detalles')->where('dte_id', $dteId)->update([
                'seleccionado_inventario' => 0,
                'qty_a_ingresar' => null,
                'updated_at' => now(),
            ]);

            foreach ($lines as $detalleId => $payload) {
                $detalleId = (int) $detalleId;
                if (!$detalles->has($detalleId)) {
                    continue;
                }

                if (!is_array($payload)) {
                    $payload = [];
                }

                $selected = isset($payload['selected']) && (string) $payload['selected'] === '1';
                if (!$selected) {
                    continue;
                }

                $det = $detalles->get($detalleId);
                $maxQty = (float) ($det->qty ?? 0);

                $qty = $payload['qty'] ?? $maxQty;
                $qty = is_numeric($qty) ? (float) $qty : 0.0;

                if ($qty < 0)
                    $qty = 0;
                if ($qty > $maxQty)
                    $qty = $maxQty;

                DB::table('dte_detalles')->where('id', $detalleId)->update([
                    'seleccionado_inventario' => 1,
                    'qty_a_ingresar' => number_format($qty, 3, '.', ''),
                    'updated_at' => now(),
                ]);

                $selectedCount++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return back()->with('ok', "Selección guardada. Líneas seleccionadas: {$selectedCount}");
    }


    private function tipoDteNombre(string $tipo): string
    {
        return match ((int) $tipo) {
            33 => 'Factura electrónica',
            34 => 'Factura exenta electrónica',
            46 => 'Factura de compra electrónica',
            52 => 'Guía de despacho electrónica',
            56 => 'Nota de débito electrónica',
            61 => 'Nota de crédito electrónica',
            default => $tipo ? "DTE {$tipo}" : 'Desconocido',
        };
    }
}
