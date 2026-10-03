<?php

namespace App\Http\Controllers;

use App\Models\DamagePhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Damage photos (farm locations of named farmers) are only shown to signed-in users who can view damage reports. */
class DamagePhotoController extends Controller
{
    public function __invoke(Request $request, DamagePhoto $photo): StreamedResponse
    {
        $report = $photo->report;
        abort_if($report === null || ($report->trashed() && ! $request->user()->can('damage.configure')), 404);
        abort_unless(Storage::disk(DamagePhoto::DISK)->exists($photo->path), 404);

        return Storage::disk(DamagePhoto::DISK)->response($photo->path, $photo->original_name, [
            'Cache-Control' => 'private, max-age=86400',
        ], 'inline');
    }
}
