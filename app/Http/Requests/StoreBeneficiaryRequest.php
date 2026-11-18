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
            // Walk-in verification (no ghost farmers).
            'valid_id_type' => ['required', Rule::in(array_keys(Beneficiary::VALID_IDS))],
            'valid_id_number' => ['required', 'string', 'max:50'],
            'valid_id_photo' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:8192'],
        ];
    }

    public function messages(): array
    {
        return [
            ...$this->beneficiaryMessages(),
            'consent.accepted' => 'Confirm that the farmer gave consent before saving.',
            'valid_id_type.*' => 'Choose the type of valid ID.',
            'valid_id_number.required' => 'Enter the ID number.',
            'valid_id_photo.required' => 'Attach a photo or scan of the valid ID (JPG or PNG).',
            'valid_id_photo.mimes' => 'Attach a photo or scan of the valid ID (JPG or PNG).',
            'valid_id_photo.max' => 'The ID photo must be 8 MB or smaller.',
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
            },
        ];
    }
}
