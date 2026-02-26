<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class XmlDsigSignerService
{
    /**
     * Firma el Documento DTE por atributo ID (enveloped signature XMLDSIG SHA1/RSA).
     */
    public function signDocumentoById(DOMDocument $doc, string $documentId): void
    {
        $documento = $this->findElementoById($doc, 'Documento', $documentId);
        if (!$documento) {
            throw new RuntimeException("No se encontró Documento con ID={$documentId} para firma XMLDSIG.");
        }

        $dteNode = $documento->parentNode;
        if (!$dteNode instanceof DOMElement || strtoupper($dteNode->localName ?? '') !== 'DTE') {
            throw new RuntimeException('Estructura XML inválida: Documento no está dentro de DTE.');
        }

        $credentials = $this->loadSigningCredentials();
        if ($credentials === null) {
            Log::info('Firma omitida (DEV MODE)', [
                'reason' => 'pfx_missing',
                'document_id' => $documentId,
            ]);
            return;
        }

        [$privateKey, $certPem] = $credentials;
        $certBase64 = $this->normalizeCertificateToBase64($certPem);

        $digestValue = $this->buildReferenceDigestValue($documento);
        $signatureNode = $this->buildSignatureNode($doc, $documentId, $digestValue, $certBase64, $privateKey);

        $dteNode->appendChild($signatureNode);
    }

    private function findElementoById(DOMDocument $doc, string $localName, string $id): ?DOMElement
    {
        $xpath = new \DOMXPath($doc);
        $list = $xpath->query(sprintf('//*[local-name()="%s" and @ID="%s"]', $localName, $id));
        $node = $list?->item(0);

        return $node instanceof DOMElement ? $node : null;
    }

    private function buildReferenceDigestValue(DOMElement $documento): string
    {
        $tmpDoc = new DOMDocument('1.0', 'UTF-8');
        $tmpDoc->formatOutput = false;
        $tmpDocumento = $tmpDoc->importNode($documento, true);
        $tmpDoc->appendChild($tmpDocumento);

        // Enveloped-signature transform: remover cualquier Signature existente.
        $xp = new \DOMXPath($tmpDoc);
        $nodes = $xp->query('//*[local-name()="Signature" and namespace-uri()="http://www.w3.org/2000/09/xmldsig#"]');
        if ($nodes) {
            for ($i = $nodes->length - 1; $i >= 0; $i--) {
                $sig = $nodes->item($i);
                if ($sig?->parentNode) {
                    $sig->parentNode->removeChild($sig);
                }
            }
        }

        $canon = $tmpDoc->C14N(false, false);
        if (!is_string($canon) || $canon === '') {
            throw new RuntimeException('No se pudo canonicalizar Documento para DigestValue.');
        }

        return base64_encode(sha1($canon, true));
    }

    private function buildSignatureNode(
        DOMDocument $doc,
        string $documentId,
        string $digestValue,
        string $certBase64,
        mixed $privateKey
    ): DOMElement {
        $dsNs = 'http://www.w3.org/2000/09/xmldsig#';

        $signature = $doc->createElementNS($dsNs, 'ds:Signature');
        $signedInfo = $doc->createElementNS($dsNs, 'ds:SignedInfo');
        $signature->appendChild($signedInfo);

        $canonMethod = $doc->createElementNS($dsNs, 'ds:CanonicalizationMethod');
        $canonMethod->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $signedInfo->appendChild($canonMethod);

        $sigMethod = $doc->createElementNS($dsNs, 'ds:SignatureMethod');
        $sigMethod->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#rsa-sha1');
        $signedInfo->appendChild($sigMethod);

        $reference = $doc->createElementNS($dsNs, 'ds:Reference');
        $reference->setAttribute('URI', '#' . $documentId);
        $signedInfo->appendChild($reference);

        $transforms = $doc->createElementNS($dsNs, 'ds:Transforms');
        $reference->appendChild($transforms);

        $transformEnv = $doc->createElementNS($dsNs, 'ds:Transform');
        $transformEnv->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#enveloped-signature');
        $transforms->appendChild($transformEnv);

        $transformC14n = $doc->createElementNS($dsNs, 'ds:Transform');
        $transformC14n->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $transforms->appendChild($transformC14n);

        $digestMethod = $doc->createElementNS($dsNs, 'ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#sha1');
        $reference->appendChild($digestMethod);

        $reference->appendChild($doc->createElementNS($dsNs, 'ds:DigestValue', $digestValue));

        $signedInfoCanon = $signedInfo->C14N(false, false);
        if (!is_string($signedInfoCanon) || $signedInfoCanon === '') {
            throw new RuntimeException('No se pudo canonicalizar SignedInfo para firma.');
        }

        $signatureBin = '';
        $ok = openssl_sign($signedInfoCanon, $signatureBin, $privateKey, OPENSSL_ALGO_SHA1);
        if (!$ok) {
            throw new RuntimeException('OpenSSL no pudo firmar SignedInfo (RSA-SHA1).');
        }

        $signature->appendChild($doc->createElementNS($dsNs, 'ds:SignatureValue', base64_encode($signatureBin)));

        $keyInfo = $doc->createElementNS($dsNs, 'ds:KeyInfo');
        $x509Data = $doc->createElementNS($dsNs, 'ds:X509Data');
        $x509Data->appendChild($doc->createElementNS($dsNs, 'ds:X509Certificate', $certBase64));
        $keyInfo->appendChild($x509Data);
        $signature->appendChild($keyInfo);

        return $signature;
    }

    /**
     * @return array{0:mixed,1:string}|null
     */
    private function loadSigningCredentials(): ?array
    {
        $disk = (string) config('dte.signature.disk', 'local');
        $path = trim((string) config('dte.signature.pfx_path', ''));
        $pass = (string) config('dte.signature.pfx_password', '');

        if ($path === '') {
            return null;
        }
        if (!Storage::disk($disk)->exists($path)) {
            return null;
        }

        $pfx = Storage::disk($disk)->get($path);
        if (!is_string($pfx) || $pfx === '') {
            throw new RuntimeException("No se pudo leer el contenido del PFX {$path}.");
        }

        $certs = [];
        if (!openssl_pkcs12_read($pfx, $certs, $pass)) {
            throw new RuntimeException('No se pudo abrir el .pfx. Revisa contraseña y formato.');
        }

        $privateKeyPem = (string) ($certs['pkey'] ?? '');
        $certPem = (string) ($certs['cert'] ?? '');
        if ($privateKeyPem === '' || $certPem === '') {
            throw new RuntimeException('El .pfx no contiene llave privada y certificado válidos.');
        }

        $privateKey = openssl_pkey_get_private($privateKeyPem);
        if ($privateKey === false) {
            throw new RuntimeException('No se pudo cargar la llave privada desde el .pfx.');
        }

        return [$privateKey, $certPem];
    }

    private function normalizeCertificateToBase64(string $certPem): string
    {
        $clean = preg_replace('/\-+BEGIN CERTIFICATE\-+|\-+END CERTIFICATE\-+|\s+/', '', $certPem) ?? '';
        $clean = trim($clean);
        if ($clean === '') {
            throw new RuntimeException('Certificado X509 inválido dentro del .pfx.');
        }

        return $clean;
    }
}
