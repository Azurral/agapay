<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;

/** Accounts that have signed in on this browser, shown in the sidebar account switcher. */
final class KnownAccounts
{
    public const COOKIE = 'agapay_accounts';

    private const LIMIT = 5;

    private const MINUTES = 60 * 24 * 30;

    public static function remember(User $user, Request $request): void
    {
        $ids = collect([$user->id, ...self::ids($request)])->unique()->take(self::LIMIT)->values()->all();

        Cookie::queue(self::COOKIE, json_encode($ids), self::MINUTES);
    }

    /** @return list<int> */
    public static function ids(Request $request): array
    {
        $decoded = json_decode((string) $request->cookie(self::COOKIE), true);

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, fn ($id) => is_int($id) && $id > 0));
    }

    /** @return Collection<int, User> */
    public static function others(Request $request, User $current): Collection
    {
        $ids = array_values(array_diff(self::ids($request), [$current->id]));

        if ($ids === []) {
            return collect();
        }

        return User::with('role')
            ->whereIn('id', $ids)
            ->where('status', User::STATUS_ACTIVE)
            ->whereNotNull('role_id')
            ->get()
            ->sortBy(fn (User $user) => array_search($user->id, $ids, true))
            ->values();
    }
}
