<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Columns that production gained from alterations older than the base-table migration. */
    private const COLUMNS = ['workflow_status', 'payment_status', 'inventory_status', 'paid_at', 'stock_posted_at', 'stock_movement_id', 'xml_raw'];

    public function up(): void
    {
        // Production predates the base-table migration. Its guarded 12:05 and
        // 12:35 alterations run before the 14:03 CREATE on an empty test database.
        $schema = Schema::connection('fuelcontrol');
        $schema->table('gmail_dte_documents', function (Blueprint $table) use ($schema) {
            $missing = fn (string $column): bool => ! $schema->hasColumn('gmail_dte_documents', $column);
            if ($missing('workflow_status')) {
                $table->string('workflow_status')->default('aceptado');
            }
            if ($missing('payment_status')) {
                $table->string('payment_status', 20)->default('sin_pagar');
            }
            if ($missing('inventory_status')) {
                $table->string('inventory_status', 20)->default('pendiente');
            }
            foreach (['paid_at', 'stock_posted_at'] as $column) {
                if ($missing($column)) {
                    $table->timestamp($column)->nullable();
                }
            }
            if ($missing('stock_movement_id')) {
                $table->unsignedBigInteger('stock_movement_id')->nullable();
            }
            if ($missing('xml_raw')) {
                $table->longText('xml_raw')->nullable();
            }
        });
        // And the 11:52 tax fields of each line.
        if (! $schema->hasColumn('gmail_dte_document_lines', 'impuesto_codigo')) {
            $schema->table('gmail_dte_document_lines', function (Blueprint $table) {
                $table->string('impuesto_codigo', 20)->nullable();
                $table->decimal('impuesto_tasa', 8, 4)->nullable();
                $table->string('impuesto_label', 60)->nullable();
                $table->boolean('es_exento')->default(false);
            });
        }
    }

    public function down(): void
    {
        Schema::connection('fuelcontrol')->table('gmail_dte_document_lines', fn (Blueprint $table) => $table->dropColumn(['impuesto_codigo', 'impuesto_tasa', 'impuesto_label', 'es_exento']));
        Schema::connection('fuelcontrol')->table('gmail_dte_documents', fn (Blueprint $table) => $table->dropColumn(self::COLUMNS));
    }
};
