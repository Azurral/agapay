<?php

namespace App\Http\Requests;

use App\Models\Beneficiary;
use Illuminate\Validation\Rule;

/** Field rules shared by Add Beneficiary and the encoder's profile edit. */
trait BeneficiaryRules
{
    /** @return array<string, array<int, mixed>> */
    protected function beneficiaryRules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'birthdate' => ['required', 'date', 'before_or_equal:'.now()->subYears(18)->toDateString()],
            'house_no' => ['nullable', 'string', 'max:100'],
            'street' => ['nullable', 'string', 'max:100'],
            'sitio' => ['required', 'string', 'max:100'],
            'barangay_id' => ['required', 'integer', Rule::exists('barangays', 'id')],
            'contact_number' => ['nullable', 'regex:/^09\d{2}-?\d{3}-?\d{4}$/'],
            'farm_area_ha' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'crop_type' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    protected function beneficiaryMessages(): array
    {
        return [
            'birthdate.before_or_equal' => 'Applicant must be at least 18 years old.',
            'contact_number.regex' => 'Use the format 09XX-XXX-XXXX.',
            'barangay_id.required' => 'Select a barangay.',
            'sitio.required' => 'Enter the sitio or purok.',
            'farm_area_ha.*' => 'Enter the farm area in hectares, e.g. 1.5.',
        ];
    }

    /** RSBSA numbers are unique across all profiles, archived ones included, ignoring case. */
    protected function rsbsaNumberTaken(string $number, ?int $exceptId = null): bool
    {
        return Beneficiary::withTrashed()
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->whereRaw('LOWER(TRIM(rsbsa_number)) = ?', [mb_strtolower($number)])
            ->exists();
    }

    /** Trims name fields so " JUAN " and "Juan" are treated alike. */
    protected function trimNames(): void
    {
        $this->merge(collect(['first_name', 'middle_name', 'last_name', 'house_no', 'street', 'sitio'])
            ->filter(fn (string $key) => is_string($this->input($key)))
            ->mapWithKeys(fn (string $key) => [$key => trim(preg_replace('/\s+/', ' ', $this->input($key)))])
            ->all());
    }
}
