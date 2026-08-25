<?php

namespace App\Services\PurchaseRequests\Reading;

/**
 * Puerto del lector de cotizaciones.
 *
 * Recibe un documento —PDF o imagen— y devuelve las partidas que reconoce.
 * Quien lo implemente debe respetar tres reglas, que son las mismas que rigen
 * todo el módulo:
 *
 *  - No inventar. Si una cantidad o una unidad no está en el documento, se
 *    devuelve vacía y se avisa; nunca se rellena con algo plausible.
 *  - No fusionar líneas repetidas: dos renglones iguales pueden ser dos
 *    destinos distintos.
 *  - No adivinar unidades fuera del catálogo que se le entrega.
 */
interface QuotationReader
{
    public function isEnabled(): bool;

    /** Nombre legible del motor, para el registro y la auditoría. */
    public function describe(): string;

    /**
     * @param  string  $absolutePath  ruta local del archivo ya validado
     * @param  list<string>  $knownUnits  unidades del catálogo
     */
    public function read(string $absolutePath, string $mimeType, array $knownUnits = []): QuotationReading;
}
