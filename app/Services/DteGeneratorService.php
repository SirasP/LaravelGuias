<?php

namespace App\Services;

use DOMDocument;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DteGeneratorService
{
    /**
     * @param  array{
     *   movement_id:int,
     *   destinatario:string,
     *   receptor_email:?string,
     *   folio:?int,
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
        $folio = (int) ($data['folio'] ?? 0);
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

        $folio = $folio > 0 ? $folio : $this->buildTestFolio($movementId);
        $emisor = $this->mockEmisor();
        $receptor = $this->buildReceptor($destinatario, $receptorEmail);

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
            'RUTEmisor' => '76000000-0',
            'RznSoc' => 'EMPRESA DEMO SPA',
            'GiroEmis' => 'COMERCIALIZACION DE PRODUCTOS',
            'Acteco' => '469000',
            'DirOrigen' => 'AV. DEMO 123',
            'CmnaOrigen' => 'SANTIAGO',
            'CiudadOrigen' => 'SANTIAGO',
            'RutEnvia' => '76000000-0',
            'RutReceptor' => '60803000-K',
            'FchResol' => '2024-01-01',
            'NroResol' => '0',
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

    private function buildTestFolio(int $movementId): int
    {
        $base = 100000 + $movementId;
        if ($base > 99999999) {
            return (int) substr((string) $base, -8);
        }

        return $base;
    }

    private function formatDecimal(float $value, int $decimals): string
    {
        return number_format($value, $decimals, '.', '');
    }
}

