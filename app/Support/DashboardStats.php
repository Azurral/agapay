<?php

namespace App\Support;

use App\Models\Beneficiary;
use App\Models\DistributionCycle;
use App\Models\InterventionRecord;
use App\Models\User;
use App\Services\InventoryService;

/**
 * Live sidebar counters. Each module plan replaces its own match arm
 * (beneficiaries: Phase 3, interventions: Phase 4, inventory: Phase 5, damage: Phase 7);
 * until a module's tables exist its counter is 0.
 */
final class DashboardStats
{
    public static function value(string $key, User $user): int
    {
        return match ($key) {
            'active_users' => User::where('status', User::STATUS_ACTIVE)->whereNotNull('role_id')->count(),
            'total_beneficiaries' => Beneficiary::count(),
            'pending_rsbsa' => Beneficiary::whereIn('rsbsa_status', Beneficiary::RSBSA_IN_PROGRESS)->count(),
            'encoded_this_month' => Beneficiary::where('created_by', $user->id)
                ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            'records_to_update' => Beneficiary::whereNotNull('encoding_issue')->count(),
            // Programs with at least one active record in the current distribution cycle.
            'active_interventions' => ($cycle = DistributionCycle::current())
                ? InterventionRecord::where('distribution_cycle_id', $cycle->id)->distinct()->count('intervention_id')
                : 0,
            'pending_validation' => InterventionRecord::where('validation_status', InterventionRecord::VALIDATION_PENDING)->count(),
            'low_stock_items' => app(InventoryService::class)->lowStockCount(),
            'reports_filed_this_month' => 0,
        };
    }
}
