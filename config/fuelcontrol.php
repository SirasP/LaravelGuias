<?php

return [
    'estanques' => (bool) env('FUELCONTROL_ESTANQUES', false),
    'preview' => (bool) env('FUELCONTROL_PREVIEW', false),
    'web_estanques' => (bool) env('FUELCONTROL_WEB_ESTANQUES', false),
    'capacidades' => [
        1 => env('FUELCONTROL_ESTANQUE_1_CAPACIDAD'),
        2 => env('FUELCONTROL_ESTANQUE_2_CAPACIDAD'),
    ],
    // Destino confirmado expresamente para pendientes de una app anterior.
    'recuperacion_salidas' => [
        'estanque_id' => (int) env('FUELCONTROL_RECUPERACION_ESTANQUE', 0),
        'usuarios' => array_values(array_filter(array_map('trim', explode('|', (string) env('FUELCONTROL_RECUPERACION_USUARIOS', ''))))),
        'fecha_limite' => env('FUELCONTROL_RECUPERACION_FECHA_LIMITE'),
        'cola_revision' => env('FUELCONTROL_COLA_EN_REVISION'),
    ],
];
