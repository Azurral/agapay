<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Asks GitHub for the latest AGAPAY release (the office copy only; the online copy is updated by deployment).
 * The answer is cached for hours, and a failed check (offline laptop) is cached too, so pages never wait on it.
 */
final class UpdateChecker
{
    public const PACKAGE_PREFIX = 'AGAPAY-app-';

    /** @return array{version: string, notes: string, url: string}|null */
    public function latest(): ?array
    {
        $repository = (string) config('agapay.updates.repository');
        $cached = Cache::get($key = "agapay.latest-release.{$repository}");
        if ($cached !== null) {
            return $cached['release'];
        }

        $release = $this->fetch($repository);
        Cache::put($key, ['release' => $release], $release ? now()->addHours(6) : now()->addHour());

        return $release;
    }

    /** The newer release, or null when updates are off, nothing is newer or GitHub cannot be reached. */
    public function available(): ?array
    {
        if (! config('agapay.updates.enabled')) {
            return null;
        }
        $release = $this->latest();

        return $release && version_compare($release['version'], (string) config('agapay.version'), '>') ? $release : null;
    }

    private function fetch(string $repository): ?array
    {
        try {
            $response = Http::timeout(5)->acceptJson()->get("https://api.github.com/repos/{$repository}/releases/latest");
        } catch (Throwable) {
            return null;
        }
        if (! $response->successful()) {
            return null;
        }

        $asset = collect($response->json('assets', []))->first(fn ($a) => str_starts_with((string) ($a['name'] ?? ''), self::PACKAGE_PREFIX)
            && str_ends_with((string) $a['name'], '.zip'));
        $url = (string) ($asset['browser_download_url'] ?? '');
        if (! str_starts_with($url, "https://github.com/{$repository}/releases/download/")) {
            return null;
        }

        return [
            'version' => ltrim((string) $response->json('tag_name'), 'vV'),
            'notes' => trim((string) $response->json('body')),
            'url' => $url,
        ];
    }
}
