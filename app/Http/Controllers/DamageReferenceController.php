<?php

namespace App\Http\Controllers;

use App\Models\Crop;
use App\Models\Disaster;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * "Disasters & Crop Values" (no Figma frame): the Administrator keeps the disaster list and the crop values
 * of the damage formula (spec rule 11). Filed reports keep the values they were computed with.
 */
class DamageReferenceController extends Controller
{
    /** field => [label, min, max] */
    private const LIMITS = [
        'yield_mt_per_ha' => ['the yield must be between 0.01 and 999.99 MT/ha', 0.01, 999.99],
        'price_per_mt' => ['the farmgate price must be between ₱0 and ₱9,999,999.99 per MT', 0, 9999999.99],
        'partial_loss_factor' => ['the partial-damage factor must be between 0 and 1', 0, 1],
    ];

    public function updateCrops(Request $request): RedirectResponse
    {
        $crops = Crop::orderBy('name')->get()->keyBy('id');
        $submitted = is_array($request->input('crops')) ? $request->input('crops') : [];

        $errors = [];
        $changes = [];
        foreach ($submitted as $id => $values) {
            $crop = $crops->get((int) $id);
            if ($crop === null || ! is_array($values)) {
                continue;
            }
            foreach (self::LIMITS as $field => [$message, $min, $max]) {
                $value = $values[$field] ?? null;
                if (! is_string($value) || ! is_numeric($value) || (float) $value < $min || (float) $value > $max) {
                    $errors[] = "{$crop->name}: {$message}.";

                    continue;
                }
                if (round((float) $value, 2) !== (float) $crop->{$field}) {
                    $changes[$crop->id][$field] = round((float) $value, 2);
                }
            }
        }

        [$newCrop, $newCropErrors] = $this->newCrop($request);
        if ($errors !== [] || $newCropErrors !== []) {
            return back()->withInput()->withErrors([...($errors ? ['crops.*' => $errors] : []), ...$newCropErrors], 'reference');
        }

        DB::transaction(function () use ($crops, $changes, $newCrop) {
            $old = [];
            $new = [];
            foreach ($changes as $id => $values) {
                $crop = $crops[$id];
                $old[$crop->name] = $crop->only(array_keys($values));
                $crop->update($values);
                $new[$crop->name] = $crop->fresh()->only(array_keys($values));
            }
            if ($newCrop !== null) {
                $crop = Crop::create($newCrop);
                $new[$crop->name] = $crop->fresh()->only(['yield_mt_per_ha', 'price_per_mt', 'partial_loss_factor']);
            }

            if ($new !== []) {
                AuditLogger::record('Updated Crop Reference Values', null, 'Crop Reference Values', $old, $new);
            }
        });

        return redirect()->route('damage.index')->with('status', 'Crop reference values saved.');
    }

    public function storeDisaster(Request $request): RedirectResponse
    {
        $request->merge(['name' => is_string($request->input('name')) ? trim(preg_replace('/\s+/', ' ', $request->input('name'))) : $request->input('name')]);
        $validator = Validator::make($request->only('name', 'occurred_on'), [
            'name' => ['required', 'string', 'max:100', Rule::unique('disasters', 'name')],
            'occurred_on' => ['required', 'date', 'before_or_equal:today'],
        ], [
            'name.required' => 'Enter the disaster name, e.g. Typhoon Egay.',
            'name.unique' => 'That disaster is already on the list.',
            'occurred_on.*' => 'Enter the date it struck (not in the future).',
        ]);
        if ($validator->fails() || $this->nameTaken('disasters', $request->input('name'))) {
            $validator->errors()->addIf(! $validator->errors()->has('name'), 'name', 'That disaster is already on the list.');

            return back()->withInput()->withErrors($validator, 'reference');
        }

        $disaster = Disaster::create($validator->validated());

        return redirect()->route('damage.index')->with('status', "{$disaster->name} added.");
    }

    /**
     * The optional add-crop row: [null, []] when left blank, [attributes, []] when valid, [null, errors] otherwise.
     *
     * @return array{0: array<string, mixed>|null, 1: array<string, list<string>>}
     */
    private function newCrop(Request $request): array
    {
        $input = is_array($request->input('new_crop')) ? $request->input('new_crop') : [];
        $input['name'] = is_string($input['name'] ?? null) ? trim(preg_replace('/\s+/', ' ', $input['name'])) : '';
        if ($input['name'] === '') {
            return [null, []];
        }

        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:50'],
            'yield_mt_per_ha' => ['required', 'numeric', 'min:0.01', 'max:999.99'],
            'price_per_mt' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'partial_loss_factor' => ['required', 'numeric', 'min:0', 'max:1'],
        ], [
            'yield_mt_per_ha.*' => 'Enter the new crop\'s yield (0.01–999.99 MT/ha).',
            'price_per_mt.*' => 'Enter the new crop\'s farmgate price per MT.',
            'partial_loss_factor.*' => 'Enter the new crop\'s partial-damage factor (0 to 1).',
        ]);
        $validator->after(function ($validator) use ($input) {
            if ($this->nameTaken('crops', $input['name'])) {
                $validator->errors()->add('name', 'That crop is already on the list.');
            }
        });
        if ($validator->fails()) {
            return [null, collect($validator->errors()->toArray())->mapWithKeys(fn ($messages, $key) => ["new_crop.{$key}" => $messages])->all()];
        }

        return [$validator->validated(), []];
    }

    /** Names are compared without case: "rice" is Rice. */
    private function nameTaken(string $table, mixed $name): bool
    {
        return is_string($name) && DB::table($table)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists();
    }
}
