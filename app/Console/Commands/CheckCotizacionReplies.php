<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Google\Client as GoogleClient;
use Google\Service\Gmail;
use Google\Service\Gmail\ModifyMessageRequest;

class CheckCotizacionReplies extends Command
{
    protected $signature = 'cotizaciones:check-replies
                            {--all : Leer también correos ya leídos (no solo unread)}';
    protected $description = 'Lee respuestas de proveedores en Gmail y las registra automáticamente en cotizaciones';

    public function handle(): int
    {
        $db = DB::connection('fuelcontrol');

        /* ─────────────────────────────────────────
         | 1. CONEXIÓN GMAIL
         ───────────────────────────────────────── */
        $tokenPath = storage_path('app/gmail/token.json');

        if (!file_exists($tokenPath)) {
            $this->error('No hay token de Gmail guardado.');
            $this->line('Conecta la cuenta Gmail desde la aplicación web.');
            return Command::FAILURE;
        }

        $client = new GoogleClient();
        $client->setApplicationName('FuelControl Cotizaciones');
        $client->setScopes([Gmail::GMAIL_MODIFY]);
        $client->setAuthConfig(storage_path('app/gmail/credentials.json'));
        $client->setAccessType('offline');
        $client->setAccessToken(json_decode(file_get_contents($tokenPath), true));

        if ($client->isAccessTokenExpired()) {
            if (!$client->getRefreshToken()) {
                $this->error('Token expirado y sin refresh token. Reconecta Gmail.');
                return Command::FAILURE;
            }
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            file_put_contents($tokenPath, json_encode($client->getAccessToken()));
            $this->line('Token renovado.');
        }

        $service = new Gmail($client);

        /* ─────────────────────────────────────────
         | 2. BUSCAR CORREOS CON NÚMERO DE COT
         ───────────────────────────────────────── */
        $query = $this->option('all')
            ? 'subject:(COT-)'
            : 'is:unread subject:(COT-)';

        $this->line("🔍 Buscando correos con: {$query}");

        $msgList = $service->users_messages->listUsersMessages('me', [
            'q'          => $query,
            'maxResults' => 50,
        ]);

        $batch = $msgList->getMessages() ?? [];
        $this->line('   Encontrados: ' . count($batch));

        $imported = 0;
        $skipped  = 0;

        foreach ($batch as $msg) {
            /* ─────────────────────────────────────
             | 3. EVITAR DUPLICADOS
             ───────────────────────────────────── */
            if ($db->table('purchase_order_replies')->where('email_message_id', $msg->getId())->exists()) {
                $skipped++;
                continue;
            }

            /* ─────────────────────────────────────
             | 4. OBTENER MENSAJE COMPLETO
             ───────────────────────────────────── */
            $message = $service->users_messages->get('me', $msg->getId(), ['format' => 'full']);
            $payload = $message->getPayload();
            $headers = $this->parseHeaders($payload->getHeaders());

            $subject = $headers['subject'] ?? '';
            $fromRaw = $headers['from']    ?? '';

            /* ─────────────────────────────────────
             | 5. EXTRAER NÚMERO COT DEL ASUNTO
             ───────────────────────────────────── */
            if (!preg_match('/COT-\d{4}-\d{5}/', $subject, $m)) {
                $this->line('   ⏭ Sin número COT en asunto: ' . mb_substr($subject, 0, 70));
                $this->markRead($service, $msg->getId());
                continue;
            }

            $orderNumber = $m[0];

            /* ─────────────────────────────────────
             | 6. BUSCAR COTIZACIÓN EN BD
             ───────────────────────────────────── */
            $order = $db->table('purchase_orders')->where('order_number', $orderNumber)->first();

            if (!$order) {
                $this->warn("   ⚠  Cotización no encontrada en BD: {$orderNumber}");
                $this->markRead($service, $msg->getId());
                continue;
            }

            /* ─────────────────────────────────────
             | 7. PARSEAR REMITENTE Y EMPAREJAR
             |    CON PROVEEDOR EN BD
             ───────────────────────────────────── */
            [$senderName, $senderEmail] = $this->parseFrom($fromRaw);

            $supplier = null;
            if ($senderEmail) {
                $spEmail = $db->table('purchase_order_supplier_emails')->where('email', $senderEmail)->first();
                if ($spEmail) {
                    $supplier = $db->table('purchase_order_suppliers')->where('id', $spEmail->supplier_id)->first();
                }
            }

            $supplierId   = $supplier?->id;
            $supplierName = $supplier?->name ?? $senderName ?? $senderEmail ?? 'Desconocido';

            /* ─────────────────────────────────────
             | 8. EXTRAER CUERPO DEL CORREO
             ───────────────────────────────────── */
            $bodyText = $this->extractBody($payload);
            $bodyText = strip_tags($bodyText ?? '');
            $bodyText = trim(preg_replace('/\n{3,}/', "\n\n", $bodyText));
            if (mb_strlen($bodyText) > 5000) {
                $bodyText = mb_substr($bodyText, 0, 5000) . '…';
            }

            /* ─────────────────────────────────────
             | 9. DESCARGAR PRIMER ADJUNTO
             |    (PDF, imagen)
             ───────────────────────────────────── */
            $pdfPath         = null;
            $pdfOriginalName = null;

            foreach ($this->flattenParts($payload->getParts() ?? []) as $part) {
                $filename = $part->getFilename();
                if (!$filename) continue;

                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                if (!in_array($ext, ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'gif'])) continue;

                $attachId = $part->getBody()->getAttachmentId();
                if (!$attachId) continue;

                try {
                    $attach  = $service->users_messages_attachments->get('me', $msg->getId(), $attachId);
                    $content = $this->decodeBase64Url((string) $attach->getData());
                    if (!$content) continue;

                    $safeName  = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
                    $storePath = 'cotizacion-pdfs/' . now()->format('Ymd_His') . '_' . $safeName;
                    Storage::disk('public')->put($storePath, $content);

                    $pdfPath         = $storePath;
                    $pdfOriginalName = $filename;
                    break; // Solo el primer adjunto válido
                } catch (\Throwable $e) {
                    $this->warn('   ⚠ Error descargando adjunto: ' . $e->getMessage());
                }
            }

            /* ─────────────────────────────────────
             | 10. GUARDAR RESPUESTA EN BD
             ───────────────────────────────────── */
            $db->table('purchase_order_replies')->insert([
                'purchase_order_id' => $order->id,
                'supplier_id'       => $supplierId,
                'supplier_name'     => $supplierName,
                'notes'             => $bodyText ?: null,
                'total_quoted'      => null, // el precio debe confirmarse manualmente
                'currency'          => $order->currency ?? 'CLP',
                'pdf_path'          => $pdfPath,
                'pdf_original_name' => $pdfOriginalName,
                'source'            => 'email',
                'email_message_id'  => $msg->getId(),
                'sender_email'      => $senderEmail,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            $this->markRead($service, $msg->getId());
            $this->info("   ✅ {$orderNumber} ← {$supplierName} ({$senderEmail})");
            $imported++;
        }

        $this->info("Finalizado: {$imported} importadas, {$skipped} ya procesadas.");
        return Command::SUCCESS;
    }

    /* ══════════════════════════════════════════════════════════════════════
     | HELPERS
     ══════════════════════════════════════════════════════════════════════ */

    private function parseHeaders(array $headers): array
    {
        $map = [];
        foreach ($headers as $h) {
            $map[strtolower($h->getName())] = $h->getValue();
        }
        return $map;
    }

    /** Parsea "Nombre <email@x.com>" o solo "email@x.com" → [name, email] */
    private function parseFrom(string $from): array
    {
        $from = trim($from);
        if (preg_match('/^(.+?)\s*<([^>]+)>$/', $from, $m)) {
            return [trim($m[1], '" '), strtolower(trim($m[2]))];
        }
        return [$from, strtolower($from)];
    }

    /** Extrae texto del payload, prefiriendo text/plain sobre text/html */
    private function extractBody(\Google\Service\Gmail\MessagePart $payload): ?string
    {
        $mimeType = $payload->getMimeType() ?? '';

        if (in_array($mimeType, ['text/plain', 'text/html'])) {
            return $this->decodeBase64Url($payload->getBody()->getData() ?? '');
        }

        $textPlain = null;
        $textHtml  = null;

        foreach ($this->flattenParts($payload->getParts() ?? []) as $part) {
            $mt   = $part->getMimeType() ?? '';
            $data = $part->getBody()->getData() ?? '';
            if (!$data) continue;

            if ($mt === 'text/plain' && $textPlain === null) {
                $textPlain = $this->decodeBase64Url($data);
            } elseif ($mt === 'text/html' && $textHtml === null) {
                $textHtml = $this->decodeBase64Url($data);
            }
        }

        return $textPlain ?? $textHtml;
    }

    /** Aplana partes anidadas de un mensaje multipart */
    private function flattenParts(array $parts): array
    {
        $flat = [];
        foreach ($parts as $part) {
            $flat[] = $part;
            foreach ($this->flattenParts($part->getParts() ?? []) as $sub) {
                $flat[] = $sub;
            }
        }
        return $flat;
    }

    private function decodeBase64Url(string $data): string
    {
        $b64 = strtr($data, '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad > 0) $b64 .= str_repeat('=', 4 - $pad);
        return (string) base64_decode($b64, true);
    }

    private function markRead(Gmail $service, string $messageId): void
    {
        try {
            $req = new ModifyMessageRequest();
            $req->setRemoveLabelIds(['UNREAD']);
            $service->users_messages->modify('me', $messageId, $req);
        } catch (\Throwable) {
            // No crítico
        }
    }
}
