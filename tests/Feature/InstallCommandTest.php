<?php

use App\Models\Barangay;
use App\Models\Beneficiary;
use App\Models\Crop;
use App\Models\Disaster;
use App\Models\DistributionCycle;
use App\Models\Intervention;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\InterventionSeeder;
use Illuminate\Support\Facades\Hash;

it('sets up a clean AGAPAY with reference data and the administrator, but no sample records', function () {
    $this->artisan('agapay:install', ['--name' => 'Catherine', '--username' => 'catherine', '--password' => 'office-pass-1'])
        ->expectsOutputToContain('AGAPAY is ready')
        ->assertSuccessful();

    $admin = User::sole();
    expect($admin)->username->toBe('catherine')->name->toBe('Catherine')->must_change_password->toBeFalse()
        ->and($admin->role->slug)->toBe(Role::ADMIN)
        ->and(Hash::check('office-pass-1', $admin->password))->toBeTrue()
        ->and(Role::count())->toBe(4)
        ->and(Barangay::count())->toBe(count(Barangay::NAMES))
        ->and(Intervention::count())->toBe(count(InterventionSeeder::PROGRAMS))
        ->and(Crop::count())->toBeGreaterThan(0)
        ->and(Beneficiary::count())->toBe(0)
        ->and(DistributionCycle::count())->toBe(0)
        ->and(Disaster::count())->toBe(0);
});

it('asks for the administrator when no details are given', function () {
    $this->artisan('agapay:install')
        ->expectsQuestion('Administrator\'s full name', 'Catherine')
        ->expectsQuestion('Username', 'catherine')
        ->expectsQuestion('Password (at least 8 characters)', 'office-pass-1')
        ->expectsQuestion('Type the password again', 'office-pass-1')
        ->assertSuccessful();

    expect(User::sole()->username)->toBe('catherine');
});

it('refuses to run twice, so real records are never touched', function () {
    $this->artisan('agapay:install', ['--name' => 'Catherine', '--username' => 'catherine', '--password' => 'office-pass-1'])->assertSuccessful();

    $this->artisan('agapay:install', ['--name' => 'Other', '--username' => 'other', '--password' => 'office-pass-2'])
        ->expectsOutputToContain('already set up')
        ->assertFailed();
    expect(User::count())->toBe(1);
});

it('refuses a weak or mistyped password', function (array $answers) {
    $this->artisan('agapay:install', ['--name' => 'Catherine', '--username' => 'catherine', ...$answers])->assertFailed();

    expect(User::count())->toBe(0);
})->with([
    'too short' => [['--password' => 'short']],
    'bad username' => [['--username' => 'has space', '--password' => 'office-pass-1']],
]);

it('reports whether AGAPAY is set up', function () {
    $this->artisan('agapay:install', ['--status' => true])->expectsOutput('not installed')->assertExitCode(1);
    $this->artisan('agapay:install', ['--name' => 'Catherine', '--username' => 'catherine', '--password' => 'office-pass-1']);
    $this->artisan('agapay:install', ['--status' => true])->expectsOutput('installed')->assertSuccessful();
});
