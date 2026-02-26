<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('fuelcontrol')->hasTable('gmail_inventory_settings')) {
            Schema::connection('fuelcontrol')->create('gmail_inventory_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key', 120)->unique();
                $table->longText('value')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('fuelcontrol')->dropIfExists('gmail_inventory_settings');
    }
};

