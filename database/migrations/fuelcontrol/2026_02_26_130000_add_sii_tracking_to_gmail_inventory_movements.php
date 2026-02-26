<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('fuelcontrol')->table('gmail_inventory_movements', function (Blueprint $table) {
            if (!Schema::connection('fuelcontrol')->hasColumn('gmail_inventory_movements', 'dte_xml_path')) {
                $table->string('dte_xml_path')->nullable()->after('precio_venta');
            }
            if (!Schema::connection('fuelcontrol')->hasColumn('gmail_inventory_movements', 'sii_track_id')) {
                $table->string('sii_track_id', 64)->nullable()->index()->after('dte_xml_path');
            }
            if (!Schema::connection('fuelcontrol')->hasColumn('gmail_inventory_movements', 'sii_estado')) {
                $table->string('sii_estado', 120)->nullable()->after('sii_track_id');
            }
            if (!Schema::connection('fuelcontrol')->hasColumn('gmail_inventory_movements', 'sii_ultimo_envio_xml')) {
                $table->longText('sii_ultimo_envio_xml')->nullable()->after('sii_estado');
            }
            if (!Schema::connection('fuelcontrol')->hasColumn('gmail_inventory_movements', 'sii_enviado_at')) {
                $table->dateTime('sii_enviado_at')->nullable()->after('sii_ultimo_envio_xml');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('fuelcontrol')->table('gmail_inventory_movements', function (Blueprint $table) {
            $drop = [];
            foreach (['dte_xml_path', 'sii_track_id', 'sii_estado', 'sii_ultimo_envio_xml', 'sii_enviado_at'] as $col) {
                if (Schema::connection('fuelcontrol')->hasColumn('gmail_inventory_movements', $col)) {
                    $drop[] = $col;
                }
            }
            if (!empty($drop)) {
                $table->dropColumn($drop);
            }
        });
    }
};

