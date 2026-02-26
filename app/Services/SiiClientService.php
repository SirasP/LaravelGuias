<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class SiiClientService
{
    public function getSeed(): string
    {
        if ($this->isDevMode()) {
            return 'DEV_SEED_' . now()->format('YmdHis');
        }

        $xml = $this->soapCall(
            (string) config('dte.sii.endpoints.seed'),
            'getSeed',
            ''
        );

        $seed = $this->extractFirstTagValue($xml, 'SEMILLA');
        if ($seed === '') {
            throw new RuntimeException('SII no devolvió SEMILLA en getSeed.');
        }

        return $seed;
    }

    public function signSeed(string $seed): string
    {
        if ($this->isDevMode()) {
            return '<getToken><item><Semilla>' . htmlspecialchars($seed, ENT_XML1) . '</Semilla></item></getToken>';
        }

        if (trim($seed) === '') {
            throw new RuntimeException('Semilla vacía para firmar.');
        }

        [$privateKey, $certPem] = $this->loadSigningCredentials();
        $certBase64 = $this->normalizeCertificateToBase64($certPem);

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = false;

        $getToken = $doc->createElement('getToken');
        $item = $doc->createElement('item');
        $item->appendChild($doc->createElement('Semilla', $seed));
        $getToken->appendChild($item);
        $doc->appendChild($getToken);

        $digestValue = $this->buildDigestWithoutSignature($getToken);
        $signature = $this->buildSignatureForNode($doc, $digestValue, $certBase64, $privateKey);
        $getToken->appendChild($signature);

        $signed = $doc->saveXML();
        if (!is_string($signed) || trim($signed) === '') {
            throw new RuntimeException('No se pudo serializar semilla firmada.');
        }

        return $signed;
    }

    public function getToken(?string $signedSeedXml = null): string
    {
        if ($this->isDevMode()) {
            return 'DEV_TOKEN_' . now()->format('YmdHis');
        }

        if ($signedSeedXml === null || trim($signedSeedXml) === '') {
            $signedSeedXml = $this->signSeed($this->getSeed());
        }

        $body = '<pszXml>' . htmlspecialchars($signedSeedXml, ENT_XML1) . '</pszXml>';
        $xml = $this->soapCall(
            (string) config('dte.sii.endpoints.token'),
            'getToken',
            $body
        );

        $token = $this->extractFirstTagValue($xml, 'TOKEN');
        if ($token === '') {
            throw new RuntimeException('SII no devolvió TOKEN en getToken.');
        }

        return $token;
    }

    /**
     * @return array{track_id:string,response_xml:string,token:string}
     */
    public function enviarDte(string $xmlPath): array
    {
        if ($this->isDevMode()) {
            $trackId = 'DEV-' . now()->format('YmdHis');
            Log::info('Envio SII omitido (DEV MODE)', [
                'track_id' => $trackId,
                'xml_path' => $xmlPath,
            ]);

            return [
                'track_id' => $trackId,
                'response_xml' => '<SII><ESTADO>DEV_NO_ENVIADO</ESTADO><TRACKID>' . $trackId . '</TRACKID></SII>',
                'token' => 'DEV_TOKEN',
                'sii_estado' => 'DEV_NO_ENVIADO',
            ];
        }

        $token = $this->getToken();

        $disk = (string) config('dte.storage_disk', 'local');
        if (!Storage::disk($disk)->exists($xmlPath)) {
            throw new RuntimeException("No existe XML DTE para envío: {$xmlPath}");
        }
        $xml = Storage::disk($disk)->get($xmlPath);
        if (!is_string($xml) || trim($xml) === '') {
            throw new RuntimeException("XML DTE vacío: {$xmlPath}");
        }

        [$rutSender, $dvSender] = $this->splitRut((string) config('dte.emisor.rut_envia', ''));
        [$rutCompany, $dvCompany] = $this->splitRut((string) config('dte.emisor.rut', ''));
        if ($rutSender === '' || $dvSender === '' || $rutCompany === '' || $dvCompany === '') {
            throw new RuntimeException('RUT emisor/rut_envia inválidos para envío SII.');
        }

        $filename = basename($xmlPath);
        $response = Http::asMultipart()
            ->withHeaders([
                'Cookie' => 'TOKEN=' . $token,
                'Accept' => 'text/xml, application/xml',
            ])
            ->attach('archivo', $xml, $filename, ['Content-Type' => 'text/xml'])
            ->post((string) config('dte.sii.endpoints.recepcion'), [
                'rutSender' => $rutSender,
                'dvSender' => $dvSender,
                'rutCompany' => $rutCompany,
                'dvCompany' => $dvCompany,
            ]);

        $this->assertHttpOk($response, 'RecepcionDTE');
        $raw = (string) $response->body();

        $trackId = $this->extractFirstTagValue($raw, 'TRACKID');
        if ($trackId === '') {
            $trackId = $this->extractFirstTagValue($raw, 'TRACK_ID');
        }
        if ($trackId === '') {
            throw new RuntimeException('SII no devolvió TRACKID al enviar DTE.');
        }

        return [
            'track_id' => $trackId,
            'response_xml' => $raw,
            'token' => $token,
            'sii_estado' => 'ENVIADO',
        ];
    }

    /**
     * @return array{track_id:string,estado:string,response_xml:string}
     */
    public function consultarEstado(string $trackId): array
    {
        $trackId = trim($trackId);
        if ($trackId === '') {
            throw new RuntimeException('trackId requerido para consultar estado SII.');
        }

        if ($this->isDevMode()) {
            return [
                'track_id' => $trackId,
                'estado' => 'DEV_NO_ENVIADO',
                'response_xml' => '<SII><ESTADO>DEV_NO_ENVIADO</ESTADO><TRACKID>' . htmlspecialchars($trackId, ENT_XML1) . '</TRACKID></SII>',
            ];
        }

        $token = $this->getToken();
        [$rutCompany, $dvCompany] = $this->splitRut((string) config('dte.emisor.rut', ''));
        if ($rutCompany === '' || $dvCompany === '') {
            throw new RuntimeException('RUT emisor inválido para consulta de estado SII.');
        }

        $body = '<rutCompania>' . $rutCompany . '</rutCompania>'
            . '<dvCompania>' . $dvCompany . '</dvCompania>'
            . '<trackId>' . htmlspecialchars($trackId, ENT_XML1) . '</trackId>'
            . '<token>' . htmlspecialchars($token, ENT_XML1) . '</token>';

        $xml = $this->soapCall(
            (string) config('dte.sii.endpoints.estado'),
            'getEstUp',
            $body
        );

        $estado = $this->extractFirstTagValue($xml, 'ESTADO');
        if ($estado === '') {
            $estado = $this->extractFirstTagValue($xml, 'GLOSA');
        }

        return [
            'track_id' => $trackId,
            'estado' => $estado,
            'response_xml' => $xml,
        ];
    }

    private function soapCall(string $endpoint, string $method, string $innerXml): string
    {
        if (trim($endpoint) === '') {
            throw new RuntimeException("Endpoint SII no configurado para {$method}.");
        }

        $envelope = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:dte="http://DefaultNamespace">'
            . '<soapenv:Header/>'
            . '<soapenv:Body>'
            . '<dte:' . $method . '>'
            . $innerXml
            . '</dte:' . $method . '>'
            . '</soapenv:Body>'
            . '</soapenv:Envelope>';

        $response = Http::withHeaders([
            'Content-Type' => 'text/xml; charset=UTF-8',
            'SOAPAction' => $method,
        ])->withBody($envelope, 'text/xml; charset=UTF-8')->post($endpoint);

        $this->assertHttpOk($response, $method);
        $raw = (string) $response->body();
        if (trim($raw) === '') {
            throw new RuntimeException("Respuesta vacía SII en {$method}.");
        }

        return $raw;
    }

    private function assertHttpOk(Response $response, string $operation): void
    {
        if ($response->successful()) {
            return;
        }

        throw new RuntimeException(
            "Error HTTP SII en {$operation}: {$response->status()} {$response->body()}"
        );
    }

    private function extractFirstTagValue(string $xml, string $tagName): string
    {
        $value = $this->extractTagValueFromString($xml, $tagName);
        if ($value !== '') {
            return trim($value);
        }

        $decoded = html_entity_decode($xml, ENT_QUOTES | ENT_XML1);
        $value = $this->extractTagValueFromString($decoded, $tagName);
        if ($value !== '') {
            return trim($value);
        }

        $inner = $this->extractTagValueFromString($xml, 'getSeedReturn')
            ?: $this->extractTagValueFromString($xml, 'getTokenReturn')
            ?: $this->extractTagValueFromString($xml, 'getEstUpReturn');
        if ($inner !== '') {
            $value = $this->extractTagValueFromString($inner, $tagName);
            if ($value !== '') {
                return trim($value);
            }
        }

        return '';
    }

    private function extractTagValueFromString(string $xml, string $tagName): string
    {
        $pattern = '/<' . preg_quote($tagName, '/') . '>\s*(.*?)\s*<\/' . preg_quote($tagName, '/') . '>/is';
        if (preg_match($pattern, $xml, $m)) {
            return (string) $m[1];
        }

        $patternNs = '/<([a-z0-9_]+:)?' . preg_quote($tagName, '/') . '>\s*(.*?)\s*<\/([a-z0-9_]+:)?'
            . preg_quote($tagName, '/') . '>/is';
        if (preg_match($patternNs, $xml, $m)) {
            return (string) $m[2];
        }

        return '';
    }

    /**
     * @return array{0:mixed,1:string}
     */
    private function loadSigningCredentials(): array
    {
        $disk = (string) config('dte.signature.disk', 'local');
        $path = trim((string) config('dte.signature.pfx_path', ''));
        $pass = (string) config('dte.signature.pfx_password', '');

        if ($path === '' || !Storage::disk($disk)->exists($path)) {
            throw new RuntimeException('Certificado .pfx no encontrado para firma de semilla.');
        }

        $pfx = Storage::disk($disk)->get($path);
        $certs = [];
        if (!is_string($pfx) || !openssl_pkcs12_read($pfx, $certs, $pass)) {
            throw new RuntimeException('No se pudo abrir .pfx para firma de semilla.');
        }

        $privateKeyPem = (string) ($certs['pkey'] ?? '');
        $certPem = (string) ($certs['cert'] ?? '');
        if ($privateKeyPem === '' || $certPem === '') {
            throw new RuntimeException('El .pfx no contiene llave/certificado para semilla.');
        }

        $privateKey = openssl_pkey_get_private($privateKeyPem);
        if ($privateKey === false) {
            throw new RuntimeException('No se pudo cargar llave privada desde .pfx para semilla.');
        }

        return [$privateKey, $certPem];
    }

    private function normalizeCertificateToBase64(string $certPem): string
    {
        $clean = preg_replace('/\-+BEGIN CERTIFICATE\-+|\-+END CERTIFICATE\-+|\s+/', '', $certPem) ?? '';
        $clean = trim($clean);
        if ($clean === '') {
            throw new RuntimeException('Certificado inválido para firma de semilla.');
        }

        return $clean;
    }

    private function buildDigestWithoutSignature(DOMElement $root): string
    {
        $tmpDoc = new DOMDocument('1.0', 'UTF-8');
        $tmp = $tmpDoc->importNode($root, true);
        $tmpDoc->appendChild($tmp);

        $xp = new \DOMXPath($tmpDoc);
        $sigs = $xp->query('//*[local-name()="Signature" and namespace-uri()="http://www.w3.org/2000/09/xmldsig#"]');
        if ($sigs) {
            for ($i = $sigs->length - 1; $i >= 0; $i--) {
                $sig = $sigs->item($i);
                if ($sig?->parentNode) {
                    $sig->parentNode->removeChild($sig);
                }
            }
        }

        $canon = $tmpDoc->C14N(false, false);
        if (!is_string($canon) || $canon === '') {
            throw new RuntimeException('No se pudo canonicalizar semilla para DigestValue.');
        }

        return base64_encode(sha1($canon, true));
    }

    private function buildSignatureForNode(
        DOMDocument $doc,
        string $digestValue,
        string $certBase64,
        mixed $privateKey
    ): DOMElement {
        $ns = 'http://www.w3.org/2000/09/xmldsig#';
        $signature = $doc->createElementNS($ns, 'ds:Signature');
        $signedInfo = $doc->createElementNS($ns, 'ds:SignedInfo');
        $signature->appendChild($signedInfo);

        $cm = $doc->createElementNS($ns, 'ds:CanonicalizationMethod');
        $cm->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $signedInfo->appendChild($cm);

        $sm = $doc->createElementNS($ns, 'ds:SignatureMethod');
        $sm->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#rsa-sha1');
        $signedInfo->appendChild($sm);

        $ref = $doc->createElementNS($ns, 'ds:Reference');
        $ref->setAttribute('URI', '');
        $signedInfo->appendChild($ref);

        $transforms = $doc->createElementNS($ns, 'ds:Transforms');
        $ref->appendChild($transforms);

        $t1 = $doc->createElementNS($ns, 'ds:Transform');
        $t1->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#enveloped-signature');
        $transforms->appendChild($t1);

        $t2 = $doc->createElementNS($ns, 'ds:Transform');
        $t2->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $transforms->appendChild($t2);

        $dm = $doc->createElementNS($ns, 'ds:DigestMethod');
        $dm->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#sha1');
        $ref->appendChild($dm);
        $ref->appendChild($doc->createElementNS($ns, 'ds:DigestValue', $digestValue));

        $canonSignedInfo = $signedInfo->C14N(false, false);
        if (!is_string($canonSignedInfo) || $canonSignedInfo === '') {
            throw new RuntimeException('No se pudo canonicalizar SignedInfo para semilla.');
        }

        $sigBin = '';
        if (!openssl_sign($canonSignedInfo, $sigBin, $privateKey, OPENSSL_ALGO_SHA1)) {
            throw new RuntimeException('No se pudo firmar semilla con RSA-SHA1.');
        }
        $signature->appendChild($doc->createElementNS($ns, 'ds:SignatureValue', base64_encode($sigBin)));

        $keyInfo = $doc->createElementNS($ns, 'ds:KeyInfo');
        $x509Data = $doc->createElementNS($ns, 'ds:X509Data');
        $x509Data->appendChild($doc->createElementNS($ns, 'ds:X509Certificate', $certBase64));
        $keyInfo->appendChild($x509Data);
        $signature->appendChild($keyInfo);

        return $signature;
    }

    /**
     * @return array{0:string,1:string}
     */
    private function splitRut(string $rut): array
    {
        $rut = trim($rut);
        if ($rut === '' || !str_contains($rut, '-')) {
            return ['', ''];
        }
        [$body, $dv] = explode('-', strtoupper($rut), 2);
        $body = preg_replace('/\D+/', '', $body) ?? '';
        $dv = trim($dv);

        return [$body, $dv];
    }

    private function isDevMode(): bool
    {
        $disk = (string) config('dte.signature.disk', 'local');
        $path = trim((string) config('dte.signature.pfx_path', ''));

        if ($path === '') {
            return true;
        }

        return !Storage::disk($disk)->exists($path);
    }
}
