<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qué producto es cada cosa, según cómo la escribe cada proveedor.
 *
 * Es el `product.supplierinfo` de Odoo, pero viviendo aquí: en Odoo esos
 * campos existen y están vacíos en las 267 filas, y de todos modos su maestro
 * no se toca.
 *
 * La idea que sostiene la tabla: el emparejado por parecido es barato y
 * desechable; el minuto de criterio de la persona que dijo «esto es aquello»
 * es lo caro. Eso es lo que se guarda, para no volver a pedirlo.
 *
 * Tabla nueva y aditiva. No modifica nada existente, y nace sin que ninguna
 * pantalla la lea.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_product_links', function (Blueprint $table): void {
            $table->id();
            $table->string('company_code', 12)->default('EHE');

            /*
             * El proveedor de Odoo. Puede ir vacío: un alias heredado del
             * módulo de facturas no sabe de quién venía, y sirve igual aunque
             * valga menos que uno que sí lo sepa.
             */
            $table->unsignedBigInteger('odoo_partner_id')->nullable();
            $table->string('partner_name', 255)->nullable();

            // Tal como venía escrito, para poder mirarlo después y entender
            // qué se emparejó con qué.
            $table->string('source_text', 500);

            // Y el mismo texto normalizado, que es por donde se busca.
            $table->string('normalized_text', 500);

            $table->unsignedBigInteger('odoo_product_id')->nullable();
            $table->string('odoo_product_name', 500)->nullable();

            /*
             * El producto equivalente en el inventario propio.
             *
             * Hoy nadie lo llena: el módulo de facturas no lee esta tabla y no
             * se va a tocar. La columna existe para que el día que se comparta
             * el diccionario, media respuesta ya esté dada.
             */
            $table->unsignedBigInteger('fuelcontrol_product_id')->nullable();

            // De dónde salió: lo confirmó una persona, o se heredó del JSON
            // del módulo de facturas. No valen lo mismo.
            $table->string('source', 20)->default('confirmed');

            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->string('confirmed_by_name', 255)->nullable();
            $table->timestamp('confirmed_at')->nullable();

            $table->timestamps();

            // Un texto de un proveedor apunta a una sola cosa. Sin proveedor,
            // el texto es único por sí solo.
            $table->unique(['company_code', 'odoo_partner_id', 'normalized_text'], 'ppl_partner_text_uq');
            $table->index('normalized_text');
            $table->index('odoo_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_product_links');
    }
};
