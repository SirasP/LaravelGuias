<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Google\Client as GoogleClient;
use Google\Service\Gmail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GmailImportHistorico extends Command
{
    protected $signature = 'gmail:import-historico';

    protected $description = 'Importa HISTÓRICO de XML DTE desde Gmail (sin tocar stock ni marcar leídos)';

    public function handle()
    {
        /* ===============================
         | 1️⃣ CONEXIÓN BD
         =============================== */
        $db = DB::connection('fuelcontrol');

        /* ===============================
         | 2️⃣ CLIENTE GMAIL
         =============================== */
        $client = new GoogleClient;
        $client->setApplicationName('FuelControl Gmail Import Historico');
        $client->setScopes([Gmail::GMAIL_READONLY]);
        $client->setAuthConfig(storage_path('app/gmail/credentials.json'));
        $client->setAccessType('offline');

        $tokenPath = storage_path('app/gmail/token.json');

        if (file_exists($tokenPath)) {
            $client->setAccessToken(json_decode(file_get_contents($tokenPath), true));
        }

        if ($client->isAccessTokenExpired() && $client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            file_put_contents($tokenPath, json_encode($client->getAccessToken()));
        }

        $service = new Gmail($client);

        /* ===============================
         | 3️⃣ LISTAR CORREOS (PAGINADO)
         =============================== */
        $pageToken = null;

        $procesados = 0;
        $omitidos = 0;
        $xmlValidos = 0;
        $xmlInvalidos = 0;
        $movimientos = 0;

        $this->info('🚀 Iniciando importación HISTÓRICA...');
        $this->line('----------------------------------------');

        do {
            $params = [
                'q' => 'has:attachment filename:xml',
                'maxResults' => 100,
            ];

            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            $messages = $service->users_messages->listUsersMessages('me', $params);

            if (! $messages->getMessages()) {
                break;
            }

            foreach ($messages->getMessages() as $msg) {

                /* ===============================
                 | 4️⃣ CONTROL gmail_imports
                 =============================== */
                if ($db->table('gmail_imports')->where('gmail_message_id', $msg->getId())->exists()) {
                    $omitidos++;

                    continue;
                }

                // Registrar mensaje procesado
                $db->table('gmail_imports')->insert([
                    'gmail_message_id' => $msg->getId(),
                    'processed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $procesados++;

                $message = $service->users_messages->get('me', $msg->getId());
                $parts = $message->getPayload()->getParts() ?? [];

                foreach ($parts as $part) {

                    if (
                        ! $part->getFilename() ||
                        ! str_ends_with(strtolower($part->getFilename()), '.xml')
                    ) {
                        continue;
                    }

                    $this->line("📎 Histórico XML: {$part->getFilename()}");

                    $attachment = $service->users_messages_attachments->get(
                        'me',
                        $msg->getId(),
                        $part->getBody()->getAttachmentId()
                    );

                    $xmlContent = trim(
                        base64_decode(strtr($attachment->getData(), '-_', '+/'))
                    );

                    // Protección básica
                    if (! str_starts_with($xmlContent, '<')) {
                        $this->warn('⚠️ No es XML válido, se omite');
                        $xmlInvalidos++;

                        continue;
                    }

                    libxml_use_internal_errors(true);
                    $xml = simplexml_load_string($xmlContent);

                    if ($xml === false) {
                        $this->warn('⚠️ XML mal formado, se omite');
                        $xmlInvalidos++;
                        libxml_clear_errors();

                        continue;
                    }

                    $xmlValidos++;
                    $xml->registerXPathNamespace('sii', 'http://www.sii.cl/SiiDte');

                    /* ===============================
                     | 5️⃣ FECHA DTE
                     =============================== */
                    $fch = $xml->xpath('//sii:Encabezado/sii:IdDoc/sii:FchEmis')[0] ?? null;
                    if (! $fch) {
                        $this->warn('⚠️ Sin FchEmis, se omite XML');

                        continue;
                    }

                    $fechaEmision = Carbon::parse((string) $fch);

                    /* ===============================
                     | 6️⃣ DETALLES
                     =============================== */
                    foreach ($xml->xpath('//sii:Detalle') as $detalle) {

                        $nombre = strtoupper((string) $detalle->NmbItem);
                        $cantidad = (float) $detalle->QtyItem;

                        if ($cantidad <= 0) {
                            continue;
                        }

                        $productoNombre =
                            str_contains($nombre, 'DIESEL') ? 'Diesel' :
                            (str_contains($nombre, 'GASOLINA') ? 'Gasolina' : null);

                        if (! $productoNombre) {
                            continue;
                        }

                        $hash = hash('sha256', implode('|', [
                            $msg->getId(),
                            $part->getFilename(),
                            $productoNombre,
                            $cantidad,
                        ]));

                        if ($db->table('movimientos')->where('hash_unico', $hash)->exists()) {
                            continue;
                        }

                        $producto = $db->table('productos')
                            ->where('nombre', $productoNombre)
                            ->first();

                        if (! $producto) {
                            $this->warn("⚠️ Producto {$productoNombre} no existe");

                            continue;
                        }

                        // Registrar movimiento histórico
                        $db->table('movimientos')->insert([
                            'producto_id' => $producto->id,
                            'vehiculo_id' => null,
                            'cantidad' => $cantidad,
                            'tipo' => 'entrada',
                            'origen' => 'xml',
                            'referencia' => $part->getFilename(),
                            'usuario' => 'gmail_historico',
                            'fecha_movimiento' => $fechaEmision,
                            'hash_unico' => $hash,
                        ]);

                        $movimientos++;
                    }
                }
            }

            $pageToken = $messages->getNextPageToken();

        } while ($pageToken);

        /* ===============================
         | 7️⃣ RESUMEN FINAL
         =============================== */
        $this->line('----------------------------------------');
        $this->info('✔ IMPORTACIÓN HISTÓRICA FINALIZADA');
        $this->info("📨 Correos procesados: {$procesados}");
        $this->info("⏭️ Correos omitidos: {$omitidos}");
        $this->info("📄 XML válidos: {$xmlValidos}");
        $this->info("❌ XML inválidos: {$xmlInvalidos}");
        $this->info("🧾 Movimientos creados: {$movimientos}");

        return Command::SUCCESS;
    }
}
