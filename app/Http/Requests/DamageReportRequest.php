<?php

namespace App\Http\Requests;

use App\Models\DamageReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Figma 423:786 / 423:306 New Damage Report (spec rule 11); also used when editing an unvalidated report. */
class DamageReportRequest extends FormRequest
{
    public const MAX_PHOTOS = 10;

    public const MAX_PHOTO_KILOBYTES = 5 * 1024;

    public function rules(): array
    {
        $area = ['nullable', 'numeric', 'min:0', 'max:9999.99'];

        return [
            'disaster_id' => ['required', 'integer', Rule::exists('disasters', 'id')],
            'beneficiary_id' => ['required', 'integer', Rule::exists('beneficiaries', 'id')->whereNull('deleted_at')],
            'barangay_id' => ['required', 'integer', Rule::exists('barangays', 'id')],
            'crop_id' => ['required', 'integer', Rule::exists('crops', 'id')],
            'farm_location' => ['nullable', 'string', 'max:255'],
            'crop_stage' => ['required', 'string', Rule::in(array_keys(DamageReport::STAGES))],
            'total_area_ha' => $area,
            'partial_area_ha' => $area,
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'photos' => ['nullable', 'array', 'max:'.self::MAX_PHOTOS],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png', 'max:'.self::MAX_PHOTO_KILOBYTES],
            'remove_photos' => ['nullable', 'array'],
            'remove_photos.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        $area = 'Enter the area in hectares, e.g. 1.5.';
        $photo = 'Photos must be JPG or PNG files up to 5 MB.';

        return [
            'disaster_id.*' => 'Choose a disaster.',
            'beneficiary_id.*' => 'Choose a farmer from the list.',
            'barangay_id.*' => 'Choose a barangay.',
            'crop_id.*' => 'Choose a crop.',
            'crop_stage.*' => 'Choose a crop stage.',
            'total_area_ha.*' => $area,
            'partial_area_ha.*' => $area,
            'latitude.required_with' => 'Enter both latitude and longitude.',
            'longitude.required_with' => 'Enter both latitude and longitude.',
            'latitude.*' => 'Latitude must be between -90 and 90.',
            'longitude.*' => 'Longitude must be between -180 and 180.',
            'photos.array' => 'Attach up to 10 photos.',
            'photos.max' => 'Attach up to 10 photos.',
            'photos.*.*' => $photo,
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $areas = $validator->errors()->hasAny(['total_area_ha', 'partial_area_ha'])
                    ? null
                    : (float) $this->input('total_area_ha') + (float) $this->input('partial_area_ha');

                if ($areas !== null && $areas <= 0) {
                    $validator->errors()->add('total_area_ha', 'Enter the damaged area.');
                }
            },
        ];
    }
}
