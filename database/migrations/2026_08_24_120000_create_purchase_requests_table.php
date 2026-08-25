<?php

use App\Enums\PurchaseRequestStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            // El MVP pertenece explícitamente a Agrícola EHE SpA. Los snapshots
            // evitan que un cambio de nombre futuro altere solicitudes históricas.
            $table->string('company_code', 20)->default('EHE');
            $table->string('company_name_snapshot')->default('Agrícola EHE SpA');

            $table->string('folio', 40)->nullable()->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('requester_name_snapshot');
            $table->string('requested_for_name')->nullable();

            $table->date('request_date');
            $table->date('required_date');
            $table->string('department', 120);
            $table->text('reason');
            $table->string('priority', 20)->default('normal');
            $table->text('urgent_reason')->nullable();
            $table->string('cost_center', 120)->nullable();
            $table->string('delivery_location')->nullable();
            $table->text('internal_notes')->nullable();
            $table->json('suggested_suppliers')->nullable();

            $table->string('status', 32)->default(PurchaseRequestStatus::DRAFT->value);
            $table->unsignedInteger('revision_number')->default(1);
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_comment')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'required_date']);
            $table->index(['company_code', 'request_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_requests');
    }
};
