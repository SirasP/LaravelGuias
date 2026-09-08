<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permite enseñar una equivalencia sin pasar por Odoo.
 *
 * Hasta ahora un alias sólo sabía decir «este texto es el producto 8015 de
 * Odoo», y por eso no se podía enseñar nada de una solicitud que todavía no
 * se ha enviado: no había a qué apuntar. Con esto un alias también puede
 * decir «este texto significa lo mismo que este otro texto», que es lo que
 * una persona quiere afirmar cuando mira una cotización al lado de su
 * solicitud. Si además hay producto de Odoo, se guardan los dos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_product_links', function (Blueprint $table) {
            $table->string('canonical_text', 500)->nullable()->after('odoo_product_name');
            $table->index(['company_code', 'canonical_text'], 'ppl_canonico');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_product_links', function (Blueprint $table) {
            $table->dropIndex('ppl_canonico');
            $table->dropColumn('canonical_text');
        });
    }
};
