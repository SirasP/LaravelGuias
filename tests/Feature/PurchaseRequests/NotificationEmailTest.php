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

it('shows the field on the profile page, verified email or not', function () {
    // La primera vez quedó dentro del aviso de «correo sin verificar», que sólo
    // se dibuja cuando el correo NO está verificado: el campo existía, se podía
    // guardar por la ruta, y en pantalla no aparecía. Los tests de modelo y de
    // ruta pasaban igual. Por eso este mira la página.
    $verificado = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($verificado)->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Correo para avisos')
        ->assertSee('name="notification_email"', false);

    $sinVerificar = User::factory()->unverified()->create();

    $this->actingAs($sinVerificar)->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('name="notification_email"', false);
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

it('can restrict outgoing mail to a few addresses while testing', function () {
    config(['purchase_requests.mail_enabled' => true]);

    $yo = User::factory()->create(['email' => 's.lopez.epple@gmail.com']);
    $otra = User::factory()->create(['email' => 'adm.agricolae.h@gmail.com']);

    $solicitud = $this->submitPurchaseRequest($yo, $this->createPurchaseRequestDraft($yo));
    $aviso = new PurchaseRequestSubmitted($solicitud, $yo);

    // Sin restricción, el correo sale a todo el mundo.
    expect($aviso->via($yo))->toContain('mail')
        ->and($aviso->via($otra))->toContain('mail');

    config(['purchase_requests.mail_only' => ['s.lopez.epple@gmail.com']]);

    expect($aviso->via($yo))->toContain('mail');

    // La otra persona deja de recibir correo, pero NO deja de estar avisada:
    // su notificación en pantalla sigue llegando igual.
    expect($aviso->via($otra))->not->toContain('mail')
        ->and($aviso->via($otra))->toContain('database');
});

it('matches the address the notices actually go to, not the login one', function () {
    config([
        'purchase_requests.mail_enabled' => true,
        'purchase_requests.mail_only' => ['sebastian.lopez@ehe.cl'],
    ]);

    // Si la lista se comparara contra el correo de acceso, poner el @ehe.cl
    // dejaría sin correo justo a la persona que está haciendo las pruebas.
    $conReenvio = User::factory()->create([
        'email' => 's.lopez.epple@gmail.com',
        'notification_email' => 'sebastian.lopez@ehe.cl',
    ]);

    $solicitud = $this->submitPurchaseRequest($conReenvio, $this->createPurchaseRequestDraft($conReenvio));

    expect((new PurchaseRequestSubmitted($solicitud, $conReenvio))->via($conReenvio))->toContain('mail');
});
