<?php

namespace App\Support;

use DOMDocument;

/**
 * Un sobre del SII (EnvioDTE) puede traer varios documentos.
 *
 * Implementos manda la 7100539 y la 7100545 en el mismo XML. Leído entero,
 * `//sii:Detalle` le pegaba a la primera las líneas de ambas, y la segunda
 * nunca se registraba. Aquí cada documento sale como un XML propio.
 */
final class DteEnvelope
{
    public const NAMESPACE = 'http://www.sii.cl/SiiDte';

    /**
     * Un XML por documento. Con uno solo (o si no se puede leer) devuelve el
     * original sin tocarlo: lo de siempre sigue exactamente igual.
     *
     * @return list<string>
     */
    public static function documents(string $xml): array
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_PARSEHUGE);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $dtes = $loaded ? $dom->getElementsByTagNameNS(self::NAMESPACE, 'DTE') : null;
        if (! $dtes || $dtes->length < 2) {
            return [$xml];
        }

        $documents = [];
        foreach ($dtes as $dte) {
            // importNode trae las declaraciones de espacio de nombres que el
            // documento heredaba del sobre, así que se lee igual que uno suelto.
            $single = new DOMDocument('1.0', 'UTF-8');
            $single->appendChild($single->importNode($dte, true));
            $documents[] = $single->saveXML();
        }

        return $documents;
    }
}
