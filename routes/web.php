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
use App\Http\Controllers\Inventario\DashboardController;
use App\Http\Controllers\CentroController;

/*
|--------------------------------------------------------------------------
| HOME
|--------------------------------------------------------------------------
*/
Route::get('/', [DashboardController::class, 'index'])
    ->middleware('auth')
    ->name('index');

/*
/*
|--------------------------------------------------------------------------
| INVENTARIO (todos los usuarios autenticados)
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

        // =======================
// DTEs (Gmail primero)
// =======================
    
        Route::get('/dtes/gmail', [DtesController::class, 'gmailIndex'])
            ->name('dtes.gmail');

        Route::post('/dtes/gmail/import', [DtesController::class, 'gmailImportSelected'])
            ->name('dtes.gmail.import');

        // Importar desde Gmail (por message id)
        Route::post('/dtes/import', [DtesController::class, 'importFromGmail'])
            ->name('dtes.import');

        // DTEs guardados en BD
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
    ->get('/dashboard', function () {

        $movimientos = DB::table('users')
            ->orderBy('id', 'desc')
            ->limit(20)
            ->get();

        return view('dashboard', compact('movimientos'));

    })->name('dashboard');

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
});

/*
|--------------------------------------------------------------------------
| AUTH (Breeze)
|--------------------------------------------------------------------------
*/
require __DIR__ . '/auth.php';


/*
|--------------------------------------------------------------------------
| DOCUMENTOS DE COMPRA (API)
|--------------------------------------------------------------------------
*/


Route::middleware(['auth', 'role:admin,operator'])
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



/*|--------------------------------------------------------------------------
| IMPORTAR PDF DE DOCUMENTOS DE COMPRA
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:admin,operator'])->group(function () {

    Route::get('/pdf', [PdfImportController::class, 'index'])->name('pdf.index');

    Route::get('/pdf/import', [PdfImportController::class, 'create'])->name('pdf.import.form');
    Route::post('/pdf/import', [PdfImportController::class, 'store'])->name('pdf.import');

    Route::get('/pdf/imports/{id}', [PdfImportController::class, 'showJson'])
        ->whereNumber('id')
        ->name('pdf.import.show');

    Route::get('/pdf/imports/{id}/archivo', [PdfImportController::class, 'ver'])
        ->whereNumber('id')
        ->name('pdf.show');

    Route::get('/pdf/imports/{id}/ver', [PdfImportController::class, 'show'])
        ->whereNumber('id')
        ->name('pdf.show');

    Route::get('/pdf/imports/export.xlsx', [PdfImportController::class, 'exportXlsx'])
        ->name('pdf.export.xlsx');

    Route::get('/excel/import', [PdfImportController::class, 'excelForm'])->name('excel.import.form');

    Route::post('/excel/import', [PdfImportController::class, 'importExcelQc'])->name('excel.import.qc');

    Route::post('/excel/import/rfp', [PdfImportController::class, 'importExcelRfp'])
        ->name('excel.import.rfp');

    Route::post('/pdf/import/xml', [PdfImportController::class, 'storeXml'])
        ->name('pdf.import.xml');

    Route::get('/excel-out-transfers', [ExcelOutTransferController::class, 'index'])
        ->name('excel_out_transfers.index');

    Route::get('/excel-out-transfers/import', fn() => view('excel_out_transfers.import'))
        ->name('excel_out_transfers.form');

    Route::post('/excel-out-transfers/import', [ExcelOutTransferController::class, 'importExcelOutTransfers'])
        ->name('excel_out_transfers.import');


    Route::get('/excel-out-transfers/export', [ExcelOutTransferController::class, 'export'])
        ->name('excel_out_transfers.export');

    Route::get('/excel-out-transfers/{transfer}', [ExcelOutTransferController::class, 'show'])
        ->name('excel_out_transfers.show');



});
/*
|--------------------------------------------------------------------------
| AGRAK REGISTROS IMPORT
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:admin,operator'])
    ->prefix('agrak')
    ->name('agrak.')
    ->group(function () {

        Route::get('/', [AgrakController::class, 'index'])->name('index');

        Route::get('/{id}', [AgrakController::class, 'show'])
            ->whereNumber('id')
            ->name('show');

        Route::get('/import', fn() => view('agrak.import'))
            ->name('import.form');




        Route::post('/import', [PdfImportController::class, 'importExcelAgrak'])
            ->name('import');
    });

/* ======================================================
 |  VISTA GUiAS COMFRUT REGISTROS (COMFRUT CONTROLLER)
 ======================================================*/

Route::prefix('guias')->name('guias.')->middleware('auth')->group(function () {

    Route::prefix('comfrut')->name('comfrut.')->group(function () {

        Route::get('/', [ComfrutGuiaController::class, 'index'])
            ->name('index');

        Route::get('/import', [ComfrutGuiaController::class, 'importForm'])
            ->name('import.form');

        Route::post('/import', [ComfrutGuiaController::class, 'import'])
            ->name('import');

        Route::get('/export-php', [ComfrutGuiaController::class, 'exportExcelPhpSpreadsheet'])->name('export-php');

        // ✅ SHOW CORRECTO
        Route::get('/{guia}', [ComfrutGuiaController::class, 'show'])
            ->name('show');
    });
});




Route::get('/agrak/create', [CamionController::class, 'create'])
    ->name('agrak.create');

Route::post('/agrak', [CamionController::class, 'store'])
    ->name('agrak.store');

Route::get('/agrak/export', [AgrakExportController::class, 'exportAll'])
    ->name('agrak.export');


Route::middleware('auth')->group(function () {

    Route::post('/agrak/kg-promedio', [DashboardController::class, 'updateKgPromedio'])
        ->name('agrak.kg-promedio');

    Route::get('/centros/detalle', [CentroController::class, 'show'])
        ->name('centros.detalle');

});

