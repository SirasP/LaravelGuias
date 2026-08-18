<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'fuelcontrol';

    /**
     * Marca vehículos cuyo combustible se carga en bomba (estación de
     * servicio) y por lo tanto NO entra al estanque propio.
     *
     * Por defecto 0: todos los vehículos existentes mantienen el
     * comportamiento actual. La exclusión se activa una por una.
     */
    public function up(): void
    {
        if (!Schema::connection('fuelcontrol')->hasColumn('vehiculos', 'excluye_stock')) {
            Schema::connection('fuelcontrol')->table('vehiculos', function (Blueprint $table) {
                $table->boolean('excluye_stock')->default(false)->after('is_active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('fuelcontrol')->hasColumn('vehiculos', 'excluye_stock')) {
            Schema::connection('fuelcontrol')->table('vehiculos', function (Blueprint $table) {
                $table->dropColumn('excluye_stock');
            });
        }
    }
};
