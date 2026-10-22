<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('fuelcontrol');
        $schema->create('fuel_estanques', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 60);
            $table->unsignedInteger('producto_id')->index();
            $table->decimal('cantidad', 14, 2)->default(0);
            $table->decimal('saldo_inicial', 14, 2)->nullable();
            $table->decimal('ultimo_contador', 14, 2)->nullable();
            $table->boolean('inicializado')->default(false);
            $table->timestamps();
        });
        $schema->create('fuel_oc_destinos', function (Blueprint $table) {
            $table->id();
            $table->string('numero_oc', 40)->unique();
            $table->unsignedBigInteger('estanque_id');
            $table->timestamps();
            $table->foreign('estanque_id')->references('id')->on('fuel_estanques');
        });
        $schema->create('fuel_operaciones', function (Blueprint $table) {
            $table->string('clave', 100)->primary();
            $table->string('payload_hash', 64);
            $table->text('respuesta');
            $table->timestamp('created_at');
        });
        if ($schema->hasTable('movimientos')) {
            $schema->table('movimientos', function (Blueprint $table) {
                $table->unsignedBigInteger('estanque_id')->nullable()->index();
                $table->string('orden_compra', 40)->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('fuelcontrol');
        if ($schema->hasTable('movimientos') && $schema->hasColumn('movimientos', 'estanque_id')) {
            $schema->table('movimientos', fn (Blueprint $table) => $table->dropColumn(['estanque_id', 'orden_compra']));
        }
        $schema->dropIfExists('fuel_operaciones');
        $schema->dropIfExists('fuel_oc_destinos');
        $schema->dropIfExists('fuel_estanques');
    }
};
