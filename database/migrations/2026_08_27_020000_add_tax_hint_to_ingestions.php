<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La conclusión sobre el IVA, guardada junto a la lectura.
 *
 * La lectura existe antes que la solicitud: se decide aquí, al leer el
 * documento, y viaja a la solicitud cuando una persona confirma la lectura.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_request_ingestions', function (Blueprint $table): void {
            $table->boolean('prices_include_tax')->nullable()->after('supplier_tax_id');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_request_ingestions', function (Blueprint $table): void {
            $table->dropColumn('prices_include_tax');
        });
    }
};
