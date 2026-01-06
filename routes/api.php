<?php

use App\Http\Controllers\DocumentosCompraController;

Route::prefix('documentos-compra')->group(function () {
    Route::post('/', [DocumentosCompraController::class, 'crear']);
    Route::post('/{id}/lineas', [DocumentosCompraController::class, 'agregarLinea']);
    Route::post('/{id}/contabilizar', [DocumentosCompraController::class, 'contabilizar']);
    Route::get('/{id}', [DocumentosCompraController::class, 'ver']);
});