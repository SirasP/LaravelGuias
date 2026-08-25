<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cada envío congela la solicitud completa. El snapshot es la fuente del
     * PDF histórico: la revisión 1 se sigue imprimiendo con los datos de la
     * revisión 1 aunque la solicitud haya sido corregida y reenviada después.
     */
    public function up(): void
    {
        Schema::create('purchase_request_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision_number');
            $table->string('status', 32);
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('submitted_by_name_snapshot');
            $table->timestamp('submitted_at');

            // Copia íntegra de cabecera y partidas tal como se enviaron.
            $table->json('header_snapshot');
            $table->json('items_snapshot');
            $table->unsignedInteger('item_count')->default(0);

            // El PDF se materializa una sola vez, en disco privado.
            $table->string('pdf_disk', 40)->nullable();
            $table->string('pdf_path', 500)->nullable();
            $table->string('pdf_sha256', 64)->nullable();
            $table->timestamps();

            $table->unique(
                ['purchase_request_id', 'revision_number'],
                'pr_revisions_request_revision_uq'
            );
            $table->index(['purchase_request_id', 'submitted_at'], 'pr_revisions_request_submitted_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_revisions');
    }
};
