<?php

namespace App\Http\Controllers;

use Google\Client;
use Google\Service\Gmail;
use Illuminate\Support\Facades\Cache;

class GoogleOAuthController extends Controller
{
    private function client(): Client
    {
        $client = new Client();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.redirect'));
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setScopes([
            Gmail::GMAIL_READONLY
        ]);

        return $client;
    }

    public function redirect()
    {
        return redirect()->away($this->client()->createAuthUrl());
    }

    public function callback()
    {
        $client = $this->client();
        $token = $client->fetchAccessTokenWithAuthCode(request('code'));

        Cache::put('gmail_token', $token, now()->addHour());

        return redirect()->route('inventario.dte')->with('ok', 'Gmail conectado');
    }
}