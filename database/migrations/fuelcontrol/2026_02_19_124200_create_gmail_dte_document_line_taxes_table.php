<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('fuelcontrol')->hasTable('gmail_dte_document_line_taxes')) {
            Schema::connection('fuelcontrol')->create('gmail_dte_document_line_taxes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('document_id')->index();
                $table->unsignedBigInteger('dte_line_id')->index();
                $table->unsignedInteger('nro_linea')->nullable()->index();

                $table->string('tax_type', 40)->nullable();
                $table->string('codigo', 30)->nullable();
                $table->decimal('tasa', 12, 6)->nullable();
                $table->decimal('monto', 18, 6)->nullable();
                $table->decimal('base', 18, 6)->nullable();
                $table->string('descripcion', 190)->nullable();
                $table->longText('raw_json')->nullable();

                $table->timestamps();

                $table->index(['document_id', 'dte_line_id'], 'gmail_dte_line_taxes_doc_line_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('fuelcontrol')->dropIfExists('gmail_dte_document_line_taxes');
    }
};
