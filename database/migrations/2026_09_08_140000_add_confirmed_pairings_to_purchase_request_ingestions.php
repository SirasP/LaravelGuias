<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guarda, por documento, qué renglón contesta a qué partida.
 *
 * Enseñar «este texto del proveedor es esta partida» sirve mientras el texto
 * identifique algo. El formulario de MAX SERVICE corta los nombres a
 * veintiocho caracteres, y diez de sus diecinueve renglones quedan con el
 * nombre repetido: cuatro overoles idénticos que son cuatro tallas. Contra eso
 * el alias de texto no puede —los cuatro escriben en la misma fila y gana el
 * último—, así que la confirmación se guarda además pegada a este documento,
 * que es donde el número de renglón sí distingue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_request_ingestions', function (Blueprint $table) {
            $table->json('confirmed_pairings')->nullable()->after('warnings');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_request_ingestions', function (Blueprint $table) {
            $table->dropColumn('confirmed_pairings');
        });
    }
};
