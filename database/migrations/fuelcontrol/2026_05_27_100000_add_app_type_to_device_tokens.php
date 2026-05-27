<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('fuelcontrol')->table('device_tokens', function (Blueprint $table) {
            $table->string('app_type', 30)->default('combustible')->after('device_name');
            $table->index('app_type');
        });
    }

    public function down(): void
    {
        Schema::connection('fuelcontrol')->table('device_tokens', function (Blueprint $table) {
            $table->dropIndex(['app_type']);
            $table->dropColumn('app_type');
        });
    }
};
