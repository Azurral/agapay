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
    $this->artisan('agapay:install', ['--first-name' => 'Catherine', '--last-name' => 'Santos', '--username' => 'catherine', '--password' => 'office-pass-1'])
        ->expectsOutputToContain('AGAPAY is ready')
        ->assertSuccessful();

    $admin = User::sole();
    expect($admin)->username->toBe('catherine')->name->toBe('Catherine Santos')->must_change_password->toBeFalse()
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
        ->expectsQuestion('Last name', 'Santos')
        ->expectsQuestion('First name', 'Catherine')
        ->expectsQuestion('Middle initial (leave blank if none)', 'M')
        ->expectsQuestion('Suffix such as Jr. (leave blank if none)', '')
        ->expectsQuestion('Username', 'catherine')
        ->expectsQuestion('Password (at least 8 characters)', 'office-pass-1')
        ->expectsQuestion('Type the password again', 'office-pass-1')
        ->assertSuccessful();

    expect(User::sole())->username->toBe('catherine')->name->toBe('Catherine M. Santos');
});

it('refuses to run twice, so real records are never touched', function () {
    $this->artisan('agapay:install', ['--first-name' => 'Catherine', '--last-name' => 'Santos', '--username' => 'catherine', '--password' => 'office-pass-1'])->assertSuccessful();

    $this->artisan('agapay:install', ['--first-name' => 'Other', '--last-name' => 'Person', '--username' => 'other', '--password' => 'office-pass-2'])
        ->expectsOutputToContain('already set up')
        ->assertFailed();
    expect(User::count())->toBe(1);
});

it('refuses a weak or mistyped password', function (array $answers) {
    $this->artisan('agapay:install', ['--first-name' => 'Catherine', '--last-name' => 'Santos', '--username' => 'catherine', ...$answers])->assertFailed();

    expect(User::count())->toBe(0);
})->with([
    'too short' => [['--password' => 'short']],
    'no last name' => [['--last-name' => '', '--password' => 'office-pass-1']],
    'bad username' => [['--username' => 'has space', '--password' => 'office-pass-1']],
]);

it('reports whether AGAPAY is set up', function () {
    $this->artisan('agapay:install', ['--status' => true])->expectsOutput('not installed')->assertExitCode(1);
    $this->artisan('agapay:install', ['--first-name' => 'Catherine', '--last-name' => 'Santos', '--username' => 'catherine', '--password' => 'office-pass-1']);
    $this->artisan('agapay:install', ['--status' => true])->expectsOutput('installed')->assertSuccessful();
});

it('reads the administrator from a file and deletes the file right away', function () {
    $file = tempnam(sys_get_temp_dir(), 'agapay');
    file_put_contents($file, json_encode(['last_name' => 'Santos', 'first_name' => 'Catherine', 'middle_initial' => 'M',
        'suffix' => 'Jr.', 'username' => 'catherine', 'password' => 'office-pass-1']));

    $this->artisan('agapay:install', ['--from-file' => $file])->assertSuccessful();

    expect(User::sole()->name)->toBe('Catherine M. Santos Jr.')
        ->and(file_exists($file))->toBeFalse();
});
