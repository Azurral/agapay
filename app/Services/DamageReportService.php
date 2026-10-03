<?php

namespace App\Services;

use App\Models\Beneficiary;
use App\Models\Crop;
use App\Models\DamagePhoto;
use App\Models\DamageReport;
use App\Models\Disaster;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Files, edits, validates, archives and restores damage reports (spec rule 11). */
final class DamageReportService
{
    private const FIELDS = ['disaster_id', 'beneficiary_id', 'barangay_id', 'crop_id', 'farm_location', 'crop_stage', 'latitude', 'longitude'];

    /**
     * @param  array<string, mixed>  $data  validated DamageReportRequest input
     * @param  list<UploadedFile>  $photos
     */
    public function file(array $data, array $photos, User $actor): DamageReport
    {
        $stored = [];

        try {
            return DB::transaction(function () use ($data, $photos, $actor, &$stored) {
                // One filing per farmer at a time, so a double-clicked Submit cannot create the same report twice.
                $beneficiary = Beneficiary::whereKey($data['beneficiary_id'])->lockForUpdate()->firstOrFail();
                $this->ensureNotDuplicate($beneficiary, (int) $data['disaster_id'], (int) $data['crop_id']);

                $report = DamageReport::create([
                    ...$this->attributes($data),
                    'status' => DamageReport::FOR_VALIDATION,
                    'reported_by' => $actor->id,
                ]);
                $this->storePhotos($report, $photos, $stored);

                return $report;
            }, attempts: 3);
        } catch (Throwable $e) {
            Storage::disk(DamagePhoto::DISK)->delete($stored);

            throw $e;
        }
    }

    /**
     * Recalculates with the crop's current values (the edited report is re-filed).
     *
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $newPhotos
     * @param  list<int>  $removePhotoIds
     */
    public function update(DamageReport $report, array $data, array $newPhotos, array $removePhotoIds, User $actor): DamageReport
    {
        $stored = [];

        try {
            [$report, $removed] = DB::transaction(function () use ($report, $data, $newPhotos, $removePhotoIds, &$stored) {
                Beneficiary::whereKey($data['beneficiary_id'])->lockForUpdate()->firstOrFail();
                $report = DamageReport::whereKey($report->id)->lockForUpdate()->firstOrFail();
                if ($report->isValidated()) {
                    throw ValidationException::withMessages(['report' => 'This report is already validated.']);
                }
                $this->ensureNotDuplicate(Beneficiary::findOrFail($data['beneficiary_id']), (int) $data['disaster_id'], (int) $data['crop_id'], $report->id);

                $report->update($this->attributes($data));

                $removed = $report->photos()->whereIn('id', $removePhotoIds)->get();
                $report->photos()->whereKey($removed->modelKeys())->delete();
                if ($report->photos()->count() + count($newPhotos) > 10) {
                    throw ValidationException::withMessages(['photos' => 'Attach up to 10 photos.']);
                }
                $this->storePhotos($report, $newPhotos, $stored);

                return [$report, $removed];
            }, attempts: 3);
        } catch (Throwable $e) {
            Storage::disk(DamagePhoto::DISK)->delete($stored);

            throw $e;
        }

        Storage::disk(DamagePhoto::DISK)->delete($removed->pluck('path')->all());

        return $report;
    }

    /**
     * Spec rule 11: the Agricultural Technologist (or Admin) confirms the figures, adjusting them with a reason.
     * Blank loss / cost keep the filed figures.
     */
    public function validate(DamageReport $report, ?string $lossMt, ?string $cost, ?string $note, User $actor): DamageReport
    {
        $input = Validator::make(['loss_mt' => $lossMt, 'cost' => $cost, 'adjustment_note' => $note], [
            'loss_mt' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'adjustment_note' => ['nullable', 'string', 'max:1000'],
        ], [
            'loss_mt.*' => 'Enter the loss in metric tons, e.g. 5.5.',
            'cost.*' => 'Enter the cost in pesos, e.g. 110000.',
        ])->validate();

        return DB::transaction(function () use ($report, $input, $actor) {
            $report = DamageReport::whereKey($report->id)->lockForUpdate()->firstOrFail();
            if ($report->isValidated()) {
                throw ValidationException::withMessages(['report' => 'This report is already validated.']);
            }

            $loss = $input['loss_mt'] !== null ? round((float) $input['loss_mt'], 2) : (float) $report->loss_mt;
            $cost = $input['cost'] !== null ? round((float) $input['cost'], 2) : (float) $report->cost;
            $adjusted = $loss !== (float) $report->loss_mt || $cost !== (float) $report->cost;
            $note = trim((string) ($input['adjustment_note'] ?? ''));
            if ($adjusted && $note === '') {
                throw ValidationException::withMessages(['adjustment_note' => 'Explain why the figures were adjusted.']);
            }

            $keys = ['status', 'loss_mt', 'cost', 'adjustment_note'];
            $old = $report->only($keys);
            $report->forceFill([
                'status' => DamageReport::VALIDATED,
                'loss_mt' => $loss,
                'cost' => $cost,
                'adjustment_note' => $adjusted ? $note : null,
                'validated_by' => $actor->id,
                'validated_at' => now(),
            ])->saveQuietly();
            AuditLogger::record('Validated Damage Report', $report, null, $old, $report->fresh()->only($keys), $actor);

            return $report;
        });
    }

    /** Spec rule 3: a recoverable delete with a reason. */
    public function archive(DamageReport $report, string $reason, User $actor): void
    {
        DB::transaction(function () use ($report, $reason, $actor) {
            $report = DamageReport::whereKey($report->id)->lockForUpdate()->firstOrFail();
            $report->forceFill(['delete_reason' => $reason, 'deleted_by' => $actor->id])->saveQuietly();
            $report->delete();
        });
    }

    public function restore(DamageReport $report, User $actor): DamageReport
    {
        return DB::transaction(function () use ($report) {
            $report = DamageReport::onlyTrashed()->whereKey($report->id)->lockForUpdate()->firstOrFail();
            Beneficiary::withTrashed()->whereKey($report->beneficiary_id)->lockForUpdate()->first();
            try {
                $this->ensureNotDuplicate($report->beneficiary, $report->disaster_id, $report->crop_id);
            } catch (ValidationException $e) {
                throw ValidationException::withMessages(['report' => $e->errors()['beneficiary_id'][0]]);
            }

            $report->forceFill(['delete_reason' => null, 'deleted_by' => null])->saveQuietly();
            $report->restore();

            return $report;
        });
    }

    /** @return array<string, mixed> */
    private function attributes(array $data): array
    {
        $total = (float) ($data['total_area_ha'] ?? 0);
        $partial = (float) ($data['partial_area_ha'] ?? 0);

        return [
            ...Arr::only($data, self::FIELDS),
            'farm_location' => Str::of((string) ($data['farm_location'] ?? ''))->squish()->toString() ?: null,
            'total_area_ha' => $total,
            'partial_area_ha' => $partial,
            ...DamageCalculator::forCrop(Crop::findOrFail($data['crop_id']), $total, $partial),
        ];
    }

    private function ensureNotDuplicate(Beneficiary $beneficiary, int $disasterId, int $cropId, ?int $ignoreId = null): void
    {
        $exists = DamageReport::where(['beneficiary_id' => $beneficiary->id, 'disaster_id' => $disasterId, 'crop_id' => $cropId])
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            $crop = Crop::findOrFail($cropId)->name;
            $disaster = Disaster::findOrFail($disasterId)->name;

            throw ValidationException::withMessages([
                'beneficiary_id' => "{$beneficiary->fullName()} already has a {$crop} damage report for {$disaster}.",
            ]);
        }
    }

    /**
     * @param  list<UploadedFile>  $photos
     * @param  list<string>  $stored  paths written so far, for clean-up if the transaction fails
     */
    private function storePhotos(DamageReport $report, array $photos, array &$stored): void
    {
        foreach ($photos as $photo) {
            $path = $photo->store("damage/{$report->id}", DamagePhoto::DISK);
            $stored[] = $path;

            $report->photos()->create([
                'path' => $path,
                'original_name' => Str::limit($photo->getClientOriginalName(), 250, ''),
                'size' => (int) $photo->getSize(),
            ]);
        }
    }
}
