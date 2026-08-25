<?php

namespace App\Models;

use App\Enums\PurchaseRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Copia congelada de una solicitud en el momento de enviarse.
 *
 * Es inmutable a propósito: el PDF de la revisión 1 debe seguir mostrando lo
 * que se envió en la revisión 1, aunque después la solicitud se corrija.
 */
class PurchaseRequestRevision extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_request_id',
        'revision_number',
        'status',
        'submitted_by',
        'submitted_by_name_snapshot',
        'submitted_at',
        'header_snapshot',
        'items_snapshot',
        'item_count',
        'pdf_disk',
        'pdf_path',
        'pdf_sha256',
    ];

    protected function casts(): array
    {
        return [
            'revision_number' => 'integer',
            'status' => PurchaseRequestStatus::class,
            'submitted_at' => 'datetime',
            'header_snapshot' => 'array',
            'items_snapshot' => 'array',
            'item_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $revision): void {
            // El PDF se materializa de forma diferida, así que esas tres
            // columnas son lo único que puede escribirse tras crear la
            // revisión. El contenido enviado jamás cambia.
            $mutable = ['pdf_disk', 'pdf_path', 'pdf_sha256', 'updated_at'];

            if (array_diff(array_keys($revision->getDirty()), $mutable) !== []) {
                throw new LogicException('El contenido de una revisión enviada es inmutable.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Las revisiones de solicitudes de compra no se eliminan.');
        });
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function hasPdf(): bool
    {
        return filled($this->pdf_path);
    }
}
