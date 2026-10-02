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

                $alreadyRegistered = Beneficiary::query()
                    ->whereRaw('LOWER(first_name) = ?', [mb_strtolower($this->input('first_name'))])
                    ->whereRaw('LOWER(last_name) = ?', [mb_strtolower($this->input('last_name'))])
                    ->whereDate('birthdate', $this->input('birthdate'))
                    ->where('barangay_id', $this->input('barangay_id'))
                    ->exists();

                if ($alreadyRegistered) {
                    $validator->errors()->add('first_name', 'This person is already registered.');
                }
            },
        ];
    }
}
