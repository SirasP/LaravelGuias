<?php

namespace Database\Factories;

use App\Enums\PurchaseRequestStatus;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseRequest>
 */
class PurchaseRequestFactory extends Factory
{
    protected $model = PurchaseRequest::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $requestDate = now()->toImmutable();

        return [
            'company_code' => PurchaseRequest::COMPANY_CODE,
            'company_name_snapshot' => PurchaseRequest::COMPANY_NAME,
            'user_id' => User::factory(),
            'requester_name_snapshot' => fake()->name(),
            'requested_for_name' => null,
            'request_date' => $requestDate->toDateString(),
            'required_date' => $requestDate->addDays(7)->toDateString(),
            'department' => 'Administración',
            'reason' => fake()->sentence(8),
            'priority' => 'normal',
            'urgent_reason' => null,
            'cost_center' => null,
            'delivery_location' => null,
            'internal_notes' => null,
            'suggested_suppliers' => [],
            'status' => PurchaseRequestStatus::DRAFT,
            'revision_number' => 1,
            'lock_version' => 0,
        ];
    }

    public function configure(): static
    {
        // El folio se emite en el servidor una vez que existe la clave.
        return $this->afterCreating(function (PurchaseRequest $purchaseRequest): void {
            if (blank($purchaseRequest->folio)) {
                $purchaseRequest->forceFill([
                    'folio' => sprintf(
                        'SC-%s-%06d',
                        $purchaseRequest->request_date->format('Y'),
                        $purchaseRequest->getKey(),
                    ),
                ])->save();
            }
        });
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (): array => [
            'user_id' => $user->getKey(),
            'requester_name_snapshot' => $user->name,
        ]);
    }

    public function submitted(): static
    {
        return $this->state(fn (): array => [
            'status' => PurchaseRequestStatus::SUBMITTED,
            'submitted_at' => now(),
        ]);
    }

    public function changesRequested(): static
    {
        return $this->state(fn (): array => [
            'status' => PurchaseRequestStatus::CHANGES_REQUESTED,
            'submitted_at' => now()->subDay(),
            'review_comment' => 'Falta detallar la especificación.',
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => PurchaseRequestStatus::APPROVED,
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now(),
        ]);
    }

    public function urgent(): static
    {
        return $this->state(fn (): array => [
            'priority' => 'urgent',
            'urgent_reason' => 'Detiene el riego del cuartel 3.',
        ]);
    }
}
