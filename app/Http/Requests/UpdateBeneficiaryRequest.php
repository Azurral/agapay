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
            function (Validator $validator) {
                $number = $this->input('rsbsa_number');
                if ($validator->errors()->has('rsbsa_number') || ! is_string($number) || $number === '') {
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
