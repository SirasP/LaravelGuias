<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Inventario\DtesController;
use App\Http\Controllers\PdfImportController;
use App\Http\Controllers\AgrakController;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\ComprasController;
use App\Http\Controllers\Guias\ComfrutGuiaController;
use App\Http\Controllers\ExcelOutTransferController;
use App\Http\Controllers\CamionController;
use App\Http\Controllers\AgrakExportController;
use App\Http\Controllers\CentroController;
use App\Http\Controllers\Inventario\DashboardController as InventarioDashboard;
use App\Http\Controllers\FuelControl\DashboardController as FuelDashboard;
use App\Http\Controllers\FuelControl\ProductoController;
use App\Http\Controllers\FuelControl\VehiculoController;
use App\Http\Controllers\FuelControl\MovimientoController;
use App\Http\Controllers\GmailAuthController;


/*
|--------------------------------------------------------------------------
| HOME
|--------------------------------------------------------------------------
*/
Route::get('/', [InventarioDashboard::class, 'index'])
    ->middleware('auth')
    ->name('index');

/*
|--------------------------------------------------------------------------
| INVENTARIO (solo ADMIN)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin'])
    ->prefix('inventario')
    ->name('inventario.')
    ->group(function () {

        Route::get('/', function () {
            return view('index');
        })->name('index');

        Route::get('/productos', [App\Http\Controllers\Inventario\ProductosController::class, 'index'])->name('productos');
        Route::post('/productos', [App\Http\Controllers\Inventario\ProductosController::class, 'store'])->name('productos.store');

        Route::get('/categorias', function () {
            return view('inventario.categorias');
        })->name('categorias');

        Route::get('/movimientos', function () {
            return view('inventario.movimientos');
        })->name('movimientos');

        Route::get('/stock', [App\Http\Controllers\Inventario\StockController::class, 'index'])
            ->name('stock');

        Route::get('/stock/entrada', [App\Http\Controllers\Inventario\StockEntradaController::class, 'create'])
            ->name('stock.entrada');

        Route::post('/stock/entrada', [App\Http\Controllers\Inventario\StockEntradaController::class, 'store'])
            ->name('stock.entrada.store');

        Route::patch('/productos/{id}/toggle', [App\Http\Controllers\Inventario\ProductosController::class, 'toggle'])
            ->name('productos.toggle');

        Route::get('/dte', function () {
            return view('inventario.dte');
        })->name('dte');

        Route::post('/dtes/{dte}/detalles/seleccion', [DtesController::class, 'updateDetallesSelection'])
            ->name('dtes.detalles.updateSelection');

        Route::post('/dtes/{dte}/inventario/ingresar', [DtesController::class, 'ingresarSeleccionadosInventario'])
            ->name('dtes.inventario.ingresar');

        Route::get('dte/ver/{id}', [\App\Http\Controllers\Inventario\DteController::class, 'ver'])
            ->name('dte.ver');

        Route::get('/dtes/gmail', [DtesController::class, 'gmailIndex'])
            ->name('dtes.gmail');

        Route::post('/dtes/gmail/import', [DtesController::class, 'gmailImportSelected'])
            ->name('dtes.gmail.import');

        Route::post('/dtes/import', [DtesController::class, 'importFromGmail'])
            ->name('dtes.import');

        Route::get('/dtes', [DtesController::class, 'index'])
            ->name('dtes.index');

        Route::get('/dtes/{dte}', [DtesController::class, 'show'])
            ->whereNumber('dte')
            ->name('dtes.show');
    });

/*
|--------------------------------------------------------------------------
| GOOGLE OAUTH
|--------------------------------------------------------------------------
*/
Route::get('/google/oauth/token', function () {
    return Cache::get('gmail_token') ?? 'NO TOKEN';
})->middleware('auth');

Route::get('/debug/google', function () {
    return response()->json([
        'client_id' => config('services.google.client_id'),
        'client_secret_set' => !empty(config('services.google.client_secret')),
        'redirect' => config('services.google.redirect'),
    ]);
});

Route::get('/debug/env', function () {
    return response()->json([
        'env_client_id' => env('GOOGLE_CLIENT_ID'),
        'cfg_client_id' => config('services.google.client_id'),
        'app_env' => app()->environment(),
    ]);
});

Route::get('/google/oauth/redirect', [App\Http\Controllers\GoogleOAuthController::class, 'redirect'])
    ->name('google.oauth.redirect');

Route::get('/google/oauth/callback', [App\Http\Controllers\GoogleOAuthController::class, 'callback'])
    ->name('google.oauth.callback');

Route::get('/inventario/dte/leer', [App\Http\Controllers\Inventario\DteController::class, 'leer'])
    ->name('inventario.dte.leer');

Route::get('/gmail/test', function () {
    return 'Laravel OK';
});

/*
|--------------------------------------------------------------------------
| USUARIOS / DASHBOARD (solo ADMIN)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin'])
    ->get('/usuarios', function () {
        $movimientos = DB::table('users')
            ->orderBy('id', 'desc')
            ->limit(20)
            ->get();

        return view('usuarios.index', compact('movimientos'));
    })->name('dashboard');

Route::middleware(['auth', 'role:admin'])
    ->get('/dashboard', function () {
        return redirect()->route('dashboard');
    });

/*
|--------------------------------------------------------------------------
| PERFIL (cualquier usuario autenticado)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/*
|--------------------------------------------------------------------------
| CRUD USUARIOS (solo ADMIN)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::patch('/users/{user}/toggle-active', [UserController::class, 'toggleActive'])
        ->name('users.toggleActive');
    Route::patch('/users/{user}/role', [UserController::class, 'updateRole'])
        ->name('users.updateRole');
});

/*
|--------------------------------------------------------------------------
| AUTH (Breeze)
|--------------------------------------------------------------------------
*/
require __DIR__ . '/auth.php';

/*
|--------------------------------------------------------------------------
| DOCUMENTOS — Vistas (todos los autenticados)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {

    // PDF - solo lectura
    Route::get('/pdf', [PdfImportController::class, 'index'])->name('pdf.index');
    Route::get('/pdf/imports/{id}/archivo', [PdfImportController::class, 'ver'])
        ->whereNumber('id')
        ->name('pdf.import.archivo');
    Route::get('/pdf/imports/{id}/ver', [PdfImportController::class, 'show'])
        ->whereNumber('id')
        ->name('pdf.import.ver');
    Route::get('/pdf/imports/{id}', [PdfImportController::class, 'showJson'])
        ->whereNumber('id')
        ->name('pdf.import.json');
    Route::get('/pdf/imports/export.xlsx', [PdfImportController::class, 'exportXlsx'])
        ->name('pdf.export.xlsx');

    // Guías ODOO - solo lectura
    Route::get('/excel-out-transfers', [ExcelOutTransferController::class, 'index'])
        ->name('excel_out_transfers.index');
    Route::get('/excel-out-transfers/export', [ExcelOutTransferController::class, 'export'])
        ->name('excel_out_transfers.export');
    Route::get('/excel-out-transfers/{transfer}', [ExcelOutTransferController::class, 'show'])
        ->name('excel_out_transfers.show');

});

/*
|--------------------------------------------------------------------------
| DOCUMENTOS — Importaciones (solo ADMIN)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin'])->group(function () {

    // PDF - importar
    Route::get('/pdf/import', [PdfImportController::class, 'create'])->name('pdf.import.form');
    Route::post('/pdf/import', [PdfImportController::class, 'store'])->name('pdf.import');
    Route::post('/pdf/import/xml', [PdfImportController::class, 'storeXml'])
        ->name('pdf.import.xml');

    // Excel QC / RFP
    Route::get('/excel/import', [PdfImportController::class, 'excelForm'])->name('excel.import.form');
    Route::post('/excel/import', [PdfImportController::class, 'importExcelQc'])->name('excel.import.qc');
    Route::post('/excel/import/rfp', [PdfImportController::class, 'importExcelRfp'])
        ->name('excel.import.rfp');

    // Guías ODOO - importar
    Route::get('/excel-out-transfers/import', fn() => view('excel_out_transfers.import'))
        ->name('excel_out_transfers.form');
    Route::post('/excel-out-transfers/import', [ExcelOutTransferController::class, 'importExcelOutTransfers'])
        ->name('excel_out_transfers.import');

});

/*
|--------------------------------------------------------------------------
| COMPRAS (solo ADMIN)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin'])
    ->prefix('compras')
    ->group(function () {

        Route::get('/documentos/crear', [ComprasController::class, 'formCrear'])
            ->name('compras.documentos.crear_form');

        Route::post('/documentos', [ComprasController::class, 'crear'])
            ->name('compras.documentos.crear');

        Route::get('/documentos/{id}', [ComprasController::class, 'ver'])
            ->name('compras.documentos.ver');

        Route::post('/documentos/{id}/lineas', [ComprasController::class, 'agregarLinea'])
            ->name('compras.documentos.lineas.agregar');

        Route::post('/documentos/{id}/contabilizar', [ComprasController::class, 'contabilizar'])
            ->name('compras.documentos.contabilizar');
    });

/*
|--------------------------------------------------------------------------
| AGRAK — Vistas (todos los autenticados)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')
    ->prefix('agrak')
    ->name('agrak.')
    ->group(function () {
        Route::get('/', [AgrakController::class, 'index'])->name('index');
        Route::get('/{id}', [AgrakController::class, 'show'])
            ->whereNumber('id')
            ->name('show');
    });

Route::middleware('auth')->group(function () {
    Route::get('/agrak/export', [AgrakExportController::class, 'exportAll'])
        ->name('agrak.export');
    Route::post('/agrak/kg-promedio', [InventarioDashboard::class, 'updateKgPromedio'])
        ->name('agrak.kg-promedio');
    Route::get('/centros/detalle', [CentroController::class, 'show'])
        ->name('centros.detalle');
});

/*
|--------------------------------------------------------------------------
| AGRAK — Importaciones (solo ADMIN)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin'])
    ->prefix('agrak')
    ->name('agrak.')
    ->group(function () {
        Route::get('/import', fn() => view('agrak.import'))
            ->name('import.form');
        Route::post('/import', [PdfImportController::class, 'importExcelAgrak'])
            ->name('import');
    });

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/agrak/create', [CamionController::class, 'create'])
        ->name('agrak.create');
    Route::post('/agrak', [CamionController::class, 'store'])
        ->name('agrak.store');
});

/*
|--------------------------------------------------------------------------
| GUÍAS COMFRUT — Vistas (todos los autenticados)
|--------------------------------------------------------------------------
*/
Route::prefix('guias')->name('guias.')->middleware('auth')->group(function () {

    Route::prefix('comfrut')->name('comfrut.')->group(function () {

        Route::get('/', [ComfrutGuiaController::class, 'index'])
            ->name('index');

        Route::get('/export-php', [ComfrutGuiaController::class, 'exportExcelPhpSpreadsheet'])
            ->name('export-php');

        Route::get('/{guia}', [ComfrutGuiaController::class, 'show'])
            ->name('show');
    });
});

/*
|--------------------------------------------------------------------------
| GUÍAS COMFRUT — Importaciones (solo ADMIN)
|--------------------------------------------------------------------------
*/
Route::prefix('guias')->name('guias.')->middleware(['auth', 'role:admin'])->group(function () {

    Route::prefix('comfrut')->name('comfrut.')->group(function () {

        Route::get('/import', [ComfrutGuiaController::class, 'importForm'])
            ->name('import.form');

        Route::post('/import', [ComfrutGuiaController::class, 'import'])
            ->name('import');
    });
});

/*
|--------------------------------------------------------------------------
| FUEL CONTROL (todos los autenticados)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])
    ->prefix('fuelcontrol')
    ->name('fuelcontrol.')
    ->group(function () {

        Route::get('/', [FuelDashboard::class, 'index'])
            ->name('index');
        Route::get('/export/vehiculos.xlsx', [FuelDashboard::class, 'exportVehiculosExcel'])
            ->name('export.vehiculos.xlsx');

        /* PRODUCTOS */
        Route::get('/productos', [ProductoController::class, 'index'])
            ->name('productos');
        Route::get('/productos/crear', [ProductoController::class, 'create'])
            ->name('productos.create');
        Route::post('/productos', [ProductoController::class, 'store'])
            ->name('productos.store');
        Route::get('/productos/{id}/editar', [ProductoController::class, 'edit'])
            ->name('productos.edit');
        Route::put('/productos/{id}', [ProductoController::class, 'update'])
            ->name('productos.update');
        Route::delete('/productos/{id}', [ProductoController::class, 'destroy'])
            ->name('productos.destroy');

        Route::post('/notificaciones/{id}/leer', function ($id) {
            DB::connection('fuelcontrol')
                ->table('notificacion_usuarios')
                ->where('notificacion_id', $id)
                ->where('user_id', auth()->id())
                ->update([
                    'leido' => 1,
                    'updated_at' => now()
                ]);
            return response()->json(['ok' => true]);
        })->name('notificaciones.leer');

        /* XML */
        Route::get('/xml/{movimiento}', [FuelDashboard::class, 'show'])
            ->name('xml.show');
        Route::post('/xml/{movimiento}/aprobar', [FuelDashboard::class, 'aprobar'])
            ->name('xml.aprobar');
        Route::post('/xml/{movimiento}/rechazar', [FuelDashboard::class, 'rechazar'])
            ->name('xml.rechazar');

        /* VEHÍCULOS */
        Route::get('/vehiculos', [VehiculoController::class, 'index'])
            ->name('vehiculos.index');
        Route::post('/vehiculos', [VehiculoController::class, 'store'])
            ->name('vehiculos.store');
        Route::put('/vehiculos/{id}', [VehiculoController::class, 'update'])
            ->whereNumber('id')
            ->name('vehiculos.update');
        Route::patch('/vehiculos/{id}/toggle-active', [VehiculoController::class, 'toggleActive'])
            ->whereNumber('id')
            ->name('vehiculos.toggleActive');
        Route::delete('/vehiculos/{id}', [VehiculoController::class, 'destroy'])
            ->whereNumber('id')
            ->name('vehiculos.destroy');

        /* MOVIMIENTOS */
        Route::get('/movimientos', [MovimientoController::class, 'index'])
            ->name('movimientos');
    });

/*
|--------------------------------------------------------------------------
| GMAIL (solo ADMIN)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin'])->prefix('gmail')->name('gmail.')->group(function () {

    Route::get('/', [GmailAuthController::class, 'index'])->name('index');
    Route::get('/connect', [GmailAuthController::class, 'redirect'])->name('redirect');
    Route::get('/callback', [GmailAuthController::class, 'callback'])->name('callback');
    Route::delete('/disconnect', [GmailAuthController::class, 'disconnect'])->name('disconnect');
    Route::post('/run', [GmailAuthController::class, 'runNow'])->name('run');
    Route::get('/status', [GmailAuthController::class, 'status'])->name('status');

});
