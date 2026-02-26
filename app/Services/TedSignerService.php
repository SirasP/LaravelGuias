<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use RuntimeException;

class TedSignerService
{
    /**
     * @param array{
     *   caf_xml:string
     * } $cafData
     */
    public function timbrarTed(DOMElement $tedNode, array $cafData): string
    {
        if (!isset($cafData['caf_xml']) || trim((string) $cafData['caf_xml']) === '') {
            throw new RuntimeException('CAF no disponible para timbrar TED.');
        }

        $ddNode = $this->findFirstChildByLocalName($tedNode, 'DD');
        if (!$ddNode) {
            throw new RuntimeException('Nodo DD no encontrado dentro de TED.');
        }

        $frmtNode = $this->findFirstChildByLocalName($tedNode, 'FRMT');
        if (!$frmtNode) {
            throw new RuntimeException('Nodo FRMT no encontrado dentro de TED.');
        }

        $privateKey = $this->loadRsaskPrivateKeyFromCafXml((string) $cafData['caf_xml']);
        $ddCanonical = $this->canonicalizeDdForSii($ddNode);

        $signature = '';
        $signed = openssl_sign($ddCanonical, $signature, $privateKey, OPENSSL_ALGO_SHA1);
        if (!$signed) {
            throw new RuntimeException('No se pudo firmar DD del TED con RSASK.');
        }

        $frmt = base64_encode($signature);
        $frmtNode->nodeValue = $frmt;
        $frmtNode->setAttribute('algoritmo', 'SHA1withRSA');

        return $frmt;
    }

    private function canonicalizeDdForSii(DOMElement $ddNode): string
    {
        $canon = $ddNode->C14N(false, false);
        if (!is_string($canon) || $canon === '') {
            throw new RuntimeException('No se pudo canonicalizar DD para TED.');
        }

        $iso = @mb_convert_encoding($canon, 'ISO-8859-1', 'UTF-8');
        if (!is_string($iso) || $iso === '') {
            return $canon;
        }

        return $iso;
    }

    private function loadRsaskPrivateKeyFromCafXml(string $cafXml): mixed
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        if (!@$dom->loadXML($cafXml)) {
            throw new RuntimeException('CAF inválido al cargar RSASK.');
        }

        $xp = new \DOMXPath($dom);
        $rsask = $xp->query('//*[local-name()="RSASK"]')?->item(0);
        if (!$rsask instanceof DOMElement) {
            throw new RuntimeException('CAF no contiene nodo RSASK.');
        }

        $rawText = trim((string) $rsask->textContent);
        if (str_contains($rawText, 'BEGIN')) {
            $pem = $this->normalizePem($rawText);
            $key = openssl_pkey_get_private($pem);
            if ($key !== false) {
                return $key;
            }
        }

        $pemFromComponents = $this->buildPemFromRsaskComponents($rsask);
        $key = openssl_pkey_get_private($pemFromComponents);
        if ($key === false) {
            throw new RuntimeException('No se pudo construir llave privada desde RSASK del CAF.');
        }

        return $key;
    }

    private function normalizePem(string $pem): string
    {
        $clean = preg_replace('/\r\n?/', "\n", trim($pem)) ?? trim($pem);
        if (!str_contains($clean, 'BEGIN')) {
            throw new RuntimeException('Formato PEM inválido en RSASK.');
        }

        return $clean;
    }

    private function buildPemFromRsaskComponents(DOMElement $rsask): string
    {
        $get = function (array $names) use ($rsask): ?string {
            foreach ($names as $name) {
                foreach ($rsask->childNodes as $child) {
                    if (!$child instanceof DOMElement) {
                        continue;
                    }
                    if (strcasecmp($child->localName ?? '', $name) === 0) {
                        $v = trim((string) $child->textContent);
                        if ($v !== '') {
                            return $v;
                        }
                    }
                }
            }

            return null;
        };

        $components = [
            'n'  => $get(['M', 'N', 'MODULUS']),
            'e'  => $get(['E', 'EXPONENT']),
            'd'  => $get(['D']),
            'p'  => $get(['P']),
            'q'  => $get(['Q']),
            'dp' => $get(['DP', 'DMP1']),
            'dq' => $get(['DQ', 'DMQ1']),
            'qi' => $get(['QI', 'IQ', 'COEFF', 'INVERSEQ']),
        ];

        foreach ($components as $k => $v) {
            if ($v === null) {
                throw new RuntimeException("RSASK incompleto: falta componente {$k}.");
            }
        }

        $der = $this->derSequence([
            $this->derInteger("\x00"), // version
            $this->derInteger($this->b64($components['n'])),
            $this->derInteger($this->b64($components['e'])),
            $this->derInteger($this->b64($components['d'])),
            $this->derInteger($this->b64($components['p'])),
            $this->derInteger($this->b64($components['q'])),
            $this->derInteger($this->b64($components['dp'])),
            $this->derInteger($this->b64($components['dq'])),
            $this->derInteger($this->b64($components['qi'])),
        ]);

        $pem = "-----BEGIN RSA PRIVATE KEY-----\n"
            . chunk_split(base64_encode($der), 64, "\n")
            . "-----END RSA PRIVATE KEY-----\n";

        return $pem;
    }

    private function b64(string $value): string
    {
        $bin = base64_decode(preg_replace('/\s+/', '', $value) ?? '', true);
        if (!is_string($bin) || $bin === '') {
            throw new RuntimeException('RSASK contiene un componente base64 inválido.');
        }

        return $bin;
    }

    private function derSequence(array $parts): string
    {
        $body = implode('', $parts);

        return "\x30" . $this->derLen(strlen($body)) . $body;
    }

    private function derInteger(string $bytes): string
    {
        $bytes = ltrim($bytes, "\x00");
        if ($bytes === '') {
            $bytes = "\x00";
        }
        if ((ord($bytes[0]) & 0x80) !== 0) {
            $bytes = "\x00" . $bytes;
        }

        return "\x02" . $this->derLen(strlen($bytes)) . $bytes;
    }

    private function derLen(int $len): string
    {
        if ($len < 0x80) {
            return chr($len);
        }

        $hex = dechex($len);
        if ((strlen($hex) % 2) !== 0) {
            $hex = '0' . $hex;
        }
        $bin = hex2bin($hex);
        if ($bin === false) {
            throw new RuntimeException('No se pudo codificar largo DER.');
        }

        return chr(0x80 | strlen($bin)) . $bin;
    }

    private function findFirstChildByLocalName(DOMElement $parent, string $localName): ?DOMElement
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && strcasecmp($child->localName ?? '', $localName) === 0) {
                return $child;
            }
        }

        return null;
    }
}

