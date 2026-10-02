@php
    use App\Models\Beneficiary;

    // status => [[action, label, modal field|null]...]
    $steps = [
        Beneficiary::RSBSA_PENDING => [['validate', 'Validate', null], ['return', 'Return', 'reason'], ['reject', 'Reject', 'reason']],
        Beneficiary::RSBSA_VALIDATED => [['endorse', 'Endorse', null], ['return', 'Return', 'reason'], ['reject', 'Reject', 'reason']],
        Beneficiary::RSBSA_ENDORSED => [['record-number', 'Record Number', 'rsbsa_number'], ['return', 'Return', 'reason'], ['reject', 'Reject', 'reason']],
        Beneficiary::RSBSA_RETURNED => [['resubmit', 'Resubmit', null], ['reject', 'Reject', 'reason']],
    ];
    $modalTitles = ['record-number' => 'Record RSBSA Number', 'return' => 'Return Registration', 'reject' => 'Reject Registration'];
    $fieldLabels = ['rsbsa_number' => 'RSBSA Number', 'reason' => 'Reason'];
    $fieldHints = ['rsbsa_number' => 'From the DA-RFO masterlist', 'reason' => 'e.g. Awaiting Barangay Confirmation'];
@endphp

<x-ui.card title="Pending RSBSA Applications" class="mt-[20px]">
    <p class="mt-[3px] text-[14px] font-medium leading-[21px] text-muted">Applications awaiting OMAG validation, DA-RFO endorsement or the masterlist RSBSA number.</p>
    <div class="divider mt-[7px]"></div>

    @if ($errors->rsbsa->has('rsbsa'))
        <p class="mt-[12px] pl-[4.5px] text-[14px] font-bold text-danger" role="alert">{{ $errors->rsbsa->first('rsbsa') }}</p>
    @endif

    <table class="mt-[4px] w-full table-fixed text-left text-[14px] font-bold leading-[21px]">
        <colgroup><col class="w-[290px]"><col class="w-[200px]"><col class="w-[190px]"><col class="w-[190px]"><col></colgroup>
        <thead>
            <tr class="h-[45px] text-muted">
                <th class="pl-[4.5px] font-bold">Name</th><th class="font-bold">Barangay</th><th class="font-bold">Date Submitted</th><th class="font-bold">Status</th>
                <th class="font-bold">@if ($canProcess) Actions @endif</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($pending as $applicant)
                <tr class="h-[55px]">
                    <td class="truncate pl-[4.5px]">{{ $applicant->fullName() }}</td>
                    <td>{{ $applicant->barangay?->name }}</td>
                    <td>{{ $applicant->created_at->format('M j, Y') }}</td>
                    <td>
                        <x-ui.status-chip :tone="Beneficiary::rsbsaStatusTone($applicant->rsbsa_status)">{{ Beneficiary::rsbsaStatusLabel($applicant->rsbsa_status) }}</x-ui.status-chip>
                        @if ($applicant->rsbsa_status_reason)
                            <span class="mt-[2px] block truncate text-[12px] font-semibold text-muted" title="{{ $applicant->rsbsa_status_reason }}">{{ $applicant->rsbsa_status_reason }}</span>
                        @endif
                    </td>
                    <td>
                        @if ($canProcess)
                            <div class="flex flex-wrap items-center gap-[8px]">
                                @foreach ($steps[$applicant->rsbsa_status] ?? [] as [$action, $label, $field])
                                    @php($modal = "{$action}-{$applicant->id}")
                                    @if ($field)
                                        <button type="button" x-data @click="$dispatch('open-modal', '{{ $modal }}')"
                                                class="pill-button h-[34px] rounded-[50px] border-[1.5px] border-field bg-white px-[14px] text-[13px] font-bold">{{ $label }}</button>
                                        <x-ui.modal :name="$modal" :title="$modalTitles[$action].' - '.$applicant->fullName()" :open="session('rsbsa_failed') === $modal" width="560">
                                            <form method="POST" action="{{ route('rsbsa.transition', [$applicant, $action]) }}" class="flex flex-col gap-[16px]">
                                                @csrf
                                                <x-ui.inline-field :label="$fieldLabels[$field]" :name="$field" :placeholder="$fieldHints[$field]" required
                                                                   :value="session('rsbsa_failed') === $modal ? old($field) : null"
                                                                   :error="session('rsbsa_failed') === $modal ? $errors->rsbsa->first($field) : null" />
                                                <button type="submit" class="bg-brand-bar gradient-button h-[44px] rounded-[50px] text-[14px] font-bold text-white">{{ $modalTitles[$action] }}</button>
                                            </form>
                                        </x-ui.modal>
                                    @else
                                        <form method="POST" action="{{ route('rsbsa.transition', [$applicant, $action]) }}">
                                            @csrf
                                            <button type="submit" class="pill-button h-[34px] rounded-[50px] border-[1.5px] border-field bg-white px-[14px] text-[13px] font-bold">{{ $label }}</button>
                                        </form>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-[12px] pl-[4.5px]">No pending RSBSA applications.</td></tr>
            @endforelse
        </tbody>
    </table>
</x-ui.card>
