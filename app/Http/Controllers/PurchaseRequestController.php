<?php

namespace App\Http\Controllers;

use App\Enums\PurchaseRequestStatus;
use App\Http\Requests\PurchaseRequests\ReviewPurchaseRequestRequest;
use App\Http\Requests\PurchaseRequests\StorePurchaseRequestRequest;
use App\Http\Requests\PurchaseRequests\UpdatePurchaseRequestRequest;
use App\Models\CostCenter;
use App\Models\Department;
use App\Models\Location;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestAttachment;
use App\Models\PurchaseRequestEvent;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Notifications\PurchaseRequestReviewed;
use App\Notifications\PurchaseRequestSubmitted;
use App\Services\PurchaseRequests\PurchaseRequestSnapshotService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PurchaseRequestController extends Controller
{
    public function __construct(
        private readonly PurchaseRequestSnapshotService $snapshots,
    ) {
    }

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', PurchaseRequest::class);

        $rawStatus = (string) $request->query('status');
        // `por_revisar` no es un estado: agrupa lo enviado y lo reenviado, que
        // son las dos formas de estar esperando una decisión.
        $awaitingReview = $rawStatus === PurchaseRequestStatus::GROUP_AWAITING_REVIEW;
        $status = $awaitingReview ? null : PurchaseRequestStatus::tryFrom($rawStatus);
        $filters = $this->filtersFrom($request);

        $query = PurchaseRequest::query()
            ->visibleTo($request->user())
            ->with('requester');

        // Los contadores reflejan los filtros activos salvo el de estado, para
        // que las pestañas sigan mostrando cuánto hay en cada uno.
        $counts = array_fill_keys(PurchaseRequestStatus::values(), 0);
        $counts = array_replace(
            $counts,
            $this->applyFilters($query->clone(), $filters)
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status')
                ->map(fn ($count): int => (int) $count)
                ->all(),
        );
        $counts['total'] = array_sum($counts);
        // Contador del grupo: lo enviado más lo corregido que volvió.
        $counts[PurchaseRequestStatus::GROUP_AWAITING_REVIEW] = array_sum(array_map(
            fn (string $value): int => $counts[$value] ?? 0,
            PurchaseRequestStatus::awaitingReviewValues(),
        ));

        $this->applyFilters($query, $filters);

        if ($awaitingReview) {
            $query->whereIn('status', PurchaseRequestStatus::awaitingReviewValues());
        } elseif ($status !== null) {
            $query->where('status', $status->value);
        }

        /** @var LengthAwarePaginator<int, PurchaseRequest> $requests */
        $requests = $query->latest('created_at')->paginate(20)->withQueryString();

        $departments = Department::query()->forCompany()->active()->ordered()->get();

        return response()->view('purchase_requests.index', compact(
            'requests', 'counts', 'status', 'filters', 'departments', 'rawStatus',
        ));
    }

    /**
     * Filtros de la bandeja. Todos se resuelven en la base de datos sobre el
     * total de registros: nunca sobre la página ya paginada.
     *
     * @return array<string, string|null>
     */
    private function filtersFrom(Request $request): array
    {
        $trim = static function (mixed $value): ?string {
            $value = is_string($value) ? trim($value) : null;

            return $value === '' ? null : $value;
        };

        return [
            'search' => $trim($request->query('search')),
            'department' => $trim($request->query('department')),
            'requester' => $trim($request->query('requester')),
            'requested_from' => $trim($request->query('requested_from')),
            'requested_to' => $trim($request->query('requested_to')),
            'required_from' => $trim($request->query('required_from')),
            'required_to' => $trim($request->query('required_to')),
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<PurchaseRequest>  $query
     * @param  array<string, string|null>  $filters
     * @return \Illuminate\Database\Eloquent\Builder<PurchaseRequest>
     */
    private function applyFilters($query, array $filters)
    {
        return $query
            ->when($filters['search'], function ($query, string $search): void {
                // Folio, motivo o nombre del solicitante en una sola caja.
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where(function ($query) use ($like): void {
                    $query->where('folio', 'like', $like)
                        ->orWhere('reason', 'like', $like)
                        ->orWhere('requester_name_snapshot', 'like', $like)
                        ->orWhere('requested_for_name', 'like', $like);
                });
            })
            ->when($filters['department'], fn ($query, string $value) => $query->where('department', $value))
            ->when($filters['requester'], function ($query, string $value): void {
                $like = '%'.addcslashes($value, '%_\\').'%';
                $query->where('requester_name_snapshot', 'like', $like);
            })
            ->when($filters['requested_from'], fn ($query, string $value) => $query->whereDate('request_date', '>=', $value))
            ->when($filters['requested_to'], fn ($query, string $value) => $query->whereDate('request_date', '<=', $value))
            ->when($filters['required_from'], fn ($query, string $value) => $query->whereDate('required_date', '>=', $value))
            ->when($filters['required_to'], fn ($query, string $value) => $query->whereDate('required_date', '<=', $value));
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', PurchaseRequest::class);

        return response()->view('purchase_requests.create', $this->catalogs());
    }

    /**
     * Catálogos que alimentan los desplegables del formulario.
     *
     * @return array<string, \Illuminate\Support\Collection<int, mixed>>
     */
    private function catalogs(): array
    {
        return [
            'departments' => Department::query()->forCompany()->active()->ordered()->get(),
            'units' => UnitOfMeasure::query()->forCompany()->active()->ordered()->get(),
            'costCenters' => CostCenter::query()->forCompany()->active()->ordered()->get(),
            'locations' => Location::query()->forCompany()->active()->ordered()->get(),
        ];
    }

    public function store(StorePurchaseRequestRequest $request): RedirectResponse
    {
        Gate::authorize('create', PurchaseRequest::class);

        $data = $request->validated();
        $user = $request->user();
        $storedPaths = [];

        try {
            $purchaseRequest = DB::transaction(function () use ($data, $request, $user, &$storedPaths): PurchaseRequest {
                $purchaseRequest = PurchaseRequest::query()->create(array_merge(
                    Arr::only($data, $this->requestFields()),
                    [
                        'user_id' => $user->getKey(),
                        'requester_name_snapshot' => $user->name,
                        'request_date' => today(),
                        'status' => PurchaseRequestStatus::DRAFT,
                        'revision_number' => 1,
                        'lock_version' => 0,
                    ],
                ));

                $purchaseRequest->forceFill([
                    'folio' => $this->folioFor($purchaseRequest),
                ])->save();

                $this->replaceItems($purchaseRequest, $data['items'] ?? []);
                $this->recordEvent($purchaseRequest, $user, PurchaseRequestEvent::CREATED, null, PurchaseRequestStatus::DRAFT, $request, [
                    'item_count' => count($data['items'] ?? []),
                    'attachment_count' => count($data['attachments'] ?? []),
                ]);
                $this->storeAttachments($purchaseRequest, $data['attachments'] ?? [], $user, $storedPaths);

                return $purchaseRequest;
            });
        } catch (Throwable $exception) {
            $this->deleteStoredPaths($storedPaths);

            throw $exception;
        }

        return to_route('purchase_requests.show', $purchaseRequest)
            ->with('success', 'La solicitud se guardó como borrador.');
    }

    public function show(PurchaseRequest $purchaseRequest): Response
    {
        Gate::authorize('view', $purchaseRequest);

        $purchaseRequest->load(['requester', 'reviewer', 'items', 'attachments.uploader', 'events.actor', 'revisions']);

        return response()->view('purchase_requests.show', compact('purchaseRequest'));
    }

    public function edit(PurchaseRequest $purchaseRequest): Response
    {
        Gate::authorize('update', $purchaseRequest);

        $purchaseRequest->load('items');

        return response()->view(
            'purchase_requests.edit',
            array_merge(compact('purchaseRequest'), $this->catalogs()),
        );
    }

    public function update(UpdatePurchaseRequestRequest $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        Gate::authorize('update', $purchaseRequest);

        $data = $request->validated();
        $user = $request->user();
        $storedPaths = [];

        try {
            DB::transaction(function () use ($data, $purchaseRequest, $request, $user, &$storedPaths): void {
                $lockedRequest = PurchaseRequest::query()->lockForUpdate()->findOrFail($purchaseRequest->getKey());
                Gate::authorize('update', $lockedRequest);

                $lockedRequest->fill(Arr::only($data, $this->requestFields()));
                $lockedRequest->save();

                $this->replaceItems($lockedRequest, $data['items'] ?? []);
                $attachmentCount = $this->storeAttachments($lockedRequest, $data['attachments'] ?? [], $user, $storedPaths);
                $this->recordEvent($lockedRequest, $user, PurchaseRequestEvent::UPDATED, $lockedRequest->status, $lockedRequest->status, $request, [
                    'item_count' => count($data['items'] ?? []),
                    'attachment_count' => $attachmentCount,
                ]);
            });
        } catch (Throwable $exception) {
            $this->deleteStoredPaths($storedPaths);

            throw $exception;
        }

        return to_route('purchase_requests.show', $purchaseRequest)
            ->with('success', 'La solicitud fue actualizada.');
    }

    public function submit(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        Gate::authorize('submit', $purchaseRequest);

        DB::transaction(function () use ($purchaseRequest, $request): void {
            $lockedRequest = PurchaseRequest::query()->lockForUpdate()->findOrFail($purchaseRequest->getKey());
            $user = $request->user();

            Gate::authorize('submit', $lockedRequest);

            if (in_array($lockedRequest->status, [PurchaseRequestStatus::SUBMITTED, PurchaseRequestStatus::RESUBMITTED], true)) {
                return;
            }

            if (! $this->hasValidItem($lockedRequest)) {
                throw ValidationException::withMessages([
                    'items' => 'Agrega al menos una partida válida antes de enviar la solicitud.',
                ]);
            }

            $fromStatus = $lockedRequest->status;
            $toStatus = $fromStatus === PurchaseRequestStatus::CHANGES_REQUESTED
                ? PurchaseRequestStatus::RESUBMITTED
                : PurchaseRequestStatus::SUBMITTED;

            $lockedRequest->forceFill([
                'status' => $toStatus,
                'revision_number' => $toStatus === PurchaseRequestStatus::RESUBMITTED
                    ? $lockedRequest->revision_number + 1
                    : $lockedRequest->revision_number,
                'submitted_at' => now(),
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_comment' => null,
                // Las marcas pendientes se limpian al reenviar: el solicitante
                // ya actuó. Lo que se pidió corregir sigue en el historial.
                'requested_corrections' => null,
            ])->save();

            // La revisión se congela dentro de la misma transacción que el
            // cambio de estado: no puede existir una enviada sin snapshot.
            $revision = $this->snapshots->capture($lockedRequest, $user);

            $this->recordEvent(
                $lockedRequest,
                $user,
                $toStatus === PurchaseRequestStatus::RESUBMITTED
                    ? PurchaseRequestEvent::RESUBMITTED
                    : PurchaseRequestEvent::SUBMITTED,
                $fromStatus,
                $toStatus,
                $request,
                ['revision_id' => $revision->getKey()],
            );

            // Enviar correo dentro de la transacción mantendría filas
            // bloqueadas mientras responde el SMTP, y un fallo de correo
            // desharía el envío. Se avisa una vez confirmado el commit.
            DB::afterCommit(fn () => $this->notifyReviewers($lockedRequest, $user));
        });

        return to_route('purchase_requests.show', $purchaseRequest)
            ->with('success', 'La solicitud fue enviada a revisión.');
    }

    public function approve(ReviewPurchaseRequestRequest $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        return $this->review($request, $purchaseRequest, 'approve', PurchaseRequestStatus::APPROVED, PurchaseRequestEvent::APPROVED, 'La solicitud fue aprobada.');
    }

    public function requestChanges(ReviewPurchaseRequestRequest $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        return $this->review($request, $purchaseRequest, 'requestChanges', PurchaseRequestStatus::CHANGES_REQUESTED, PurchaseRequestEvent::CHANGES_REQUESTED, 'Se solicitaron cambios al solicitante.');
    }

    public function reject(ReviewPurchaseRequestRequest $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        return $this->review($request, $purchaseRequest, 'reject', PurchaseRequestStatus::REJECTED, PurchaseRequestEvent::REJECTED, 'La solicitud fue rechazada.');
    }

    public function pdf(Request $request, PurchaseRequest $purchaseRequest): Response
    {
        Gate::authorize('downloadPdf', $purchaseRequest);

        $purchaseRequest->load(['requester', 'reviewer', 'items', 'events.actor']);

        // Una revisión concreta se sirve desde su snapshot inmutable. Un
        // borrador todavía no tiene revisión: se previsualiza en vivo y se
        // marca como tal en el propio documento.
        $requested = $request->query('revision');
        $revision = $requested !== null
            ? $purchaseRequest->revisions()->where('revision_number', (int) $requested)->firstOrFail()
            : $purchaseRequest->currentRevision();

        $filename = ($purchaseRequest->folio ?? 'solicitud-compra')
            .($revision !== null ? '-r'.$revision->revision_number : '-borrador')
            .'.pdf';

        if ($revision !== null) {
            return response($this->snapshots->pdfContents($revision), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
            ]);
        }

        return response(
            Pdf::loadView('purchase_requests.pdf', [
                'purchaseRequest' => $purchaseRequest,
                'revision' => null,
                'header' => null,
                'items' => null,
            ])->setPaper('letter')->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
            ],
        );
    }

    /**
     * Anula la solicitud. El borrador lo anula su autor; una vez enviada,
     * sólo un revisor, y siempre con motivo.
     */
    public function cancel(ReviewPurchaseRequestRequest $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        Gate::authorize('cancel', $purchaseRequest);

        $data = $request->validated();

        DB::transaction(function () use ($data, $purchaseRequest, $request): void {
            $lockedRequest = PurchaseRequest::query()->lockForUpdate()->findOrFail($purchaseRequest->getKey());
            Gate::authorize('cancel', $lockedRequest);

            if ($lockedRequest->lock_version !== $data['lock_version']) {
                throw ValidationException::withMessages([
                    'lock_version' => 'La solicitud fue modificada por otra persona. Recarga la página antes de anularla.',
                ]);
            }

            $fromStatus = $lockedRequest->status;
            $lockedRequest->forceFill([
                'status' => PurchaseRequestStatus::CANCELLED,
                'lock_version' => $lockedRequest->lock_version + 1,
                'cancelled_at' => now(),
                'cancelled_by' => $request->user()->getKey(),
                'cancellation_reason' => $data['comment'],
            ])->save();

            $this->recordEvent(
                $lockedRequest,
                $request->user(),
                PurchaseRequestEvent::CANCELLED,
                $fromStatus,
                PurchaseRequestStatus::CANCELLED,
                $request,
                [],
                $data['comment'],
            );

            DB::afterCommit(fn () => $this->notifyRequester($lockedRequest, $request->user(), 'anulada'));
        });

        return to_route('purchase_requests.show', $purchaseRequest)
            ->with('success', 'La solicitud fue anulada.');
    }

    /**
     * El solicitante no anula lo ya enviado: deja constancia de que pide la
     * anulación y un revisor decide.
     */
    public function requestCancellation(ReviewPurchaseRequestRequest $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        Gate::authorize('requestCancellation', $purchaseRequest);

        $data = $request->validated();

        DB::transaction(function () use ($data, $purchaseRequest, $request): void {
            $lockedRequest = PurchaseRequest::query()->lockForUpdate()->findOrFail($purchaseRequest->getKey());
            Gate::authorize('requestCancellation', $lockedRequest);

            // La solicitud no cambia de estado: sólo queda marcada la petición.
            $lockedRequest->forceFill([
                'cancellation_requested_at' => now(),
                'cancellation_reason' => $data['comment'],
            ])->save();

            $this->recordEvent(
                $lockedRequest,
                $request->user(),
                PurchaseRequestEvent::CANCELLATION_REQUESTED,
                $lockedRequest->status,
                $lockedRequest->status,
                $request,
                [],
                $data['comment'],
            );

            DB::afterCommit(fn () => $this->notifyReviewers($lockedRequest, $request->user(), 'cancellation_requested'));
        });

        return to_route('purchase_requests.show', $purchaseRequest)
            ->with('success', 'Se registró tu solicitud de anulación. Compras la revisará.');
    }

    public function downloadAttachment(PurchaseRequest $purchaseRequest, PurchaseRequestAttachment $attachment): StreamedResponse
    {
        Gate::authorize('downloadAttachment', $purchaseRequest);
        $this->ensureAttachmentBelongsToRequest($purchaseRequest, $attachment);

        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type],
        );
    }

    public function destroyAttachment(Request $request, PurchaseRequest $purchaseRequest, PurchaseRequestAttachment $attachment): RedirectResponse
    {
        Gate::authorize('destroyAttachment', $purchaseRequest);
        $this->ensureAttachmentBelongsToRequest($purchaseRequest, $attachment);

        $disk = $attachment->disk;
        $path = $attachment->path;

        DB::transaction(function () use ($purchaseRequest, $attachment, $request): void {
            $lockedRequest = PurchaseRequest::query()->lockForUpdate()->findOrFail($purchaseRequest->getKey());
            Gate::authorize('destroyAttachment', $lockedRequest);

            $lockedAttachment = PurchaseRequestAttachment::query()
                ->whereKey($attachment->getKey())
                ->where('purchase_request_id', $lockedRequest->getKey())
                ->firstOrFail();

            $lockedAttachment->delete();
            $this->recordEvent($lockedRequest, $request->user(), PurchaseRequestEvent::ATTACHMENT_REMOVED, $lockedRequest->status, $lockedRequest->status, $request, [
                'attachment_id' => $lockedAttachment->getKey(),
                'original_name' => $lockedAttachment->original_name,
            ]);
        });

        Storage::disk($disk)->delete($path);

        return to_route('purchase_requests.show', $purchaseRequest)
            ->with('success', 'El adjunto fue eliminado.');
    }

    private function review(
        ReviewPurchaseRequestRequest $request,
        PurchaseRequest $purchaseRequest,
        string $ability,
        PurchaseRequestStatus $toStatus,
        string $eventType,
        string $message,
    ): RedirectResponse {
        Gate::authorize($ability, $purchaseRequest);

        $data = $request->validated();
        DB::transaction(function () use ($data, $purchaseRequest, $request, $ability, $toStatus, $eventType): void {
            $lockedRequest = PurchaseRequest::query()->lockForUpdate()->findOrFail($purchaseRequest->getKey());
            Gate::authorize($ability, $lockedRequest);

            if ($lockedRequest->lock_version !== $data['lock_version']) {
                throw ValidationException::withMessages([
                    'lock_version' => 'La solicitud fue modificada por otra persona. Recarga la página antes de revisar.',
                ]);
            }

            $fromStatus = $lockedRequest->status;

            // Los puntos marcados sólo tienen sentido al devolver para
            // corregir; en una aprobación o un rechazo no hay nada que arreglar.
            $corrections = $toStatus === PurchaseRequestStatus::CHANGES_REQUESTED
                ? ($data['corrections'] ?? [])
                : [];

            $lockedRequest->forceFill([
                'status' => $toStatus,
                'lock_version' => $lockedRequest->lock_version + 1,
                'reviewed_by' => $request->user()->getKey(),
                'reviewed_at' => now(),
                'review_comment' => $data['comment'] ?? null,
                'requested_corrections' => $corrections ?: null,
            ])->save();

            $this->recordEvent(
                $lockedRequest,
                $request->user(),
                $eventType,
                $fromStatus,
                $toStatus,
                $request,
                // Queda también en el evento: la columna se limpia al reenviar,
                // pero el historial debe poder decir qué se pidió corregir.
                $corrections !== [] ? ['corrections' => $corrections] : [],
                $data['comment'] ?? null,
            );

            DB::afterCommit(fn () => $this->notifyRequester(
                $lockedRequest,
                $request->user(),
                $toStatus->pastTense(),
                $data['comment'] ?? null,
            ));
        });

        return to_route('purchase_requests.show', $purchaseRequest)->with('success', $message);
    }

    /**
     * Notifica al grupo revisor. Se excluye a quien realizó la acción para no
     * avisarle de su propio movimiento.
     */
    private function notifyReviewers(PurchaseRequest $purchaseRequest, User $actor, string $reason = 'submitted'): void
    {
        // Sólo quien decide necesita enterarse de que hay algo pendiente.
        $reviewers = User::query()
            ->where('role', User::ROLE_ADMIN)
            ->where('is_active', true)
            ->whereKeyNot($actor->getKey())
            ->get();

        $this->dispatchNotification(
            fn () => Notification::send($reviewers, new PurchaseRequestSubmitted($purchaseRequest, $actor, $reason)),
            $purchaseRequest,
        );
    }

    /** Notifica al solicitante el resultado de la revisión. */
    private function notifyRequester(
        PurchaseRequest $purchaseRequest,
        User $actor,
        string $outcome,
        ?string $comment = null,
    ): void {
        $requester = $purchaseRequest->requester;

        if ($requester === null || $requester->getKey() === $actor->getKey()) {
            return;
        }

        $this->dispatchNotification(
            fn () => $requester->notify(new PurchaseRequestReviewed($purchaseRequest, $actor, $outcome, $comment)),
            $purchaseRequest,
        );
    }

    /**
     * Envía el aviso sin dejar que un problema de correo rompa la operación.
     *
     * La decisión ya está guardada y auditada: si el SMTP falla, se registra
     * el problema y la persona igual verá el aviso dentro del sistema.
     */
    private function dispatchNotification(callable $send, PurchaseRequest $purchaseRequest): void
    {
        try {
            $send();
        } catch (Throwable $exception) {
            Log::warning('No se pudo enviar el aviso de la solicitud de compra.', [
                'folio' => $purchaseRequest->folio,
                'motivo' => $exception->getMessage(),
            ]);
        }
    }

    /** @return list<string> */
    private function requestFields(): array
    {
        return [
            'department',
            'requested_for_name',
            'required_date',
            'reason',
            'priority',
            'urgent_reason',
            'cost_center',
            'delivery_location',
            'internal_notes',
            'suggested_suppliers',
        ];
    }

    /** @param array<int, array<string, mixed>> $items */
    private function replaceItems(PurchaseRequest $purchaseRequest, array $items): void
    {
        $purchaseRequest->items()->delete();
        $purchaseRequest->items()->createMany(array_map(
            fn (array $item): array => Arr::only($item, [
                'sort_order', 'product_service', 'specification', 'quantity', 'unit', 'quantity_note', 'destination',
            ]),
            $items,
        ));
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @param  list<string>  $storedPaths
     */
    private function storeAttachments(PurchaseRequest $purchaseRequest, array $files, User $user, array &$storedPaths): int
    {
        $count = 0;

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $extension = $file->guessExtension() ?: $file->extension() ?: 'bin';
            $path = $file->storeAs(
                'purchase-requests/'.$purchaseRequest->getKey().'/'.$purchaseRequest->revision_number,
                (string) Str::uuid().'.'.$extension,
                'local',
            );

            if ($path === false) {
                throw new RuntimeException('No fue posible almacenar el archivo adjunto.');
            }

            $storedPaths[] = $path;
            $attachment = $purchaseRequest->attachments()->create([
                'revision_number' => $purchaseRequest->revision_number,
                'uploaded_by' => $user->getKey(),
                'disk' => 'local',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size' => $file->getSize(),
                'sha256' => hash_file('sha256', $file->getRealPath()),
            ]);

            $this->recordEvent($purchaseRequest, $user, PurchaseRequestEvent::ATTACHMENT_ADDED, $purchaseRequest->status, $purchaseRequest->status, request(), [
                'attachment_id' => $attachment->getKey(),
                'original_name' => $attachment->original_name,
            ]);
            $count++;
        }

        return $count;
    }

    private function hasValidItem(PurchaseRequest $purchaseRequest): bool
    {
        return $purchaseRequest->items()
            ->where('quantity', '>', 0)
            ->whereRaw("TRIM(product_service) <> ''")
            ->whereRaw("TRIM(unit) <> ''")
            ->exists();
    }

    /** @param array<string, mixed> $metadata */
    private function recordEvent(
        PurchaseRequest $purchaseRequest,
        User $actor,
        string $eventType,
        ?PurchaseRequestStatus $fromStatus,
        ?PurchaseRequestStatus $toStatus,
        Request $request,
        array $metadata = [],
        ?string $comment = null,
    ): void {
        $purchaseRequest->events()->create([
            'actor_id' => $actor->getKey(),
            'actor_name_snapshot' => $actor->name,
            'actor_role_snapshot' => $actor->role,
            'event_type' => $eventType,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'revision_number' => $purchaseRequest->revision_number,
            'comment' => $comment,
            'metadata' => $metadata ?: null,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 512, ''),
        ]);
    }

    private function folioFor(PurchaseRequest $purchaseRequest): string
    {
        return sprintf('SC-%s-%06d', $purchaseRequest->request_date->format('Y'), $purchaseRequest->getKey());
    }

    private function ensureAttachmentBelongsToRequest(PurchaseRequest $purchaseRequest, PurchaseRequestAttachment $attachment): void
    {
        abort_unless($attachment->purchase_request_id === $purchaseRequest->getKey(), 404);
    }

    /** @param list<string> $storedPaths */
    private function deleteStoredPaths(array $storedPaths): void
    {
        foreach ($storedPaths as $path) {
            Storage::disk('local')->delete($path);
        }
    }
}
