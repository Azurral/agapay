<?php

namespace App\Http\Controllers;

use App\Exceptions\InterventionRuleViolation;
use App\Models\Beneficiary;
use App\Models\DamagePhoto;
use App\Models\DistributionCycle;
use App\Models\Intervention;
use App\Models\InterventionRecord;
use App\Models\SignedSheet;
use App\Services\AuditLogger;
use App\Services\InterventionAssignment;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Claiming (panel): a calendar of the claiming days; per cycle, the programs to release, the acknowledgment sheet that
 * every farmer signs (wet signature) and its scanned copy as proof; after the claiming period, every unclaimed record is
 * verified and forwarded to the next claiming date or closed with a reason.
 */
class ClaimingController extends Controller
{
    public function __construct(private readonly InterventionAssignment $assignment) {}

    public function index(Request $request): View
    {
        $month = preg_match('/^\d{4}-\d{2}$/', $request->queryText('month'))
            ? CarbonImmutable::createFromFormat('!Y-m', $request->queryText('month'))
            : CarbonImmutable::today()->startOfMonth();

        $days = [];
        foreach (DistributionCycle::whereNotNull('schedule_date')->orderBy('schedule_date')->get() as $cycle) {
            foreach ($cycle->claimingDays() as $day) {
                $days[$day][] = $cycle;
            }
        }

        return view('claiming.index', [
            'month' => $month,
            'days' => $days,
            'cycles' => DistributionCycle::whereNotNull('schedule_date')->orderByDesc('schedule_date')->limit(6)->get(),
        ]);
    }

    public function show(DistributionCycle $cycle): View
    {
        $records = InterventionRecord::with(['beneficiary.barangay:id,name', 'intervention'])
            ->where('distribution_cycle_id', $cycle->id)
            ->whereIn('beneficiary_id', Beneficiary::select('id'))
            ->get();

        $programs = $records->groupBy('intervention_id')->map(fn ($group) => [
            'intervention' => $group->first()->intervention,
            'claimed' => $group->filter->isClaimed()->count(),
            'to_claim' => $group->reject->isClaimed()->whereNull('forward_status')
                ->filter(fn ($r) => in_array($r->validation_status, InterventionRecord::CLAIMABLE, true))->count(),
        ])->sortBy(fn ($p) => $p['intervention']->source.$p['intervention']->name)->values();

        return view('claiming.show', [
            'cycle' => $cycle,
            'next' => $cycle->next(),
            'programs' => $programs,
            'toVerify' => $cycle->claimingEnded()
                ? $records->reject->isClaimed()->whereNull('forward_status')
                    ->filter(fn ($r) => in_array($r->validation_status, InterventionRecord::CLAIMABLE, true))
                    ->sortBy(fn ($r) => $r->beneficiary->last_name.$r->beneficiary->first_name)->values()
                : collect(),
            'decided' => $records->whereNotNull('forward_status')->values(),
            'sheets' => SignedSheet::with(['intervention', 'uploader:id,username'])->where('distribution_cycle_id', $cycle->id)->latest()->get(),
        ]);
    }

    public function forward(Request $request, InterventionRecord $record): RedirectResponse
    {
        $cycle = $record->cycle;
        $next = $cycle?->next();

        if ($record->isClaimed() || $record->forward_status !== null) {
            return back()->withErrors(['forward' => 'This record is no longer waiting for verification.']);
        }
        if (! $next) {
            return back()->withErrors(['forward' => 'Add the next distribution cycle first; there is no later claiming date.']);
        }

        try {
            $moved = DB::transaction(function () use ($record, $next, $request) {
                $moved = $this->assignment->assign($record->beneficiary, $record->intervention, $next, ['quantity' => $record->quantity], $request->user());
                $record->forceFill([
                    'forward_status' => InterventionRecord::FORWARDED,
                    'forwarded_record_id' => $moved->id,
                    'forward_note' => "Forwarded to {$next->code}",
                    'forwarded_by' => $request->user()->id,
                    'forwarded_at' => now(),
                ])->saveQuietly();
                AuditLogger::record('Forwarded Unclaimed Record', $record, null, ['cycle' => $record->cycle->code], ['cycle' => $next->code]);

                return $moved;
            });
        } catch (InterventionRuleViolation $e) {
            return back()->withErrors(['forward' => $e->getMessage()]);
        }

        return back()->with('status', "{$record->beneficiary->fullName()}: {$record->intervention->name} forwarded to {$next->code} (claiming {$next->schedule_date->format('M j, Y')}).");
    }

    public function close(Request $request, InterventionRecord $record): RedirectResponse
    {
        $reason = trim((string) $request->validate(
            ['reason' => ['required', 'string', 'max:500']],
            ['reason.required' => 'Write why the record is not forwarded.'],
        )['reason']);

        if ($record->isClaimed() || $record->forward_status !== null) {
            return back()->withErrors(['forward' => 'This record is no longer waiting for verification.']);
        }

        $record->forceFill([
            'forward_status' => InterventionRecord::NOT_FORWARDED,
            'forward_note' => $reason,
            'forwarded_by' => $request->user()->id,
            'forwarded_at' => now(),
        ])->saveQuietly();
        AuditLogger::record('Closed Unclaimed Record', $record, null, [], ['reason' => $reason]);

        return back()->with('status', "{$record->beneficiary->fullName()}: {$record->intervention->name} closed, not forwarded.");
    }

    /** The printable acknowledgment receipt of one program: one row per farmer, signed on release (wet signature). */
    public function sheet(Request $request, DistributionCycle $cycle): Response
    {
        $intervention = Intervention::findOrFail((int) $request->query('intervention'));
        $data = $this->sheetData($cycle, $intervention);

        return Pdf::loadView('claiming.sheet', $data)->setPaper('a4', 'portrait')
            ->stream("Acknowledgment-{$cycle->code}-".str($intervention->name)->slug().'.pdf');
    }

    /** @return array{cycle: DistributionCycle, intervention: Intervention, records: Collection<int, InterventionRecord>} */
    public function sheetData(DistributionCycle $cycle, Intervention $intervention): array
    {
        $records = InterventionRecord::with(['beneficiary.barangay:id,name'])
            ->where(['distribution_cycle_id' => $cycle->id, 'intervention_id' => $intervention->id])
            ->whereIn('beneficiary_id', Beneficiary::select('id'))
            ->whereNull('forward_status')
            ->where(fn ($q) => $q->whereIn('validation_status', InterventionRecord::CLAIMABLE)->orWhere('claim_status', InterventionRecord::CLAIM_CLAIMED))
            ->get()
            ->sortBy(fn ($r) => [$r->beneficiary->barangay?->name, $r->beneficiary->last_name, $r->beneficiary->first_name])
            ->values();

        return ['cycle' => $cycle, 'intervention' => $intervention, 'records' => $records];
    }

    public function storeSigned(Request $request, DistributionCycle $cycle): RedirectResponse
    {
        $data = $request->validate([
            'intervention_id' => ['required', 'integer', 'exists:interventions,id'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:20480'],
        ], [
            'file.required' => 'Attach the scanned signed sheet (PDF, JPG or PNG).',
            'file.mimes' => 'Attach the scanned signed sheet (PDF, JPG or PNG).',
            'file.max' => 'The scan must be 20 MB or smaller.',
        ]);

        $intervention = Intervention::findOrFail($data['intervention_id']);
        $sheet = SignedSheet::create([
            'distribution_cycle_id' => $cycle->id,
            'intervention_id' => $intervention->id,
            'path' => $request->file('file')->store("signed-sheets/{$cycle->id}", DamagePhoto::disk()),
            'original_name' => mb_substr($request->file('file')->getClientOriginalName(), 0, 200),
            'uploaded_by' => $request->user()->id,
        ]);
        AuditLogger::record('Uploaded Signed Sheet', null, "{$cycle->code} - ".strtoupper($intervention->source)." - {$intervention->name} ({$sheet->original_name})");

        return back()->with('status', 'Signed sheet saved for '.strtoupper($intervention->source)." - {$intervention->name}.");
    }

    public function downloadSigned(SignedSheet $sheet): StreamedResponse
    {
        $disk = Storage::disk(DamagePhoto::disk());
        abort_unless($disk->exists($sheet->path), 404);

        return $disk->response($sheet->path, $sheet->original_name, ['Cache-Control' => 'private, no-store']);
    }
}
