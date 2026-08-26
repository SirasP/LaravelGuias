<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    /**
     * A dónde van los avisos por correo.
     *
     * Laravel consulta este método antes de mirar la propiedad `email`, así que
     * basta con definirlo para que todas las notificaciones lo respeten.
     * Sin dirección de avisos se usa la de acceso, que es como funcionaba antes.
     */
    public function routeNotificationForMail(): ?string
    {
        return $this->notification_email ?: $this->email;
    }

    protected $fillable = [
        'name',
        'email',
        'notification_email',
        'password',
        'role',
    ];


    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Roles del módulo de Solicitudes de Compra.
     *
     * Se montan sobre la columna `role` que el proyecto ya usa, en vez de
     * introducir una tabla paralela. Los roles heredados (`viewer`,
     * `bodeguero`) conservan su significado y actúan como solicitantes.
     */
    public const ROLE_ADMIN = 'admin';

    public const ROLE_BUYER = 'comprador';

    public const ROLE_AUDITOR = 'auditor';

    public const ROLE_VIEWER = 'viewer';

    public const ROLE_BODEGUERO = 'bodeguero';

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * Quién decide sobre una solicitud: aprobar, rechazar o devolver.
     *
     * En Agrícola EHE decide únicamente el administrador. El rol `comprador`
     * acompaña el proceso —ve todas las solicitudes y sus documentos— pero no
     * emite el visto bueno.
     */
    public function isPurchaseReviewer(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /** Compras acompaña y consulta, sin poder de decisión. */
    public function isPurchaseBuyer(): bool
    {
        return $this->role === self::ROLE_BUYER;
    }

    /** Auditor: lectura de todas las solicitudes, sin poder alterarlas. */
    public function isPurchaseAuditor(): bool
    {
        return $this->role === self::ROLE_AUDITOR;
    }

    /** Ve solicitudes ajenas: para decidir, para comprar o para auditar. */
    public function canSeeAllPurchaseRequests(): bool
    {
        return $this->isPurchaseReviewer()
            || $this->isPurchaseBuyer()
            || $this->isPurchaseAuditor();
    }

    /** Sólo el administrador mantiene áreas, unidades y centros de costo. */
    public function canAdministerPurchaseCatalogs(): bool
    {
        return $this->isAdmin();
    }

    /**
     * El auditor es estrictamente de consulta: no origina solicitudes.
     */
    public function canCreatePurchaseRequests(): bool
    {
        return ! $this->isPurchaseAuditor();
    }

    public function isBodeguero(): bool
    {
        return $this->role === 'bodeguero';
    }

    public function canSeeValues(): bool
    {
        return $this->role !== 'bodeguero';
    }

    public function purchaseRequests(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class, 'user_id');
    }

    public function reviewedPurchaseRequests(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class, 'reviewed_by');
    }

    public function purchaseRequestAttachments(): HasMany
    {
        return $this->hasMany(PurchaseRequestAttachment::class, 'uploaded_by');
    }

    /**
     * Avisos del módulo sin leer.
     *
     * Se filtra por tipo para no mezclar con notificaciones de otros módulos
     * que puedan usar la misma tabla más adelante.
     */
    public function unreadPurchaseNotificationsCount(): int
    {
        return $this->unreadNotifications()
            ->whereIn('type', [
                \App\Notifications\PurchaseRequestSubmitted::class,
                \App\Notifications\PurchaseRequestReviewed::class,
            ])
            ->count();
    }

    public function purchaseRequestEvents(): HasMany
    {
        return $this->hasMany(PurchaseRequestEvent::class, 'actor_id');
    }
}
