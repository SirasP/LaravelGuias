<?php

use App\Http\Controllers\DocumentosCompraController;
use App\Http\Controllers\Api\NotificacionesApiController;

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