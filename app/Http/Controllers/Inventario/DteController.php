<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use Google\Client;
use Google\Service\Gmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DteController extends Controller
{
    public function leer(Request $request)
    {
        $token = Cache::get('gmail_token');
        if (!$token) {
            return response()->json(['ok' => false, 'msg' => 'NO HAY TOKEN'], 401);
        }

        $client = new Client();
        $client->setAccessToken($token);

        if ($client->isAccessTokenExpired()) {
            return response()->json(['ok' => false, 'msg' => 'TOKEN EXPIRADO, reconecta'], 401);
        }

        $gmail = new Gmail($client);

        // 🔎 Identificar la cuenta real conectada (clave para debug)
        try {
            $profile = $gmail->users->getProfile('me');
            $emailConectado = $profile->getEmailAddress();
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'msg' => 'No pude obtener el profile (token malo o scope insuficiente)',
                'err' => $e->getMessage(),
            ], 500);
        }

        $id = (string) $request->query('id', '');
        $wantJson = (bool) $request->boolean('json', false);

        // ✅ LISTADO (cuando NO viene id)
        if ($id === '') {
            $modo = (string) $request->query('modo', 'inbox');
            $pageToken = (string) $request->query('pageToken', '');
            $maxResults = (int) $request->query('max', 30);
            $q = (string) $request->query('q', '');

            if ($maxResults < 1)
                $maxResults = 1;
            if ($maxResults > 100)
                $maxResults = 100;

            $params = [
                'maxResults' => $maxResults,
                'includeSpamTrash' => true,
            ];

            if ($pageToken !== '')
                $params['pageToken'] = $pageToken;

            if ($modo === 'xml') {
                $params['q'] = trim('filename:xml ' . $q);
            } elseif ($modo === 'inbox') {
                $params['labelIds'] = ['INBOX'];
                if ($q !== '')
                    $params['q'] = $q;
            } elseif ($modo === 'all') {
                $params['q'] = trim('in:anywhere ' . $q);
            }

            $list = $gmail->users_messages->listUsersMessages('me', $params);
            $msgs = $list->getMessages() ?? [];

            $items = [];
            foreach ($msgs as $m) {
                $mm = $gmail->users_messages->get('me', $m->getId(), [
                    'format' => 'metadata',
                    'metadataHeaders' => ['Subject', 'From', 'Date'],
                ]);
                $headers = collect($mm->getPayload()->getHeaders() ?? [])
                    ->mapWithKeys(fn($h) => [$h->getName() => $h->getValue()]);

                $items[] = (object) [
                    'id' => $m->getId(),
                    'subject' => $this->toUtf8((string) $headers->get('Subject', '')),
                    'from' => $this->toUtf8((string) $headers->get('From', '')),
                    'date' => $this->toUtf8((string) $headers->get('Date', '')),
                ];
            }

            $nextPageToken = $list->getNextPageToken();

            // ✅ Si el navegador/Fetch pide JSON -> devolvemos JSON
            if ($request->wantsJson() || $request->boolean('json')) {
                return response()->json([
                    'ok' => true,
                    'email' => $emailConectado,
                    'modo' => $modo,
                    'maxResults' => $maxResults,
                    'items' => $items,
                    'nextPageToken' => $nextPageToken,
                ], 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
            }

            // ✅ Si NO pide JSON -> devolvemos la vista bonita
            return view('inventario.dtes.gmail', [
                'items' => $items,
                'nextPageToken' => $nextPageToken,
                'perPage' => $maxResults,
                'q' => $q,
                'modo' => $modo,
                'email' => $emailConectado,
            ]);
        }



        // ✅ LECTURA POR ID (tu lógica original, con try/catch)
        $cacheKey = "gmail_xml_view_{$id}";
        if (!$wantJson) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return view('inventario.dtes.xml', $cached);
            }
        }

        try {
            $msg = $gmail->users_messages->get('me', $id, ['format' => 'full']);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'email' => $emailConectado,
                'msg' => 'No pude obtener el mensaje por ID',
                'err' => $e->getMessage(),
                'id' => $id,
            ], 500);
        }

        $payload = $msg->getPayload();

        $headers = collect($payload->getHeaders() ?? [])
            ->mapWithKeys(fn($h) => [$h->getName() => $h->getValue()]);

        [$xml, $filename] = $this->firstXmlFromMessage($gmail, $id, $payload);

        if (!$xml) {
            if ($wantJson) {
                return response()->json([
                    'ok' => false,
                    'email' => $emailConectado,
                    'msg' => 'No se encontró XML adjunto',
                    'id' => $id,
                ], 404, [], JSON_INVALID_UTF8_SUBSTITUTE);
            }
            abort(404, 'No se encontró XML adjunto');
        }

        $xml = $this->ensureUtf8Xml($xml);

        $subject = $this->toUtf8((string) $headers->get('Subject', ''));
        $from = $this->toUtf8((string) $headers->get('From', ''));
        $date = $this->toUtf8((string) $headers->get('Date', ''));

        $datos = $this->parseDteXml($xml);

        if ($wantJson) {
            return response()->json([
                'ok' => true,
                'email' => $emailConectado,
                'id' => $id,
                'subject' => $subject,
                'from' => $from,
                'date' => $date,
                'filename' => $this->toUtf8((string) ($filename ?? '')),
                'datos' => $datos,
                'xml' => $xml,
            ], 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
        }

        $pretty = $this->prettyXml($xml);

        $viewData = [
            'messageId' => $id,
            'subject' => $subject,
            'from' => $from,
            'date' => $date,
            'filename' => $this->toUtf8((string) ($filename ?? '')),
            'datos' => $datos,
            'xml' => $pretty,
        ];

        Cache::put($cacheKey, $viewData, now()->addMinutes(30));

        return view('inventario.dtes.xml', $viewData);
    }


    private function firstXmlFromMessage(Gmail $gmail, string $messageId, $payload): array
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
                $xml = base64_decode(strtr($att->getData(), '-_', '+/'));
                return [$xml, $filename];
            }
        }

        return [null, null];
    }

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

        // ✅ limpieza final: elimina bytes inválidos
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $xml);
        if (is_string($clean) && $clean !== '') {
            $xml = $clean;
        }

        return $xml;
    }


    private function toUtf8(string $s): string
    {
        if (mb_check_encoding($s, 'UTF-8'))
            return $s;
        $c = @mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1,Windows-1252,UTF-8');
        return is_string($c) && $c !== '' ? $c : $s;
    }

    private function parseDteXml(string $xml): array
    {
        libxml_use_internal_errors(true);

        $sxe = simplexml_load_string($xml);
        if (!$sxe) {
            return [
                'ok' => false,
                'error' => 'XML inválido',
                'libxml' => array_map(fn($e) => trim($e->message), libxml_get_errors()),
            ];
        }

        $x = function (string $xpath) use ($sxe): string {
            $res = $sxe->xpath($xpath);
            return (string) (($res[0] ?? '') ?: '');
        };

        $tipoDte = $x('//*[local-name()="IdDoc"]/*[local-name()="TipoDTE"]');
        $folio = $x('//*[local-name()="IdDoc"]/*[local-name()="Folio"]');
        $fchEmis = $x('//*[local-name()="IdDoc"]/*[local-name()="FchEmis"]');

        $rutEmisor = $x('//*[local-name()="Emisor"]/*[local-name()="RUTEmisor"]');
        $rzEmisor = $x('//*[local-name()="Emisor"]/*[local-name()="RznSoc"]');

        $rutRecep = $x('//*[local-name()="Receptor"]/*[local-name()="RUTRecep"]');
        $rzRecep = $x('//*[local-name()="Receptor"]/*[local-name()="RznSocRecep"]');

        $totales = [
            'MntNeto' => $x('//*[local-name()="Totales"]/*[local-name()="MntNeto"]'),
            'IVA' => $x('//*[local-name()="Totales"]/*[local-name()="IVA"]'),
            'MntTotal' => $x('//*[local-name()="Totales"]/*[local-name()="MntTotal"]'),
        ];

        // ✅ Detalle / Ítems
        $items = [];
        $detNodes = $sxe->xpath('//*[local-name()="Detalle"]') ?: [];

        foreach ($detNodes as $det) {
            $get = function ($node, string $tag): string {
                $r = $node->xpath('./*[local-name()="' . $tag . '"]');
                return (string) (($r[0] ?? '') ?: '');
            };

            $codigos = [];
            $cdgNodes = $det->xpath('./*[local-name()="CdgItem"]') ?: [];
            foreach ($cdgNodes as $cdg) {
                $tp = (string) (($cdg->xpath('./*[local-name()="TpoCodigo"]')[0] ?? '') ?: '');
                $vlr = (string) (($cdg->xpath('./*[local-name()="VlrCodigo"]')[0] ?? '') ?: '');
                $tp = trim($tp);
                $vlr = trim($vlr);
                if ($tp !== '' && $vlr !== '')
                    $codigos[] = "{$tp}:{$vlr}";
                elseif ($vlr !== '')
                    $codigos[] = $vlr;
            }

            $items[] = [
                'NroLinDet' => $get($det, 'NroLinDet'),
                'NmbItem' => $get($det, 'NmbItem'),
                'DscItem' => $get($det, 'DscItem'),
                'QtyItem' => $get($det, 'QtyItem'),
                'UnmdItem' => $get($det, 'UnmdItem'),
                'PrcItem' => $get($det, 'PrcItem'),
                'MontoItem' => $get($det, 'MontoItem'),
                'Codigos' => $codigos,
            ];
        }

        return [
            'ok' => true,
            'dte' => compact('tipoDte', 'folio', 'fchEmis'),
            'emisor' => ['RUTEmisor' => $rutEmisor, 'RznSoc' => $rzEmisor],
            'receptor' => ['RUTRecep' => $rutRecep, 'RznSocRecep' => $rzRecep],
            'totales' => $totales,
            'items' => $items,
        ];
    }

    public function ver(Request $request, string $id)
    {
        // Reutiliza leer() pero en modo "vista" (no XML)
        // Vamos a leer el mensaje/adjunto y armar $viewData igual que en leer().

        $token = \Illuminate\Support\Facades\Cache::get('gmail_token');
        if (!$token)
            abort(401, 'NO HAY TOKEN');

        $client = new \Google\Client();
        $client->setAccessToken($token);

        if ($client->isAccessTokenExpired())
            abort(401, 'TOKEN EXPIRADO, reconecta');

        $gmail = new \Google\Service\Gmail($client);

        $msg = $gmail->users_messages->get('me', $id, ['format' => 'full']);
        $payload = $msg->getPayload();

        $headers = collect($payload->getHeaders() ?? [])
            ->mapWithKeys(fn($h) => [$h->getName() => $h->getValue()]);

        [$xml, $filename] = $this->firstXmlFromMessage($gmail, $id, $payload);
        if (!$xml)
            abort(404, 'No se encontró XML adjunto');

        $xml = $this->ensureUtf8Xml($xml);

        $subject = $this->toUtf8((string) $headers->get('Subject', ''));
        $from = $this->toUtf8((string) $headers->get('From', ''));
        $date = $this->toUtf8((string) $headers->get('Date', ''));

        $datos = $this->parseDteXml($xml);

        return view('inventario.dtes.ver', [
            'messageId' => $id,
            'subject' => $subject,
            'from' => $from,
            'date' => $date,
            'filename' => $this->toUtf8((string) ($filename ?? '')),
            'datos' => $datos,
        ]);
    }

    private function prettyXml(string $xml): string
    {
        $xml = $this->ensureUtf8Xml($xml);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        if (@$dom->loadXML($xml) === false) {
            return $xml;
        }

        return $dom->saveXML();
    }
}
