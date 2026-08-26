<?php

use App\Models\User;
use App\Notifications\PurchaseRequestSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\InteractsWithPurchaseRequests;

uses(RefreshDatabase::class, InteractsWithPurchaseRequests::class);

it('sends the notices to the address chosen for them, not the login one', function () {
    $user = User::factory()->create([
        'email' => 's.lopez.epple@gmail.com',
        'notification_email' => 'sebastian.lopez@ehe.cl',
    ]);

    expect($user->routeNotificationForMail())->toBe('sebastian.lopez@ehe.cl');
});

it('keeps using the login address while nobody sets another one', function () {
    $user = User::factory()->create(['email' => 'jose@ejemplo.cl', 'notification_email' => null]);

    expect($user->routeNotificationForMail())->toBe('jose@ejemplo.cl');

    // Y una cadena vacía guardada sin querer no puede dejar a nadie sin avisos.
    $user->notification_email = '';
    expect($user->routeNotificationForMail())->toBe('jose@ejemplo.cl');
});

it('lets a person set and clear it from their profile', function () {
    $user = User::factory()->create(['email' => 'paola@ejemplo.cl']);

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => $user->email,
        'notification_email' => 'adm.agricola@ehe.cl',
    ])->assertRedirect();

    expect($user->fresh()->notification_email)->toBe('adm.agricola@ehe.cl')
        // Cambiar a dónde llegan los avisos no puede tocar el correo de acceso
        // ni obligar a verificarlo de nuevo.
        ->and($user->fresh()->email)->toBe('paola@ejemplo.cl')
        ->and($user->fresh()->email_verified_at)->not->toBeNull();

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => $user->email,
        'notification_email' => '',
    ])->assertRedirect();

    expect($user->fresh()->notification_email)->toBeNull();
});

it('actually delivers a purchase notice to the chosen address', function () {
    config(['purchase_requests.mail_enabled' => true]);
    Mail::fake();

    $revisor = User::factory()->create([
        'role' => 'admin',
        'email' => 'revisor@gmail.com',
        'notification_email' => 'compras@ehe.cl',
    ]);

    $solicitud = $this->submitPurchaseRequest($revisor, $this->createPurchaseRequestDraft($revisor));
    $destino = (new PurchaseRequestSubmitted($solicitud, $revisor))->via($revisor);

    expect($destino)->toContain('mail')
        ->and($revisor->routeNotificationForMail())->toBe('compras@ehe.cl');
});
