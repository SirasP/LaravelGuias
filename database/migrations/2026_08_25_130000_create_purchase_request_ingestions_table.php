<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Documentos de cotización leídos por el asistente.
     *
     * Cada archivo subido deja una fila desde el momento en que entra, aunque
     * la lectura falle después. Así siempre se puede responder qué se subió,
     * qué entendió el modelo y qué borrador salió de ahí: sin este registro,
     * una solicitud creada por el asistente no tendría de dónde venir.
     */
    public function up(): void
    {
        Schema::create('purchase_request_ingestions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('company_code', 20)->default('EHE');

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('uploader_name_snapshot');

            // El borrador que resultó. Nulo mientras se procesa o si falla.
            $table->foreignId('purchase_request_id')->nullable()
                ->constrained('purchase_requests')->nullOnDelete();

            // El archivo, en disco privado, igual que los adjuntos.
            $table->string('disk', 40)->default('local');
            $table->string('path', 500);
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->string('sha256', 64);

            $table->string('status', 24)->default('pending');
            // Cómo se leyó: texto del PDF, PDF escaneado o imagen.
            $table->string('source_kind', 24)->nullable();
            $table->string('model_used', 120)->nullable();

            // Lo que entendió el modelo y lo que finalmente se usó.
            $table->json('extracted')->nullable();
            $table->json('warnings')->nullable();
            $table->text('error_message')->nullable();

            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status'], 'pr_ingestions_user_status_idx');
            $table->index(['status', 'created_at'], 'pr_ingestions_status_created_idx');
            // Un mismo archivo subido dos veces no se procesa dos veces.
            $table->unique(['company_code', 'sha256'], 'pr_ingestions_company_hash_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_ingestions');
    }
};
