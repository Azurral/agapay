<?php

namespace Database\Factories;

use App\Models\Barangay;
use App\Models\Beneficiary;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Beneficiary>
 */
class BeneficiaryFactory extends Factory
{
    private const FIRST_NAMES = ['Juan', 'Maria', 'Pedro', 'Rosa', 'Carlos', 'Liza', 'Ana', 'Jose', 'Lorna', 'Teresa', 'Ramon', 'Elena'];

    private const LAST_NAMES = ['Dela Cruz', 'Santos', 'Reyes', 'Mendez', 'Ibanez', 'Domingo', 'Gomez', 'Bacani', 'Fagyan', 'Ganggangan'];

    public function definition(): array
    {
        return [
            'first_name' => fake()->randomElement(self::FIRST_NAMES),
            'middle_name' => fake()->randomElement(self::LAST_NAMES),
            'last_name' => fake()->randomElement(self::LAST_NAMES),
            'birthdate' => fake()->dateTimeBetween('-70 years', '-20 years')->format('Y-m-d'),
            'address' => 'Purok '.fake()->numberBetween(1, 9),
            'barangay_id' => fn () => Barangay::inRandomOrder()->value('id') ?? Barangay::create(['name' => 'Poblacion'])->id,
            'contact_number' => '0917-'.fake()->numerify('###-####'),
            'farm_location' => null,
            'crop_type' => fake()->randomElement(['Rice', 'Cabbage', 'Corn', 'Sweet Potato']),
            'rsbsa_number' => fn () => 'RSBSA-'.fake()->unique()->numerify('#####'),
            'rsbsa_status' => Beneficiary::RSBSA_REGISTERED,
            'source' => Beneficiary::SOURCE_MANUAL,
        ];
    }
}
