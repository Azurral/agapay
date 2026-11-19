<?php

namespace App\Http\Requests;

use App\Models\Beneficiary;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBeneficiaryRequest extends FormRequest
{
    use BeneficiaryRules;

    protected function prepareForValidation(): void
    {
        $this->trimNames();

        if (is_string($this->input('rsbsa_number'))) {
            $number = trim($this->input('rsbsa_number'));
            $this->merge(['rsbsa_number' => Beneficiary::isNoRsbsa($number) ? null : $number]);
        }
    }

    public function rules(): array
    {
        return [
            ...$this->beneficiaryRules(),
            // Optional: farmers outside the RSBSA can still receive LGU programs.
            'rsbsa_number' => ['nullable', 'string', 'max:50'],
            'consent' => ['accepted'],
            // Geolocation: every farm has its GPS position (damage photos are checked against it).
            'farm_latitude' => ['required', 'numeric', 'between:-90,90'],
            'farm_longitude' => ['required', 'numeric', 'between:-180,180'],
            // Walk-in verification (no ghost farmers).
            'valid_id_type' => ['required', Rule::in(array_keys(Beneficiary::VALID_IDS))],
            'valid_id_number' => ['required', 'string', 'max:50'],
            'valid_id_photo' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:8192'],
            // A farmer with an RSBSA No. also shows the RSBSA / agricultural ID with their signature.
            'rsbsa_id_photo' => ['required_with:rsbsa_number', 'nullable', 'file', 'mimes:jpg,jpeg,png', 'max:8192'],
        ];
    }

    public function messages(): array
    {
        return [
            ...$this->beneficiaryMessages(),
            'consent.accepted' => 'Confirm that the farmer gave consent before saving.',
            'farm_latitude.required' => "Enter the farm's GPS location (latitude and longitude).",
            'farm_longitude.required' => "Enter the farm's GPS location (latitude and longitude).",
            'farm_latitude.*' => 'Latitude must be a number between -90 and 90, e.g. 17.0894.',
            'farm_longitude.*' => 'Longitude must be a number between -180 and 180, e.g. 120.9770.',
            'valid_id_type.*' => 'Choose the type of valid ID.',
            'valid_id_number.required' => 'Enter the ID number.',
            'valid_id_photo.required' => 'Attach a photo or scan of the valid ID (JPG or PNG).',
            'valid_id_photo.mimes' => 'Attach a photo or scan of the valid ID (JPG or PNG).',
            'valid_id_photo.max' => 'The ID photo must be 8 MB or smaller.',
            'rsbsa_id_photo.required_with' => "Attach a photo of the RSBSA / agricultural ID with the farmer's signature (JPG or PNG).",
            'rsbsa_id_photo.mimes' => "Attach a photo of the RSBSA / agricultural ID with the farmer's signature (JPG or PNG).",
            'rsbsa_id_photo.max' => 'The RSBSA ID photo must be 8 MB or smaller.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if (Beneficiary::isAlreadyRegistered($this->input('first_name'), $this->input('last_name'), $this->input('birthdate'), (int) $this->input('barangay_id'))) {
                    $validator->errors()->add('first_name', 'This person is already registered.');

                    return;
                }

                $number = $this->input('rsbsa_number');
                if (is_string($number) && $number !== '' && $this->rsbsaNumberTaken($number)) {
                    $validator->errors()->add('rsbsa_number', "RSBSA number {$number} is already assigned to another beneficiary.");
                }

                // Proof cannot be borrowed: one ID (type + number) and one ID photo belong to one farmer only.
                $holder = Beneficiary::holderOfValidId((string) $this->input('valid_id_type'), (string) $this->input('valid_id_number'));
                if ($holder !== null) {
                    $label = Beneficiary::VALID_IDS[$this->input('valid_id_type')];
                    $validator->errors()->add('valid_id_number', "That {$label} number is already on {$holder->fullName()}'s profile.");
                }
                foreach (['valid_id_photo', 'rsbsa_id_photo'] as $field) {
                    $file = $this->file($field);
                    if ($file && ($used = Beneficiary::holderOfIdPhoto(hash_file('sha256', $file->getRealPath())))) {
                        $validator->errors()->add($field, "This ID photo was already used for {$used->fullName()}. Take a photo of this farmer's own ID.");
                    }
                }
            },
        ];
    }
}
