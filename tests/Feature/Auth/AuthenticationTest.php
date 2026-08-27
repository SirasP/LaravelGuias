<?php

use App\Models\User;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();

    // No es el «dashboard» que traía Breeze: esta aplicación manda a `index`,
    // y a quien tiene rol de bodeguero directo al listado de facturas.
    $response->assertRedirect(route('index', absolute: false));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();

    // Al salir se vuelve al login, no a la raíz: la raíz exige sesión y sólo
    // rebotaría otra vez al login.
    $response->assertRedirect('/login');
});
