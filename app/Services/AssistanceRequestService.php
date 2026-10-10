<?php

namespace App\Services;

use App\Exceptions\InterventionRuleViolation;
use App\Models\AssistanceRequest;
use App\Models\Beneficiary;
use App\Models\DistributionCycle;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Files assistance requests and records the Administrator's decision on them. */
final class AssistanceRequestService
{
    public function __construct(private readonly InterventionAssignment $assignment) {}

    /** @param array{beneficiary_id: int|string, intervention_id: int|string, disaster_id?: int|string|null, crop_id?: int|string|null, quantity?: string|null, reason?: string|null} $data */
    public function file(array $data, User $actor): AssistanceRequest
    {
        return AssistanceRequest::create([
            'beneficiary_id' => $data['beneficiary_id'],
            'intervention_id' => $data['intervention_id'],
            'disaster_id' => ($data['disaster_id'] ?? null) ?: null,
            'crop_id' => ($data['crop_id'] ?? null) ?: null,
            'quantity' => ($data['quantity'] ?? null) === '' ? null : ($data['quantity'] ?? null),
            'reason' => Beneficiary::squish($data['reason'] ?? null),
            'status' => AssistanceRequest::PENDING,
            'created_by' => $actor->id,
        ]);
    }

    /**
     * Approval gives the farmer the program in the current cycle; the usual program rules (DA needs an RSBSA No.,
     * one record per program and cycle) still apply, and a refusal leaves the request pending.
     */
    public function approve(AssistanceRequest $request, User $actor): AssistanceRequest
    {
        return DB::transaction(function () use ($request, $actor) {
            $request = $this->lockPending($request);
            $cycle = DistributionCycle::current() ?? throw ValidationException::withMessages(['request' => 'Add a distribution cycle first.']);

            try {
                $record = $this->assignment->assign($request->beneficiary, $request->intervention, $cycle,
                    ['quantity' => $request->quantity], $actor);
            } catch (InterventionRuleViolation $e) {
                throw ValidationException::withMessages(['request' => $e->getMessage()]);
            }

            $this->decide($request, AssistanceRequest::APPROVED, null, $actor, ['intervention_record_id' => $record->id]);
            AuditLogger::record('Approved Assistance Request', $request, null, ['status' => AssistanceRequest::PENDING],
                ['status' => AssistanceRequest::APPROVED, 'cycle' => $cycle->code], $actor);

            return $request->setRelation('record', $record);
        });
    }

    public function deny(AssistanceRequest $request, string $note, User $actor): AssistanceRequest
    {
        $note = Beneficiary::squish($note);
        if ($note === null) {
            throw ValidationException::withMessages(['decision_note' => 'Say why the request is denied.']);
        }

        return DB::transaction(function () use ($request, $note, $actor) {
            $request = $this->lockPending($request);
            $this->decide($request, AssistanceRequest::DENIED, mb_substr($note, 0, 500), $actor);
            AuditLogger::record('Denied Assistance Request', $request, null, ['status' => AssistanceRequest::PENDING],
                ['status' => AssistanceRequest::DENIED, 'decision_note' => $request->decision_note], $actor);

            return $request;
        });
    }

    private function lockPending(AssistanceRequest $request): AssistanceRequest
    {
        $request = AssistanceRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
        if (! $request->isPending()) {
            throw ValidationException::withMessages(['request' => 'This request was already decided.']);
        }

        return $request;
    }

    private function decide(AssistanceRequest $request, string $status, ?string $note, User $actor, array $extra = []): void
    {
        // Saved quietly: the decision gets its own audit entry instead of a generic "Updated" one.
        $request->forceFill([
            'status' => $status, 'decision_note' => $note, 'decided_by' => $actor->id, 'decided_at' => now(), ...$extra,
        ])->saveQuietly();
    }
}
