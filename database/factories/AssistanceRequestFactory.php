<?php

namespace Database\Factories;

use App\Models\AssistanceRequest;
use App\Models\Beneficiary;
use App\Models\Intervention;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssistanceRequest>
 */
class AssistanceRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'beneficiary_id' => Beneficiary::factory(),
            'intervention_id' => fn () => Intervention::where('source', Intervention::SOURCE_LGU)->inRandomOrder()->value('id'),
            'quantity' => fake()->randomElement([null, 1, 2, 5, 10]),
            'reason' => fake()->randomElement(['Crops damaged', 'Seedbed washed out', 'No harvest this season']),
            'status' => AssistanceRequest::PENDING,
        ];
    }
}
