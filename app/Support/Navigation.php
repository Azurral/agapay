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

    /** @return array{width:int, gap:int, items:list<array{label:string, url:string}>} */
    public static function quickActions(User $user): array
    {
        $config = config('agapay.quick_actions.'.$user->role?->slug, ['width' => 176, 'gap' => 14, 'items' => []]);

        return [
            'width' => $config['width'],
            'gap' => $config['gap'],
            'items' => collect($config['items'])
                ->filter(fn (array $item) => $user->hasPermission($item['permission']))
                ->map(fn (array $item) => ['label' => $item['label'], 'url' => self::url($item)])
                ->values()
                ->all(),
        ];
    }

    /** Routes of modules built in later phases render as "#" until registered. */
    private static function url(array $item): string
    {
        return Route::has($item['route']) ? route($item['route'], $item['query'] ?? []) : '#';
    }
}
