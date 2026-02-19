<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Google\Client as GoogleClient;
use Google\Service\Gmail;
use Google\Service\Gmail\ModifyMessageRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class GmailLeerXml extends Command
{
    protected $signature   = 'gmail:leer-xml
                            {--all : Leer tambien correos ya leidos (has:attachment)}
                            {--reprocess : Reprocesar tambien mensajes ya registrados en gmail_imports}
                            {--fuel-to-dte-only : Temporal: guardar tambien Diesel/Gasolina en DTE y NO tocar FuelControl}
                            {--only-messages= : Lista CSV de gmail_message_id para procesar solo esos mensajes}';
    protected $description = 'Lee correos Gmail, procesa XML DTE y controla inventario';

    public function handle(): int
    {
        /* ─────────────────────────────────────────
         | 1. CONEXIÓN BD
         ───────────────────────────────────────── */
        $db = DB::connection('fuelcontrol');

        /* ─────────────────────────────────────────
         | 2. CLIENTE GMAIL  (solo token guardado)
         |    Si no hay token → error claro, sin
         |    pedir input por consola.
         ───────────────────────────────────────── */
        $tokenPath = storage_path('app/gmail/token.json');

        if (!file_exists($tokenPath)) {
            $this->error('No hay token de Gmail guardado.');
            $this->line('Visita la sección Gmail DTE en la aplicación para autorizar el acceso.');
            return Command::FAILURE;
        }

        $client = new GoogleClient();
        $client->setApplicationName('FuelControl Gmail Import');
        $client->setScopes([Gmail::GMAIL_MODIFY]);
        $client->setAuthConfig(storage_path('app/gmail/credentials.json'));
        $client->setAccessType('offline');

        $client->setAccessToken(json_decode(file_get_contents($tokenPath), true));

        // Refrescar si expiró
        if ($client->isAccessTokenExpired()) {
            if (!$client->getRefreshToken()) {
                $this->error('El token expiró y no hay refresh token.');
                $this->line('Reconecta Gmail desde la aplicación web.');
                return Command::FAILURE;
            }

            $this->line('Renovando token…');
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            file_put_contents($tokenPath, json_encode($client->getAccessToken()));
            $this->info('Token renovado.');
        }

        $service = new Gmail($client);

        /* ─────────────────────────────────────────
         | 3. CORREOS NO LEÍDOS CON ADJUNTO
         ───────────────────────────────────────── */
        $gmailQuery = $this->option('all') ? 'has:attachment' : 'has:attachment is:unread';
        $reprocess = (bool) $this->option('reprocess');
        $fuelToDteOnly = (bool) $this->option('fuel-to-dte-only');
        $onlyMessagesCsv = trim((string) $this->option('only-messages', ''));
        $onlyMessages = collect(explode(',', $onlyMessagesCsv))
            ->map(fn ($id) => trim((string) $id))
            ->filter(fn ($id) => $id !== '')
            ->values();
        $onlyMessagesEnabled = $onlyMessages->isNotEmpty();

        $this->line($this->option('all')
            ? 'Modo forzado: leyendo correos leidos y no leidos con adjuntos.'
            : 'Modo normal: leyendo solo correos no leidos con adjuntos.');
        $this->line($reprocess
            ? 'Modo reproceso: se incluiran mensajes ya registrados.'
            : 'Modo normal de historial: mensajes ya registrados se omiten.');
        $this->line($fuelToDteOnly
            ? 'Modo temporal fuel->DTE: Diesel/Gasolina se guardan en DTE y se omite FuelControl.'
            : 'Modo normal combustible: Diesel/Gasolina siguen flujo FuelControl.');
        if ($onlyMessagesEnabled) {
            $this->line('Filtro only-messages activo: ' . $onlyMessages->count() . ' mensaje(s).');
        }

        $pageToken = null;
        $totalFound = 0;
        $totalProcessed = 0;

        do {
            $listParams = [
                'q' => $gmailQuery,
                'maxResults' => 500,
            ];
            if ($pageToken) {
                $listParams['pageToken'] = $pageToken;
            }

            $messages = $service->users_messages->listUsersMessages('me', $listParams);
            $batchMessages = $messages->getMessages() ?? [];
            $totalFound += count($batchMessages);

            foreach ($batchMessages as $msg) {
                if ($onlyMessagesEnabled && !$onlyMessages->contains((string) $msg->getId())) {
                    continue;
                }

                /* ─────────────────────────────────────
                 | 4. EVITAR REPROCESAR
                 ───────────────────────────────────── */
                $alreadyProcessed = $db->table('gmail_imports')
                    ->where('gmail_message_id', $msg->getId())
                    ->exists();

                if ($alreadyProcessed && !$reprocess) {
                    continue;
                }

                if ($alreadyProcessed) {
                    $db->table('gmail_imports')
                        ->where('gmail_message_id', $msg->getId())
                        ->update([
                            'processed_at' => now(),
                            'updated_at' => now(),
                        ]);
                } else {
                    $db->table('gmail_imports')->insert([
                        'gmail_message_id' => $msg->getId(),
                        'processed_at'     => now(),
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ]);
                }

                $message = $service->users_messages->get('me', $msg->getId());
                $parts   = $message->getPayload()->getParts() ?? [];

                foreach ($parts as $part) {

                    if (!$part->getFilename() || !str_ends_with(strtolower($part->getFilename()), '.xml')) {
                        continue;
                    }

                    $this->info("📎 XML encontrado: {$part->getFilename()}");

                    /* ─────────────────────────────────
                     | 5. DESCARGAR Y PARSEAR XML
                     ─────────────────────────────────  */
                    $attachment = $service->users_messages_attachments->get(
                        'me', $msg->getId(), $part->getBody()->getAttachmentId()
                    );

                    $rawData = (string) $attachment->getData();
                    $b64 = strtr($rawData, '-_', '+/');
                    $pad = strlen($b64) % 4;
                    if ($pad > 0) {
                        $b64 .= str_repeat('=', 4 - $pad);
                    }

                    $contenidoXml = base64_decode($b64, true);
                    if ($contenidoXml === false || trim($contenidoXml) === '') {
                        $this->error("❌ Adjunto no decodificable como XML: {$part->getFilename()}");
                        continue;
                    }

                    // Normaliza XML a UTF-8 para evitar truncamiento en DB (ej. XML ISO-8859-1 con Ñ).
                    $contenidoXml = $this->normalizeXmlToUtf8($contenidoXml);

                    libxml_use_internal_errors(true);
                    $xml = simplexml_load_string($contenidoXml);
                    if (!$xml) {
                        $err = libxml_get_last_error();
                        $detail = $err ? trim($err->message) : 'contenido no XML';
                        libxml_clear_errors();
                        $this->error("❌ XML inválido: {$part->getFilename()} ({$detail})");
                        continue;
                    }
                    libxml_clear_errors();

                    $xml->registerXPathNamespace('sii', 'http://www.sii.cl/SiiDte');

                    Storage::disk('local')->put('xml/' . $part->getFilename(), $contenidoXml);

                    /* ─────────────────────────────────
                     | 6. FECHA EMISIÓN (rango 5 días)
                     ─────────────────────────────────  */
                    $fch = $xml->xpath('//sii:Encabezado/sii:IdDoc/sii:FchEmis')[0] ?? null;

                    if (!$fch) {
                        $this->error("❌ No se pudo leer FchEmis en {$part->getFilename()}");
                        continue;
                    }

                    $fechaEmision = Carbon::parse((string) $fch);
                    $afectaStock  = $fechaEmision->greaterThanOrEqualTo(now()->subDays(5));

                    /* ─────────────────────────────────
                     | 7. DETECTAR LEY 18.502 (vehículo)
                     ─────────────────────────────────  */
                    $usaVehiculo = false;

                    foreach ($xml->xpath('//sii:Referencia') as $ref) {
                        $razon = strtoupper((string) ($ref->RazonRef ?? ''));
                        if (str_contains($razon, 'LEY 18.502') || str_contains($razon, 'VEHICUL')) {
                            $usaVehiculo = true;
                            break;
                        }
                    }

                    /* ─────────────────────────────────
                     | 8. PROCESAR DETALLES
                     ─────────────────────────────────  */
                    $detalleRows = $xml->xpath('//sii:Detalle') ?? [];
                    $fuelDetails = [];

                    foreach ($detalleRows as $detalle) {

                        $nombre   = strtoupper((string) $detalle->NmbItem);
                        $cantidad = (float) $detalle->QtyItem;

                        $productoNombre = match (true) {
                            str_contains($nombre, 'DIESEL')   => 'Diesel',
                            str_contains($nombre, 'GASOLINA') => 'Gasolina',
                            default                            => null,
                        };

                        if (!$productoNombre || $cantidad <= 0) {
                            continue;
                        }

                        $fuelDetails[] = [
                            'producto' => $productoNombre,
                            'cantidad' => $cantidad,
                            'filename' => $part->getFilename(),
                        ];
                    }

                    // ✅ Sin combustible, o en modo temporal fuel->DTE: guardar en módulo DTE.
                    if (count($fuelDetails) === 0 || $fuelToDteOnly) {
                        $saved = $this->persistNonFuelDte(
                            $db,
                            $msg->getId(),
                            (string) $part->getFilename(),
                            $xml,
                            $fechaEmision,
                            $reprocess,
                            $contenidoXml
                        );

                        if ($saved) {
                            $this->info($fuelToDteOnly && count($fuelDetails) > 0
                                ? "🧾 DTE combustible guardado temporalmente en DTE: {$part->getFilename()}"
                                : "🧾 DTE no combustible guardado: {$part->getFilename()}");
                        } else {
                            $this->line($fuelToDteOnly && count($fuelDetails) > 0
                                ? "⏭ DTE combustible ya existía en DTE: {$part->getFilename()}"
                                : "⏭ DTE no combustible ya existía: {$part->getFilename()}");
                        }
                        continue;
                    }

                    foreach ($fuelDetails as $fuel) {

                        $productoNombre = $fuel['producto'];
                        $cantidad = (float) $fuel['cantidad'];
                        $filename = $fuel['filename'];

                        $this->line("⛽ {$productoNombre} → {$cantidad} L");

                        $hash = hash('sha256', implode('|', [
                            $msg->getId(), $filename, $productoNombre, $cantidad
                        ]));

                        if ($db->table('movimientos')->where('hash_unico', $hash)->exists()) {
                            $this->line("⏭ Ya procesado, omitiendo.");
                            continue;
                        }

                        $producto = $db->table('productos')->where('nombre', $productoNombre)->first();
                        if (!$producto) {
                            $this->warn("⚠ Producto '{$productoNombre}' no encontrado en BD.");
                            continue;
                        }

                        /* ─── Actualizar stock ─────────── */
                        if (!$usaVehiculo && $afectaStock) {
                            $db->table('productos')
                                ->where('id', $producto->id)
                                ->increment('cantidad', $cantidad);
                            $this->info("📦 Stock actualizado: +{$cantidad} L de {$productoNombre}");
                            $estado = 'aprobado';
                        } else {
                            $this->warn("🚫 DTE vehicular → Requiere aprobación manual.");
                            $estado = 'pendiente';
                        }

                        /* ─── Insertar movimiento ──────── */
                        $movimientoId = $db->table('movimientos')->insertGetId([
                            'producto_id'      => $producto->id,
                            'vehiculo_id'      => null,
                            'cantidad'         => $cantidad,
                            'tipo'             => $usaVehiculo ? 'vehiculo' : 'entrada',
                            'origen'           => $usaVehiculo ? 'xml_vehiculo' : 'xml_estanque',
                            'referencia'       => $filename,
                            'requiere_revision'=> $usaVehiculo ? 1 : 0,
                            'estado'           => $estado,
                            'xml_path'         => $filename,
                            'usuario'          => 'gmail',
                            'fecha_movimiento' => $fechaEmision,
                            'hash_unico'       => $hash,
                            'created_at'       => now(),
                            'updated_at'       => now(),
                        ]);

                        /* ─── Notificaciones ───────────── */
                        $notificacionId = DB::connection('fuelcontrol')->table('notificaciones')->insertGetId([
                            'tipo'         => $usaVehiculo ? 'xml_revision' : 'xml_entrada',
                            'titulo'       => $usaVehiculo
                                ? 'XML requiere revisión'
                                : "Ingreso de {$productoNombre}",
                            'movimiento_id'=> $movimientoId,
                            'mensaje'      => $usaVehiculo
                                ? "{$cantidad} L detectados como posible carga vehicular (Ley 18.502)"
                                : "+{$cantidad} L desde XML ({$filename})",
                            'created_at'   => now(),
                            'updated_at'   => now(),
                        ]);

                        $users = DB::table('users')->pluck('id');
                        foreach ($users as $userId) {
                            DB::connection('fuelcontrol')->table('notificacion_usuarios')->insert([
                                'notificacion_id' => $notificacionId,
                                'user_id'         => $userId,
                                'leido'           => 0,
                                'created_at'      => now(),
                                'updated_at'      => now(),
                            ]);
                        }

                        /* ─── WebSocket notify ─────────── */
                        try {
                            Http::timeout(3)->post('http://127.0.0.1:3001/notify', [
                                'type'    => $usaVehiculo ? 'xml_vehiculo' : 'xml_entrada',
                                'titulo'  => $usaVehiculo ? 'XML de consumo vehicular' : "Ingreso de {$productoNombre}",
                                'mensaje' => $usaVehiculo
                                    ? "{$cantidad} L (Ley 18.502)"
                                    : "+{$cantidad} L desde XML ({$filename})",
                                'producto' => $productoNombre,  // 🔥 Diesel o Gasolina
                                'cantidad' => $cantidad,
                                'movimiento_id' => $movimientoId,
                                'timestamp' => now()->toIso8601String(),
                            ]);
                        } catch (\Throwable) {
                            // El servidor de notificaciones no está disponible — ignorar
                        }

                        /* ─── Firebase Push Notifications ─── */
                        $this->enviarNotificacionPush(
                            titulo: $usaVehiculo ? '🚗 XML requiere revisión' : "⛽ Ingreso de {$productoNombre}",
                            mensaje: $usaVehiculo
                                ? "{$cantidad} L detectados (Ley 18.502)"
                                : "+{$cantidad} L agregados al stock",
                            producto: $productoNombre,
                            cantidad: $cantidad,
                            movimientoId: $movimientoId,
                            tipo: $usaVehiculo ? 'xml_revision' : 'xml_entrada'
                        );
                    }
                }

                /* ─────────────────────────────────────
                 | 9. MARCAR CORREO COMO LEÍDO
                 ───────────────────────────────────── */
                $modify = new ModifyMessageRequest();
                $modify->setRemoveLabelIds(['UNREAD']);
                $service->users_messages->modify('me', $msg->getId(), $modify);

                $this->line("✉️ Correo {$msg->getId()} marcado como leído.");
                $totalProcessed++;
            }

            $pageToken = $messages->getNextPageToken();
        } while (!empty($pageToken));

        if ($totalFound === 0) {
            $this->info('No hay correos nuevos.');
            return Command::SUCCESS;
        }
        $this->info("✔ Proceso completo. Mensajes listados: {$totalFound}. Mensajes procesados: {$totalProcessed}.");
        return Command::SUCCESS;
    }

    private function normalizeXmlToUtf8(string $xmlContent): string
    {
        $xmlContent = ltrim($xmlContent, "\xEF\xBB\xBF \t\r\n");

        $declaredEncoding = null;
        if (preg_match('/<\?xml[^>]*encoding=["\']([^"\']+)["\']/i', $xmlContent, $m)) {
            $declaredEncoding = strtoupper(trim((string) $m[1]));
        }

        $normalized = $xmlContent;

        if ($declaredEncoding && $declaredEncoding !== 'UTF-8') {
            $converted = @mb_convert_encoding($normalized, 'UTF-8', $declaredEncoding . ',ISO-8859-1,Windows-1252,UTF-8');
            if (is_string($converted) && $converted !== '') {
                $normalized = $converted;
            }
        } elseif (!mb_check_encoding($normalized, 'UTF-8')) {
            $converted = @mb_convert_encoding($normalized, 'UTF-8', 'ISO-8859-1,Windows-1252,UTF-8');
            if (is_string($converted) && $converted !== '') {
                $normalized = $converted;
            }
        }

        $normalized = preg_replace(
            '/<\?xml\s+version=["\']1\.0["\']\s+encoding=["\'][^"\']+["\']\s*\?>/i',
            '<?xml version="1.0" encoding="UTF-8"?>',
            $normalized,
            1
        ) ?? $normalized;

        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $normalized);
        if ($clean !== false && $clean !== '') {
            $normalized = $clean;
        }

        return $normalized;
    }

    /**
     * Guardar DTE no combustible para módulo administrativo.
     */
    private function persistNonFuelDte($db, string $messageId, string $filename, \SimpleXMLElement $xml, Carbon $fechaEmision, bool $refreshExisting = false, ?string $xmlRaw = null): bool
    {
        $get = function (string $path) use ($xml): ?string {
            $node = $xml->xpath($path)[0] ?? null;
            if (!$node) {
                return null;
            }
            $val = trim((string) $node);
            return $val === '' ? null : $val;
        };

        $tipoDte = (int) ($get('//sii:Encabezado/sii:IdDoc/sii:TipoDTE') ?? 0);
        $folio = $get('//sii:Encabezado/sii:IdDoc/sii:Folio');
        $proveedorRut = $get('//sii:Encabezado/sii:Emisor/sii:RUTEmisor');
        $proveedorNombre = $get('//sii:Encabezado/sii:Emisor/sii:RznSoc')
            ?? $get('//sii:Encabezado/sii:Emisor/sii:RznSocEmisor');

        $fechaFactura = $get('//sii:Encabezado/sii:IdDoc/sii:FchEmis');
        $fechaContable = $fechaFactura;
        $fechaVencimiento = $get('//sii:Encabezado/sii:IdDoc/sii:FchVenc');

        $referencia = $get('//sii:Referencia/sii:NroRef') ?? $get('//sii:Referencia/sii:RazonRef');

        $montoNeto = (float) ($get('//sii:Encabezado/sii:Totales/sii:MntNeto') ?? 0);
        $montoIva = (float) ($get('//sii:Encabezado/sii:Totales/sii:IVA') ?? 0);
        $tasaIvaDoc = (float) ($get('//sii:Encabezado/sii:Totales/sii:TasaIVA') ?? 0);
        $montoTotal = (float) ($get('//sii:Encabezado/sii:Totales/sii:MntTotal') ?? 0);

        $hash = hash('sha256', implode('|', [
            $messageId,
            $filename,
            (string) $tipoDte,
            (string) $folio,
            (string) $proveedorRut,
            (string) $montoTotal,
        ]));

        if ($db->table('gmail_dte_documents')->where('hash_unico', $hash)->exists()) {
            if ($refreshExisting) {
                $existing = $db->table('gmail_dte_documents')->where('hash_unico', $hash)->first();
                if ($existing) {
                    $db->table('gmail_dte_documents')
                        ->where('id', $existing->id)
                        ->update([
                            'xml_raw' => $xmlRaw,
                            'updated_at' => now(),
                        ]);
                    $this->updateExistingDocumentLineTaxes($db, (int) $existing->id, $xml, $tasaIvaDoc);
                }
            }
            return false;
        }

        $docId = $db->table('gmail_dte_documents')->insertGetId([
            'gmail_message_id' => $messageId,
            'xml_filename' => $filename,
            'xml_path' => 'xml/' . $filename,
            'xml_raw' => $xmlRaw,
            'hash_unico' => $hash,
            'tipo_dte' => $tipoDte ?: null,
            'folio' => $folio,
            'proveedor_rut' => $proveedorRut,
            'proveedor_nombre' => $proveedorNombre,
            'fecha_factura' => $fechaFactura ? Carbon::parse($fechaFactura)->toDateString() : $fechaEmision->toDateString(),
            'fecha_contable' => $fechaContable ? Carbon::parse($fechaContable)->toDateString() : $fechaEmision->toDateString(),
            'fecha_vencimiento' => $fechaVencimiento ? Carbon::parse($fechaVencimiento)->toDateString() : null,
            'referencia' => $referencia,
            'monto_neto' => $montoNeto,
            'monto_iva' => $montoIva,
            'monto_total' => $montoTotal,
            'payment_status' => 'sin_pagar',
            'workflow_status' => 'aceptado',
            'inventory_status' => 'pendiente',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $lines = $xml->xpath('//sii:Detalle') ?? [];
        foreach ($lines as $line) {
            $nroLinea = (int) ((string) ($line->NroLinDet ?? 0));
            $esExento = ((string) ($line->IndExe ?? '')) === '1';
            $impuestoCodigo = trim((string) ($line->CodImpAdic ?? '')) ?: null;
            $impuestoTasa = null;

            if (isset($line->ImptoReten) && isset($line->ImptoReten->TasaImp)) {
                $impuestoTasa = (float) ((string) $line->ImptoReten->TasaImp);
            } elseif (!$esExento && $tasaIvaDoc > 0) {
                $impuestoTasa = $tasaIvaDoc;
            }

            $impuestoLabel = null;
            if ($esExento) {
                $impuestoLabel = 'Exento';
            } elseif ($impuestoCodigo) {
                $impuestoLabel = 'Imp. adic. ' . $impuestoCodigo . ($impuestoTasa !== null ? ' (' . rtrim(rtrim((string) $impuestoTasa, '0'), '.') . '%)' : '');
            } elseif ($impuestoTasa !== null) {
                $impuestoLabel = 'IVA ' . rtrim(rtrim((string) $impuestoTasa, '0'), '.') . '%';
            }

            $lineId = $db->table('gmail_dte_document_lines')->insertGetId([
                'document_id' => $docId,
                'nro_linea' => $nroLinea,
                'codigo' => trim((string) ($line->CdgItem->VlrCodigo ?? '')) ?: null,
                'descripcion' => trim((string) ($line->NmbItem ?? '')) ?: null,
                'cantidad' => (float) ((string) ($line->QtyItem ?? 0)),
                'unidad' => trim((string) ($line->UnmdItem ?? '')) ?: null,
                'precio_unitario' => (float) ((string) ($line->PrcItem ?? 0)),
                'monto_item' => (float) ((string) ($line->MontoItem ?? 0)),
                'impuesto_codigo' => $impuestoCodigo,
                'impuesto_tasa' => $impuestoTasa,
                'impuesto_label' => $impuestoLabel,
                'es_exento' => $esExento ? 1 : 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $taxes = $this->extractLineTaxes($line, $tasaIvaDoc);
            $this->persistLineTaxes($db, $docId, $lineId, $nroLinea, $taxes);
        }

        return true;
    }

    private function updateExistingDocumentLineTaxes($db, int $documentId, \SimpleXMLElement $xml, float $tasaIvaDoc): void
    {
        $lines = $xml->xpath('//sii:Detalle') ?? [];

        foreach ($lines as $line) {
            $nroLinea = (int) ((string) ($line->NroLinDet ?? 0));
            if ($nroLinea <= 0) {
                continue;
            }

            $esExento = ((string) ($line->IndExe ?? '')) === '1';
            $impuestoCodigo = trim((string) ($line->CodImpAdic ?? '')) ?: null;
            $impuestoTasa = null;

            if (isset($line->ImptoReten) && isset($line->ImptoReten->TasaImp)) {
                $impuestoTasa = (float) ((string) $line->ImptoReten->TasaImp);
            } elseif (!$esExento && $tasaIvaDoc > 0) {
                $impuestoTasa = $tasaIvaDoc;
            }

            $impuestoLabel = null;
            if ($esExento) {
                $impuestoLabel = 'Exento';
            } elseif ($impuestoCodigo) {
                $impuestoLabel = 'Imp. adic. ' . $impuestoCodigo . ($impuestoTasa !== null ? ' (' . rtrim(rtrim((string) $impuestoTasa, '0'), '.') . '%)' : '');
            } elseif ($impuestoTasa !== null) {
                $impuestoLabel = 'IVA ' . rtrim(rtrim((string) $impuestoTasa, '0'), '.') . '%';
            }

            $lineRow = $db->table('gmail_dte_document_lines')
                ->where('document_id', $documentId)
                ->where('nro_linea', $nroLinea)
                ->first();

            $db->table('gmail_dte_document_lines')
                ->where('document_id', $documentId)
                ->where('nro_linea', $nroLinea)
                ->update([
                    'impuesto_codigo' => $impuestoCodigo,
                    'impuesto_tasa' => $impuestoTasa,
                    'impuesto_label' => $impuestoLabel,
                    'es_exento' => $esExento ? 1 : 0,
                    'updated_at' => now(),
                ]);

            if ($lineRow) {
                $db->table('gmail_dte_document_line_taxes')
                    ->where('document_id', $documentId)
                    ->where('dte_line_id', $lineRow->id)
                    ->delete();

                $taxes = $this->extractLineTaxes($line, $tasaIvaDoc);
                $this->persistLineTaxes($db, $documentId, (int) $lineRow->id, $nroLinea, $taxes);
            }
        }
    }

    private function extractLineTaxes(\SimpleXMLElement $line, float $tasaIvaDoc): array
    {
        $taxes = [];
        $esExento = ((string) ($line->IndExe ?? '')) === '1';

        if ($esExento) {
            $taxes[] = [
                'tax_type' => 'EXENTO',
                'codigo' => null,
                'tasa' => 0,
                'monto' => null,
                'base' => null,
                'descripcion' => 'Exento',
                'raw_json' => json_encode(['IndExe' => (string) ($line->IndExe ?? '1')], JSON_UNESCAPED_UNICODE),
            ];
        } elseif ($tasaIvaDoc > 0) {
            $taxes[] = [
                'tax_type' => 'IVA',
                'codigo' => 'IVA',
                'tasa' => $tasaIvaDoc,
                'monto' => null,
                'base' => null,
                'descripcion' => 'IVA ' . rtrim(rtrim((string) $tasaIvaDoc, '0'), '.') . '%',
                'raw_json' => json_encode(['TasaIVA' => $tasaIvaDoc], JSON_UNESCAPED_UNICODE),
            ];
        }

        $codImpAdic = trim((string) ($line->CodImpAdic ?? ''));
        if ($codImpAdic !== '') {
            $desc = 'Imp. adic. ' . $codImpAdic;
            $taxes[] = [
                'tax_type' => 'IMP_ADIC',
                'codigo' => $codImpAdic,
                'tasa' => null,
                'monto' => null,
                'base' => null,
                'descripcion' => $desc,
                'raw_json' => json_encode(['CodImpAdic' => $codImpAdic], JSON_UNESCAPED_UNICODE),
            ];
        }

        if (isset($line->ImptoReten)) {
            foreach ($line->ImptoReten as $ret) {
                $codigo = trim((string) ($ret->TipoImp ?? '')) ?: null;
                $tasa = is_numeric((string) ($ret->TasaImp ?? null)) ? (float) $ret->TasaImp : null;
                $monto = is_numeric((string) ($ret->MontoImp ?? null)) ? (float) $ret->MontoImp : null;
                $base = is_numeric((string) ($ret->BaseImp ?? null)) ? (float) $ret->BaseImp : null;

                $desc = 'Retencion';
                if ($codigo) {
                    $desc .= ' ' . $codigo;
                }
                if (!is_null($tasa)) {
                    $desc .= ' (' . rtrim(rtrim((string) $tasa, '0'), '.') . '%)';
                }

                $taxes[] = [
                    'tax_type' => 'IMPTO_RETEN',
                    'codigo' => $codigo,
                    'tasa' => $tasa,
                    'monto' => $monto,
                    'base' => $base,
                    'descripcion' => $desc,
                    'raw_json' => json_encode([
                        'TipoImp' => (string) ($ret->TipoImp ?? ''),
                        'TasaImp' => (string) ($ret->TasaImp ?? ''),
                        'MontoImp' => (string) ($ret->MontoImp ?? ''),
                        'BaseImp' => (string) ($ret->BaseImp ?? ''),
                    ], JSON_UNESCAPED_UNICODE),
                ];
            }
        }

        return $taxes;
    }

    private function persistLineTaxes($db, int $documentId, int $lineId, int $nroLinea, array $taxes): void
    {
        foreach ($taxes as $tax) {
            $db->table('gmail_dte_document_line_taxes')->insert([
                'document_id' => $documentId,
                'dte_line_id' => $lineId,
                'nro_linea' => $nroLinea > 0 ? $nroLinea : null,
                'tax_type' => $tax['tax_type'] ?? null,
                'codigo' => $tax['codigo'] ?? null,
                'tasa' => $tax['tasa'] ?? null,
                'monto' => $tax['monto'] ?? null,
                'base' => $tax['base'] ?? null,
                'descripcion' => $tax['descripcion'] ?? null,
                'raw_json' => $tax['raw_json'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Enviar notificación push a todos los dispositivos activos
     */
    private function enviarNotificacionPush(
        string $titulo,
        string $mensaje,
        string $producto,
        float $cantidad,
        int $movimientoId,
        string $tipo
    ): void
    {
        // Verificar si existe el archivo de credenciales de Firebase
        $credentialsPath = storage_path('app/firebase/firebase-credentials.json');

        if (!file_exists($credentialsPath)) {
            $this->warn('⚠️  Firebase no configurado. Notificaciones push desactivadas.');
            $this->line('   Para activarlas, configura Firebase (ver docs/FLUTTER_INTEGRATION.md)');
            return;
        }

        try {
            // Obtener tokens FCM activos
            $tokens = DB::connection('fuelcontrol')
                ->table('device_tokens')
                ->where('active', true)
                ->pluck('fcm_token')
                ->toArray();

            if (empty($tokens)) {
                $this->line('   No hay dispositivos registrados para notificaciones push.');
                return;
            }

            // Crear cliente Firebase
            $factory = (new Factory)->withServiceAccount($credentialsPath);
            $messaging = $factory->createMessaging();

            // Crear notificación
            $notification = Notification::create($titulo, $mensaje);

            $enviados = 0;
            $errores = 0;

            foreach ($tokens as $token) {
                try {
                    $message = CloudMessage::fromArray([
                        'token' => $token,
                        'notification' => [
                            'title' => $titulo,
                            'body' => $mensaje,
                        ],
                        'data' => [
                            'tipo' => $tipo,
                            'producto' => $producto,
                            'cantidad' => (string) $cantidad,
                            'movimiento_id' => (string) $movimientoId,
                            'timestamp' => now()->toIso8601String(),
                        ],
                    ]);

                    $messaging->send($message);
                    $enviados++;
                } catch (\Throwable $e) {
                    $errores++;
                    // Token inválido, desactivarlo
                    if (str_contains($e->getMessage(), 'not-found') ||
                        str_contains($e->getMessage(), 'invalid-registration-token')) {
                        DB::connection('fuelcontrol')
                            ->table('device_tokens')
                            ->where('fcm_token', $token)
                            ->update(['active' => false]);
                    }
                }
            }

            $this->info("📱 Push enviadas: {$enviados} exitosas" . ($errores > 0 ? ", {$errores} fallidas" : ""));
        } catch (\Throwable $e) {
            $this->error("Error al enviar notificaciones push: {$e->getMessage()}");
        }
    }
}
