<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Precio por partida.
 *
 * La especificación original los excluía porque los formularios internos no
 * los traen. Pero desde que el asistente lee cotizaciones reales, el precio
 * está ahí escrito, y quien aprueba necesita saber cuánto está aprobando.
 *
 * Queda opcional: una solicitud sin precios sigue siendo válida, que es el
 * caso de quien pide algo sin haber cotizado todavía.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_request_items', function (Blueprint $table): void {
            $table->decimal('unit_price', 14, 2)->nullable()->after('unit');
        });

        Schema::table('purchase_requests', function (Blueprint $table): void {
            // Un precio sin moneda no dice nada. Casi todo será CLP, pero las
            // cotizaciones de maquinaria importada llegan en dólares o UF.
            $table->string('currency', 3)->default('CLP')->after('priority');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_request_items', function (Blueprint $table): void {
            $table->dropColumn('unit_price');
        });

        Schema::table('purchase_requests', function (Blueprint $table): void {
            $table->dropColumn('currency');
        });
    }
};
