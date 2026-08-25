<?php

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'company_code' => 'EHE',
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
