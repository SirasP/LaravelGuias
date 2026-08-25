<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Puntos concretos que el revisor marcó al devolver la solicitud.
     *
     * El comentario explica el porqué; esto señala el dónde, para que el
     * solicitante no tenga que adivinar qué campo o qué partida corregir.
     * Se limpia al reenviar: son correcciones pendientes, no historial. El
     * registro permanente vive en el evento correspondiente.
     */
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table): void {
            $table->json('requested_corrections')->nullable()->after('review_comment');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table): void {
            $table->dropColumn('requested_corrections');
        });
    }
};
