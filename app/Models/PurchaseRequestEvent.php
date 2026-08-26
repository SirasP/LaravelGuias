<?php

namespace App\Models;

use App\Enums\PurchaseRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PurchaseRequestEvent extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const SUBMITTED = 'submitted';

    public const RESUBMITTED = 'resubmitted';

    public const APPROVED = 'approved';

    public const CHANGES_REQUESTED = 'changes_requested';

    public const REJECTED = 'rejected';

    public const ATTACHMENT_ADDED = 'attachment_added';

    public const ATTACHMENT_REMOVED = 'attachment_removed';

    public const CANCELLED = 'cancelled';

    public const CANCELLATION_REQUESTED = 'cancellation_requested';

    /** Se creó la cotización en Odoo. Queda en el historial como todo lo demás. */
    public const EXPORTED = 'exported_to_odoo';

    public const CANCELLATION_WITHDRAWN = 'cancellation_withdrawn';

    public const AI_DRAFTED = 'ai_drafted';

    protected $fillable = [
        'purchase_request_id',
        'actor_id',
        'actor_name_snapshot',
        'actor_role_snapshot',
        'event_type',
        'from_status',
        'to_status',
        'revision_number',
        'comment',
        'metadata',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => PurchaseRequestStatus::class,
            'to_status' => PurchaseRequestStatus::class,
            'revision_number' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Los eventos de solicitudes de compra son inmutables.');
        });

        static::deleting(function (): never {
            throw new LogicException('Los eventos de solicitudes de compra no se eliminan.');
        });
    }

    /**
     * Etiqueta legible del evento. La interfaz es en español: el historial no
     * debe mostrar el identificador técnico en inglés.
     */
    public function label(): string
    {
        return match ($this->event_type) {
            self::CREATED => 'Solicitud creada',
            self::UPDATED => 'Solicitud editada',
            self::SUBMITTED => 'Enviada a revisión',
            self::RESUBMITTED => 'Corregida y reenviada',
            self::APPROVED => 'Aprobada',
            self::CHANGES_REQUESTED => 'Devuelta para corrección',
            self::REJECTED => 'Rechazada',
            self::CANCELLED => 'Anulada',
            self::CANCELLATION_REQUESTED => 'Anulación solicitada',
            self::EXPORTED => 'Enviada a Odoo',
            self::CANCELLATION_WITHDRAWN => 'Petición de anulación retirada',
            self::ATTACHMENT_ADDED => 'Adjunto agregado',
            self::ATTACHMENT_REMOVED => 'Adjunto eliminado',
            self::AI_DRAFTED => 'Borrador sugerido por el asistente',
            default => \Illuminate\Support\Str::headline((string) $this->event_type),
        };
    }

    /** Marca visual del evento, que acompaña al texto y nunca lo sustituye. */
    public function dotClasses(): string
    {
        return match ($this->event_type) {
            self::APPROVED => 'bg-emerald-500',
            self::REJECTED, self::CANCELLED => 'bg-rose-500',
            self::CHANGES_REQUESTED, self::CANCELLATION_REQUESTED => 'bg-amber-500',
            self::EXPORTED => 'bg-violet-500',
            self::CANCELLATION_WITHDRAWN => 'bg-slate-400',
            self::SUBMITTED, self::RESUBMITTED => 'bg-blue-500',
            default => 'bg-slate-400',
        };
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
