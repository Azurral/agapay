<?php

use Spatie\Backup\Notifications\Notifiable;
use Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes;

/*
 * AGAPAY cloud copy: 3-2-1 backups (spatie/laravel-backup).
 *   Copy 1 = the live database on the host.
 *   Copy 2 = an encrypted zip (database + photos + reports) on the server's own volume (disk "backups").
 *   Copy 3 = the same zip on S3-compatible object storage at another provider (disk "offsite"), when configured.
 * The zip is encrypted with AES-256 (BACKUP_ARCHIVE_PASSWORD), so neither storage provider can read the records.
 * The office copy ("AGAPAY for Windows") keeps using its own tools\backup.ps1.
 */
$disks = array_values(array_filter(['backups', env('BACKUP_OFFSITE_BUCKET') ? 'offsite' : null]));

return [
    'backup' => [
        'name' => 'AGAPAY',

        'source' => [
            'files' => [
                // Damage photos and generated reports (the private disk). Import uploads are temporary and left out.
                'include' => [storage_path('app/private')],
                'exclude' => [storage_path('app/private/imports'), storage_path('app/private/damage-staging')],
                'follow_links' => false,
                'ignore_unreadable_directories' => true,
                'relative_path' => storage_path('app'),
            ],
            'databases' => [env('DB_CONNECTION', 'mysql')],
        ],

        'database_dump_compressor' => null,
        'database_dump_file_timestamp_format' => null,
        'database_dump_filename_base' => 'database',
        'database_dump_file_extension' => '',

        'destination' => [
            'compression_method' => ZipArchive::CM_DEFAULT,
            'compression_level' => 9,
            'filename_prefix' => 'AGAPAY-backup-',
            'disks' => $disks,
            // A failed off-site upload must not lose the on-server copy.
            'continue_on_failure' => true,
        ],

        'temporary_directory' => storage_path('app/backup-temp'),
        'password' => env('BACKUP_ARCHIVE_PASSWORD'),
        'encryption' => 'default',
        'verify_backup' => false,
        'tries' => 2,
        'retry_delay' => 30,
    ],

    // No e-mail is set up; results are shown on the Backups page and in the audit trail instead.
    'notifications' => [
        'notifications' => [],
        'notifiable' => Notifiable::class,
        'mail' => ['to' => 'agapay@example.org', 'from' => ['address' => env('MAIL_FROM_ADDRESS', 'agapay@example.org'), 'name' => 'AGAPAY']],
        'slack' => ['webhook_url' => '', 'channel' => null, 'username' => null, 'icon' => null],
        'discord' => ['webhook_url' => '', 'username' => '', 'avatar_url' => ''],
        'webhook' => ['url' => ''],
    ],

    'log_channel' => null,

    'monitor_backups' => [
        [
            'name' => 'AGAPAY',
            'disks' => $disks,
            'health_checks' => [MaximumAgeInDays::class => 1, MaximumStorageInMegabytes::class => 5000],
        ],
    ],

    // Like the office copy: the newest 30 daily backups are kept.
    'cleanup' => [
        'strategy' => DefaultStrategy::class,
        'default_strategy' => [
            'keep_all_backups_for_days' => 30,
            'keep_daily_backups_for_days' => 0,
            'keep_weekly_backups_for_weeks' => 0,
            'keep_monthly_backups_for_months' => 0,
            'keep_yearly_backups_for_years' => 0,
            'delete_oldest_backups_when_using_more_megabytes_than' => 5000,
        ],
        'tries' => 1,
        'retry_delay' => 0,
    ],
];
