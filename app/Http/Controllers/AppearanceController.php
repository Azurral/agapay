<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** Remembers the user's colour theme and dark mode (display settings, so they are not audited). */
class AppearanceController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $data = $request->validate([
            'palette' => ['sometimes', 'string', Rule::in(array_keys(config('agapay.themes')))],
            'dark' => ['sometimes', 'boolean'],
        ]);

        $request->user()->forceFill(array_filter([
            'color_theme' => $data['palette'] ?? null,
            'dark_mode' => isset($data['dark']) ? (bool) $data['dark'] : null,
        ], fn ($v) => $v !== null))->saveQuietly();

        return response()->noContent();
    }
}
