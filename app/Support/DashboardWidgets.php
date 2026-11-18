<?php

namespace App\Support;

use App\Models\Role;
use App\Models\User;

/**
 * The home page's building blocks. Each user picks which ones to show and in what order (Customize); until they do,
 * their role's defaults apply. A widget is only ever offered or shown when the user's permissions allow its data.
 */
final class DashboardWidgets
{
    /** id => [label shown in Customize, permission needed, group] */
    public const WIDGETS = [
        'tile_farmers' => ['Registered farmers (count)', 'dashboard.view', 'tile'],
        'tile_released' => ['Released this cycle (count)', 'dashboard.view', 'tile'],
        'tile_requests' => ['Pending assistance requests (count)', 'dashboard.view', 'tile'],
        'tile_low_stock' => ['Low-stock items (count)', 'inventory.view', 'tile'],
        'chart_releases' => ['Releases this cycle per program (chart)', 'dashboard.view', 'chart'],
        'chart_barangays' => ['Farmers per barangay (chart)', 'dashboard.view', 'chart'],
        'chart_stock' => ['Stock levels (chart)', 'inventory.view', 'chart'],
        'walkins' => ['Walk-in registrations waiting for approval', 'beneficiaries.approve', 'panel'],
        'claiming' => ['Next claiming dates', 'dashboard.view', 'panel'],
        'damage' => ['Latest crisis damage', 'damage.view', 'panel'],
        'to_verify' => ['Unclaimed records to verify after claiming', 'interventions.validate', 'panel'],
        'recent_list' => ['All beneficiaries (latest)', 'beneficiaries.view', 'list'],
        'encoding_queue' => ['Pending encoding queue', 'beneficiaries.manage', 'list'],
    ];

    private const DEFAULTS = [
        Role::ADMIN => ['tile_farmers', 'tile_released', 'tile_requests', 'tile_low_stock', 'chart_releases', 'chart_barangays', 'chart_stock', 'walkins', 'recent_list'],
        Role::AGRITECH => ['tile_farmers', 'tile_released', 'tile_requests', 'chart_releases', 'chart_barangays', 'damage', 'to_verify', 'claiming', 'recent_list'],
        Role::ENCODER => ['tile_farmers', 'tile_released', 'tile_low_stock', 'chart_releases', 'chart_stock', 'claiming', 'encoding_queue'],
        Role::AGRITECH_ENCODER => ['tile_farmers', 'tile_released', 'tile_requests', 'tile_low_stock', 'chart_releases', 'chart_stock', 'damage', 'to_verify', 'claiming', 'encoding_queue'],
    ];

    /** @return array<string, string> id => label, the widgets this user may show */
    public static function allowed(User $user): array
    {
        return collect(self::WIDGETS)
            ->filter(fn (array $w) => $user->hasPermission($w[1]))
            ->map(fn (array $w) => $w[0])
            ->all();
    }

    /** @return list<string> the role's default widgets this user may see */
    public static function defaults(User $user): array
    {
        $allowed = self::allowed($user);

        return array_values(array_filter(self::DEFAULTS[$user->role?->slug] ?? ['tile_farmers'], fn (string $id) => isset($allowed[$id])));
    }

    /** @return list<string> what Home shows for this user, in order */
    public static function chosen(User $user): array
    {
        return $user->dashboard_widgets === null ? self::defaults($user) : self::clean($user, $user->dashboard_widgets);
    }

    /**
     * Keeps only known widgets the user may see, once each, in the given order.
     *
     * @param  array<int, mixed>  $ids
     * @return list<string>
     */
    public static function clean(User $user, array $ids): array
    {
        $allowed = self::allowed($user);

        return array_values(array_unique(array_filter($ids, fn ($id) => is_string($id) && isset($allowed[$id]))));
    }

    public static function group(string $id): string
    {
        return self::WIDGETS[$id][2] ?? 'panel';
    }
}
