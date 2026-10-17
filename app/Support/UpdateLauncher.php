<?php

namespace App\Support;

use RuntimeException;

/**
 * Starts the office copy's updater (tools\update.ps1 in the "AGAPAY for Windows" folder) as a separate process:
 * it backs up, stops AGAPAY, swaps in the new version, applies database changes and starts AGAPAY again.
 */
class UpdateLauncher
{
    public function launch(string $version, string $url): void
    {
        $home = (string) env('AGAPAY_HOME', '');
        $script = $home.'\\tools\\update.ps1';
        if (PHP_OS_FAMILY !== 'Windows' || $home === '' || ! is_file($script)) {
            throw new RuntimeException('Updates are installed from the AGAPAY for Windows folder only.');
        }
        if (! preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/', $version) || ! str_starts_with($url, 'https://github.com/')) {
            throw new RuntimeException('Unexpected update package.');
        }

        // "start" detaches it: the updater outlives this request, which ends when the web server restarts.
        $command = sprintf('start "" /B powershell -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File "%s" -Version "%s" -Url "%s"',
            $script, $version, $url);
        pclose(popen($command, 'r'));
    }
}
