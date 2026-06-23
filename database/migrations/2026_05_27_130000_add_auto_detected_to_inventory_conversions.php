<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inventory_conversions', function (Blueprint $table) {
            $table->boolean('auto_detected')->default(false)->after('unidad_compra')
                ->comment('true = detectado automáticamente por regex; false = configurado manualmente');
        });
    }
    public function down(): void
    {
        Schema::table('inventory_conversions', function (Blueprint $table) {
            $table->dropColumn('auto_detected');
        });
    }
};
