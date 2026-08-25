<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

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
