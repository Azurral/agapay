<?php

use App\Models\AuditLog;
use App\Models\Role;
use App\Support\Navigation;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification;
use Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification;

// Cloud copy: the Administrator's Backups page shows the encrypted 3-2-1 backups (server volume + off-site storage).

beforeEach(function () {
    seedRoles();
    config(['agapay.edition' => 'cloud', 'backup.backup.destination.disks' => ['backups', 'offsite']]);
    Storage::fake('backups');
    Storage::fake('offsite');
    $this->admin = userWithRole(Role::ADMIN);
});

it('is for the Administrator only', function () {
    $this->get('/backups')->assertRedirect(route('login'));
    $this->actingAs(userWithRole(Role::ENCODER))->get('/backups')->assertForbidden();
    $this->actingAs(userWithRole(Role::AGRITECH))->get('/backups')->assertForbidden();
    $this->actingAs($this->admin)->get('/backups')->assertOk();
});

it('does not exist on the office copy, which has its own Backup now tool', function () {
    config(['agapay.edition' => 'office']);

    $this->actingAs($this->admin)->get('/backups')->assertNotFound();
});

it('lists each backup with where it is kept, newest first', function () {
    Storage::disk('backups')->put('AGAPAY/AGAPAY-backup-2026-11-17-12-00-00.zip', str_repeat('x', 2048));
    Storage::disk('backups')->put('AGAPAY/AGAPAY-backup-2026-11-18-12-00-00.zip', 'x');
    Storage::disk('offsite')->put('AGAPAY/AGAPAY-backup-2026-11-18-12-00-00.zip', 'x');

    $this->actingAs($this->admin)->get('/backups')
        ->assertOk()
        ->assertSee('3-2-1')
        ->assertSeeInOrder(['AGAPAY-backup-2026-11-18-12-00-00.zip', 'AGAPAY-backup-2026-11-17-12-00-00.zip'])
        ->assertSee('On the server')
        ->assertSee('Off-site storage')
        ->assertSee('Encrypted');
});

it('says when the off-site copy is not set up', function () {
    config(['backup.backup.destination.disks' => ['backups']]);

    $this->actingAs($this->admin)->get('/backups')->assertOk()->assertSee('Off-site storage is not set up');
});

it('backs up now and records it in the audit trail', function () {
    Artisan::shouldReceive('call')->once()->with('backup:run', [])->andReturn(0);
    Artisan::shouldReceive('output')->andReturn('Backup completed!');

    $this->actingAs($this->admin)->post('/backups')
        ->assertRedirect('/backups')
        ->assertSessionHas('status', 'Backup completed: saved on the server and off-site, encrypted.');

    expect(AuditLog::where('action', 'Created Backup')->where('user_id', $this->admin->id)->exists())->toBeTrue();
});

it('reports a failed backup instead of claiming success', function () {
    Artisan::shouldReceive('call')->once()->andReturn(1);
    Artisan::shouldReceive('output')->andReturn('Backup failed because: mysqldump not found');

    $this->actingAs($this->admin)->post('/backups')
        ->assertRedirect('/backups')
        ->assertSessionHas('error');

    expect(AuditLog::where('action', 'Created Backup')->exists())->toBeFalse();
});

it('downloads a backup file and logs who downloaded it', function () {
    Storage::disk('backups')->put('AGAPAY/AGAPAY-backup-2026-11-18-12-00-00.zip', 'zip-bytes');

    $this->actingAs($this->admin)->get('/backups/backups/AGAPAY-backup-2026-11-18-12-00-00.zip')
        ->assertOk()
        ->assertDownload('AGAPAY-backup-2026-11-18-12-00-00.zip');

    expect(AuditLog::where('action', 'Downloaded Backup')->exists())->toBeTrue();
});

it('refuses files outside the backup folders', function () {
    Storage::disk('backups')->put('AGAPAY/AGAPAY-backup-2026-11-18-12-00-00.zip', 'zip-bytes');

    $this->actingAs($this->admin)->get('/backups/local/AGAPAY-backup-2026-11-18-12-00-00.zip')->assertNotFound();
    $this->actingAs($this->admin)->get('/backups/backups/..%2F..%2F.env')->assertNotFound();
    $this->actingAs($this->admin)->get('/backups/backups/AGAPAY-backup-1999-01-01-00-00-00.zip')->assertNotFound();
});

it('backs up every day at noon and keeps 30 days of backups', function () {
    $events = collect(app(Schedule::class)->events())->map(fn ($event) => [$event->command, $event->expression]);

    expect($events->first(fn ($e) => str_contains($e[0], 'backup:run')))->not->toBeNull()
        ->and($events->first(fn ($e) => str_contains($e[0], 'backup:run'))[1])->toBe('0 12 * * *')
        ->and($events->first(fn ($e) => str_contains($e[0], 'backup:clean')))->not->toBeNull();
});

it('shows Backups in the Administrator\'s menu on the cloud copy only', function () {
    $labels = fn () => collect(Navigation::items($this->admin))->pluck('label')->all();

    expect($labels())->toContain('Backups');

    config(['agapay.edition' => 'office']);
    expect($labels())->not->toContain('Backups');
});

it('lists every backup notification (with no channel), so a failure is reported instead of crashing', function () {
    $notifications = config('backup.notifications.notifications');

    foreach ([
        BackupHasFailedNotification::class,
        BackupWasSuccessfulNotification::class,
        CleanupHasFailedNotification::class,
        CleanupWasSuccessfulNotification::class,
        HealthyBackupWasFoundNotification::class,
        UnhealthyBackupWasFoundNotification::class,
    ] as $class) {
        expect($notifications)->toHaveKey($class)->and($notifications[$class])->toBe([]);
    }
});

it('dumps a hosted MySQL that has a self-signed certificate and limited rights', function () {
    expect(config('database.connections.mysql.dump.add_extra_option'))
        ->toContain('--skip-ssl-verify-server-cert')
        ->toContain('--no-tablespaces');
});

it('runs the scheduled backups only on the cloud copy', function () {
    $backup = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command, 'backup:run'));

    expect($backup->filtersPass(app()))->toBeTrue();

    config(['agapay.edition' => 'office']);
    expect($backup->filtersPass(app()))->toBeFalse();
});
