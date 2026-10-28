<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // All decisions live beside the requests. The DTE/inventory database is read-only.
        Schema::create('dte_purchase_decisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id')->unique();
            $table->string('invoice_key', 64)->nullable()->unique();
            $table->string('classification', 30)->default('unidentified');
            $table->string('document_fingerprint', 64)->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamps();
        });

        Schema::create('dte_purchase_references', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id')->index();
            $table->unsignedBigInteger('document_line_id')->index();
            $table->foreignId('purchase_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_request_item_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('invoice_quantity', 18, 6);
            $table->decimal('request_quantity', 18, 6);
            $table->string('supplier_rut', 20);
            $table->string('source', 20)->default('manual');
            $table->string('source_key', 120)->nullable()->unique();
            $table->uuid('operation_key')->nullable()->unique();
            $table->json('snapshot');
            $table->json('evidence')->nullable();
            $table->text('note');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->unsignedBigInteger('revoked_by')->nullable();
            $table->text('revocation_note')->nullable();
            $table->timestamps();
            $table->index(['document_id', 'revoked_at'], 'dte_purchase_active_document');
            $table->index(['purchase_request_item_id', 'revoked_at'], 'dte_purchase_active_item');
        });

        Schema::create('dte_purchase_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id')->index();
            $table->string('event', 30);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->json('details');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dte_purchase_events');
        Schema::dropIfExists('dte_purchase_references');
        Schema::dropIfExists('dte_purchase_decisions');
    }
};
