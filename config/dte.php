<?php

return [
    'storage_disk' => env('DTE_STORAGE_DISK', 'local'),

    'caf_disk' => env('DTE_CAF_DISK', 'local'),

    'caf_paths' => [
        33 => env('DTE_CAF_33_PATH', 'caf/caf_33.xml'),
    ],
    'caf_dev_folio_desde' => (int) env('DTE_CAF_DEV_FOLIO_DESDE', 90000000),
    'caf_dev_folio_hasta' => (int) env('DTE_CAF_DEV_FOLIO_HASTA', 99999999),

    'emisor' => [
        'rut' => env('DTE_EMISOR_RUT', '76000000-0'),
        'razon_social' => env('DTE_EMISOR_RAZON_SOCIAL', 'EMPRESA DEMO SPA'),
        'giro' => env('DTE_EMISOR_GIRO', 'COMERCIALIZACION DE PRODUCTOS'),
        'acteco' => env('DTE_EMISOR_ACTECO', '469000'),
        'direccion' => env('DTE_EMISOR_DIRECCION', 'AV. DEMO 123'),
        'comuna' => env('DTE_EMISOR_COMUNA', 'SANTIAGO'),
        'ciudad' => env('DTE_EMISOR_CIUDAD', 'SANTIAGO'),
        'rut_envia' => env('DTE_RUT_ENVIA', '76000000-0'),
    ],

    'envio' => [
        'rut_receptor' => env('DTE_ENVIO_RUT_RECEPTOR', '60803000-K'),
        'fecha_resolucion' => env('DTE_ENVIO_FECHA_RESOL', '2024-01-01'),
        'numero_resolucion' => env('DTE_ENVIO_NRO_RESOL', '0'),
    ],

    'signature' => [
        'disk' => env('DTE_SIGNATURE_DISK', 'local'),
        'pfx_path' => env('DTE_SIGNATURE_PFX_PATH', 'certs/dte_certificacion.pfx'),
        'pfx_password' => env('DTE_SIGNATURE_PFX_PASSWORD', ''),
    ],

    'sii' => [
        'endpoints' => [
            'seed' => env('SII_SEED_URL', 'https://maullin.sii.cl/DTEWS/CrSeed.jws'),
            'token' => env('SII_TOKEN_URL', 'https://maullin.sii.cl/DTEWS/GetTokenFromSeed.jws'),
            'recepcion' => env('SII_RECEPCION_URL', 'https://maullin.sii.cl/DTEWS/RecepcionDTE.jws'),
            'estado' => env('SII_ESTADO_URL', 'https://maullin.sii.cl/DTEWS/QueryEstUp.jws'),
        ],
    ],
];
