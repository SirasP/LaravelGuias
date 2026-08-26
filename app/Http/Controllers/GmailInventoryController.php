<?php

namespace App\Http\Controllers;

use App\Services\InventoryConfigService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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

        // Cargar estado del token de Banco de Chile
        $bcTokenRow = DB::table('banco_chile_tokens')
            ->where('activo', true)
            ->latest()
            ->first();
        $bcTokenActivo = $bcTokenRow !== null;
        $bcTokenExpira = $bcTokenRow && $bcTokenRow->expires_at
            ? \Carbon\Carbon::parse($bcTokenRow->expires_at)->diffForHumans()
            : 'Desconocido';
        $bcTokenGuardado = $bcTokenActivo
            ? substr($bcTokenRow->token, 0, 30).'...'
            : null;

        // Cargar variables de ambiente activo
        $bcEnv = $settings->get('banco_chile_env', 'qa');

        // QA CONFIGS (Fallback a valores de prueba del código)
        $qaOdooUrl = $settings->get('qa_odoo_url', 'https://agricolaehe-prueba-31455293.dev.odoo.com');
        $qaOdooDb = $settings->get('qa_odoo_db', 'agricolaehe-prueba-31455293');
        $qaOdooUser = $settings->get('qa_odoo_user', 's.lopez.epple@gmail.com');
        $qaOdooPassword = $settings->get('qa_odoo_password', '1234');
        $qaOdooJournalId = $settings->get('qa_odoo_journal_id', '22');
        $qaBcClientId = $settings->get('qa_bc_client_id', '721816d1e407fb656e73374a21bc9ebb');
        $qaBcClientSecret = $settings->get('qa_bc_client_secret', '93cac5b5a54a51d685aba881c6f2d872');
        $qaBcApiUrl = $settings->get('qa_bc_api_url', 'https://gw.apistore.bancochile.cl/banco-chile/sandbox/v1/movimientos-cuenta/obtener');
        $qaBcCuentaOrigen = $settings->get('qa_bc_cuenta_origen', '902252590600');
        $qaBcRutOrigen = $settings->get('qa_bc_rut_origen', '90512020-3');
        $qaBcUsuario = $settings->get('qa_bc_usuario', 'userCli');
        $qaBcRutApoderado = $settings->get('qa_bc_rut_apoderado', '1-9');

        // PRODUCTION CONFIGS (Fallback a variables del .env)
        $prodOdooUrl = $settings->get('prod_odoo_url', config('services.odoo.url', 'https://agricolaehe.odoo.com'));
        $prodOdooDb = $settings->get('prod_odoo_db', config('services.odoo.db', 'beluckycl-agricolaehe-main-22926049'));
        $prodOdooUser = $settings->get('prod_odoo_user', config('services.odoo.user', 's.lopez.epple@gmail.com'));
        $prodOdooPassword = $settings->get('prod_odoo_password', config('services.odoo.password', '1234'));
        $prodOdooJournalId = $settings->get('prod_odoo_journal_id', config('services.odoo.journal_id', '22'));
        $prodBcClientId = $settings->get('prod_bc_client_id', config('services.banco_chile.client_id', ''));
        $prodBcClientSecret = $settings->get('prod_bc_client_secret', config('services.banco_chile.client_secret', ''));
        $prodBcApiUrl = $settings->get('prod_bc_api_url', 'https://gw.apistore.bancochile.cl/banco-chile/v1/movimientos-cuenta/obtener');
        $prodBcCuentaOrigen = $settings->get('prod_bc_cuenta_origen', config('services.banco_chile.cuenta_origen', ''));
        $prodBcRutOrigen = $settings->get('prod_bc_rut_origen', config('services.banco_chile.rut_origen', ''));
        $prodBcUsuario = $settings->get('prod_bc_usuario', config('services.banco_chile.usuario', ''));
        $prodBcRutApoderado = $settings->get('prod_bc_rut_apoderado', config('services.banco_chile.rut_apoderado', ''));

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
            'lowStockEmails' => implode(', ', $settings->getLowStockEmails()),
            'hasPfxPassword' => $settings->getDtePfxPassword() !== null,
            'fuelMinimoDiesel' => $settings->getFuelMinimo('diesel'),
            'fuelMinimoGasolina' => $settings->getFuelMinimo('gasolina'),
            'bcTokenActivo' => $bcTokenActivo,
            'bcTokenExpira' => $bcTokenExpira,
            'bcTokenGuardado' => $bcTokenGuardado,

            // Ambientes
            'bcEnv' => $bcEnv,
            'qaOdooUrl' => $qaOdooUrl,
            'qaOdooDb' => $qaOdooDb,
            'qaOdooUser' => $qaOdooUser,
            'qaOdooPassword' => $qaOdooPassword,
            'qaOdooJournalId' => $qaOdooJournalId,
            'qaBcClientId' => $qaBcClientId,
            'qaBcClientSecret' => $qaBcClientSecret,
            'qaBcApiUrl' => $qaBcApiUrl,
            'qaBcCuentaOrigen' => $qaBcCuentaOrigen,
            'qaBcRutOrigen' => $qaBcRutOrigen,
            'qaBcUsuario' => $qaBcUsuario,
            'qaBcRutApoderado' => $qaBcRutApoderado,

            'prodOdooUrl' => $prodOdooUrl,
            'prodOdooDb' => $prodOdooDb,
            'prodOdooUser' => $prodOdooUser,
            'prodOdooPassword' => $prodOdooPassword,
            'prodOdooJournalId' => $prodOdooJournalId,
            'prodBcClientId' => $prodBcClientId,
            'prodBcClientSecret' => $prodBcClientSecret,
            'prodBcApiUrl' => $prodBcApiUrl,
            'prodBcCuentaOrigen' => $prodBcCuentaOrigen,
            'prodBcRutOrigen' => $prodBcRutOrigen,
            'prodBcUsuario' => $prodBcUsuario,
            'prodBcRutApoderado' => $prodBcRutApoderado,
        ]);
    }

    /**
     * Actualiza la configuración guardada sobre SII y Banco de Chile.
     */
    public function siiConfigUpdate(Request $request, InventoryConfigService $settings): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'low_stock_emails' => 'nullable|string|max:2000',
            'dte_signature_pfx_password' => 'nullable|string|max:255',
            'fuel_minimo_diesel' => 'nullable|numeric|min:0',
            'fuel_minimo_gasolina' => 'nullable|numeric|min:0',

            // Multiambiente Banco de Chile
            'banco_chile_env' => 'nullable|string|in:qa,production',

            // QA
            'qa_odoo_url' => 'nullable|string|max:255',
            'qa_odoo_db' => 'nullable|string|max:255',
            'qa_odoo_user' => 'nullable|string|max:255',
            'qa_odoo_password' => 'nullable|string|max:255',
            'qa_odoo_journal_id' => 'nullable|integer',
            'qa_bc_client_id' => 'nullable|string|max:255',
            'qa_bc_client_secret' => 'nullable|string|max:255',
            'qa_bc_api_url' => 'nullable|string|max:500',
            'qa_bc_cuenta_origen' => 'nullable|string|max:255',
            'qa_bc_rut_origen' => 'nullable|string|max:255',
            'qa_bc_usuario' => 'nullable|string|max:255',
            'qa_bc_rut_apoderado' => 'nullable|string|max:255',

            // PROD
            'prod_odoo_url' => 'nullable|string|max:255',
            'prod_odoo_db' => 'nullable|string|max:255',
            'prod_odoo_user' => 'nullable|string|max:255',
            'prod_odoo_password' => 'nullable|string|max:255',
            'prod_odoo_journal_id' => 'nullable|integer',
            'prod_bc_client_id' => 'nullable|string|max:255',
            'prod_bc_client_secret' => 'nullable|string|max:255',
            'prod_bc_api_url' => 'nullable|string|max:500',
            'prod_bc_cuenta_origen' => 'nullable|string|max:255',
            'prod_bc_rut_origen' => 'nullable|string|max:255',
            'prod_bc_usuario' => 'nullable|string|max:255',
            'prod_bc_rut_apoderado' => 'nullable|string|max:255',
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

        // Guardar variables del Banco
        if (isset($validated['banco_chile_env'])) {
            $settings->set('banco_chile_env', $validated['banco_chile_env']);
        }

        // QA
        foreach (['qa_odoo_url', 'qa_odoo_db', 'qa_odoo_user', 'qa_odoo_password', 'qa_odoo_journal_id', 'qa_bc_client_id', 'qa_bc_client_secret', 'qa_bc_api_url', 'qa_bc_cuenta_origen', 'qa_bc_rut_origen', 'qa_bc_usuario', 'qa_bc_rut_apoderado'] as $key) {
            if (isset($validated[$key])) {
                $settings->set($key, trim($validated[$key]));
            }
        }

        // PROD
        foreach (['prod_odoo_url', 'prod_odoo_db', 'prod_odoo_user', 'prod_odoo_password', 'prod_odoo_journal_id', 'prod_bc_client_id', 'prod_bc_client_secret', 'prod_bc_api_url', 'prod_bc_cuenta_origen', 'prod_bc_rut_origen', 'prod_bc_usuario', 'prod_bc_rut_apoderado'] as $key) {
            if (isset($validated[$key])) {
                $settings->set($key, trim($validated[$key]));
            }
        }

        return back()->with('success', 'Configuraciones actualizadas.');
    }

    /**
     * Procesa la subida del archivo CAF (Certificate Authorization Factor).
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
     */
    public function productsApi(Request $request): \Illuminate\Http\JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $limit = min(50, max(1, (int) $request->query('limit', 6)));

        $withStock = (string) $request->query('with_stock', '1');

        $query = $this->db()
            ->table('gmail_inventory_products')
            ->where('is_active', 1)
            ->when($withStock !== '0', fn ($qb) => $qb->where('stock_actual', '>', 0))
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
                'id' => $existing->id,
                'nombre' => $existing->nombre,
                'codigo' => $existing->codigo,
                'unidad' => $existing->unidad,
                'stock_actual' => $existing->stock_actual,
                'costo_promedio' => $existing->costo_promedio,
                'already_existed' => true,
            ]);
        }

        $id = $this->db()->table('gmail_inventory_products')->insertGetId([
            'nombre' => $nombre,
            'codigo' => $codigo,
            'unidad' => $unidad,
            'stock_actual' => 0,
            'costo_promedio' => 0,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $product = $this->db()->table('gmail_inventory_products')->find($id);

        return response()->json([
            'id' => $product->id,
            'nombre' => $product->nombre,
            'codigo' => $product->codigo,
            'unidad' => $product->unidad,
            'stock_actual' => $product->stock_actual,
            'costo_promedio' => $product->costo_promedio,
            'already_existed' => false,
        ], 201);
    }

    /**
     * API - Obtiene los destinatarios.
     */
    public function destinatariosApi(Request $request): \Illuminate\Http\JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
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
     */
    public function contactsApi(Request $request): \Illuminate\Http\JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
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
     */
    public function contactStore(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|integer|exists:fuelcontrol.gmail_inventory_contacts,id',
            'tipo' => 'required|string|in:cliente,trabajador,destinatario',
            'nombre' => 'required|string|max:200',
            'rut' => 'nullable|string|max:30',
            'empresa' => 'nullable|string|max:200',
            'cargo' => 'nullable|string|max:100',
            'area' => 'nullable|string|max:100',
            'telefono' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:200',
            'notas' => 'nullable|string|max:1000',
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
     */
    public function stockValuation(Request $request): \Illuminate\View\View
    {
        $q = trim((string) $request->query('q', ''));

        $products = $this->db()
            ->table('gmail_inventory_products')
            ->where('is_active', true)
            ->when($q !== '', fn ($query) => $query->where(function ($sub) use ($q) {
                $sub->where('nombre', 'like', "%{$q}%")
                    ->orWhere('codigo', 'like', "%{$q}%");
            }))
            ->orderByDesc(DB::raw('stock_actual * costo_promedio'))
            ->get(['id', 'nombre', 'codigo', 'unidad', 'stock_actual', 'costo_promedio', 'stock_minimo']);

        $totalValor = $products->sum(fn ($p) => (float) $p->stock_actual * (float) $p->costo_promedio);
        $totalProductos = $products->count();
        $totalConStock = $products->where('stock_actual', '>', 0)->count();
        $totalBajoMinimo = $products->filter(fn ($p) => $p->stock_minimo !== null && (float) $p->stock_actual < (float) $p->stock_minimo)->count();

        return view('gmail.inventory.stock_valuation', compact(
            'products', 'totalValor', 'totalProductos', 'totalConStock', 'totalBajoMinimo', 'q'
        ));
    }
}
