<?php

namespace App\Http\Requests;

use App\Models\Beneficiary;
use Illuminate\Foundation\Http\FormRequest;
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
        ];
    }

    public function messages(): array
    {
        return $this->beneficiaryMessages();
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
