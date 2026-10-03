<?php

use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureCanAny;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active' => EnsureAccountActive::class,
            'can.any' => EnsureCanAny::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        // An upload bigger than php.ini's post_max_size never reaches the controller (and has no session yet):
        // send the uploader back to the form, which shows the size message.
        $exceptions->render(fn (PostTooLargeException $e, Request $request) => match (true) {
            $request->is('import') => redirect()->route('import.index', ['too_large' => 1]),
            $request->is('damage-reports') => redirect()->route('damage.create', ['too_large' => 1]),
            $request->is('damage-reports/*') => redirect()->to('/damage-reports/'.(int) $request->segment(2).'/edit?too_large=1'),
            default => null,
        });
    })->create();
