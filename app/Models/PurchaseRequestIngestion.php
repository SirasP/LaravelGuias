<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Un documento de cotización entregado al asistente.
 *
 * Guarda el archivo, lo que el modelo entendió y en qué terminó. Es la
 * respuesta a «¿de dónde salió esta solicitud?» cuando la creó el asistente.
 */
class PurchaseRequestIngestion extends Model
{
    use HasFactory;

    public const PENDING = 'pending';

    /** El modelo no está accesible; el documento espera a que vuelva. */
    public const WAITING = 'waiting';

    public const PROCESSING = 'processing';

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    /** Se leyó, pero con tan poca confianza que no vale crear un borrador. */
    public const NEEDS_REVIEW = 'needs_review';

    public const SOURCE_PDF_TEXT = 'pdf_text';

    public const SOURCE_PDF_SCAN = 'pdf_scan';

    public const SOURCE_IMAGE = 'image';

    protected $fillable = [
        'public_id', 'company_code', 'user_id', 'uploader_name_snapshot',
        'purchase_request_id', 'disk', 'path', 'original_name', 'mime_type',
        'size', 'sha256', 'status', 'source_kind', 'model_used',
        'supplier_name', 'supplier_tax_id', 'customer_tax_id', 'customer_matches_company',
        'extracted', 'warnings', 'error_message', 'attempts',
        'started_at', 'finished_at', 'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'customer_matches_company' => 'boolean',
            'extracted' => 'array',
            'warnings' => 'array',
            'attempts' => 'integer',
            'duration_ms' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $ingestion): void {
            $ingestion->public_id ??= (string) Str::ulid();
            $ingestion->company_code ??= 'EHE';
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::PENDING => 'En cola',
            self::WAITING => 'Esperando al lector',
            self::PROCESSING => 'Leyendo el documento',
            self::COMPLETED => 'Borrador creado',
            self::NEEDS_REVIEW => 'Leído con dudas',
            self::FAILED => 'No se pudo leer',
            default => Str::headline((string) $this->status),
        };
    }

    public function statusIcon(): string
    {
        return match ($this->status) {
            self::PENDING => '◷',
            self::WAITING => '⏸',
            self::PROCESSING => '⟳',
            self::COMPLETED => '✓',
            self::NEEDS_REVIEW => '?',
            self::FAILED => '✕',
            default => '·',
        };
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::COMPLETED, self::FAILED, self::NEEDS_REVIEW], true);
    }
}
