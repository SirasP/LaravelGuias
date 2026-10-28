<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('odoo_account_move_lines', function (Blueprint $table) {
            // El detalle de las facturas digitadas en Odoo, que nunca llegan con XML:
            // «product» son las líneas de la factura; «tax» y «payment_term», el asiento.
            $table->string('display_type', 20)->nullable()->after('name');
            $table->unsignedBigInteger('product_odoo_id')->nullable()->after('display_type');
            $table->decimal('quantity', 18, 4)->nullable()->after('product_odoo_id');
            $table->string('uom_name', 60)->nullable()->after('quantity');
            $table->decimal('price_unit', 18, 4)->nullable()->after('uom_name');
            $table->decimal('discount', 8, 4)->nullable()->after('price_unit');
            $table->decimal('price_subtotal', 18, 2)->nullable()->after('discount');
        });
    }

    public function down(): void
    {
        Schema::table('odoo_account_move_lines', function (Blueprint $table) {
            $table->dropColumn(['display_type', 'product_odoo_id', 'quantity', 'uom_name', 'price_unit', 'discount', 'price_subtotal']);
        });
    }
};
