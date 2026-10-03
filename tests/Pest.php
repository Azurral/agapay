<?php

use App\Models\Barangay;
use App\Models\Beneficiary;
use App\Models\Intervention;
use App\Models\InterventionRecord;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/** Seed the three roles and their default permissions. */
function seedRoles(): void
{
    test()->seed(RolePermissionSeeder::class);
}

/** Create a user holding the given role slug (roles must be seeded). */
function userWithRole(?string $slug, array $attributes = []): User
{
    return User::factory()->create([
        'role_id' => $slug ? Role::where('slug', $slug)->value('id') : null,
        ...$attributes,
    ]);
}

/** Id of a seeded barangay by name (BarangaySeeder must have run). */
function brgy(string $name): int
{
    return Barangay::where('name', $name)->valueOrFail('id');
}

/**
 * Beneficiaries sharing one household (same address in Poblacion), e.g. sameHousehold(['Juan', 'Maria']).
 *
 * @param  list<string>  $firstNames
 * @return list<Beneficiary>
 */
function sameHousehold(array $firstNames, string $lastName = 'Dela Cruz'): array
{
    return array_map(fn (string $first) => Beneficiary::factory()->create([
        'first_name' => $first, 'middle_name' => null, 'last_name' => $lastName,
        'address' => 'Purok 3', 'barangay_id' => brgy('Poblacion'),
    ]), $firstNames);
}

/** An intervention record for the beneficiary in the current cycle (InterventionSeeder must have run). */
function record(Beneficiary $beneficiary, Intervention $intervention, array $attributes = []): InterventionRecord
{
    return InterventionRecord::factory()->create([
        'beneficiary_id' => $beneficiary->id, 'intervention_id' => $intervention->id, ...$attributes,
    ]);
}

/** A seeded intervention by source and name. */
function program(string $source, string $name): Intervention
{
    return Intervention::where(['source' => $source, 'name' => $name])->sole();
}
