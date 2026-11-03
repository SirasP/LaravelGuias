<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Una factura de proveedor subida a mano —PDF o foto— para llevarla a Odoo.
 *
 * `data` es la factura tal como se va a crear: lo que leyó la IA y, encima,
 * lo que corrigió la persona. `raw_response` guarda la respuesta del modelo
 * sin tocar, para poder auditar una lectura después.
 */
class SupplierInvoiceUpload extends Model
{
    public const QUEUED = 'queued';

    public const READING = 'reading';

    /** El lector no estaba: la Mac se durmió. Se reintenta solo. */
    public const WAITING = 'waiting';

    public const READ = 'read';

    public const FAILED = 'failed';

    public const SENDING = 'sending';

    public const SENT = 'sent';

    public const STATUS_LABELS = [
        self::QUEUED => 'En cola',
        self::READING => 'Leyendo',
        self::WAITING => 'Esperando a la IA',
        self::READ => 'Por revisar',
        self::FAILED => 'No se pudo leer',
        self::SENDING => 'Creando en Odoo',
        self::SENT => 'En Odoo',
    ];

    /** Los tipos de documento que se pueden crear desde aquí. */
    public const TIPOS = [
        '33' => 'Factura electrónica',
        '34' => 'Factura exenta',
    ];

    protected $fillable = [
        'public_id', 'user_id', 'uploader_name_snapshot',
        'disk', 'path', 'original_name', 'mime_type', 'size', 'sha256',
        'status', 'source_kind', 'model_used', 'attempts', 'error_message',
        'raw_response', 'data', 'warnings',
        'odoo_move_id', 'odoo_move_name', 'odoo_amount_total', 'sent_by_name', 'sent_at',
        'finished_at',
    ];

    protected $casts = [
        'raw_response' => 'array',
        'data' => 'array',
        'warnings' => 'array',
        'odoo_amount_total' => 'float',
        'sent_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $upload): void {
            $upload->public_id ??= (string) Str::ulid();
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

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /** Todavía la tiene la IA: la pantalla se refresca sola. */
    public function isReading(): bool
    {
        return in_array($this->status, [self::QUEUED, self::READING, self::WAITING], true);
    }

    /** Se puede corregir y llevar a Odoo. */
    public function isEditable(): bool
    {
        return in_array($this->status, [self::READ, self::FAILED], true);
    }

    public function isSent(): bool
    {
        return $this->status === self::SENT;
    }

    /** Un dato de la factura: `rut`, `folio`, `total`… */
    public function dato(string $clave): mixed
    {
        return data_get($this->data ?? [], $clave);
    }

    /** @return list<array{detalle: string, cantidad: ?float, precio: ?float}> */
    public function lineas(): array
    {
        return array_values((array) ($this->data['lineas'] ?? []));
    }

    /**
     * La que ya estaba en Odoo, si estaba: `{id, name, state, url}`.
     *
     * @return array<string, mixed>|null
     */
    public function yaEnOdoo(): ?array
    {
        return $this->isSent() ? null : ($this->data['ya_en_odoo'] ?? null);
    }

    /** Anota (o borra) que esta factura ya estaba en Odoo. */
    public function marcarDuplicada(?array $duplicada, string $odooUrl): void
    {
        $data = $this->data ?? [];
        $nuevo = $duplicada === null ? null : [...$duplicada, 'url' => $odooUrl];

        if (($data['ya_en_odoo'] ?? null) === $nuevo) {
            return;
        }

        $data['ya_en_odoo'] = $nuevo;
        $this->forceFill(['data' => array_filter($data, fn ($v, $k) => $k !== 'ya_en_odoo' || $v !== null, ARRAY_FILTER_USE_BOTH)])->save();
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }
}
