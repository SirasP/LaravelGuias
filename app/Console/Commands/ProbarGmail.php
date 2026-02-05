<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Google\Client as GoogleClient;
use Google\Service\Gmail;

class ProbarGmail extends Command
{
    protected $signature = 'gmail:test';
    protected $description = 'Prueba conexión con Gmail API';

    public function handle()
    {
        $client = new GoogleClient();
        $client->setApplicationName('FuelControl Gmail Import');
        $client->setScopes([Gmail::GMAIL_READONLY]);
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
                $authUrl = $client->createAuthUrl();
                $this->info("Abre esta URL en el navegador:");
                $this->line($authUrl);

                $this->info("Pega aquí el código:");
                $authCode = trim(fgets(STDIN));

                $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
                $client->setAccessToken($accessToken);
            }

            file_put_contents($tokenPath, json_encode($client->getAccessToken()));
        }

        $service = new Gmail($client);
        $messages = $service->users_messages->listUsersMessages('me', [
            'maxResults' => 5
        ]);

        $this->info('✅ Conectado a Gmail API');
        $this->info('Correos encontrados: ' . count($messages->getMessages()));

        foreach ($messages->getMessages() as $msg) {
            $this->line('Mensaje ID: ' . $msg->getId());
        }

        return 0;

    }
}
