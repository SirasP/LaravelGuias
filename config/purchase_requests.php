<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Envío de correo
    |--------------------------------------------------------------------------
    |
    | Controla si los avisos del módulo salen además por correo. Los avisos
    | dentro del sistema funcionan siempre, con esto encendido o apagado.
    |
    | Se apaga solo en el entorno de pruebas para que la suite jamás intente
    | contactar un servidor SMTP.
    |
    */
    'mail_enabled' => (bool) env('PURCHASE_REQUESTS_MAIL', true),
];
