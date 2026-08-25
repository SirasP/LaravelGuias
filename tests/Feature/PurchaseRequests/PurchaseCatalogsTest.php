<?php

use App\Models\Department;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lets only the admin reach the catalogs', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('purchase_catalogs.index'))->assertOk();

    foreach (['comprador', 'auditor', 'viewer', 'bodeguero'] as $rol) {
        $user = User::factory()->create(['role' => $rol]);

        $this->actingAs($user)->get(route('purchase_catalogs.index'))->assertForbidden();
        $this->actingAs($user)
            ->post(route('purchase_catalogs.store', 'areas'), ['name' => 'Intruso'])
            ->assertForbidden();
    }

    expect(Department::query()->where('name', 'Intruso')->exists())->toBeFalse();
});

it('requires authentication', function () {
    $this->get(route('purchase_catalogs.index'))->assertRedirect(route('login'));
});

it('adds an area and makes it available in the request form', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('purchase_catalogs.store', 'areas'), ['name' => 'Riego', 'sort_order' => 20])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('purchase_catalogs.index', 'areas'));

    $area = Department::query()->where('slug', 'riego')->firstOrFail();
    expect($area->name)->toBe('Riego')->and($area->is_active)->toBeTrue();

    // Y aparece de inmediato en el desplegable del formulario.
    $this->actingAs($admin)
        ->get(route('purchase_requests.create'))
        ->assertOk()
        ->assertSee('Riego');
});

it('refuses an area that only differs in accents or case', function () {
    $admin = User::factory()->admin()->create();
    Department::query()->create(['company_code' => 'EHE', 'name' => 'Administración', 'is_active' => true]);

    // El problema real de los formularios en papel: ADMINISTRACION,
    // Administracion y Administración conviviendo como tres áreas.
    foreach (['ADMINISTRACION', 'administracion', 'Administración '] as $variante) {
        $this->actingAs($admin)
            ->post(route('purchase_catalogs.store', 'areas'), ['name' => $variante])
            ->assertSessionHasErrors('name');
    }

    expect(Department::query()->count())->toBe(1);
});

it('points to the disabled twin instead of creating a duplicate', function () {
    $admin = User::factory()->admin()->create();
    Department::query()->create(['company_code' => 'EHE', 'name' => 'Packing', 'is_active' => false]);

    $this->actingAs($admin)
        ->post(route('purchase_catalogs.store', 'areas'), ['name' => 'packing'])
        ->assertSessionHasErrors('name');

    expect(session('errors')->first('name'))->toContain('desactivada');
    expect(Department::query()->count())->toBe(1);
});

it('deactivates an entry without deleting it or touching past requests', function () {
    $admin = User::factory()->admin()->create();
    $area = Department::query()->create(['company_code' => 'EHE', 'name' => 'Bodega', 'is_active' => true]);

    $this->actingAs($admin)
        ->post(route('purchase_catalogs.toggle', ['areas', $area->id]))
        ->assertSessionHasNoErrors();

    $area->refresh();

    // Sigue existiendo: sólo deja de ofrecerse.
    expect($area->exists)->toBeTrue()->and($area->is_active)->toBeFalse();
    expect(Department::query()->count())->toBe(1);

    $this->actingAs($admin)
        ->get(route('purchase_requests.create'))
        ->assertOk()
        ->assertDontSee('>Bodega<', false);

    // Y se puede volver a activar.
    $this->actingAs($admin)->post(route('purchase_catalogs.toggle', ['areas', $area->id]));
    expect($area->fresh()->is_active)->toBeTrue();
});

it('renames an entry and recomputes its canonical key', function () {
    $admin = User::factory()->admin()->create();
    $area = Department::query()->create(['company_code' => 'EHE', 'name' => 'Manteniion', 'is_active' => true]);

    $this->actingAs($admin)
        ->put(route('purchase_catalogs.update', ['areas', $area->id]), ['name' => 'Mantención', 'sort_order' => 5])
        ->assertSessionHasNoErrors();

    $area->refresh();
    expect($area->name)->toBe('Mantención')
        ->and($area->slug)->toBe('mantencion')
        ->and($area->sort_order)->toBe(5);
});

it('manages units with their abbreviation and decimal rule', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('purchase_catalogs.store', 'unidades'), [
            'name' => 'Tambores',
            'code' => 'tambor',
            'allows_decimals' => '1',
        ])
        ->assertSessionHasNoErrors();

    $unidad = UnitOfMeasure::query()->where('slug', 'tambores')->firstOrFail();

    expect($unidad->code)->toBe('tambor')
        ->and($unidad->allows_decimals)->toBeTrue();

    // La abreviatura es obligatoria en unidades.
    $this->actingAs($admin)
        ->post(route('purchase_catalogs.store', 'unidades'), ['name' => 'Bidones'])
        ->assertSessionHasErrors('code');
});

it('rejects an unknown catalog', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('purchase_catalogs.index', 'inventado'))->assertNotFound();
});

it('does not let the catalogs route be swallowed by the request route', function () {
    $admin = User::factory()->admin()->create();

    // /solicitudes-compra/{purchaseRequest} podría capturar «catalogos».
    $this->actingAs($admin)
        ->get('/solicitudes-compra/catalogos')
        ->assertOk()
        ->assertSee('Catálogos de solicitudes');
});
