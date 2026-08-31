<?php

/*
|--------------------------------------------------------------------------
| Credenciales FCM por aplicación móvil
|--------------------------------------------------------------------------
|
| Cada app vive en su propio proyecto de Firebase, y un token sólo es válido
| para el proyecto que lo emitió. Enviar con la cuenta de servicio equivocada
| falla siempre, sin importar que el token esté bien registrado.
|
| Por eso la credencial se elige según el app_type del token, y no puede ser
| una ruta fija como antes.
|
*/

return [

    'credentials' => [
        // AppMantencionMaquinaria — proyecto app-mantencion-maquinaria
        'mantencion' => env(
            'FCM_CREDENTIALS_MANTENCION',
            storage_path('app/firebase/firebase-credentials.json')
        ),

        // TerraFuel / FuelControl — proyecto huertoapp-cc3a0
        'combustible' => env(
            'FCM_CREDENTIALS_COMBUSTIBLE',
            storage_path('app/firebase/firebase-combustible.json')
        ),
    ],

];
