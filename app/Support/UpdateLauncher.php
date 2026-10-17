<?php

namespace App\Support;

use RuntimeException;

/**
 * Starts the office copy's updater (tools\update.ps1 in the "AGAPAY for Windows" folder). It runs as the
 * "AGAPAY Update" scheduled task, so it keeps going while the web server that served this request restarts.
 * It backs up, stops AGAPAY, swaps in the new version, applies database changes and starts AGAPAY again.
 */
class UpdateLauncher
{
    public function launch(string $version, string $url): void
    {
        $home = (string) ($_SERVER['AGAPAY_HOME'] ?? getenv('AGAPAY_HOME') ?: '');
        $updates = $home.'/data/updates';
        if (PHP_OS_FAMILY !== 'Windows' || $home === '' || ! is_dir($updates)) {
            throw new RuntimeException('Updates are installed from the AGAPAY for Windows folder only.');
        }
        if (! preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/', $version) || ! str_starts_with($url, 'https://github.com/')) {
            throw new RuntimeException('Unexpected update package.');
        }

        file_put_contents($updates.'/pending.json', json_encode(['version' => $version, 'url' => $url]));
        exec('schtasks /Run /TN "AGAPAY Update"', $output, $code);
        if ($code !== 0) {
            throw new RuntimeException('The updater could not be started: '.implode(' ', $output));
        }
    }
}
