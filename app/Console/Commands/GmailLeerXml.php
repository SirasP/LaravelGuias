<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Google\Client as GoogleClient;
use Google\Service\Gmail;
use Google\Service\Gmail\ModifyMessageRequest;
use Illuminate\Support\Facades\Http;

class GmailLeerXml extends Command
{
    protected $signature = 'gmail:leer-xml';
    protected $description = 'Lee correos Gmail, procesa XML DTE y controla inventario';

    public function handle()
    {
        /* ===============================
         | 1️⃣ CONEXIÓN BD
         =============================== */
        $db = DB::connection('fuelcontrol');

        /* ===============================
         | 2️⃣ CLIENTE GMAIL
         =============================== */
        $client = new GoogleClient();
        $client->setApplicationName('FuelControl Gmail Import');
        $client->setScopes([Gmail::GMAIL_MODIFY]);
        $client->setAuthConfig(storage_path('app/gmail/credentials.json'));
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');

        $tokenPath = storage_path('app/gmail/token.json');

        if (file_exists($tokenPath)) {
            $client->setAccessToken(json_decode(file_get_contents($tokenPath), true));
        }

        if ($client->isAccessTokenExpired()) {
            if ($client->getRefreshToken()) {
                $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            } else {
                $this->line($client->createAuthUrl());
                $this->info("Pega aquí el código:");
                $client->setAccessToken(
                    $client->fetchAccessTokenWithAuthCode(trim(fgets(STDIN)))
                );
            }
            file_put_contents($tokenPath, json_encode($client->getAccessToken()));
        }

        $service = new Gmail($client);

        /* ===============================
         | 3️⃣ CORREOS NO LEÍDOS
         =============================== */
        $messages = $service->users_messages->listUsersMessages('me', [
            'q' => 'has:attachment is:unread'
        ]);

        if (!$messages->getMessages()) {
            $this->info('No hay correos nuevos');
            return Command::SUCCESS;
        }

        foreach ($messages->getMessages() as $msg) {

            /* ===============================
             | 4️⃣ REGISTRAR GMAIL IMPORT
             =============================== */
            if ($db->table('gmail_imports')->where('gmail_message_id', $msg->getId())->exists()) {
                continue;
            }

            $db->table('gmail_imports')->insert([
                'gmail_message_id' => $msg->getId(),
                'processed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $message = $service->users_messages->get('me', $msg->getId());
            $parts = $message->getPayload()->getParts();

            foreach ($parts as $part) {

                if (!$part->getFilename() || !str_ends_with(strtolower($part->getFilename()), '.xml')) {
                    continue;
                }

                $this->info("📎 XML encontrado: {$part->getFilename()}");

                $attachment = $service->users_messages_attachments->get(
                    'me',
                    $msg->getId(),
                    $part->getBody()->getAttachmentId()
                );

                $xml = simplexml_load_string(
                    base64_decode(strtr($attachment->getData(), '-_', '+/'))
                );

                if (!$xml) {
                    $this->error("❌ XML inválido");
                    continue;
                }

                $xml->registerXPathNamespace('sii', 'http://www.sii.cl/SiiDte');



                /* ===============================
                 | 5️⃣ FECHA DTE
                 =============================== */
                $fch = $xml->xpath('//sii:Encabezado/sii:IdDoc/sii:FchEmis')[0] ?? null;

                if (!$fch) {
                    $this->error("❌ No se pudo leer FchEmis");
                    continue;
                }

                $fechaEmision = Carbon::parse((string) $fch);
                $limite = now()->subDays(5);
                $afectaStock = $fechaEmision->greaterThanOrEqualTo($limite);
                /* ===============================
                 | 🔍 DETECTAR LEY 18.502
                 =============================== */
                $usaVehiculo = false;

                foreach ($xml->xpath('//sii:Referencia') as $ref) {
                    $razon = strtoupper((string) ($ref->RazonRef ?? ''));

                    if (str_contains($razon, 'LEY 18.502') || str_contains($razon, 'VEHICUL')) {
                        $usaVehiculo = true;
                        break;
                    }
                }
                /* ===============================
                 | 6️⃣ DETALLES
                 =============================== */
                foreach ($xml->xpath('//sii:Detalle') as $detalle) {

                    $nombre = strtoupper((string) $detalle->NmbItem);
                    $cantidad = (float) $detalle->QtyItem;

                    $productoNombre = str_contains($nombre, 'DIESEL') ? 'Diesel' :
                        (str_contains($nombre, 'GASOLINA') ? 'Gasolina' : null);

                    if (!$productoNombre || $cantidad <= 0)
                        continue;

                    $this->line("⛽ {$productoNombre} → {$cantidad}");

                    $hash = hash('sha256', implode('|', [
                        $msg->getId(),
                        $part->getFilename(),
                        $productoNombre,
                        $cantidad
                    ]));

                    if ($db->table('movimientos')->where('hash_unico', $hash)->exists()) {
                        continue;
                    }

                    $producto = $db->table('productos')->where('nombre', $productoNombre)->first();
                    if (!$producto)
                        continue;

                    if ($afectaStock && !$usaVehiculo) {

                        $db->table('productos')
                            ->where('id', $producto->id)
                            ->increment('cantidad', $cantidad);

                        $this->info("📦 Stock actualizado");
                    } else {
                        $this->warn("🚫 DTE asociado a VEHÍCULO (Ley 18.502) → NO suma stock");
                    }

                    $movimientoId = $$db = DB::connection('fuelcontrol')
                        ->table('movimientos')->insertGetId([
                                'producto_id' => $producto->id,
                                'vehiculo_id' => null,
                                'cantidad' => $cantidad,
                                'tipo' => $usaVehiculo ? 'vehiculo' : 'entrada',
                                'origen' => $usaVehiculo ? 'xml_vehiculo' : 'xml_estanque',
                                'referencia' => $part->getFilename(),

                                // flags importantes
                                'requiere_revision' => $usaVehiculo ? 1 : 0,
                                'xml_path' => $part->getFilename(),

                                'usuario' => 'gmail',
                                'fecha_movimiento' => $fechaEmision,
                                'hash_unico' => $hash,
                            ]);

                    $notificacionId = DB::connection('fuelcontrol')
                        ->table('notificaciones')
                        ->insertGetId([
                            'tipo' => $usaVehiculo ? 'xml_revision' : 'xml_entrada',
                            'titulo' => $usaVehiculo
                                ? 'XML requiere revisión'
                                : "Ingreso de {$productoNombre}",

                            'movimiento_id' => $movimientoId, // ✅ AHORA SÍ

                            'mensaje' => $usaVehiculo
                                ? "{$cantidad} L detectados como posible carga vehicular (Ley 18.502)"
                                : "+{$cantidad} L desde XML ({$part->getFilename()})",

                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);



                    $users = DB::table('users')->pluck('id');

                    foreach ($users as $userId) {
                        DB::connection('fuelcontrol')
                            ->table('notificacion_usuarios')
                            ->insert([
                                'notificacion_id' => $notificacionId,
                                'user_id' => $userId,
                                'leido' => 0,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                    }


                    $titulo = $usaVehiculo
                        ? "XML de consumo vehicular detectado"
                        : "Ingreso de {$productoNombre}";

                    $mensaje = $usaVehiculo
                        ? "{$cantidad} L (Ley 18.502, no suma stock)"
                        : "+{$cantidad} L desde XML ({$part->getFilename()})";


                    Http::post('http://127.0.0.1:3001/notify', [
                        'titulo' => $titulo,
                        'mensaje' => $mensaje,
                    ]);

                }
            }


            /* ===============================
             | 7️⃣ MARCAR LEÍDO
             =============================== */
            $modify = new ModifyMessageRequest();
            $modify->setRemoveLabelIds(['UNREAD']);
            $service->users_messages->modify('me', $msg->getId(), $modify);

            $this->line("✉️ Correo {$msg->getId()} marcado como leído");
        }

        $this->info('✔ Proceso completo');
        return Command::SUCCESS;
    }
}
