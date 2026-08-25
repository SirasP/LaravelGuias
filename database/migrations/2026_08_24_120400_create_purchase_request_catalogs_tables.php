<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogos normalizados para área, centro de costo, lugar de entrega y
     * unidad de medida. Las solicitudes conservan además el texto elegido como
     * snapshot, de modo que renombrar un catálogo no reescribe el historial.
     */
    public function up(): void
    {
        foreach (['departments', 'cost_centers', 'locations'] as $catalog) {
            Schema::create($catalog, function (Blueprint $table) use ($catalog): void {
                $table->id();
                $table->string('company_code', 20)->default('EHE');
                $table->string('name', 120);
                $table->string('slug', 140);
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                // El slug normalizado impide que convivan "Administración",
                // "ADMINISTRACION" y "Administracion" como áreas distintas.
                $table->unique(['company_code', 'slug'], substr($catalog, 0, 20).'_company_slug_uq');
                $table->index(['company_code', 'is_active'], substr($catalog, 0, 20).'_company_active_idx');
            });
        }

        Schema::create('units_of_measure', function (Blueprint $table): void {
            $table->id();
            $table->string('company_code', 20)->default('EHE');
            $table->string('code', 40);
            $table->string('name', 120);
            $table->string('slug', 140);
            $table->boolean('allows_decimals')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_code', 'slug'], 'units_of_measure_company_slug_uq');
            $table->index(['company_code', 'is_active'], 'units_of_measure_company_active_idx');
        });

        Schema::table('purchase_requests', function (Blueprint $table): void {
            $table->foreignId('department_id')->nullable()->after('department')
                ->constrained('departments')->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->after('cost_center')
                ->constrained('cost_centers')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->after('delivery_location')
                ->constrained('locations')->nullOnDelete();

            // Una cancelación posterior al envío se pide, no se ejecuta sola.
            $table->timestamp('cancellation_requested_at')->nullable()->after('review_comment');
            $table->text('cancellation_reason')->nullable()->after('cancellation_requested_at');
            $table->timestamp('cancelled_at')->nullable()->after('cancellation_reason');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('purchase_request_items', function (Blueprint $table): void {
            $table->foreignId('unit_of_measure_id')->nullable()->after('unit')
                ->constrained('units_of_measure')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_request_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('unit_of_measure_id');
        });

        Schema::table('purchase_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('cost_center_id');
            $table->dropConstrainedForeignId('location_id');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['cancellation_requested_at', 'cancellation_reason', 'cancelled_at']);
        });

        Schema::dropIfExists('units_of_measure');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('cost_centers');
        Schema::dropIfExists('departments');
    }
};
