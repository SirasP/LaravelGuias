<?php

use App\Models\User;
use App\Notifications\PurchaseRequestReviewed;
use App\Notifications\PurchaseRequestSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Support\InteractsWithPurchaseRequests;

uses(RefreshDatabase::class, InteractsWithPurchaseRequests::class);

it('never contacts a real mail server during the suite', function () {
    // Cinturón y tirantes: phpunit.xml fuerza el transporte `array`, así que
    // ningún correo sale de la máquina aunque el módulo los genere.
    expect(config('mail.default'))->toBe('array');
});

it('emails the admin when a request is submitted', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $this->submitPurchaseRequest($owner, $this->createPurchaseRequestDraft($owner));

    Notification::assertSentTo($admin, PurchaseRequestSubmitted::class, function ($notification, array $channels) {
        // Dentro del sistema y por correo.
        return in_array('database', $channels, true) && in_array('mail', $channels, true);
    });

    // El solicitante no recibe aviso de su propio envío.
    Notification::assertNotSentTo($owner, PurchaseRequestSubmitted::class);
});

it('emails the requester the decision, with the comment', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $request = $this->submitPurchaseRequest($owner, $this->createPurchaseRequestDraft($owner));

    $this->actingAs($admin)->post(route('purchase_requests.request_changes', $request), [
        'lock_version' => (string) $request->lock_version,
        'comment' => 'Falta la marca del tubo.',
        'corrections' => ['reason'],
    ])->assertSessionHasNoErrors();

    Notification::assertSentTo($owner, PurchaseRequestReviewed::class, function ($notification) {
        return $notification->comment === 'Falta la marca del tubo.'
            && $notification->outcome === 'devuelta para corrección';
    });
});

it('writes the outcome in readable Spanish, not as a label', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $request = $this->submitPurchaseRequest($owner, $this->createPurchaseRequestDraft($owner));

    Mail::fake();
    Notification::fake();

    $this->actingAs($admin)->post(route('purchase_requests.request_changes', $request), [
        'lock_version' => (string) $request->lock_version,
        'comment' => 'Corrige el motivo.',
    ]);

    Notification::assertSentTo($owner, PurchaseRequestReviewed::class, function ($notification) use ($owner) {
        $mail = $notification->toMail($owner);

        // «fue Cambios solicitados» era la redacción antigua y estaba torcida.
        expect($mail->subject)->toContain('devuelta para corrección')
            ->and($mail->subject)->not->toContain('Cambios solicitados');

        return true;
    });
});

it('renders the email with the folio, the comment and the marked points', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $request = $this->submitPurchaseRequest($owner, $this->createPurchaseRequestDraft($owner));

    $this->actingAs($admin)->post(route('purchase_requests.request_changes', $request), [
        'lock_version' => (string) $request->lock_version,
        'comment' => 'Revisa la fecha requerida.',
        'corrections' => ['required_date'],
    ]);

    $request->refresh();

    $html = view('emails.purchase_requests.reviewed', [
        'notifiable' => $owner,
        'purchaseRequest' => $request,
        'actor' => $admin,
        'outcome' => 'devuelta para corrección',
        'comment' => 'Revisa la fecha requerida.',
        'url' => route('purchase_requests.show', $request->public_id),
    ])->render();

    expect($html)->toContain($request->folio)
        ->toContain('Revisa la fecha requerida.')
        ->toContain('Fecha requerida')
        ->toContain('Agrícola EHE SpA')
        // Enlace de respaldo para clientes que bloquean botones.
        ->toContain(route('purchase_requests.show', $request->public_id));
});

it('keeps working when the mail server fails', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $request = $this->submitPurchaseRequest($owner, $this->createPurchaseRequestDraft($owner));

    // El SMTP se cae justo al aprobar.
    Notification::shouldReceive('send')->andThrow(new RuntimeException('SMTP caído'));

    $this->actingAs($admin)
        ->post(route('purchase_requests.approve', $request), ['lock_version' => (string) $request->lock_version])
        ->assertSessionHasNoErrors();

    // La decisión quedó guardada igual: el correo es un aviso, no la verdad.
    $request->refresh();
    expect($request->status->value)->toBe('approved')
        ->and($request->reviewed_by)->toBe($admin->id);
});

it('does not email when the channel is switched off', function () {
    config()->set('purchase_requests.mail_enabled', false);
    Notification::fake();

    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $this->submitPurchaseRequest($owner, $this->createPurchaseRequestDraft($owner));

    Notification::assertSentTo($admin, PurchaseRequestSubmitted::class, function ($notification, array $channels) {
        // El aviso interno se mantiene; el correo no sale.
        return in_array('database', $channels, true) && ! in_array('mail', $channels, true);
    });
});

/**
 * El correo de «enviada a revisión» tiene un solo dueño: quien acepta las
 * solicitudes para Odoo. Repartirlo entre los tres revisores hacía que ninguno
 * se sintiera el responsable de decidir.
 */
it('sends the review email to the one address that accepts requests', function () {
    config()->set('purchase_requests.approver_mail', 'sebastian.lopez@ehe.cl');
    Notification::fake();

    $owner = User::factory()->create();
    $unRevisor = User::factory()->admin()->create();
    $otroRevisor = User::factory()->admin()->create();

    $this->submitPurchaseRequest($owner, $this->createPurchaseRequestDraft($owner));

    // Los revisores siguen viendo el aviso en pantalla. Sin correo.
    foreach ([$unRevisor, $otroRevisor] as $revisor) {
        Notification::assertSentTo(
            $revisor,
            PurchaseRequestSubmitted::class,
            fn ($notificacion, array $canales): bool => $canales === ['database'],
        );
    }

    // Y el correo sale una sola vez, hacia la dirección configurada.
    Notification::assertSentOnDemand(
        PurchaseRequestSubmitted::class,
        fn ($notificacion, array $canales, $destinatario): bool => $canales === ['mail']
            && ($destinatario->routes['mail'] ?? null) === 'sebastian.lopez@ehe.cl',
    );
});

/** Sin dirección configurada se comporta como siempre: cada revisor, su correo. */
it('falls back to emailing every reviewer when no address is set', function () {
    config()->set('purchase_requests.approver_mail', '');
    Notification::fake();

    $owner = User::factory()->create();
    $revisor = User::factory()->admin()->create();

    $this->submitPurchaseRequest($owner, $this->createPurchaseRequestDraft($owner));

    Notification::assertSentTo(
        $revisor,
        PurchaseRequestSubmitted::class,
        fn ($notificacion, array $canales): bool => in_array('mail', $canales, true),
    );
});

/**
 * El resultado de la lectura es para quien subió el documento: es quien tiene
 * que ir a revisar lo leído. El administrador lo ve dentro del sistema.
 */
it('emails the reading result to whoever is quoting, not to the admins', function () {
    $quienCotiza = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $lectura = App\Models\PurchaseRequestIngestion::query()->create([
        'user_id' => $quienCotiza->getKey(),
        'uploader_name_snapshot' => $quienCotiza->name,
        'disk' => 'local', 'path' => 'cot.pdf', 'original_name' => 'cot.pdf',
        'mime_type' => 'application/pdf', 'size' => 10, 'sha256' => str_repeat('e', 64),
        'status' => App\Models\PurchaseRequestIngestion::COMPLETED,
    ]);

    $aviso = new App\Notifications\QuotationDraftReady($lectura);

    expect($aviso->via($quienCotiza))->toBe(['database', 'mail'])
        ->and($aviso->via($admin))->toBe(['database']);
});
