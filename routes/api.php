<?php

use App\Http\Controllers\DocumentosCompraController;
use App\Http\Controllers\Api\NotificacionesApiController;
use App\Http\Controllers\Api\MantencionApiController;
use App\Http\Controllers\Api\AppSyncController;

Route::prefix('documentos-compra')->group(function () {
    Route::post('/', [DocumentosCompraController::class, 'crear']);
    Route::post('/{id}/lineas', [DocumentosCompraController::class, 'agregarLinea']);
    Route::post('/{id}/contabilizar', [DocumentosCompraController::class, 'contabilizar']);
    Route::get('/{id}', [DocumentosCompraController::class, 'ver']);
});

// 🔥 API para Flutter - Notificaciones de combustible
Route::prefix('notificaciones')->group(function () {
    Route::get('/', [NotificacionesApiController::class, 'index']);
    Route::post('/{id}/leer', [NotificacionesApiController::class, 'marcarLeida']);
});

Route::prefix('combustible')->group(function () {
    Route::get('/movimientos', [NotificacionesApiController::class, 'movimientosCombustible']);
    Route::get('/stock', [NotificacionesApiController::class, 'stockCombustible']);
    Route::post('/fcm-token', [NotificacionesApiController::class, 'registrarFcmToken']);
    Route::post('/fcm-token/deactivate', [NotificacionesApiController::class, 'desactivarFcmToken']);
});

// 🔧 API para App Mantención Maquinaria
Route::prefix('mantencion')->group(function () {
    // Inventario Odoo (read-only)
    Route::get('/repuestos', [MantencionApiController::class, 'repuestos']);
    Route::get('/repuestos/{id}/movimientos', [MantencionApiController::class, 'movimientos']);
    Route::post('/egresos', [MantencionApiController::class, 'registrarEgresos']);
    // Conversiones unidades
    Route::get('/conversiones', [MantencionApiController::class, 'conversiones']);
    Route::post('/conversiones', [MantencionApiController::class, 'upsertConversion']);
    Route::delete('/conversiones/{productId}', [MantencionApiController::class, 'deleteConversion']);
    // FCM tokens
    Route::post('/fcm-token', [MantencionApiController::class, 'registerFcmToken']);
    Route::delete('/fcm-token', [MantencionApiController::class, 'deactivateFcmToken']);
    // Sync: equipos, mantenciones, kits, ordenes
    Route::get('/equipos', [AppSyncController::class, 'indexEquipos']);
    Route::post('/equipos', [AppSyncController::class, 'upsertEquipo']);
    Route::delete('/equipos/{id}', [AppSyncController::class, 'deleteEquipo']);
    Route::get('/mantenciones', [AppSyncController::class, 'indexMantenciones']);
    Route::post('/mantenciones', [AppSyncController::class, 'upsertMantencion']);
    Route::get('/kits', [AppSyncController::class, 'indexKits']);
    Route::post('/kits', [AppSyncController::class, 'upsertKit']);
    Route::post('/ordenes', [AppSyncController::class, 'upsertWorkOrder']);
});

// 🏦 Integración Banco de Chile: Carga automática de lotes desde Odoo
Route::prefix('bancochile')->middleware('bch.webhook.log')->group(function () {
    Route::post('/pago-webhook', [\App\Http\Controllers\BancoChileController::class, 'procesarPagoWebhook']);
});