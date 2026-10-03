<?php

namespace App\Http\Requests;

use App\Models\Beneficiary;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateBeneficiaryRequest extends FormRequest
{
    use BeneficiaryRules;

    protected function prepareForValidation(): void
    {
        $this->trimNames();

        if (is_string($this->input('rsbsa_number'))) {
            $this->merge(['rsbsa_number' => trim($this->input('rsbsa_number'))]);
        }
    }

    public function rules(): array
    {
        /** @var Beneficiary $beneficiary */
        $beneficiary = $this->route('beneficiary');

        return [
            ...$this->beneficiaryRules(),
            // A number from the DA-RFO masterlist can be corrected but not removed.
            'rsbsa_number' => [$beneficiary->rsbsa_number ? 'required' : 'nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            ...$this->beneficiaryMessages(),
            'rsbsa_number.required' => 'A registered RSBSA number cannot be removed.',
        ];
    }

    public function after(): array
    {
        return [
            // Same person = same first + last name, birthdate and barangay as another active profile.
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['first_name', 'last_name', 'birthdate', 'barangay_id'])) {
                    return;
                }
                if (Beneficiary::isAlreadyRegistered((string) $this->input('first_name'), (string) $this->input('last_name'),
                    (string) $this->input('birthdate'), (int) $this->input('barangay_id'), $this->route('beneficiary')->getKey())) {
                    $validator->errors()->add('first_name', 'Another profile already has this name, birthdate and barangay.');
                }
            },
            function (Validator $validator) {
                $number = $this->input('rsbsa_number');
                if ($validator->errors()->has('rsbsa_number') || ! is_string($number) || $number === '') {
                    return;
                }

                // A first number completes the RSBSA workflow, so it needs the same DA-RFO endorsement step.
                $beneficiary = $this->route('beneficiary');
                if (! $beneficiary->rsbsa_number && ! in_array($beneficiary->rsbsa_status, [Beneficiary::RSBSA_ENDORSED, Beneficiary::RSBSA_REGISTERED], true)) {
                    $validator->errors()->add('rsbsa_number', 'Record the RSBSA number after DA-RFO endorsement.');

                    return;
                }

                $taken = Beneficiary::withTrashed()
                    ->whereKeyNot($this->route('beneficiary')->getKey())
                    ->whereRaw('LOWER(TRIM(rsbsa_number)) = ?', [mb_strtolower($number)])
                    ->exists();

                if ($taken) {
                    $validator->errors()->add('rsbsa_number', "RSBSA number {$number} is already assigned to another beneficiary.");
                }
            },
        ];
    }
}
