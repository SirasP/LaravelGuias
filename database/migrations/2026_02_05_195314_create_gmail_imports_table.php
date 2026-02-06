<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::connection('fuelcontrol')->hasTable('gmail_imports')) {
            Schema::connection('fuelcontrol')->create('gmail_imports', function (Blueprint $table) {
                $table->id();
                $table->string('gmail_message_id')->unique();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
            });
        }
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gmail_imports');
    }
};
