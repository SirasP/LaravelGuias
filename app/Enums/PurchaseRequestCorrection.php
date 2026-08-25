<?php

namespace App\Enums;

/**
 * Puntos de la solicitud que un revisor puede marcar como "hay que corregir
 * esto" al devolverla.
 *
 * Vive en un solo lugar para que el formulario del revisor, la validación y
 * el resaltado que ve el solicitante no puedan desincronizarse. Las partidas
 * no están aquí: se marcan por número de línea (`item:3`), porque son
 * variables.
 */
enum PurchaseRequestCorrection: string
{
    case DEPARTMENT = 'department';
    case REQUIRED_DATE = 'required_date';
    case REQUESTED_FOR = 'requested_for_name';
    case REASON = 'reason';
    case PRIORITY = 'priority';
    case COST_CENTER = 'cost_center';
    case DELIVERY_LOCATION = 'delivery_location';
    case SUPPLIERS = 'suggested_suppliers';
    case ATTACHMENTS = 'attachments';
    case ITEMS = 'items';

    public function label(): string
    {
        return match ($this) {
            self::DEPARTMENT => 'Área o departamento',
            self::REQUIRED_DATE => 'Fecha requerida',
            self::REQUESTED_FOR => 'Solicitado para',
            self::REASON => 'Motivo de la compra',
            self::PRIORITY => 'Prioridad o justificación de urgencia',
            self::COST_CENTER => 'Centro de costo o proyecto',
            self::DELIVERY_LOCATION => 'Lugar de entrega o uso',
            self::SUPPLIERS => 'Proveedores sugeridos',
            self::ATTACHMENTS => 'Antecedentes adjuntos',
            self::ITEMS => 'Las partidas en general',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }

    /**
     * Marca de una partida concreta, por su posición en la solicitud.
     */
    public static function itemKey(int $sortOrder): string
    {
        return 'item:'.$sortOrder;
    }

    /** Devuelve la posición si la marca apunta a una partida, o null. */
    public static function itemPosition(string $key): ?int
    {
        if (! str_starts_with($key, 'item:')) {
            return null;
        }

        $position = substr($key, 5);

        return ctype_digit($position) ? (int) $position : null;
    }

    /** Etiqueta legible de cualquier marca, sea campo o partida. */
    public static function labelFor(string $key): string
    {
        $position = self::itemPosition($key);

        if ($position !== null) {
            return 'Partida N° '.$position;
        }

        return self::tryFrom($key)?->label() ?? $key;
    }
}
