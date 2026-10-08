<?php

namespace App\Http\Requests;

use App\Models\Crop;
use App\Models\DamageReport;
use App\Services\DamageCalculator;
use App\Support\StagedPhotos;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
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
            // Paper (interview, Section G Q9): photographic documentation is mandatory. An edit keeps the stored photos.
            // Photos kept from an earlier try (StagedPhotos) count as attached.
            'photos' => [Rule::requiredIf(fn () => $this->isMethod('post') && StagedPhotos::keptTokens($this) === []), 'array', 'max:'.self::MAX_PHOTOS],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png', 'max:'.intdiv(self::photoLimitBytes(), 1024)],
            'kept_photos' => ['nullable', 'array'],
            'remove_photos' => ['nullable', 'array'],
            'remove_photos.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        $area = 'Enter the area in hectares, e.g. 1.5.';
        $photo = 'Each photo must be JPG or PNG and at most '.self::photoLimitLabel().'.';

        return [
            'disaster_id.*' => 'Choose a crisis.',
            'beneficiary_id.*' => 'Choose a farmer from the list.',
            'barangay_id.*' => 'Choose a barangay.',
            'crop_id.*' => 'Choose a crop.',
            'crop_stage.*' => 'Choose a crop stage.',
            'total_area_ha.*' => $area,
            'partial_area_ha.*' => $area,
            'latitude.required_with' => 'Enter both latitude and longitude.',
            'longitude.required_with' => 'Enter both latitude and longitude.',
            'latitude.numeric' => 'Enter the latitude in decimal degrees, e.g. 17.0894.',
            'longitude.numeric' => 'Enter the longitude in decimal degrees, e.g. 120.9750.',
            'latitude.*' => 'Latitude must be between -90 and 90.',
            'longitude.*' => 'Longitude must be between -180 and 180.',
            'photos.required' => 'Attach at least one photo of the damage (JPG or PNG).',
            'photos.array' => 'Attach up to 10 photos.',
            'photos.max' => 'Attach up to 10 photos.',
            'photos.*.*' => $photo,
        ];
    }

    /** The largest photo accepted: 5 MB, or less when php.ini's upload_max_filesize is lower. */
    public static function photoLimitBytes(): int
    {
        return min(self::MAX_PHOTO_KILOBYTES * 1024, self::bytes((string) ini_get('upload_max_filesize')) ?: PHP_INT_MAX);
    }

    /** "5 MB", "2 MB" */
    public static function photoLimitLabel(): string
    {
        return rtrim(rtrim(number_format(self::photoLimitBytes() / 1048576, 1), '0'), '.').' MB';
    }

    /** php.ini size ("2M", "512K", "1G", "8388608") in bytes; 0 means no limit. */
    public static function bytes(string $size): int
    {
        $size = trim($size);
        $number = (int) $size;

        return match (strtoupper(substr($size, -1))) {
            'G' => $number * 1024 ** 3,
            'M' => $number * 1024 ** 2,
            'K' => $number * 1024,
            default => $number,
        };
    }

    /** The browser forgets chosen files on an error, so the valid ones are kept for the next try. */
    protected function failedValidation(ValidatorContract $validator): void
    {
        StagedPhotos::stash($this);

        parent::failedValidation($validator);
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $photos = count(array_filter((array) $this->file('photos', []))) + count(StagedPhotos::keptTokens($this));
                if ($photos > self::MAX_PHOTOS && ! $validator->errors()->has('photos')) {
                    $validator->errors()->add('photos', 'Attach up to 10 photos.');
                }

                $areas = $validator->errors()->hasAny(['total_area_ha', 'partial_area_ha'])
                    ? null
                    : (float) $this->input('total_area_ha') + (float) $this->input('partial_area_ha');

                if ($areas !== null && $areas <= 0) {
                    $validator->errors()->add('total_area_ha', 'Enter the damaged area.');
                }

                // The cost column holds up to ₱999,999,999,999.99; only absurd crop values get past it.
                $crop = $areas > 0 && ! $validator->errors()->has('crop_id') ? Crop::find($this->integer('crop_id')) : null;
                if ($crop && DamageCalculator::forCrop($crop, (float) $this->input('total_area_ha'), (float) $this->input('partial_area_ha'))['cost'] > 999_999_999_999.99) {
                    $validator->errors()->add('total_area_ha', 'The computed cost is too large — check the crop values.');
                }
            },
        ];
    }
}
