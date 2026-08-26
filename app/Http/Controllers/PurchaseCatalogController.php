<?php

namespace App\Http\Controllers;

use App\Models\CostCenter;
use App\Models\Department;
use App\Models\Location;
use App\Models\PurchaseSupplier;
use App\Models\UnitOfMeasure;
use App\Support\Rut;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * Mantención de los catálogos del módulo de Solicitudes de Compra.
 *
 * Sólo el administrador entra aquí. Las entradas se desactivan, nunca se
 * eliminan: una solicitud histórica pudo haberlas usado, y aunque el nombre
 * queda guardado como snapshot, borrar el catálogo rompería los informes.
 */
class PurchaseCatalogController extends Controller
{
    /**
     * Catálogos administrables, con su modelo y sus etiquetas.
     *
     * @return array<string, array{model: class-string<Model>, singular: string, plural: string, hint: string}>
     */
    public static function catalogs(): array
    {
        return [
            'areas' => [
                'model' => Department::class,
                'singular' => 'Área o departamento',
                'plural' => 'Áreas y departamentos',
                'hint' => 'Quién pide la compra. Tener una lista corta evita que convivan «Administración» y «Admistración» como áreas distintas.',
            ],
            'unidades' => [
                'model' => UnitOfMeasure::class,
                'singular' => 'Unidad de medida',
                'plural' => 'Unidades de medida',
                'hint' => 'Cómo se mide lo que se pide: metros, cubos, cada talla. Se sugieren al escribir una partida.',
            ],
            'centros-costo' => [
                'model' => CostCenter::class,
                'singular' => 'Centro de costo',
                'plural' => 'Centros de costo y proyectos',
                'hint' => 'A qué proyecto o faena se imputa la compra. Opcional en la solicitud.',
            ],
            'proveedores' => [
                'model' => PurchaseSupplier::class,
                'singular' => 'Proveedor',
                'plural' => 'Proveedores',
                'hint' => 'A quién se le compra, identificado por su RUT. El nombre suele vivir dentro del logo del documento, que es una imagen: registrándolo aquí una vez, deja de depender de que un modelo lo adivine.',
            ],
            'lugares' => [
                'model' => Location::class,
                'singular' => 'Lugar de entrega',
                'plural' => 'Lugares de entrega o uso',
                'hint' => 'Dónde se necesita lo pedido. Opcional en la solicitud.',
            ],
        ];
    }

    public function index(Request $request, string $catalog = 'areas'): Response
    {
        $this->authorizeAdmin($request);

        // Los proveedores tienen su propia pantalla: llevan RUT, y ése es su
        // identificador real, no el nombre.
        if ($catalog === 'proveedores') {
            return $this->suppliers($request);
        }

        $config = $this->configFor($catalog);

        /** @var class-string<Model> $model */
        $model = $config['model'];

        $entries = $model::query()
            ->forCompany()
            ->orderByDesc('is_active')
            ->ordered()
            ->get();

        return response()->view('purchase_catalogs.index', [
            'catalog' => $catalog,
            'config' => $config,
            'catalogs' => self::catalogs(),
            'entries' => $entries,
            'isUnit' => $model === UnitOfMeasure::class,
        ]);
    }

    /** Proveedores: identificados por RUT, no por nombre. */
    private function suppliers(Request $request): Response
    {
        $suppliers = PurchaseSupplier::query()
            ->forCompany()
            ->orderByRaw('CASE WHEN (name IS NULL OR name = \'\') AND (trade_name IS NULL OR trade_name = \'\') THEN 0 ELSE 1 END')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return response()->view('purchase_catalogs.suppliers', [
            'catalog' => 'proveedores',
            'catalogs' => self::catalogs(),
            'suppliers' => $suppliers,
            'sinNombre' => $suppliers->filter->needsName()->count(),
        ]);
    }

    public function storeSupplier(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'tax_id' => ['required', 'string', 'max:20', function ($attribute, $value, $fail): void {
                if (! Rut::isValid($value)) {
                    $fail('Ese RUT no es válido: revisa el número y su dígito verificador.');
                }
            }],
            'name' => ['required', 'string', 'max:200'],
            'trade_name' => ['nullable', 'string', 'max:200'],
            'email' => ['nullable', 'email', 'max:200'],
        ], [], ['tax_id' => 'el RUT', 'name' => 'la razón social', 'trade_name' => 'el nombre de fantasía', 'email' => 'el correo']);

        $rut = Rut::normalize($data['tax_id']);

        $existente = PurchaseSupplier::query()->forCompany()->where('tax_id', $rut)->first();

        if ($existente !== null) {
            throw ValidationException::withMessages([
                'tax_id' => 'Ese RUT ya está registrado como «'.($existente->displayName() ?? 'sin nombre').'».',
            ]);
        }

        PurchaseSupplier::query()->create([
            'company_code' => 'EHE',
            'tax_id' => $rut,
            'name' => $data['name'],
            'trade_name' => $data['trade_name'] ?? null,
            'email' => $data['email'] ?? null,
            'source' => PurchaseSupplier::SOURCE_MANUAL,
            'is_active' => true,
        ]);

        return to_route('purchase_catalogs.index', 'proveedores')
            ->with('success', 'Proveedor agregado.');
    }

    public function updateSupplier(Request $request, int $supplier): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $registro = PurchaseSupplier::query()->forCompany()->findOrFail($supplier);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'trade_name' => ['nullable', 'string', 'max:200'],
            'email' => ['nullable', 'email', 'max:200'],
        ], [], ['name' => 'la razón social', 'trade_name' => 'el nombre de fantasía', 'email' => 'el correo']);

        // El RUT no se edita: es la identidad del proveedor. Si está errado,
        // se desactiva este y se crea el correcto.
        $registro->fill($data)->save();

        return to_route('purchase_catalogs.index', 'proveedores')
            ->with('success', 'Proveedor actualizado. Las solicitudes ya emitidas conservan el nombre que tenían.');
    }

    public function toggleSupplier(Request $request, int $supplier): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $registro = PurchaseSupplier::query()->forCompany()->findOrFail($supplier);
        $registro->forceFill(['is_active' => ! $registro->is_active])->save();

        return to_route('purchase_catalogs.index', 'proveedores')->with(
            'success',
            $registro->is_active ? 'Proveedor reactivado.' : 'Proveedor desactivado.',
        );
    }

    public function store(Request $request, string $catalog): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $config = $this->configFor($catalog);

        /** @var class-string<Model> $model */
        $model = $config['model'];

        $data = $this->validated($request, $model);
        $slug = $model::slugFor($data['name']);

        // El slug canónico es la clave real: absorbe tildes y mayúsculas, de
        // modo que «ADMINISTRACION» no pueda entrar como área nueva.
        $existing = $model::query()->forCompany()->where('slug', $slug)->first();

        if ($existing !== null) {
            throw ValidationException::withMessages([
                'name' => $existing->is_active
                    ? 'Ya existe una entrada equivalente: «'.$existing->name.'».'
                    : 'Existe una entrada equivalente desactivada: «'.$existing->name.'». Actívala en vez de crear otra.',
            ]);
        }

        $model::query()->create($this->attributesFor($model, $data));

        return to_route('purchase_catalogs.index', $catalog)
            ->with('success', $config['singular'].' agregada correctamente.');
    }

    public function update(Request $request, string $catalog, int $entry): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $config = $this->configFor($catalog);

        /** @var class-string<Model> $model */
        $model = $config['model'];
        $record = $model::query()->forCompany()->findOrFail($entry);

        $data = $this->validated($request, $model);
        $slug = $model::slugFor($data['name']);

        $clash = $model::query()->forCompany()->where('slug', $slug)->whereKeyNot($record->getKey())->exists();

        if ($clash) {
            throw ValidationException::withMessages([
                'name' => 'Ya existe otra entrada equivalente con ese nombre.',
            ]);
        }

        $record->fill($this->attributesFor($model, $data))->save();

        return to_route('purchase_catalogs.index', $catalog)
            ->with('success', 'Cambio guardado. Las solicitudes ya emitidas conservan el nombre que tenían.');
    }

    /**
     * Activa o desactiva. Nunca elimina: las solicitudes históricas pueden
     * apuntar a esta entrada.
     */
    public function toggle(Request $request, string $catalog, int $entry): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $config = $this->configFor($catalog);

        /** @var class-string<Model> $model */
        $model = $config['model'];
        $record = $model::query()->forCompany()->findOrFail($entry);

        $record->forceFill(['is_active' => ! $record->is_active])->save();

        return to_route('purchase_catalogs.index', $catalog)->with(
            'success',
            $record->is_active
                ? '«'.$record->name.'» vuelve a estar disponible.'
                : '«'.$record->name.'» ya no se ofrecerá en solicitudes nuevas. Las anteriores no cambian.',
        );
    }

    /** @param class-string<Model> $model */
    private function validated(Request $request, string $model): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];

        if ($model === UnitOfMeasure::class) {
            $rules['code'] = ['required', 'string', 'max:40'];
            $rules['allows_decimals'] = ['nullable', 'boolean'];
            // La unidad equivalente en Odoo. Opcional: sin ella la línea entra
            // con la de por defecto y la unidad real viaja en la descripción.
            $rules['odoo_uom_id'] = ['nullable', 'integer', 'min:1'];
        }

        $validated = $request->validate($rules, [], [
            'name' => 'el nombre',
            'code' => 'la abreviatura',
            'sort_order' => 'el orden',
            'odoo_uom_id' => 'la unidad de Odoo',
        ]);

        $validated['name'] = trim($validated['name']);

        return $validated;
    }

    /** @param class-string<Model> $model */
    private function attributesFor(string $model, array $data): array
    {
        $attributes = [
            'company_code' => 'EHE',
            'name' => $data['name'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => true,
        ];

        if ($model === UnitOfMeasure::class) {
            $attributes['code'] = trim($data['code']);
            $attributes['allows_decimals'] = (bool) ($data['allows_decimals'] ?? false);
            $attributes['odoo_uom_id'] = filled($data['odoo_uom_id'] ?? null) ? (int) $data['odoo_uom_id'] : null;
        }

        return $attributes;
    }

    /** @return array{model: class-string<Model>, singular: string, plural: string, hint: string} */
    private function configFor(string $catalog): array
    {
        return self::catalogs()[$catalog] ?? abort(404);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless(
            $request->user()?->canAdministerPurchaseCatalogs() ?? false,
            403,
            'Sólo el administrador mantiene los catálogos.',
        );
    }
}
