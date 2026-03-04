<?php

namespace App\Http\Controllers;

use App\Services\DteGeneratorService;
use App\Services\GmailDteInventoryService;
use App\Services\InventoryConfigService;
use App\Services\SiiClientService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class GmailInventoryController extends Controller
{
    private function db()
    {
        return DB::connection('fuelcontrol');
    }

    // GET /gmail/inventario/salida
    public function exitCreate()
    {
        return view('gmail.inventory.exit_form');
    }

    /**
     * Muestra el dashboard de configuraciones y estado del SII.
     *
     * @param InventoryConfigService $settings
     * @return \Illuminate\View\View
     */
    public function siiStatus(InventoryConfigService $settings): \Illuminate\View\View
    {
        $cafDisk = (string) config('dte.caf_disk', 'local');
        $cafPath = (string) config('dte.caf_paths.33', 'caf/caf_33.xml');
        $pfxDisk = (string) config('dte.signature.disk', 'local');
        $pfxPath = (string) config('dte.signature.pfx_path', '');

        $cafExists = $cafPath !== '' && Storage::disk($cafDisk)->exists($cafPath);
        $pfxExists = $pfxPath !== '' && Storage::disk($pfxDisk)->exists($pfxPath);
        $isRealMode = $cafExists && $pfxExists;

        return view('gmail.inventory.configuraciones', [
            'cafDisk' => $cafDisk,
            'cafPath' => $cafPath,
            'cafExists' => $cafExists,
            'pfxDisk' => $pfxDisk,
            'pfxPath' => $pfxPath,
            'pfxExists' => $pfxExists,
            'isRealMode' => $isRealMode,
            'seedUrl' => config('dte.sii.endpoints.seed') ? true : false,
            'tokenUrl' => config('dte.sii.endpoints.token') ? true : false,
            'recepcionUrl' => config('dte.sii.endpoints.recepcion') ? true : false,
            'estadoUrl' => config('dte.sii.endpoints.estado') ? true : false,
            'lowStockEmails'    => implode(', ', $settings->getLowStockEmails()),
            'hasPfxPassword'    => $settings->getDtePfxPassword() !== null,
            'fuelMinimoDiesel'  => $settings->getFuelMinimo('diesel'),
            'fuelMinimoGasolina'=> $settings->getFuelMinimo('gasolina'),
        ]);
    }

    /**
     * Actualiza la configuración guardada sobre SII.
     *
     * @param Request $request
     * @param InventoryConfigService $settings
     * @return \Illuminate\Http\RedirectResponse
     */
    public function siiConfigUpdate(Request $request, InventoryConfigService $settings): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'low_stock_emails'           => 'nullable|string|max:2000',
            'dte_signature_pfx_password' => 'nullable|string|max:255',
            'fuel_minimo_diesel'         => 'nullable|numeric|min:0',
            'fuel_minimo_gasolina'       => 'nullable|numeric|min:0',
        ]);

        $emails = trim((string) ($validated['low_stock_emails'] ?? ''));
        $settings->set('low_stock_emails', $emails);

        $pwd = (string) ($validated['dte_signature_pfx_password'] ?? '');
        if ($pwd !== '') {
            $settings->setDtePfxPassword($pwd);
        }

        if (isset($validated['fuel_minimo_diesel'])) {
            $settings->set('fuel_minimo_diesel', (string) max(0.0, (float) $validated['fuel_minimo_diesel']));
        }

        if (isset($validated['fuel_minimo_gasolina'])) {
            $settings->set('fuel_minimo_gasolina', (string) max(0.0, (float) $validated['fuel_minimo_gasolina']));
        }

        return back()->with('success', 'Configuraciones actualizadas.');
    }

    /**
     * Procesa la subida del archivo CAF (Certificate Authorization Factor).
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function uploadCaf(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'caf_file' => 'required|file|mimes:xml|max:10240',
        ]);

        $disk = (string) config('dte.caf_disk', 'local');
        $path = (string) config('dte.caf_paths.33', 'caf/caf_33.xml');
        $dir = trim(dirname($path), '.');
        if ($dir !== '') {
            Storage::disk($disk)->makeDirectory($dir);
        }
        Storage::disk($disk)->put($path, file_get_contents($request->file('caf_file')->getRealPath()));

        return back()->with('success', 'CAF cargado correctamente.');
    }

    /**
     * Sube y valida el certificado PFX para operaciones del SII.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function uploadPfx(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'pfx_file' => 'required|file|max:10240',
        ]);

        $disk = (string) config('dte.signature.disk', 'local');
        $path = (string) config('dte.signature.pfx_path', 'certs/dte_certificacion.pfx');
        $dir = trim(dirname($path), '.');
        if ($dir !== '') {
            Storage::disk($disk)->makeDirectory($dir);
        }
        Storage::disk($disk)->put($path, file_get_contents($request->file('pfx_file')->getRealPath()));

        return back()->with('success', 'Certificado PFX cargado correctamente.');
    }


    private function resolveQuickRange(string $range): array
    {
        return match ($range) {
            'today' => [now()->toDateString(), now()->toDateString()],
            '7d' => [now()->subDays(6)->toDateString(), now()->toDateString()],
            '30d' => [now()->subDays(29)->toDateString(), now()->toDateString()],
            default => [null, null],
        };
    }

    /**
     * API - Devuelve productos de inventario.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function productsApi(Request $request): \Illuminate\Http\JsonResponse
    {
        $q     = trim((string) $request->query('q', ''));
        $limit = min(50, max(1, (int) $request->query('limit', 6)));

        $withStock = (string) $request->query('with_stock', '1');

        $query = $this->db()
            ->table('gmail_inventory_products')
            ->where('is_active', 1)
            ->when($withStock !== '0', fn($qb) => $qb->where('stock_actual', '>', 0))
            ->orderBy('nombre')
            ->limit($limit);

        if ($q !== '') {
            $query->where(function ($qb) use ($q) {
                $qb->where('nombre', 'like', "%{$q}%")
                   ->orWhere('codigo', 'like', "%{$q}%");
            });
        }

        $products = $query->get(['id', 'nombre', 'codigo', 'unidad', 'stock_actual', 'costo_promedio']);

        return response()->json($products);
    }

    /**
     * API - Crea un producto desde el modal.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createProductApi(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'codigo' => 'nullable|string|max:100',
            'unidad' => 'nullable|string|max:20',
        ]);

        $nombre = trim($validated['nombre']);
        $unidad = strtoupper(trim($validated['unidad'] ?? 'UN')) ?: 'UN';
        $codigo = trim($validated['codigo'] ?? '') ?: null;

        // Si ya existe con mismo nombre+unidad, devolvemos el existente
        $existing = $this->db()
            ->table('gmail_inventory_products')
            ->where('nombre', $nombre)
            ->where('unidad', $unidad)
            ->first();

        if ($existing) {
            return response()->json([
                'id'             => $existing->id,
                'nombre'         => $existing->nombre,
                'codigo'         => $existing->codigo,
                'unidad'         => $existing->unidad,
                'stock_actual'   => $existing->stock_actual,
                'costo_promedio' => $existing->costo_promedio,
                'already_existed' => true,
            ]);
        }

        $id = $this->db()->table('gmail_inventory_products')->insertGetId([
            'nombre'         => $nombre,
            'codigo'         => $codigo,
            'unidad'         => $unidad,
            'stock_actual'   => 0,
            'costo_promedio' => 0,
            'is_active'      => 1,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $product = $this->db()->table('gmail_inventory_products')->find($id);

        return response()->json([
            'id'             => $product->id,
            'nombre'         => $product->nombre,
            'codigo'         => $product->codigo,
            'unidad'         => $product->unidad,
            'stock_actual'   => $product->stock_actual,
            'costo_promedio' => $product->costo_promedio,
            'already_existed' => false,
        ], 201);
    }

    /**
     * API - Obtiene los destinatarios.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function destinatariosApi(Request $request): \Illuminate\Http\JsonResponse
    {
        $q    = trim((string) $request->query('q', ''));
        $tipo = trim((string) $request->query('tipo', ''));

        $query = $this->db()
            ->table('gmail_inventory_movements')
            ->where('tipo', 'SALIDA')
            ->whereNotNull('destinatario')
            ->where('destinatario', '!=', '')
            ->selectRaw('destinatario, max(created_at) as last_used')
            ->groupBy('destinatario')
            ->orderByDesc('last_used')
            ->limit(6);

        if ($tipo !== '') {
            $query->where('tipo_salida', $tipo);
        }
        if ($q !== '') {
            $query->where('destinatario', 'like', "%{$q}%");
        }

        return response()->json($query->pluck('destinatario'));
    }

    /**
     * API - Muestra los lotes asociados a un producto.
     *
     * @param int $productId
     * @return \Illuminate\Http\JsonResponse
     */
    public function lotsApi(int $productId): \Illuminate\Http\JsonResponse
    {
        $lots = $this->db()
            ->table('gmail_inventory_lots')
            ->where('product_id', $productId)
            ->where('estado', 'ABIERTO')
            ->where('cantidad_disponible', '>', 0)
            ->orderBy('ingresado_el')
            ->orderBy('id')
            ->get(['id', 'ingresado_el', 'costo_unitario', 'cantidad_disponible']);

        return response()->json($lots);
    }


    /**
     * API - Obtiene la lista de contactos.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function contactsApi(Request $request): \Illuminate\Http\JsonResponse
    {
        $q    = trim((string) $request->query('q', ''));
        $tipo = trim((string) $request->query('tipo', ''));

        $query = $this->db()
            ->table('gmail_inventory_contacts')
            ->orderByDesc('updated_at')
            ->limit(8);

        if ($tipo !== '') {
            $query->where('tipo', $tipo);
        }
        if ($q !== '') {
            $query->where(function ($qb) use ($q) {
                $qb->where('nombre', 'like', "%{$q}%")
                   ->orWhere('rut', 'like', "%{$q}%")
                   ->orWhere('empresa', 'like', "%{$q}%");
            });
        }

        return response()->json(
            $query->get(['id', 'tipo', 'nombre', 'rut', 'empresa', 'cargo', 'area', 'telefono', 'email'])
        );
    }


    /**
     * Guarda un nuevo contacto vía API.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function contactStore(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'id'       => 'nullable|integer|exists:fuelcontrol.gmail_inventory_contacts,id',
            'rut'      => 'nullable|string|max:50',
            'nombre'   => 'required|string|max:255',
            'giro'     => 'nullable|string|max:255',
            'direccion'=> 'nullable|string|max:255',
            'comuna'   => 'nullable|string|max:100',
            'tipo'     => 'required|string|in:PROVEEDOR,CLIENTE,INTERNO',
            'email'    => 'nullable|email|max:255',
            'telefono' => 'nullable|string|max:50',
        ]);

        if (empty($validated['id'])) {
            unset($validated['id']);
            $contactId = $this->db()->table('gmail_inventory_contacts')->insertGetId([
                ...$validated,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $contactId = $validated['id'];
            unset($validated['id']);
            $this->db()->table('gmail_inventory_contacts')->where('id', $contactId)->update([
                ...$validated,
                'updated_at' => now(),
            ]);
        }

        return response()->json(
            $this->db()->table('gmail_inventory_contacts')->find($contactId)
        );
    }

    /**
     * Muestra la valoración actual del stock (Valorizado).
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function stockValuation(Request $request): \Illuminate\View\View
    {
        $q = trim((string) $request->query('q', ''));

        $products = $this->db()
            ->table('gmail_inventory_products')
            ->where('is_active', true)
            ->when($q !== '', fn($query) => $query->where(function ($sub) use ($q) {
                $sub->where('nombre', 'like', "%{$q}%")
                    ->orWhere('codigo', 'like', "%{$q}%");
            }))
            ->orderByDesc(DB::raw('stock_actual * costo_promedio'))
            ->get(['id', 'nombre', 'codigo', 'unidad', 'stock_actual', 'costo_promedio', 'stock_minimo']);

        $totalValor      = $products->sum(fn($p) => (float)$p->stock_actual * (float)$p->costo_promedio);
        $totalProductos  = $products->count();
        $totalConStock   = $products->where('stock_actual', '>', 0)->count();
        $totalBajoMinimo = $products->filter(fn($p) => $p->stock_minimo !== null && (float)$p->stock_actual < (float)$p->stock_minimo)->count();

        return view('gmail.inventory.stock_valuation', compact(
            'products', 'totalValor', 'totalProductos', 'totalConStock', 'totalBajoMinimo', 'q'
        ));
    }

}