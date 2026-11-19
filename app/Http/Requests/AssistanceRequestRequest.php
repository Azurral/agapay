<?php

namespace App\Http\Requests;

use App\Models\Beneficiary;
use App\Models\Disaster;
use App\Models\Intervention;
use App\Support\FarmPhotoCheck;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * New Assistance Request: a registered farmer asking for one program, optionally after a crisis. A crisis-related
 * request needs photos of the damage taken at the farm (GPS) after the crisis (date taken), as proof for the
 * Administrator who decides it.
 */
class AssistanceRequestRequest extends FormRequest
{
    public const MAX_PHOTOS = 5;

    public function rules(): array
    {
        $source = is_string($this->input('source')) ? $this->input('source') : '';

        return [
            'beneficiary_id' => ['required', 'integer', Rule::exists('beneficiaries', 'id')->whereNull('deleted_at')],
            'source' => ['required', 'string', Rule::in(Intervention::SOURCES)],
            'intervention_id' => ['required', 'integer', Rule::exists('interventions', 'id')->where('source', $source)],
            'quantity' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'disaster_id' => ['nullable', 'integer', Rule::exists('disasters', 'id')],
            'crop_id' => ['nullable', 'integer', Rule::exists('crops', 'id')],
            'reason' => ['nullable', 'string', 'max:500'],
            'photos' => ['required_with:disaster_id', 'array', 'max:'.self::MAX_PHOTOS],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png', 'max:8192'],
        ];
    }

    public function messages(): array
    {
        return [
            'beneficiary_id.*' => 'Choose a beneficiary from the search results.',
            'source.*' => 'Choose DA or LGU.',
            'intervention_id.*' => 'Choose what the farmer is asking for.',
            'quantity.*' => 'Enter the quantity as a number, e.g. 2.',
            'disaster_id.*' => 'Choose a crisis from the list.',
            'crop_id.*' => 'Choose a crop from the list.',
            'reason.max' => 'Keep the reason under 500 characters.',
            'photos.required_with' => "Attach at least one photo of the damage, taken at the farm with the phone's location on.",
            'photos.max' => 'Attach up to '.self::MAX_PHOTOS.' photos.',
            'photos.*.mimes' => 'Attach photos as JPG or PNG.',
            'photos.*.max' => 'Each photo must be 8 MB or smaller.',
        ];
    }

    /** @return list<UploadedFile> */
    public function photoUploads(): array
    {
        return array_values(array_filter((array) $this->file('photos', []), fn ($file) => $file instanceof UploadedFile && $file->isValid()));
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $crisis = $this->filled('disaster_id') && ! $validator->errors()->has('disaster_id') ? Disaster::find($this->integer('disaster_id')) : null;
                $farmer = $validator->errors()->has('beneficiary_id') ? null : Beneficiary::find($this->integer('beneficiary_id'));
                if ($crisis === null || $farmer === null || $validator->errors()->has('photos') || $validator->errors()->has('photos.*')) {
                    return;
                }
                if ($missing = FarmPhotoCheck::missingFarmLocation($farmer)) {
                    $validator->errors()->add('beneficiary_id', $missing);

                    return;
                }
                foreach ($this->photoUploads() as $photo) {
                    if ($problem = FarmPhotoCheck::problem($photo, $farmer) ?? FarmPhotoCheck::timeProblem($photo, $crisis)) {
                        $validator->errors()->add('photos', $problem);

                        return;
                    }
                }
            },
        ];
    }
}
