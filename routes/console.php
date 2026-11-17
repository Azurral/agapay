<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cloud copy (config/backup.php): an encrypted backup every day at noon on the server and off-site; 30 days are kept.
// The office copy has its own scheduled tools\backup.ps1, so these do not run there.
$cloud = fn () => config('agapay.edition') === 'cloud';
Schedule::command('backup:run')->dailyAt('12:00')->timezone('Asia/Manila')->when($cloud)->withoutOverlapping();
Schedule::command('backup:clean')->dailyAt('12:30')->timezone('Asia/Manila')->when($cloud)->withoutOverlapping();
