<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una dirección para los avisos, separada de la de acceso.
 *
 * La empresa está pasando a los correos @ehe.cl, pero cambiar el correo del
 * usuario cambia también con qué inicia sesión y a dónde llegan los enlaces
 * para recuperar la contraseña. Si el correo nuevo todavía no recibe bien, esa
 * persona queda fuera del sistema sin manera de volver a entrar.
 *
 * Queda en blanco por defecto: mientras nadie la complete, los avisos siguen
 * yendo exactamente a donde iban.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('notification_email')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('notification_email');
        });
    }
};
