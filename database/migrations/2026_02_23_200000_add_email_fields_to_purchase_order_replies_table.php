<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'fuelcontrol';

    public function up(): void
    {
        Schema::connection('fuelcontrol')->table('purchase_order_replies', function (Blueprint $table) {
            // 'manual' = agregado por el usuario, 'email' = importado automáticamente del correo
            $table->string('source', 20)->default('manual')->after('pdf_original_name');
            // Gmail message ID para evitar procesar el mismo correo dos veces
            $table->string('email_message_id', 255)->nullable()->unique()->after('source');
            // Dirección del remitente cuando viene del correo
            $table->string('sender_email', 255)->nullable()->after('email_message_id');
        });
    }

    public function down(): void
    {
        Schema::connection('fuelcontrol')->table('purchase_order_replies', function (Blueprint $table) {
            $table->dropColumn(['source', 'email_message_id', 'sender_email']);
        });
    }
};
