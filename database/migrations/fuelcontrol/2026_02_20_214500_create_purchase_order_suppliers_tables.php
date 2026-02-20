<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('fuelcontrol')->hasTable('purchase_order_suppliers')) {
            Schema::connection('fuelcontrol')->create('purchase_order_suppliers', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('rut', 25)->nullable()->index();
                $table->string('taxpayer_type')->nullable();
                $table->string('activity_description')->nullable();
                $table->string('address_line_1')->nullable();
                $table->string('address_line_2')->nullable();
                $table->string('comuna', 120)->nullable();
                $table->string('region', 120)->nullable();
                $table->string('postal_code', 30)->nullable();
                $table->string('country', 120)->nullable()->default('Chile');
                $table->string('phone', 60)->nullable();
                $table->string('mobile', 60)->nullable();
                $table->string('website')->nullable();
                $table->string('language', 30)->nullable()->default('es_CL');
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

        if (!Schema::connection('fuelcontrol')->hasTable('purchase_order_supplier_emails')) {
            Schema::connection('fuelcontrol')->create('purchase_order_supplier_emails', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('supplier_id')->index();
                $table->string('email')->index();
                $table->boolean('is_primary')->default(false);
                $table->timestamps();

                $table->unique(['supplier_id', 'email']);
            });
        }

        if (
            Schema::connection('fuelcontrol')->hasTable('purchase_orders')
            && !Schema::connection('fuelcontrol')->hasColumn('purchase_orders', 'supplier_id')
        ) {
            Schema::connection('fuelcontrol')->table('purchase_orders', function (Blueprint $table) {
                $table->unsignedBigInteger('supplier_id')->nullable()->after('order_number')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('fuelcontrol')->hasTable('purchase_orders')
            && Schema::connection('fuelcontrol')->hasColumn('purchase_orders', 'supplier_id')) {
            Schema::connection('fuelcontrol')->table('purchase_orders', function (Blueprint $table) {
                $table->dropColumn('supplier_id');
            });
        }

        Schema::connection('fuelcontrol')->dropIfExists('purchase_order_supplier_emails');
        Schema::connection('fuelcontrol')->dropIfExists('purchase_order_suppliers');
    }
};
