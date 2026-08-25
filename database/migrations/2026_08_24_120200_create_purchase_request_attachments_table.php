<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_request_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision_number')->default(1);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk', 40)->default('local');
            $table->string('path', 500)->unique();
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->string('sha256', 64);
            $table->timestamps();

            // Nombres explícitos y cortos: el autogenerado por Laravel para el
            // índice compuesto supera los 64 caracteres que admite MySQL.
            $table->index(
                ['purchase_request_id', 'revision_number'],
                'pr_attachments_request_revision_idx'
            );
            $table->unique(
                ['purchase_request_id', 'revision_number', 'sha256'],
                'pr_attachments_request_revision_hash_uq'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_attachments');
    }
};
