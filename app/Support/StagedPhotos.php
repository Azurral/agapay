<?php

namespace App\Support;

use App\Http\Requests\DamageReportRequest;
use App\Models\DamagePhoto;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Damage photos kept between tries of the damage form: a browser forgets chosen files when the form comes back
 * with an error, so the valid photos are held here (per session) and sent again as "kept_photos".
 */
final class StagedPhotos
{
    public const SESSION = 'damage_staged_photos';

    public const DIR = 'damage-staging';

    private const TYPES = ['image/jpeg', 'image/png'];

    /** @return array<string, array{path: string, name: string, size: int}> token => photo */
    public static function all(): array
    {
        return (array) session(self::SESSION, []);
    }

    /** @return list<string> the kept-photo tokens the form sent that belong to this session */
    public static function keptTokens(Request $request): array
    {
        $kept = array_filter((array) $request->input('kept_photos', []), 'is_string');

        return array_values(array_intersect($kept, array_keys(self::all())));
    }

    /**
     * The kept photos as uploads, so the damage report service stores them like new ones.
     *
     * @param  list<string>  $tokens
     * @return list<UploadedFile>
     */
    public static function files(array $tokens): array
    {
        $disk = Storage::disk(DamagePhoto::DISK);

        return collect(self::all())->only($tokens)
            ->filter(fn (array $photo) => $disk->exists($photo['path']))
            ->map(fn (array $photo) => new UploadedFile($disk->path($photo['path']), $photo['name'], null, null, true))
            ->values()->all();
    }

    /** Holds the photos the user kept plus the valid new ones (up to ten), for the form's next try. */
    public static function stash(Request $request): void
    {
        self::prune();
        $staged = self::all();
        $kept = array_intersect_key($staged, array_flip(self::keptTokens($request)));

        foreach (array_filter((array) $request->file('photos', []), fn ($file) => $file instanceof UploadedFile) as $file) {
            if (count($kept) >= DamageReportRequest::MAX_PHOTOS) {
                break;
            }
            if (! $file->isValid() || ! in_array($file->getMimeType(), self::TYPES, true) || $file->getSize() > DamageReportRequest::photoLimitBytes()) {
                continue;
            }

            $token = Str::random(40);
            $kept[$token] = [
                'path' => $file->storeAs(self::DIR, $token.'.'.($file->guessExtension() ?: 'jpg'), DamagePhoto::DISK),
                'name' => Str::limit($file->getClientOriginalName(), 250, ''),
                'size' => (int) $file->getSize(),
            ];
        }

        Storage::disk(DamagePhoto::DISK)->delete(array_column(array_diff_key($staged, $kept), 'path'));
        session()->put(self::SESSION, $kept);
    }

    /** Forgets the kept photos and deletes their files (after filing, or when the form is opened fresh). */
    public static function clear(): void
    {
        Storage::disk(DamagePhoto::DISK)->delete(array_column(self::all(), 'path'));
        session()->forget(self::SESSION);
    }

    /** Files of abandoned forms (another session, a closed browser) are removed after a day. */
    private static function prune(): void
    {
        $disk = Storage::disk(DamagePhoto::DISK);
        $old = array_filter($disk->files(self::DIR), fn (string $path) => $disk->lastModified($path) < now()->subDay()->getTimestamp());
        $disk->delete($old);
    }
}
