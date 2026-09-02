<?php

namespace App\Enums;

enum PurchaseRequestStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case CHANGES_REQUESTED = 'changes_requested';
    case RESUBMITTED = 'resubmitted';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Borrador',
            self::SUBMITTED => 'Enviada',
            self::CHANGES_REQUESTED => 'Cambios solicitados',
            self::RESUBMITTED => 'Reenviada',
            self::APPROVED => 'Aprobada',
            self::REJECTED => 'Rechazada',
            self::CANCELLED => 'Anulada',
        };
    }

    /**
     * Marca textual que acompaña al color. Un estado nunca se distingue sólo
     * por el color: el lector daltónico y el PDF en blanco y negro necesitan
     * el símbolo y la etiqueta.
     */
    public function icon(): string
    {
        return match ($this) {
            self::DRAFT => '✎',
            self::SUBMITTED => '↑',
            self::CHANGES_REQUESTED => '↺',
            self::RESUBMITTED => '⇈',
            self::APPROVED => '✓',
            self::REJECTED => '✕',
            self::CANCELLED => '⊘',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::DRAFT => 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-800 dark:text-slate-200',
            self::SUBMITTED => 'bg-blue-50 text-blue-700 ring-blue-700/10 dark:bg-blue-950/40 dark:text-blue-300',
            self::CHANGES_REQUESTED => 'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-950/40 dark:text-amber-300',
            self::RESUBMITTED => 'bg-indigo-50 text-indigo-700 ring-indigo-700/10 dark:bg-indigo-950/40 dark:text-indigo-300',
            self::APPROVED => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-950/40 dark:text-emerald-300',
            self::REJECTED => 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-950/40 dark:text-rose-300',
            self::CANCELLED => 'bg-zinc-100 text-zinc-700 ring-zinc-600/20 dark:bg-zinc-800 dark:text-zinc-300',
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::DRAFT, self::CHANGES_REQUESTED], true);
    }

    public function isReviewable(): bool
    {
        return in_array($this, [self::SUBMITTED, self::RESUBMITTED], true);
    }

    /**
     * Forma verbal del estado, para redactar avisos.
     *
     * `label()` sirve para etiquetas («Cambios solicitados»), pero produce
     * frases torcidas al encadenarla: «tu solicitud fue Cambios solicitados».
     */
    public function pastTense(): string
    {
        return match ($this) {
            self::DRAFT => 'devuelta a borrador',
            self::SUBMITTED => 'enviada a revisión',
            self::CHANGES_REQUESTED => 'devuelta para corrección',
            self::RESUBMITTED => 'corregida y reenviada',
            self::APPROVED => 'aprobada',
            self::REJECTED => 'rechazada',
            self::CANCELLED => 'anulada',
        };
    }

    /**
     * Un estado terminal ya no admite ninguna transición.
     *
     * Se deduce de la tabla de transiciones en vez de repetir la lista: así
     * no puede quedar mintiendo cuando la tabla cambia. `APPROVED` dejó de
     * ser terminal el 02-09-2026, al abrirse la corrección de lo aprobado.
     */
    public function isFinal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /**
     * Máquina de estados explícita. Toda transición del módulo se valida
     * contra esta tabla, tanto en la policy como en el servicio de dominio.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::DRAFT => [self::SUBMITTED, self::CANCELLED],
            self::SUBMITTED => [self::APPROVED, self::REJECTED, self::CHANGES_REQUESTED, self::CANCELLED],
            self::CHANGES_REQUESTED => [self::RESUBMITTED, self::CANCELLED],
            self::RESUBMITTED => [self::APPROVED, self::REJECTED, self::CHANGES_REQUESTED, self::CANCELLED],
            // Aprobada no es el final: hasta que se envía a Odoo todavía se
            // puede devolver para corregir o anular. Quien revisa a veces se
            // entera después de que faltaba algo, y no tener salida obligaba
            // a crear otra solicitud y dejar ésta colgada para siempre.
            // Una vez en Odoo la puerta se cierra, pero eso lo decide la
            // policy: el estado por sí solo no sabe si ya se exportó.
            self::APPROVED => [self::CHANGES_REQUESTED, self::CANCELLED],
            self::REJECTED, self::CANCELLED => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** Estados que exigen un comentario obligatorio para llegar a ellos. */
    public function requiresComment(): bool
    {
        return in_array($this, [self::CHANGES_REQUESTED, self::REJECTED, self::CANCELLED], true);
    }

    /**
     * Estados que están esperando decisión del revisor.
     *
     * Son dos: la enviada por primera vez y la corregida que volvió. Contar
     * sólo la primera hace desaparecer de la bandeja las solicitudes ya
     * corregidas, que es justamente cuando más urge revisarlas.
     *
     * @return list<self>
     */
    public static function awaitingReview(): array
    {
        return [self::SUBMITTED, self::RESUBMITTED];
    }

    /** Clave del filtro agrupado que reúne todo lo pendiente de decisión. */
    public const GROUP_AWAITING_REVIEW = 'por_revisar';

    /** @return list<string> */
    public static function awaitingReviewValues(): array
    {
        return array_map(fn (self $status): string => $status->value, self::awaitingReview());
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $status): string => $status->value, self::cases());
    }
}
