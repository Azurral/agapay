<?php

namespace App\Http\Controllers;

use App\Models\Beneficiary;
use App\Models\DamageReport;
use App\Models\Disaster;
use App\Models\DistributionCycle;
use App\Models\InterventionRecord;
use App\Services\AuditLogger;
use App\Support\DashboardDetails;
use App\Support\DashboardOverview;
use App\Support\DashboardSummary;
use App\Support\DashboardWidgets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $widgets = DashboardWidgets::chosen($user);
        $has = fn (string $id) => in_array($id, $widgets, true);

        return view('dashboard', [
            'widgets' => $widgets,
            'allowed' => DashboardWidgets::allowed($user),
            'overview' => DashboardOverview::build(),
            'details' => DashboardDetails::build($widgets),   // the windows behind the tiles and chart rows
            // Data Encoders see their encoding queue; Admin and Agri Tech see all beneficiaries (Figma).
            'queue' => $has('encoding_queue')
                ? Beneficiary::whereNotNull('encoding_issue')->oldest()->oldest('id')->limit(10)->get()
                : collect(),
            'recent' => $has('recent_list')
                ? Beneficiary::forTable()->latest('updated_at')->latest('id')->limit(10)->get()
                : collect(),
            'walkins' => $has('walkins')
                ? Beneficiary::with('barangay:id,name')->where('verification_status', Beneficiary::VERIFICATION_PENDING)->oldest()->get()
                : collect(),
            'claimingNext' => $has('claiming')
                ? DistributionCycle::whereNotNull('schedule_date')->get()->reject->claimingEnded()->sortBy('schedule_date')->take(3)->values()
                : collect(),
            'damage' => $has('damage') ? $this->latestDamage() : null,
            'toVerify' => $has('to_verify') ? $this->toVerify() : collect(),
            // Quarterly and yearly summary (panel): the year chosen on Home, else this year.
            'summary' => $has('summary')
                ? DashboardSummary::build(min(max((int) $request->query('year', now()->year), 2000), (int) now()->year))
                : null,
        ]);
    }

    /** Saves the widgets the user ticked, in order, or goes back to the role's defaults. */
    public function customize(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($request->boolean('reset')) {
            $user->forceFill(['dashboard_widgets' => null])->saveQuietly();
            AuditLogger::record('Customized Home Page', $user, null, [], ['widgets' => 'role default']);

            return redirect()->route('dashboard')->with('status', 'Home page reset to the default for your role.');
        }

        $chosen = DashboardWidgets::clean($user, (array) $request->input('widgets', []));
        $user->forceFill(['dashboard_widgets' => $chosen])->saveQuietly();
        AuditLogger::record('Customized Home Page', $user, null, [], ['widgets' => implode(', ', $chosen)]);

        return redirect()->route('dashboard')->with('status', 'Home page saved.');
    }

    /** @return array{crisis: Disaster, farmers: int, cost: float, reports: int, validated: int}|null */
    private function latestDamage(): ?array
    {
        $crisis = Disaster::orderByDesc('occurred_on')->orderByDesc('id')->first();
        if (! $crisis) {
            return null;
        }
        $reports = DamageReport::where('disaster_id', $crisis->id);

        return [
            'crisis' => $crisis,
            'farmers' => (clone $reports)->distinct()->count('beneficiary_id'),
            'cost' => (float) (clone $reports)->sum('cost'),
            'reports' => (clone $reports)->count(),
            'validated' => (clone $reports)->where('status', DamageReport::VALIDATED)->count(),
        ];
    }

    /** @return Collection<int, array{cycle: DistributionCycle, count: int}> */
    private function toVerify()
    {
        return DistributionCycle::whereNotNull('schedule_date')->get()->filter->claimingEnded()
            ->map(fn (DistributionCycle $cycle) => ['cycle' => $cycle, 'count' => InterventionRecord::where('distribution_cycle_id', $cycle->id)
                ->whereIn('beneficiary_id', Beneficiary::select('id'))
                ->where('claim_status', InterventionRecord::CLAIM_UNCLAIMED)
                ->whereIn('validation_status', InterventionRecord::CLAIMABLE)
                ->whereNull('forward_status')->count()])
            ->filter(fn (array $row) => $row['count'] > 0)
            ->sortByDesc(fn (array $row) => $row['cycle']->schedule_date)->values();
    }
}
