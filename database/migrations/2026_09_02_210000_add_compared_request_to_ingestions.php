<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una cotización recibida se lee para compararla con una solicitud.
     *
     * `purchase_request_id` ya existe, pero significa lo contrario: la
     * solicitud que NACIÓ de esa lectura. Aquí la solicitud es anterior y el
     * documento llega después, a contrastarse. Reusar la misma columna haría
     * imposible distinguir «esto salió de aquí» de «esto se comparó con
     * aquello», que es justo lo que habría que saber al revisar el historial.
     */
    public function up(): void
    {
        Schema::table('purchase_request_ingestions', function (Blueprint $table): void {
            $table->foreignId('compared_request_id')->nullable()->after('purchase_request_id')
                ->constrained('purchase_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_request_ingestions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('compared_request_id');
        });
    }
};
