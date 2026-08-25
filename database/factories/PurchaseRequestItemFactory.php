<?php

namespace Database\Factories;

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseRequestItem>
 */
class PurchaseRequestItemFactory extends Factory
{
    protected $model = PurchaseRequestItem::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'purchase_request_id' => PurchaseRequest::factory(),
            'sort_order' => 1,
            'product_service' => fake()->words(3, true),
            'specification' => null,
            'quantity' => fake()->randomFloat(3, 1, 500),
            'unit' => 'Unidades',
            'quantity_note' => null,
            'destination' => null,
        ];
    }
}
