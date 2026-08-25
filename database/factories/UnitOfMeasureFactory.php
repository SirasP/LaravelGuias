<?php

namespace Database\Factories;

use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnitOfMeasure>
 */
class UnitOfMeasureFactory extends Factory
{
    protected $model = UnitOfMeasure::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->word());

        return [
            'company_code' => 'EHE',
            'code' => strtolower(substr($name, 0, 6)),
            'name' => $name,
            'allows_decimals' => true,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
