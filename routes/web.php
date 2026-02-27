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
use App\Http\Controllers\GmailDteDocumentController;
use App\Http\Controllers\PurchaseOrderController;


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

        Route::get('/productos/{id}', [App\Http\Controllers\Inventario\ProductosController::class, 'show'])->name('productos.show');

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

        Route::patch('/productos/{id}/minimo', [App\Http\Controllers\Inventario\ProductosController::class, 'updateMinimo'])
            ->name('producto.minimo')->whereNumber('id');

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
| AGRAK — Vistas (admin y viewer)
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
| GMAIL — Admin + Bodeguero (facturas solo lectura + inventario + agregar stock)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin,bodeguero'])->prefix('gmail')->name('gmail.')->group(function () {

    // Facturas proveedor — listado y detalle (bodeguero ve sin valores)
    Route::get('/dtes/listado', [GmailDteDocumentController::class, 'list'])->name('dtes.list');
    Route::get('/dtes/facturas/listado', [GmailDteDocumentController::class, 'facturasList'])->name('dtes.facturas.list');
    Route::get('/dtes/boletas/listado', [GmailDteDocumentController::class, 'boletasList'])->name('dtes.boletas.list');
    Route::get('/dtes/guias/listado', [GmailDteDocumentController::class, 'guiasList'])->name('dtes.guias.list');
    Route::get('/dtes/stock-products', [GmailDteDocumentController::class, 'stockProductsApi'])->name('dtes.stock_products');
    Route::get('/dtes/{id}/stock-review', [GmailDteDocumentController::class, 'reviewStockMatching'])->whereNumber('id')->name('dtes.stock_review');
    Route::get('/dtes/{id}/print', [GmailDteDocumentController::class, 'print'])->whereNumber('id')->name('dtes.print');
    Route::post('/dtes/{id}/add-stock', [GmailDteDocumentController::class, 'addToStock'])->whereNumber('id')->name('dtes.add_stock');
    Route::post('/dtes/{id}/add-stock-mapping', [GmailDteDocumentController::class, 'addToStockWithMapping'])->whereNumber('id')->name('dtes.add_stock_mapping');
    Route::get('/dtes/{id}', [GmailDteDocumentController::class, 'show'])->whereNumber('id')->name('dtes.show');

    // Inventario — lectura completa
    Route::get('/inventario', [GmailDteDocumentController::class, 'inventoryIndex'])->name('inventory.index');
    Route::get('/inventario/listado', [GmailDteDocumentController::class, 'inventoryList'])->name('inventory.list');
    Route::get('/inventario/api/productos', [App\Http\Controllers\GmailInventoryController::class, 'productsApi'])->name('inventory.api.products');
    Route::post('/inventario/api/productos', [App\Http\Controllers\GmailInventoryController::class, 'createProductApi'])->name('inventory.api.products.create');
    Route::get('/inventario/api/destinatarios', [App\Http\Controllers\GmailInventoryController::class, 'destinatariosApi'])->name('inventory.api.destinatarios');
    Route::get('/inventario/api/lotes/{productId}', [App\Http\Controllers\GmailInventoryController::class, 'lotsApi'])->name('inventory.api.lots')->whereNumber('productId');
    Route::get('/inventario/api/salida/{id}/lineas', [App\Http\Controllers\GmailInventoryController::class, 'exitDetail'])->name('inventory.api.exit.detail')->whereNumber('id');
    Route::get('/inventario/api/contactos', [App\Http\Controllers\GmailInventoryController::class, 'contactsApi'])->name('inventory.api.contacts');
    Route::post('/inventario/api/contactos', [App\Http\Controllers\GmailInventoryController::class, 'contactStore'])->name('inventory.api.contact.store');
    Route::get('/inventario/salida', [App\Http\Controllers\GmailInventoryController::class, 'exitCreate'])->name('inventory.exit.create');
    Route::post('/inventario/salida', [App\Http\Controllers\GmailInventoryController::class, 'exitStore'])->name('inventory.exit.store');
    Route::get('/inventario/salidas', [App\Http\Controllers\GmailInventoryController::class, 'exitList'])->name('inventory.exits');
    Route::get('/inventario/sii-status', [App\Http\Controllers\GmailInventoryController::class, 'siiStatus'])->name('inventory.sii.status');
    Route::get('/inventario/ajuste', [App\Http\Controllers\GmailInventoryController::class, 'adjustCreate'])->name('inventory.adjust.create');
    Route::post('/inventario/ajuste', [App\Http\Controllers\GmailInventoryController::class, 'adjustStore'])->name('inventory.adjust.store');
    Route::get('/inventario/ajustes', [App\Http\Controllers\GmailInventoryController::class, 'adjustList'])->name('inventory.adjustments');
    Route::get('/inventario/valorizado', [App\Http\Controllers\GmailInventoryController::class, 'stockValuation'])->name('inventory.valuation');
    Route::get('/inventario/salidas/{id}/pdf', [App\Http\Controllers\GmailInventoryController::class, 'exitPdf'])->name('inventory.exits.pdf')->whereNumber('id');
    Route::get('/inventario/salidas/{id}', [App\Http\Controllers\GmailInventoryController::class, 'exitShow'])->name('inventory.exits.show')->whereNumber('id');
    Route::get('/inventario/salidas-resumen', [App\Http\Controllers\GmailInventoryController::class, 'exitGroupShow'])->name('inventory.exits.group');
    Route::get('/inventario/salidas-resumen/pdf', [App\Http\Controllers\GmailInventoryController::class, 'exitGroupPdf'])->name('inventory.exits.group.pdf');
    Route::get('/inventario/{id}', [App\Http\Controllers\Inventario\ProductosController::class, 'show'])->whereNumber('id')->name('inventory.product');

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

    Route::get('/dtes', [GmailDteDocumentController::class, 'index'])->name('dtes.index');
    Route::get('/dtes/facturas', [GmailDteDocumentController::class, 'facturasIndex'])->name('dtes.facturas.index');
    Route::get('/dtes/boletas', [GmailDteDocumentController::class, 'boletasIndex'])->name('dtes.boletas.index');
    Route::get('/dtes/guias', [GmailDteDocumentController::class, 'guiasIndex'])->name('dtes.guias.index');

    // Operaciones admin-only sobre DTEs
    Route::post('/dtes/{id}/pay', [GmailDteDocumentController::class, 'markPaid'])->whereNumber('id')->name('dtes.pay');
    Route::post('/dtes/{id}/unpay', [GmailDteDocumentController::class, 'markUnpaid'])->whereNumber('id')->name('dtes.unpay');
    Route::post('/dtes/{id}/draft', [GmailDteDocumentController::class, 'markDraft'])->whereNumber('id')->name('dtes.draft');
    Route::post('/dtes/{id}/accept', [GmailDteDocumentController::class, 'markAccepted'])->whereNumber('id')->name('dtes.accept');
    Route::post('/dtes/{id}/credit-note', [GmailDteDocumentController::class, 'markCreditNote'])->whereNumber('id')->name('dtes.credit_note');
    Route::post('/dtes/{id}/rollback-stock', [GmailDteDocumentController::class, 'rollbackStock'])->whereNumber('id')->name('dtes.rollback_stock');
    Route::post('/dtes/{id}/lines/{lineId}', [GmailDteDocumentController::class, 'updateLine'])->whereNumber('id')->whereNumber('lineId')->name('dtes.lines.update');

    // Salidas inventario — operaciones admin-only
    Route::get('/inventario/salidas/export', [App\Http\Controllers\GmailInventoryController::class, 'exitExport'])->name('inventory.exits.export');
    Route::post('/inventario/salidas/{id}/venta', [App\Http\Controllers\GmailInventoryController::class, 'exitSell'])->name('inventory.exits.sell')->whereNumber('id');
    Route::get('/inventario/salidas/{id}/editar', [App\Http\Controllers\GmailInventoryController::class, 'exitEdit'])->name('inventory.exits.edit')->whereNumber('id');
    Route::put('/inventario/salidas/{id}', [App\Http\Controllers\GmailInventoryController::class, 'exitUpdate'])->name('inventory.exits.update')->whereNumber('id');
    // Configuraciones SII (admin-only)
    Route::post('/inventario/sii-config', [App\Http\Controllers\GmailInventoryController::class, 'siiConfigUpdate'])->name('inventory.sii.config');
    Route::post('/inventario/sii-upload-caf', [App\Http\Controllers\GmailInventoryController::class, 'uploadCaf'])->name('inventory.sii.upload.caf');
    Route::post('/inventario/sii-upload-pfx', [App\Http\Controllers\GmailInventoryController::class, 'uploadPfx'])->name('inventory.sii.upload.pfx');

});

/*
|--------------------------------------------------------------------------
| ORDENES DE COMPRA (solo ADMIN)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin'])
    ->prefix('cotizaciones')
    ->name('purchase_orders.')
    ->group(function () {
        Route::get('/', [PurchaseOrderController::class, 'index'])->name('index');
        Route::get('/crear', [PurchaseOrderController::class, 'create'])->name('create');
        Route::post('/proveedores/upsert', [PurchaseOrderController::class, 'upsertSupplier'])->name('suppliers.upsert');
        Route::post('/', [PurchaseOrderController::class, 'store'])->name('store');
        Route::get('/{id}', [PurchaseOrderController::class, 'show'])->whereNumber('id')->name('show');
        Route::post('/{id}/enviar-email', [PurchaseOrderController::class, 'sendEmail'])->whereNumber('id')->name('send_email');
        Route::post('/{id}/confirmar-oc', [PurchaseOrderController::class, 'confirmAsOrder'])->whereNumber('id')->name('confirm_order');
        Route::post('/{id}/respuesta', [PurchaseOrderController::class, 'storeReply'])->whereNumber('id')->name('store_reply');
        Route::patch('/{id}/respuesta/{rid}', [PurchaseOrderController::class, 'updateReply'])->whereNumber('id')->whereNumber('rid')->name('update_reply');
        Route::delete('/{id}/respuesta/{rid}', [PurchaseOrderController::class, 'deleteReply'])->whereNumber('id')->whereNumber('rid')->name('delete_reply');
        Route::patch('/{id}/item/{itemId}', [PurchaseOrderController::class, 'updateItem'])->whereNumber('id')->whereNumber('itemId')->name('update_item');
        Route::get('/adjunto/{rid}', [PurchaseOrderController::class, 'serveAttachment'])->whereNumber('rid')->name('attachment');
    });
