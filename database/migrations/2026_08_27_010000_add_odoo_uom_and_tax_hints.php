<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // La equivalencia de cada unidad nuestra con la de Odoo. Se mapea una
        // vez y queda: las nuestras están en español y las suyas en inglés y
        // métricas, así que adivinar la equivalencia no es opción.
        Schema::table('units_of_measure', function (Blueprint $table): void {
            $table->unsignedBigInteger('odoo_uom_id')->nullable()->after('name');
        });

        // Si los precios de la solicitud llevan IVA dentro o no. Null es «no
        // se pudo determinar», que no es lo mismo que «no lo lleva».
        Schema::table('purchase_requests', function (Blueprint $table): void {
            $table->boolean('prices_include_tax')->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('units_of_measure', fn (Blueprint $t) => $t->dropColumn('odoo_uom_id'));
        Schema::table('purchase_requests', fn (Blueprint $t) => $t->dropColumn('prices_include_tax'));
    }
};
