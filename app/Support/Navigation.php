<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

final class Navigation
{
    /** @return list<array{label:string, url:string, icon:string, active:bool}> */
    public static function items(User $user): array
    {
        return collect(config('agapay.nav.'.$user->role?->slug, []))
            ->filter(fn (array $item) => $user->hasPermission($item['permission']))
            ->map(fn (array $item) => [
                'label' => $item['label'],
                'url' => self::url($item),
                'icon' => $item['icon'],
                'active' => Route::has($item['route']) && request()->routeIs($item['route'], $item['route'].'.*'),
            ])
            ->values()
            ->all();
    }

    /** @return array{width:int, gap:int, items:list<array{label:string, url:string, active:bool}>} */
    public static function quickActions(User $user): array
    {
        $config = config('agapay.quick_actions.'.$user->role?->slug, ['width' => 176, 'gap' => 14, 'items' => []]);

        return [
            'width' => $config['width'],
            'gap' => $config['gap'],
            'items' => collect($config['items'])
                ->filter(fn (array $item) => $user->hasPermission($item['permission']))
                ->map(fn (array $item) => ['label' => $item['label'], 'url' => self::url($item), 'active' => self::isOpen($item)])
                ->values()
                ->all(),
        ];
    }

    /** The quick action of the page being shown: same route and, for actions like "Add User" (?add=1), the same query. */
    private static function isOpen(array $item): bool
    {
        return Route::has($item['route']) && request()->routeIs($item['route'])
            && collect($item['query'] ?? [])->every(fn ($value, string $key) => request()->query($key) === (string) $value);
    }

    /** Routes of modules built in later phases render as "#" until registered. */
    private static function url(array $item): string
    {
        return Route::has($item['route']) ? route($item['route'], $item['query'] ?? []) : '#';
    }
}
