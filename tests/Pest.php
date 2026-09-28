<?php

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
