<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('odoo_account_moves', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('odoo_id')->unique();
            $table->string('name', 100)->nullable();         // Ej: BILL/2024/00123
            $table->string('ref', 100)->nullable()->index(); // Referencia proveedor = folio DTE
            $table->string('move_type', 30)->default('in_invoice');
            $table->string('state', 20)->default('draft');   // draft, posted, cancel
            $table->unsignedInteger('partner_odoo_id')->nullable();
            $table->string('partner_name')->nullable();
            $table->date('invoice_date')->nullable();
            $table->decimal('amount_total', 14, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odoo_account_moves');
    }
};
