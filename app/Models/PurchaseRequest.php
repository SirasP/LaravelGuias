<?php

namespace App\Models;

use App\Enums\PurchaseRequestStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PurchaseRequest extends Model
{
    use HasFactory;

    public const COMPANY_CODE = 'EHE';

    public const COMPANY_NAME = 'Agrícola EHE SpA';

    protected $fillable = [
        'public_id',
        'company_code',
        'company_name_snapshot',
        'folio',
        'user_id',
        'requester_name_snapshot',
        'requested_for_name',
        'request_date',
        'required_date',
        'department',
        'reason',
        'priority',
        'currency',
        'odoo_order_id',
        'odoo_reference',
        'odoo_exported_at',
        'urgent_reason',
        'cost_center',
        'delivery_location',
        'internal_notes',
        'suggested_suppliers',
        'status',
        'revision_number',
        'lock_version',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'review_comment',
        'requested_corrections',
        'department_id',
        'cost_center_id',
        'location_id',
        'cancellation_requested_at',
        'cancellation_reason',
        'cancelled_at',
        'cancelled_by',
    ];

    protected function casts(): array
    {
        return [
            'request_date' => 'date',
            'required_date' => 'date',
            'suggested_suppliers' => 'array',
            'requested_corrections' => 'array',
            'status' => PurchaseRequestStatus::class,
            'revision_number' => 'integer',
            'lock_version' => 'integer',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'cancellation_requested_at' => 'datetime',
            'odoo_order_id' => 'integer',
            'odoo_exported_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $purchaseRequest): void {
            $purchaseRequest->public_id ??= (string) Str::ulid();
            $purchaseRequest->company_code ??= self::COMPANY_CODE;
            $purchaseRequest->company_name_snapshot ??= self::COMPANY_NAME;
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Suma de las partidas que tienen precio.
     *
     * Devuelve null si ninguna lo tiene: mostrar «$0» en una solicitud sin
     * cotizar diría algo falso sobre lo que cuesta.
     */
    public function total(): ?float
    {
        $conPrecio = $this->items->filter(fn ($item): bool => filled($item->unit_price));

        if ($conPrecio->isEmpty()) {
            return null;
        }

        return round($conPrecio->sum(fn ($item): float => (float) $item->lineTotal()), 2);
    }

    /** ¿Hay partidas sin precio dentro de una solicitud que sí tiene precios? */
    public function hasPartialPricing(): bool
    {
        if ($this->items->isEmpty()) {
            return false;
        }

        $conPrecio = $this->items->filter(fn ($item): bool => filled($item->unit_price))->count();

        return $conPrecio > 0 && $conPrecio < $this->items->count();
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class)->orderBy('sort_order');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(PurchaseRequestAttachment::class)->orderBy('created_at');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PurchaseRequestEvent::class)->orderBy('created_at')->orderBy('id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PurchaseRequestRevision::class)->orderBy('revision_number');
    }

    public function currentRevision(): ?PurchaseRequestRevision
    {
        return $this->revisions()->where('revision_number', $this->revision_number)->first();
    }

    /**
     * Se llama `departmentCatalog` y no `department` porque la columna
     * `department` guarda el nombre del área como snapshot histórico: si la
     * relación usara ese nombre, leer el atributo devolvería el texto y no el
     * modelo, de forma silenciosa.
     */
    public function departmentCatalog(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->canSeeAllPurchaseRequests()
            ? $query
            : $query->where('user_id', $user->getKey());
    }
}
