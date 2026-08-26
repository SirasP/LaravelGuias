<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Proveedores conocidos, identificados por su RUT.
     *
     * El nombre de un proveedor casi nunca es legible: suele vivir dentro del
     * logo, que es una imagen. En una cotización real el único texto
     * disponible era un correo de otro dominio, y de ahí salió un nombre
     * equivocado. El RUT, en cambio, se extrae con expresión regular y se
     * valida por su dígito verificador: es el dato fiable.
     *
     * Registrando una vez qué empresa es cada RUT, el nombre deja de depender
     * de que un modelo lo adivine. Y `odoo_partner_id` es lo que permitirá
     * enlazar cada proveedor con su partner cuando se implemente esa
     * integración.
     */
    public function up(): void
    {
        Schema::create('purchase_suppliers', function (Blueprint $table): void {
            $table->id();
            $table->string('company_code', 20)->default('EHE');

            // Formato canónico «77045469-7», sin puntos.
            $table->string('tax_id', 20);
            $table->string('name', 200)->nullable();
            $table->string('trade_name', 200)->nullable();
            $table->string('email', 200)->nullable();
            $table->string('phone', 60)->nullable();

            // Reservado para la integración con Odoo; hoy siempre nulo.
            $table->unsignedBigInteger('odoo_partner_id')->nullable();

            // Cómo entró: leído de un documento o cargado a mano.
            $table->string('source', 20)->default('manual');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_code', 'tax_id'], 'purchase_suppliers_company_rut_uq');
            $table->index(['company_code', 'is_active'], 'purchase_suppliers_company_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_suppliers');
    }
};
