<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DteGeneratorService
{
    public function __construct(
        private readonly CafService $cafService,
        private readonly XmlDsigSignerService $xmlDsigSignerService,
        private readonly TedSignerService $tedSignerService
    ) {
    }

    /**
     * @param  array{
     *   movement_id:int,
     *   destinatario:string,
     *   receptor_email:?string,
     *   folio:?int,
     *   caf_path:?string,
     *   items:array<int,array{
     *     product_id:int,
     *     nombre:string,
     *     quantity:float,
     *     unit_price:float
     *   }>
     * } $data
     */
    public function generateFacturaXmlForExit(array $data): string
    {
        $movementId = (int) ($data['movement_id'] ?? 0);
        $destinatario = trim((string) ($data['destinatario'] ?? ''));
        $receptorEmail = trim((string) ($data['receptor_email'] ?? ''));
        $folioInput = (int) ($data['folio'] ?? 0);
        $cafPath = isset($data['caf_path']) ? trim((string) $data['caf_path']) : null;
        $items = $data['items'] ?? [];

        if ($movementId <= 0) {
            throw new RuntimeException('movement_id inválido para generar DTE.');
        }
        if ($destinatario === '') {
            throw new RuntimeException('Destinatario inválido para generar DTE.');
        }
        if (empty($items)) {
            throw new RuntimeException('No hay ítems para generar DTE.');
        }

        $emisor = $this->mockEmisor();
        $receptor = $this->buildReceptor($destinatario, $receptorEmail);
        $caf = $this->cafService->loadForTipoDte(33, $cafPath);

        $isDevCaf = (bool) ($caf['is_dev'] ?? false);

        if (!$isDevCaf && strcasecmp($caf['rut_emisor'], (string) $emisor['RUTEmisor']) !== 0) {
            throw new RuntimeException(
                "RUT emisor del CAF ({$caf['rut_emisor']}) no coincide con RUT emisor del DTE ({$emisor['RUTEmisor']})."
            );
        }

        if ($isDevCaf) {
            $folio = $folioInput > 0 ? $folioInput : $this->buildDevFolio($movementId);
        } else {
            $folio = $folioInput > 0
                ? $folioInput
                : $this->buildCafBackedFolio($movementId, $caf['folio_desde'], $caf['folio_hasta']);
            $this->cafService->assertFolioInRange($folio, $caf);
        }

        $lines = [];
        $neto = 0;

        foreach ($items as $idx => $item) {
            $qty = (float) ($item['quantity'] ?? 0);
            $price = (float) ($item['unit_price'] ?? 0);
            $name = trim((string) ($item['nombre'] ?? ''));

            if ($qty <= 0 || $name === '') {
                continue;
            }

            $montoItem = (int) round($qty * $price, 0);
            $neto += $montoItem;

            $lines[] = [
                'NroLinDet' => $idx + 1,
                'NmbItem' => $name,
                'QtyItem' => $qty,
                'PrcItem' => $price,
                'MontoItem' => $montoItem,
            ];
        }

        if (empty($lines)) {
            throw new RuntimeException('No se pudieron construir líneas válidas para el DTE.');
        }

        $iva = (int) round($neto * 0.19, 0);
        $total = $neto + $iva;

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $envio = $doc->createElement('EnvioDTE');
        $envio->setAttribute('version', '1.0');
        $envio->setAttribute('xmlns', 'http://www.sii.cl/SiiDte');
        $envio->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $envio->setAttribute('xsi:schemaLocation', 'http://www.sii.cl/SiiDte EnvioDTE_v10.xsd');
        $doc->appendChild($envio);

        $setDte = $doc->createElement('SetDTE');
        $setDte->setAttribute('ID', 'SetDoc_' . $movementId);
        $envio->appendChild($setDte);

        $caratula = $doc->createElement('Caratula');
        $caratula->setAttribute('version', '1.0');
        $setDte->appendChild($caratula);
        $caratula->appendChild($doc->createElement('RutEmisor', $emisor['RUTEmisor']));
        $caratula->appendChild($doc->createElement('RutEnvia', $emisor['RutEnvia']));
        $caratula->appendChild($doc->createElement('RutReceptor', $emisor['RutReceptor']));
        $caratula->appendChild($doc->createElement('FchResol', $emisor['FchResol']));
        $caratula->appendChild($doc->createElement('NroResol', $emisor['NroResol']));
        $caratula->appendChild($doc->createElement('TmstFirmaEnv', now()->format('Y-m-d\TH:i:s')));

        $subTot = $doc->createElement('SubTotDTE');
        $subTot->appendChild($doc->createElement('TpoDTE', '33'));
        $subTot->appendChild($doc->createElement('NroDTE', '1'));
        $caratula->appendChild($subTot);

        $dte = $doc->createElement('DTE');
        $dte->setAttribute('version', '1.0');
        $setDte->appendChild($dte);

        $documento = $doc->createElement('Documento');
        $documento->setAttribute('ID', 'F33T' . $folio);
        $dte->appendChild($documento);

        $encabezado = $doc->createElement('Encabezado');
        $documento->appendChild($encabezado);

        $idDoc = $doc->createElement('IdDoc');
        $encabezado->appendChild($idDoc);
        $idDoc->appendChild($doc->createElement('TipoDTE', '33'));
        $idDoc->appendChild($doc->createElement('Folio', (string) $folio));
        $idDoc->appendChild($doc->createElement('FchEmis', now()->toDateString()));

        $emisorNode = $doc->createElement('Emisor');
        $encabezado->appendChild($emisorNode);
        $emisorNode->appendChild($doc->createElement('RUTEmisor', $emisor['RUTEmisor']));
        $emisorNode->appendChild($doc->createElement('RznSoc', $emisor['RznSoc']));
        $emisorNode->appendChild($doc->createElement('GiroEmis', $emisor['GiroEmis']));
        $emisorNode->appendChild($doc->createElement('Acteco', $emisor['Acteco']));
        $emisorNode->appendChild($doc->createElement('DirOrigen', $emisor['DirOrigen']));
        $emisorNode->appendChild($doc->createElement('CmnaOrigen', $emisor['CmnaOrigen']));
        $emisorNode->appendChild($doc->createElement('CiudadOrigen', $emisor['CiudadOrigen']));

        $receptorNode = $doc->createElement('Receptor');
        $encabezado->appendChild($receptorNode);
        $receptorNode->appendChild($doc->createElement('RUTRecep', $receptor['RUTRecep']));
        $receptorNode->appendChild($doc->createElement('RznSocRecep', $receptor['RznSocRecep']));
        $receptorNode->appendChild($doc->createElement('GiroRecep', $receptor['GiroRecep']));
        $receptorNode->appendChild($doc->createElement('DirRecep', $receptor['DirRecep']));
        $receptorNode->appendChild($doc->createElement('CmnaRecep', $receptor['CmnaRecep']));
        $receptorNode->appendChild($doc->createElement('CiudadRecep', $receptor['CiudadRecep']));
        if ($receptor['CorreoRecep'] !== '') {
            $receptorNode->appendChild($doc->createElement('CorreoRecep', $receptor['CorreoRecep']));
        }

        $totales = $doc->createElement('Totales');
        $encabezado->appendChild($totales);
        $totales->appendChild($doc->createElement('MntNeto', (string) $neto));
        $totales->appendChild($doc->createElement('TasaIVA', '19'));
        $totales->appendChild($doc->createElement('IVA', (string) $iva));
        $totales->appendChild($doc->createElement('MntTotal', (string) $total));

        foreach ($lines as $line) {
            $detalle = $doc->createElement('Detalle');
            $detalle->appendChild($doc->createElement('NroLinDet', (string) $line['NroLinDet']));
            $detalle->appendChild($doc->createElement('NmbItem', $line['NmbItem']));
            $detalle->appendChild($doc->createElement('QtyItem', $this->formatDecimal($line['QtyItem'], 4)));
            $detalle->appendChild($doc->createElement('PrcItem', $this->formatDecimal($line['PrcItem'], 4)));
            $detalle->appendChild($doc->createElement('MontoItem', (string) $line['MontoItem']));
            $documento->appendChild($detalle);
        }

        $ted = $this->buildTedNode(
            $doc,
            $caf,
            $folio,
            $emisor['RUTEmisor'],
            $receptor['RUTRecep'],
            $receptor['RznSocRecep'],
            $total,
            $lines
        );
        if (!$isDevCaf) {
            $this->tedSignerService->timbrarTed($ted, $caf);
        } else {
            $frmt = $this->findFirstChildByLocalName($ted, 'FRMT');
            if ($frmt) {
                $frmt->nodeValue = 'SIN_CAF_DEV';
                $frmt->setAttribute('algoritmo', 'SHA1withRSA');
            }
        }
        $documento->appendChild($ted);
        if ($isDevCaf) {
            $documento->appendChild($doc->createComment('SIN_CAF_DEV'));
        }
        $documento->appendChild($doc->createElement('TmstFirma', now()->format('Y-m-d\TH:i:s')));

        $this->xmlDsigSignerService->signDocumentoById($doc, 'F33T' . $folio);

        $xmlUtf8 = $doc->saveXML();
        if (!is_string($xmlUtf8) || $xmlUtf8 === '') {
            throw new RuntimeException('No se pudo serializar XML DTE.');
        }

        $xmlIso = mb_convert_encoding($xmlUtf8, 'ISO-8859-1', 'UTF-8');
        $xmlIso = preg_replace('/^<\?xml[^>]+\?>/i', '<?xml version="1.0" encoding="ISO-8859-1"?>', $xmlIso) ?? $xmlIso;

        $relativePath = 'dte/factura_venta_' . $movementId . '_' . now()->format('Ymd_His') . '.xml';
        Storage::disk('local')->put($relativePath, $xmlIso);

        return $relativePath;
    }

    private function mockEmisor(): array
    {
        return [
            'RUTEmisor' => (string) config('dte.emisor.rut', '76000000-0'),
            'RznSoc' => (string) config('dte.emisor.razon_social', 'EMPRESA DEMO SPA'),
            'GiroEmis' => (string) config('dte.emisor.giro', 'COMERCIALIZACION DE PRODUCTOS'),
            'Acteco' => (string) config('dte.emisor.acteco', '469000'),
            'DirOrigen' => (string) config('dte.emisor.direccion', 'AV. DEMO 123'),
            'CmnaOrigen' => (string) config('dte.emisor.comuna', 'SANTIAGO'),
            'CiudadOrigen' => (string) config('dte.emisor.ciudad', 'SANTIAGO'),
            'RutEnvia' => (string) config('dte.emisor.rut_envia', '76000000-0'),
            'RutReceptor' => (string) config('dte.envio.rut_receptor', '60803000-K'),
            'FchResol' => (string) config('dte.envio.fecha_resolucion', '2024-01-01'),
            'NroResol' => (string) config('dte.envio.numero_resolucion', '0'),
        ];
    }

    private function buildReceptor(string $destinatario, string $email): array
    {
        return [
            'RUTRecep' => '66666666-6',
            'RznSocRecep' => mb_strtoupper($destinatario, 'UTF-8'),
            'GiroRecep' => 'CLIENTE',
            'DirRecep' => 'SIN DIRECCION',
            'CmnaRecep' => 'SANTIAGO',
            'CiudadRecep' => 'SANTIAGO',
            'CorreoRecep' => $email,
        ];
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
     * } $caf
     * @param array<int,array{NroLinDet:int,NmbItem:string,QtyItem:float,PrcItem:float,MontoItem:int}> $lines
     */
    private function buildTedNode(
        DOMDocument $doc,
        array $caf,
        int $folio,
        string $rutEmisor,
        string $rutReceptor,
        string $razonSocialReceptor,
        int $montoTotal,
        array $lines
    ): DOMElement {
        $ted = $doc->createElement('TED');
        $ted->setAttribute('version', '1.0');

        $dd = $doc->createElement('DD');
        $dd->appendChild($doc->createElement('RE', $rutEmisor));
        $dd->appendChild($doc->createElement('TD', '33'));
        $dd->appendChild($doc->createElement('F', (string) $folio));
        $dd->appendChild($doc->createElement('FE', now()->toDateString()));
        $dd->appendChild($doc->createElement('RR', $rutReceptor));
        $dd->appendChild($doc->createElement('RSR', $this->limitTedText($razonSocialReceptor, 40)));
        $dd->appendChild($doc->createElement('MNT', (string) $montoTotal));
        $dd->appendChild($doc->createElement('IT1', $this->limitTedText((string) ($lines[0]['NmbItem'] ?? ''), 40)));

        $dd->appendChild($this->cafService->importCafNode($doc, $caf['caf_xml']));
        $dd->appendChild($doc->createElement('TSTED', now()->format('Y-m-d\TH:i:s')));

        $ted->appendChild($dd);

        $frmt = $doc->createElement('FRMT', 'PENDIENTE_FIRMA');
        $frmt->setAttribute('algoritmo', 'SHA1withRSA');
        $ted->appendChild($frmt);

        return $ted;
    }

    private function buildCafBackedFolio(int $movementId, int $folioDesde, int $folioHasta): int
    {
        $rangeSize = ($folioHasta - $folioDesde) + 1;
        if ($rangeSize <= 0) {
            throw new RuntimeException('Rango CAF inválido para cálculo de folio.');
        }

        $offset = max($movementId - 1, 0) % $rangeSize;

        return $folioDesde + $offset;
    }

    private function buildDevFolio(int $movementId): int
    {
        $base = 90000000 + $movementId;
        if ($base > 99999999) {
            return (int) substr((string) $base, -8);
        }

        return $base;
    }

    private function formatDecimal(float $value, int $decimals): string
    {
        return number_format($value, $decimals, '.', '');
    }

    private function limitTedText(string $value, int $maxLen): string
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $value) ?? '');
        if ($normalized === '') {
            return '';
        }

        return mb_substr($normalized, 0, $maxLen, 'UTF-8');
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
