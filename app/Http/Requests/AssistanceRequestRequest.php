<?php

namespace App\Http\Requests;

use App\Models\Intervention;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** New Assistance Request: a registered farmer asking for one program, optionally after a crisis. */
class AssistanceRequestRequest extends FormRequest
{
    public function rules(): array
    {
        $source = is_string($this->input('source')) ? $this->input('source') : '';

        return [
            'beneficiary_id' => ['required', 'integer', Rule::exists('beneficiaries', 'id')->whereNull('deleted_at')],
            'source' => ['required', 'string', Rule::in(Intervention::SOURCES)],
            'intervention_id' => ['required', 'integer', Rule::exists('interventions', 'id')->where('source', $source)],
            'quantity' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'disaster_id' => ['nullable', 'integer', Rule::exists('disasters', 'id')],
            'crop_id' => ['nullable', 'integer', Rule::exists('crops', 'id')],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'beneficiary_id.*' => 'Choose a beneficiary from the search results.',
            'source.*' => 'Choose DA or LGU.',
            'intervention_id.*' => 'Choose what the farmer is asking for.',
            'quantity.*' => 'Enter the quantity as a number, e.g. 2.',
            'disaster_id.*' => 'Choose a crisis from the list.',
            'crop_id.*' => 'Choose a crop from the list.',
            'reason.max' => 'Keep the reason under 500 characters.',
        ];
    }
}
