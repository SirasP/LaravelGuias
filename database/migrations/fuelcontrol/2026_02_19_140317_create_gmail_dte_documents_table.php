<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::connection('fuelcontrol')->hasTable('gmail_dte_documents')) {
            Schema::connection('fuelcontrol')->create('gmail_dte_documents', function (Blueprint $table) {
                $table->id();
                $table->string('gmail_message_id')->nullable()->index();
                $table->string('xml_filename');
                $table->string('xml_path')->nullable();
                $table->string('hash_unico')->unique();

                $table->unsignedInteger('tipo_dte')->nullable();
                $table->string('folio')->nullable()->index();
                $table->string('proveedor_rut', 20)->nullable()->index();
                $table->string('proveedor_nombre')->nullable();

                $table->date('fecha_factura')->nullable()->index();
                $table->date('fecha_contable')->nullable()->index();
                $table->date('fecha_vencimiento')->nullable()->index();
                $table->string('referencia')->nullable();

                $table->decimal('monto_neto', 14, 2)->default(0);
                $table->decimal('monto_iva', 14, 2)->default(0);
                $table->decimal('monto_total', 14, 2)->default(0);

                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('fuelcontrol')->dropIfExists('gmail_dte_documents');
    }
};
