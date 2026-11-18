<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Cloud copy: the Administrator's view of the 3-2-1 backups (config/backup.php). Each backup is an encrypted zip of the
 * database and the photos/reports, kept on the server's volume ("backups") and at another provider ("offsite").
 */
class BackupController extends Controller
{
    /** Where each backup disk is, in the words shown on the page. */
    private const LOCATIONS = ['backups' => 'On the server', 'offsite' => 'Off-site storage'];

    private const FILE_PATTERN = '/^AGAPAY-backup-\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}\.zip$/';

    public function __construct()
    {
        abort_unless(config('agapay.edition') === 'cloud', 404);
    }

    public function index(): View
    {
        $disks = $this->disks();

        $backups = collect($disks)
            ->flatMap(fn (string $disk) => collect($this->files($disk))->map(fn (string $path) => [
                'disk' => $disk,
                'name' => basename($path),
                'location' => self::LOCATIONS[$disk] ?? $disk,
                'size' => $this->size($disk, $path),
                'date' => $this->dateOf(basename($path)),
            ]))
            ->sortByDesc('name')
            ->groupBy('name')
            ->map(fn ($copies, string $name) => [
                'name' => $name,
                'date' => $copies->first()['date'],
                'size' => $copies->max('size'),
                'copies' => $copies->values()->all(),
            ])
            ->values();

        return view('backups.index', [
            'backups' => $backups,
            'offsite' => in_array('offsite', $disks, true),
        ]);
    }

    public function store(): RedirectResponse
    {
        try {
            $exit = Artisan::call('backup:run', []);
            $output = Artisan::output();
        } catch (Throwable $e) {
            $exit = 1;
            $output = $e->getMessage();
        }

        if ($exit !== 0) {
            return redirect()->route('backups.index')->with('error', 'The backup failed: '.trim(strtok((string) $output, "\n") ?: 'unknown error').'. Nothing was changed.');
        }

        AuditLogger::record('Created Backup', label: 'Back up now');
        $where = in_array('offsite', $this->disks(), true) ? 'on the server and off-site' : 'on the server';

        return redirect()->route('backups.index')->with('status', "Backup completed: saved {$where}, encrypted.");
    }

    public function download(string $disk, string $file): StreamedResponse
    {
        abort_unless(in_array($disk, $this->disks(), true) && preg_match(self::FILE_PATTERN, $file), 404);
        $path = $this->folder().'/'.$file;
        abort_unless(Storage::disk($disk)->exists($path), 404);

        AuditLogger::record('Downloaded Backup', label: $file.' ('.(self::LOCATIONS[$disk] ?? $disk).')');

        return Storage::disk($disk)->download($path, $file);
    }

    /**
     * Deletes one backup from every place it is kept (e.g. old ones made with a password that has since been changed).
     * The newest backup always stays, so there is something to restore.
     */
    public function destroy(string $file): RedirectResponse
    {
        abort_unless(preg_match(self::FILE_PATTERN, $file), 404);
        $path = $this->folder().'/'.$file;
        $where = array_values(array_filter($this->disks(), fn (string $disk) => in_array($path, $this->files($disk), true)));
        abort_if($where === [], 404);

        $newest = collect($this->disks())->flatMap(fn (string $disk) => array_map('basename', $this->files($disk)))->max();
        if ($file === $newest) {
            return redirect()->route('backups.index')->with('error', 'The newest backup is kept, so there is always one to restore. Make a new backup first.');
        }

        foreach ($where as $disk) {
            Storage::disk($disk)->delete($path);
        }
        $places = implode(' and ', array_map(fn (string $disk) => strtolower(self::LOCATIONS[$disk] ?? $disk), $where));
        AuditLogger::record('Deleted Backup', label: $file.' ('.$places.')');

        return redirect()->route('backups.index')->with('status', "Backup {$file} deleted ({$places}).");
    }

    /** @return list<string> the backup destinations configured (copy 2 and, when set up, copy 3) */
    private function disks(): array
    {
        return array_values(array_intersect(array_keys(self::LOCATIONS), (array) config('backup.backup.destination.disks', [])));
    }

    /** spatie/laravel-backup keeps each app's zips in a folder named after config backup.backup.name. */
    private function folder(): string
    {
        return (string) config('backup.backup.name', 'AGAPAY');
    }

    /** @return list<string> */
    private function files(string $disk): array
    {
        try {
            return array_values(array_filter(Storage::disk($disk)->files($this->folder()), fn (string $path) => preg_match(self::FILE_PATTERN, basename($path))));
        } catch (Throwable) {
            return []; // an unreachable off-site store must not break the page
        }
    }

    private function size(string $disk, string $path): int
    {
        try {
            return (int) Storage::disk($disk)->size($path);
        } catch (Throwable) {
            return 0;
        }
    }

    private function dateOf(string $file): ?Carbon
    {
        return preg_match('/(\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2})/', $file, $m)
            ? Carbon::createFromFormat('Y-m-d-H-i-s', $m[1])
            : null;
    }
}
