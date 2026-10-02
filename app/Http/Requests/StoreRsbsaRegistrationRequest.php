<?php

namespace App\Http\Requests;

use App\Models\Beneficiary;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreRsbsaRegistrationRequest extends FormRequest
{
    use BeneficiaryRules;

    protected function prepareForValidation(): void
    {
        $this->trimNames();
    }

    public function rules(): array
    {
        return $this->beneficiaryRules();
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
                }
            },
        ];
    }
}
