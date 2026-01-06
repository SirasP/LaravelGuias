<?php
// database/migrations/xxxx_xx_xx_create_dtes_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dtes', function (Blueprint $table) {
            $table->id();

            $table->string('source', 20)->default('gmail');          // gmail/manual/etc
            $table->string('gmail_message_id')->nullable();
            $table->string('gmail_from')->nullable();
            $table->string('gmail_subject')->nullable();
            $table->timestamp('gmail_date')->nullable();

            $table->string('filename')->nullable();

            $table->unsignedSmallInteger('tipo_dte')->index();       // 33,52,61...
            $table->string('tipo_nombre')->nullable();
            $table->unsignedBigInteger('folio')->index();
            $table->date('fch_emis')->nullable();

            $table->string('rut_emisor', 20)->index();
            $table->string('rz_emisor')->nullable();
            $table->string('giro_emisor')->nullable();

            $table->string('rut_receptor', 20)->index();
            $table->string('rz_receptor')->nullable();

            $table->unsignedBigInteger('mnt_neto')->nullable();
            $table->unsignedBigInteger('iva')->nullable();
            $table->unsignedBigInteger('mnt_total')->nullable();

            $table->longText('xml')->nullable(); // opcional: guardar XML completo

            // Evitar duplicados típicos
            $table->unique(['rut_emisor', 'tipo_dte', 'folio'], 'dtes_unique_emisor_tipo_folio');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dtes');
    }
};
