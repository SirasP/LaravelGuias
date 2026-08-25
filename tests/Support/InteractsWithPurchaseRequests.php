<?php

namespace Tests\Support;

use App\Models\PurchaseRequest;
use App\Models\User;

trait InteractsWithPurchaseRequests
{
    /**
     * Payload contract shared by the purchase-request feature tests.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function validPurchaseRequestPayload(array $overrides = []): array
    {
        return array_replace([
            'department' => 'Administración',
            'requested_for_name' => 'Marco del Riego',
            'required_date' => now()->addDays(7)->toDateString(),
            'reason' => 'Reposición de materiales operacionales',
            'priority' => 'normal',
            'delivery_location' => 'Predio principal',
            'internal_notes' => 'Antecedente ficticio para pruebas automatizadas.',
            'items' => [
                $this->validPurchaseRequestItem(),
            ],
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function validPurchaseRequestItem(array $overrides = []): array
    {
        return array_replace([
            'product_service' => 'Tubo PVC sanitario',
            'specification' => '75 mm',
            'quantity' => '1,5',
            'unit' => 'cubos',
            'quantity_note' => null,
            'destination' => 'Casa de operarios',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createPurchaseRequestDraft(User $user, array $overrides = []): PurchaseRequest
    {
        $response = $this
            ->actingAs($user)
            ->post(route('purchase_requests.store'), $this->validPurchaseRequestPayload($overrides));

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        // Desempatar por id: dos borradores creados dentro del mismo segundo
        // comparten `created_at` y `latest()` devolvería cualquiera de ellos.
        return PurchaseRequest::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->firstOrFail();
    }

    protected function submitPurchaseRequest(User $user, PurchaseRequest $request): PurchaseRequest
    {
        $response = $this
            ->actingAs($user)
            ->post(route('purchase_requests.submit', $request));

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        return $request->fresh();
    }
}
