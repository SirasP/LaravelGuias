<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los nombres con que la gente escribe a cada proveedor.
 *
 * En la solicitud se escribe «Vicat»; en Odoo está «ARIDOS VICAT SUR SPA».
 * Nadie va a escribir el nombre legal completo, así que la primera vez lo
 * resuelve una persona y aquí queda anotado para que no se vuelva a preguntar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_suppliers', function (Blueprint $table): void {
            $table->json('aliases')->nullable()->after('trade_name');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_suppliers', function (Blueprint $table): void {
            $table->dropColumn('aliases');
        });
    }
};
