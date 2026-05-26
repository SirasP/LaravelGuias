<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('odoo_account_move_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('odoo_id')->unique();
            $table->unsignedInteger('move_odoo_id')->index(); // FK → odoo_account_moves.odoo_id
            $table->unsignedInteger('account_odoo_id')->nullable();
            $table->string('account_code', 30)->nullable()->index();
            $table->string('account_name')->nullable();       // Nombre EN desde Odoo
            $table->string('account_name_es')->nullable();    // Nombre ES desde odoo_accounts
            $table->text('name')->nullable();                 // Descripción de la línea
            $table->decimal('debit',  14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->json('analytic_distribution')->nullable(); // {"148,65": 100}
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odoo_account_move_lines');
    }
};
