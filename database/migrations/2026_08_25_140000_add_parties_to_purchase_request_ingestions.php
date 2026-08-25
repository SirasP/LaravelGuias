<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quién emite y quién recibe la cotización.
     *
     * El RUT es la única forma fiable de saber de qué proveedor se trata: el
     * nombre viene escrito de mil maneras. Y el RUT del receptor confirma que
     * el documento es para esta empresa y no para otra.
     *
     * Es también el dato que faltaría para enlazar con Odoo el día que se
     * implemente: una RFQ necesita un partner concreto, no un texto.
     */
    public function up(): void
    {
        Schema::table('purchase_request_ingestions', function (Blueprint $table): void {
            $table->string('supplier_name')->nullable()->after('model_used');
            $table->string('supplier_tax_id', 20)->nullable()->after('supplier_name');
            $table->string('customer_tax_id', 20)->nullable()->after('supplier_tax_id');
            // Si el receptor no es esta empresa, queda marcado para revisarlo.
            $table->boolean('customer_matches_company')->nullable()->after('customer_tax_id');

            $table->index('supplier_tax_id', 'pr_ingestions_supplier_rut_idx');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_request_ingestions', function (Blueprint $table): void {
            $table->dropIndex('pr_ingestions_supplier_rut_idx');
            $table->dropColumn(['supplier_name', 'supplier_tax_id', 'customer_tax_id', 'customer_matches_company']);
        });
    }
};
