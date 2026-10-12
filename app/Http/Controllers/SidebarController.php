<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Remembers whether the user left the side panel open (a display setting, so it is not audited). */
class SidebarController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $open = $request->validate(['open' => ['required', 'boolean']])['open'];

        $request->user()->forceFill(['sidebar_open' => (bool) $open])->saveQuietly();

        return response()->noContent();
    }
}
