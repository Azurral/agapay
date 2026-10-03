<?php

namespace App\Http\Requests;

use App\Models\Intervention;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Figma 446:198 Add / Edit Intervention Record. The beneficiary is chosen on create only. */
class InterventionRecordRequest extends FormRequest
{
    public const DISTRIBUTED = 'distributed';

    public const NOT_DISTRIBUTED = 'not_distributed';

    public function rules(): array
    {
        $source = is_string($this->input('source')) ? $this->input('source') : '';

        return [
            'beneficiary_id' => [Rule::requiredIf(fn () => $this->isMethod('post')), 'integer',
                Rule::exists('beneficiaries', 'id')->whereNull('deleted_at')],
            'source' => ['required', 'string', Rule::in(Intervention::SOURCES)],
            'intervention_id' => ['required', 'integer', Rule::exists('interventions', 'id')->where('source', $source)],
            'distribution_cycle_id' => ['required', 'integer', Rule::exists('distribution_cycles', 'id')],
            'quantity' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'distribution_status' => ['required', 'string', Rule::in([self::DISTRIBUTED, self::NOT_DISTRIBUTED])],
            'date_distributed' => ['nullable', 'required_if:distribution_status,'.self::DISTRIBUTED, 'date', 'before_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'beneficiary_id.required' => 'Choose a beneficiary from the search results.',
            'beneficiary_id.integer' => 'Choose a beneficiary from the search results.',
            'beneficiary_id.exists' => 'Choose a beneficiary from the search results.',
            'source.in' => 'Choose DA or LGU.',
            'intervention_id.exists' => 'Choose an intervention from the selected program.',
            'distribution_cycle_id.exists' => 'Choose a batch / cycle.',
            'date_distributed.required_if' => 'Enter the date it was distributed.',
            'date_distributed.before_or_equal' => 'The distribution date cannot be in the future.',
        ];
    }

    public function wantsDistributed(): bool
    {
        return $this->validated('distribution_status') === self::DISTRIBUTED;
    }
}
