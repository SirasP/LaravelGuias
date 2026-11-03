<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Facturas de proveedor que no llegaron a Odoo por el SII y se suben a mano:
 * un PDF o una foto que lee la IA local y que, revisada, se crea en Odoo como
 * borrador.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_invoice_uploads', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('uploader_name_snapshot')->nullable();

            $table->string('disk', 40);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64)->index();

            $table->string('status', 20)->index();
            $table->string('source_kind', 20)->nullable();
            $table->string('model_used')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('error_message')->nullable();

            // Lo que contestó el modelo, tal cual, para auditar la lectura.
            $table->json('raw_response')->nullable();
            // La factura como quedó: lo leído más lo que corrigió la persona.
            $table->json('data')->nullable();
            $table->json('warnings')->nullable();

            $table->unsignedInteger('odoo_move_id')->nullable();
            $table->string('odoo_move_name')->nullable();
            $table->decimal('odoo_amount_total', 14, 2)->nullable();
            $table->string('sent_by_name')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_invoice_uploads');
    }
};
