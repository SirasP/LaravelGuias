<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('fuelcontrol')->create('gmail_inventory_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 30);           // cliente | trabajador | destinatario
            $table->string('nombre', 200);
            $table->string('rut', 30)->nullable();
            $table->string('empresa', 200)->nullable();   // para clientes
            $table->string('cargo', 100)->nullable();     // para trabajadores
            $table->string('area', 100)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('email', 200)->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('fuelcontrol')->dropIfExists('gmail_inventory_contacts');
    }
};
