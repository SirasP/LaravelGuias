<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cada partida recuerda en qué orden de Odoo quedó.
 *
 * Hasta ahora eso lo sabía la solicitud entera, con un único número de orden,
 * y por eso una compra repartida entre dos proveedores no tenía dónde
 * anotarse: la SC-2026-000024 se compró en dos sitios y Odoo tiene las nueve
 * partidas en una sola orden a nombre de uno solo. En Odoo la recepción cuelga
 * de la orden, así que ese dato falso llega derecho al stock.
 *
 * Con la orden en la partida, una solicitud puede terminar en varias órdenes
 * —una por proveedor, enlazadas entre sí como alternativas, que es como ya
 * trabajan las 78 órdenes que usan Acuerdos de Compra en esa instancia— y las
 * partidas que nadie cotizó se quedan sin orden, en espera, a la vista.
 *
 * El id de la línea se guarda además del de la orden: buscarla después por su
 * texto funciona, pero el texto cambia —se le escribe el nombre real del
 * proveedor— y ya nos costó una orden entera que dejó de reconocerse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_request_items', function (Blueprint $table) {
            $table->unsignedBigInteger('odoo_order_id')->nullable()->after('destination');
            $table->unsignedBigInteger('odoo_line_id')->nullable()->after('odoo_order_id');
            $table->index('odoo_order_id', 'pri_odoo_orden');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_request_items', function (Blueprint $table) {
            $table->dropIndex('pri_odoo_orden');
            $table->dropColumn(['odoo_order_id', 'odoo_line_id']);
        });
    }
};
