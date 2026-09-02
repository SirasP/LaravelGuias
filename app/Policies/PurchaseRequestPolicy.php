<?php

namespace App\Policies;

use App\Enums\PurchaseRequestStatus;
use App\Models\PurchaseRequest;
use App\Models\User;

/**
 * Matriz de permisos del módulo.
 *
 * Solicitante  crea, edita y envía lo propio; corrige lo devuelto.
 * Revisor      aprueba, rechaza y devuelve lo ajeno (Compras o Jefatura).
 * Administrador  revisa, además de mantener catálogos y usuarios.
 * Auditor      sólo lectura de todo; no origina ni altera nada.
 */
class PurchaseRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $this->owns($user, $purchaseRequest)
            || $user->canSeeAllPurchaseRequests();
    }

    public function create(User $user): bool
    {
        return $user->canCreatePurchaseRequests();
    }

    public function update(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $this->owns($user, $purchaseRequest)
            && $purchaseRequest->status->isEditable();
    }

    public function submit(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $this->owns($user, $purchaseRequest)
            && ($purchaseRequest->status->isEditable()
                // Un reenvío del mismo estado se absorbe como idempotente en
                // el controlador; la policy no debe bloquearlo antes.
                || $purchaseRequest->status->isReviewable());
    }

    public function approve(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $this->canReview($user, $purchaseRequest, PurchaseRequestStatus::APPROVED);
    }

    /**
     * Enviar a Odoo es lo contrario de aprobar: sólo tiene sentido DESPUÉS.
     *
     * Por eso no sirve el permiso de `approve`, que deja de valer en cuanto la
     * solicitud queda aprobada. Aquí basta con ser de Compras y que la
     * solicitud ya esté aprobada.
     */
    public function exportToOdoo(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->isPurchaseReviewer()
            && $purchaseRequest->status === PurchaseRequestStatus::APPROVED;
    }

    public function requestChanges(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $this->canReview($user, $purchaseRequest, PurchaseRequestStatus::CHANGES_REQUESTED);
    }

    public function reject(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $this->canReview($user, $purchaseRequest, PurchaseRequestStatus::REJECTED);
    }

    /**
     * El solicitante anula su propio borrador. Una vez enviada, sólo un
     * revisor puede anularla; el solicitante debe pedirlo.
     */
    public function cancel(User $user, PurchaseRequest $purchaseRequest): bool
    {
        // Lo que ya está en Odoo se anula en Odoo. Anularlo aquí dejaría a
        // los dos sistemas contando cosas distintas sobre la misma compra.
        if ($purchaseRequest->hasBeenExportedToOdoo()) {
            return false;
        }

        if (! $purchaseRequest->status->canTransitionTo(PurchaseRequestStatus::CANCELLED)) {
            return false;
        }

        if ($purchaseRequest->status === PurchaseRequestStatus::DRAFT) {
            return $this->owns($user, $purchaseRequest);
        }

        return $user->isPurchaseReviewer();
    }

    /**
     * Tras el envío el solicitante pide la anulación, con motivo.
     *
     * Quien ya puede anular directamente no necesita pedirlo: así la pantalla
     * no ofrece las dos acciones a la vez.
     */
    public function requestCancellation(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $this->owns($user, $purchaseRequest)
            && ! $this->cancel($user, $purchaseRequest)
            && ! $purchaseRequest->status->isFinal()
            && $purchaseRequest->status !== PurchaseRequestStatus::DRAFT;
    }

    /** Retirar la propia petición de anulación, mientras nadie la resuelva. */
    public function withdrawCancellation(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $this->owns($user, $purchaseRequest)
            && $purchaseRequest->cancellation_requested_at !== null
            && ! $purchaseRequest->status->isFinal();
    }

    public function downloadPdf(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $this->view($user, $purchaseRequest);
    }

    public function downloadAttachment(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $this->view($user, $purchaseRequest);
    }

    public function destroyAttachment(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $this->update($user, $purchaseRequest);
    }

    private function owns(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $purchaseRequest->user_id === $user->getKey();
    }

    /**
     * Sólo el administrador decide, y la transición debe estar permitida por
     * la máquina de estados.
     *
     * A diferencia del criterio por defecto del documento de requisitos, el
     * administrador SÍ puede resolver sus propias solicitudes: en Agrícola EHE
     * es la única persona con esa atribución, y prohibírselo dejaría sus
     * solicitudes trabadas para siempre. Queda igualmente registrado en el
     * historial quién decidió y cuándo.
     */
    private function canReview(User $user, PurchaseRequest $purchaseRequest, PurchaseRequestStatus $target): bool
    {
        return $user->isPurchaseReviewer()
            && ! $purchaseRequest->hasBeenExportedToOdoo()
            && $purchaseRequest->status->canTransitionTo($target);
    }
}
