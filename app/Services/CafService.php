<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class CafService
{
    /**
     * @return array{
     *   tipo_dte:int,
     *   rut_emisor:string,
     *   folio_desde:int,
     *   folio_hasta:int,
     *   idk:string,
     *   rsapk_m:string,
     *   rsapk_e:string,
     *   frma:string,
     *   caf_xml:string,
     *   caf_path:string
     * }
     */
    public function loadForTipoDte(int $tipoDte, ?string $cafPath = null): array
    {
        $disk = (string) config('dte.caf_disk', 'local');
        $path = $cafPath ?: (string) config("dte.caf_paths.{$tipoDte}", '');

        if ($path === '' || !Storage::disk($disk)->exists($path)) {
            return $this->buildDevCafData($tipoDte, $path);
        }

        $raw = Storage::disk($disk)->get($path);
        if (!is_string($raw) || trim($raw) === '') {
            return $this->buildDevCafData($tipoDte, $path);
        }

        return $this->parseCafXml($raw, $tipoDte, $path);
    }

    /**
     * @param array{
     *   tipo_dte:int,
     *   rut_emisor:string,
     *   folio_desde:int,
     *   folio_hasta:int,
     *   idk:string,
     *   rsapk_m:string,
     *   rsapk_e:string,
     *   frma:string,
     *   caf_xml:string,
     *   caf_path:string
     * } $cafData
     */
    public function assertFolioInRange(int $folio, array $cafData): void
    {
        if ($folio < $cafData['folio_desde'] || $folio > $cafData['folio_hasta']) {
            throw new RuntimeException(
                "Folio {$folio} fuera de rango CAF ({$cafData['folio_desde']} - {$cafData['folio_hasta']})."
            );
        }
    }

    public function importCafNode(DOMDocument $targetDoc, string $cafXml): DOMElement
    {
        $cafDoc = new DOMDocument('1.0', 'UTF-8');
        if (!@$cafDoc->loadXML($cafXml)) {
            throw new RuntimeException('No se pudo parsear el nodo CAF para insertar TED.');
        }

        /** @var DOMElement|null $cafNode */
        $cafNode = $cafDoc->documentElement;
        if (!$cafNode || strtoupper($cafNode->localName ?? '') !== 'CAF') {
            throw new RuntimeException('El XML CAF no contiene nodo <CAF> válido.');
        }

        /** @var DOMElement $imported */
        $imported = $targetDoc->importNode($cafNode, true);

        return $imported;
    }

    /**
     * @return array{
     *   tipo_dte:int,
     *   rut_emisor:string,
     *   folio_desde:int,
     *   folio_hasta:int,
     *   idk:string,
     *   rsapk_m:string,
     *   rsapk_e:string,
     *   frma:string,
     *   caf_xml:string,
     *   caf_path:string,
     *   is_dev:bool
     * }
     */
    private function buildDevCafData(int $tipoDte, string $path): array
    {
        $rutEmisor = (string) config('dte.emisor.rut', '76000000-0');
        $folioDesde = (int) config('dte.caf_dev_folio_desde', 90000000);
        $folioHasta = (int) config('dte.caf_dev_folio_hasta', 99999999);
        if ($folioDesde <= 0 || $folioHasta <= 0 || $folioDesde > $folioHasta) {
            $folioDesde = 90000000;
            $folioHasta = 99999999;
        }

        $cafXml = '<CAF version="1.0">'
            . '<DA>'
            . '<RE>' . htmlspecialchars($rutEmisor, ENT_XML1) . '</RE>'
            . '<RS>SIN_CAF_DEV</RS>'
            . '<TD>' . $tipoDte . '</TD>'
            . '<RNG><D>' . $folioDesde . '</D><H>' . $folioHasta . '</H></RNG>'
            . '<FA>' . now()->toDateString() . '</FA>'
            . '<RSAPK><M>SIN_CAF_DEV</M><E>SIN_CAF_DEV</E></RSAPK>'
            . '<IDK>0</IDK>'
            . '</DA>'
            . '<FRMA algoritmo="SHA1withRSA">SIN_CAF_DEV</FRMA>'
            . '</CAF>';

        return [
            'tipo_dte' => $tipoDte,
            'rut_emisor' => $rutEmisor,
            'folio_desde' => $folioDesde,
            'folio_hasta' => $folioHasta,
            'idk' => '0',
            'rsapk_m' => 'SIN_CAF_DEV',
            'rsapk_e' => 'SIN_CAF_DEV',
            'frma' => 'SIN_CAF_DEV',
            'caf_xml' => $cafXml,
            'caf_path' => $path,
            'is_dev' => true,
        ];
    }

    /**
     * @return array{
     *   tipo_dte:int,
     *   rut_emisor:string,
     *   folio_desde:int,
     *   folio_hasta:int,
     *   idk:string,
     *   rsapk_m:string,
     *   rsapk_e:string,
     *   frma:string,
     *   caf_xml:string,
     *   caf_path:string
     * }
     */
    private function parseCafXml(string $xml, int $expectedTipoDte, string $path): array
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        if (!@$dom->loadXML($xml)) {
            throw new RuntimeException("CAF mal formado: {$path}");
        }

        $xp = new DOMXPath($dom);

        $cafNode = $this->queryNode($xp, '//*[local-name()="CAF"]');
        $daPrefix = '//*[local-name()="CAF"]/*[local-name()="DA"]';

        $tipoDte = (int) $this->queryValue($xp, $daPrefix . '/*[local-name()="TD"]');
        $rutEmisor = trim($this->queryValue($xp, $daPrefix . '/*[local-name()="RE"]'));
        $folioDesde = (int) $this->queryValue($xp, $daPrefix . '/*[local-name()="RNG"]/*[local-name()="D"]');
        $folioHasta = (int) $this->queryValue($xp, $daPrefix . '/*[local-name()="RNG"]/*[local-name()="H"]');
        $idk = trim($this->queryValue($xp, $daPrefix . '/*[local-name()="IDK"]'));
        $rsapkM = trim($this->queryValue($xp, $daPrefix . '/*[local-name()="RSAPK"]/*[local-name()="M"]'));
        $rsapkE = trim($this->queryValue($xp, $daPrefix . '/*[local-name()="RSAPK"]/*[local-name()="E"]'));
        $frma = trim($this->queryValue($xp, '//*[local-name()="CAF"]/*[local-name()="FRMA"]'));

        if ($tipoDte !== $expectedTipoDte) {
            throw new RuntimeException("CAF {$path} no corresponde a TipoDTE {$expectedTipoDte}.");
        }
        if ($rutEmisor === '' || $folioDesde <= 0 || $folioHasta <= 0 || $folioDesde > $folioHasta) {
            throw new RuntimeException("CAF {$path} tiene datos incompletos de emisor/rango.");
        }
        if ($rsapkM === '' || $rsapkE === '' || $idk === '') {
            throw new RuntimeException("CAF {$path} no contiene RSAPK/IDK válidos.");
        }

        return [
            'tipo_dte' => $tipoDte,
            'rut_emisor' => $rutEmisor,
            'folio_desde' => $folioDesde,
            'folio_hasta' => $folioHasta,
            'idk' => $idk,
            'rsapk_m' => $rsapkM,
            'rsapk_e' => $rsapkE,
            'frma' => $frma,
            'caf_xml' => $dom->saveXML($cafNode) ?: '',
            'caf_path' => $path,
            'is_dev' => false,
        ];
    }

    private function queryValue(DOMXPath $xp, string $expr): string
    {
        $nodes = $xp->query($expr);
        if (!$nodes || $nodes->length === 0) {
            return '';
        }

        return trim((string) $nodes->item(0)?->textContent);
    }

    private function queryNode(DOMXPath $xp, string $expr): DOMElement
    {
        $nodes = $xp->query($expr);
        $node = $nodes?->item(0);
        if (!$node instanceof DOMElement) {
            throw new RuntimeException('No se encontró nodo CAF en archivo autorizado.');
        }

        return $node;
    }
}
