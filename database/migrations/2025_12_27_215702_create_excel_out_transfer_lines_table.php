<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
   
    public function up(): void
    {
        Schema::create('excel_out_transfer_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('excel_out_transfer_id')->constrained()->cascadeOnDelete();

            $table->string('producto')->nullable();
            $table->decimal('cantidad', 12, 3)->nullable();

            $table->string('source_file')->nullable();
            $table->unsignedInteger('excel_row')->nullable();
            $table->json('raw')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('excel_out_transfer_lines');
    }
};
