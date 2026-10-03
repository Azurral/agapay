@php
    $money = fn ($value) => '₱'.\App\Services\DamageCalculator::decimal((float) $value);
    $facts = [
        'Disaster' => $report->disaster->name.' · '.$report->disaster->occurred_on->format('M j, Y'),
        'Farmer' => $report->beneficiary->fullName().' · '.$report->beneficiary->rsbsaDisplay(),
        'Barangay' => $report->barangay->name,
        'Crop / Farm Location' => $report->crop->name.($report->farm_location ? ' · '.$report->farm_location : ''),
        'Crop Stage' => $report->stageLabel(),
        'Damaged Area (Total/Partial)' => $report->areaLabel(),
        'Production Loss' => \App\Services\DamageCalculator::decimal((float) $report->loss_mt).' MT',
        'Cost of Damage' => $money($report->cost),
        'Crop Values Used' => $report->crop->name.' · '.\App\Services\DamageCalculator::decimal((float) $report->yield_mt_per_ha).' MT/ha · '
            .$money($report->price_per_mt).'/MT · partial ×'.\App\Services\DamageCalculator::decimal((float) $report->partial_loss_factor),
        'Filed' => $report->created_at->format('M j, Y g:i A').' by '.($report->reporter?->name ?? '—'),
    ];
    if ($report->isValidated()) {
        $facts['Validated'] = $report->validated_at?->format('M j, Y g:i A').' by '.($report->validator?->name ?? '—');
    }
    if ($report->adjustment_note) {
        $facts['Adjustment Note'] = $report->adjustment_note;
    }
    $damageErrors = $errors->damage;
    $canValidate = ! $report->trashed() && ! $report->isValidated() && auth()->user()->can('damage.validate');
    $canConfigure = auth()->user()->can('damage.configure');
@endphp
<x-layouts.app title="AGRICULTURAL DAMAGE REPORT">
    <x-intervention.flash />
    @if ($damageErrors->has('report'))
        <p class="rounded-[10px] border-[1.5px] border-bad bg-white px-[16px] py-[10px] text-[14px] font-bold text-danger" role="alert">{{ $damageErrors->first('report') }}</p>
    @endif
    @if ($report->trashed())
        <p class="rounded-[10px] border-[1.5px] border-bad bg-white px-[16px] py-[10px] text-[14px] font-bold" role="status">
            Archived on {{ $report->deleted_at->format('M j, Y g:i A') }}{{ $report->deleted_by ? ' by '.\App\Models\User::find($report->deleted_by)?->name : '' }}: {{ $report->delete_reason }}
        </p>
    @endif

    {{-- No Figma frame: uses the 423:786 card style (spec §2). --}}
    <x-ui.card :title="'Damage Report · '.$report->beneficiary->fullName()">
        <x-slot:actions>
            <x-damage.status :report="$report" />
        </x-slot:actions>
        <div class="divider mt-[15px]"></div>

        <dl class="mt-[16px] grid grid-cols-2 gap-x-[40px] gap-y-[12px] text-[14px] leading-[20px]">
            @foreach ($facts as $label => $text)
                <div class="flex gap-[10px]">
                    <dt class="w-[200px] shrink-0 font-medium text-muted">{{ $label }}</dt>
                    <dd class="font-bold">{{ $text }}</dd>
                </div>
            @endforeach
            <div class="flex gap-[10px]">
                <dt class="w-[200px] shrink-0 font-medium text-muted">GPS Coordinates</dt>
                <dd class="font-bold">
                    @if ($report->latitude !== null)
                        <a href="https://www.openstreetmap.org/?mlat={{ (float) $report->latitude }}&amp;mlon={{ (float) $report->longitude }}#map=17/{{ (float) $report->latitude }}/{{ (float) $report->longitude }}"
                           target="_blank" rel="noopener" class="text-brand hover:underline">{{ (float) $report->latitude }}, {{ (float) $report->longitude }}</a>
                    @else
                        —
                    @endif
                </dd>
            </div>
        </dl>

        <h3 class="mt-[22px] text-[16px] font-bold leading-[20px]">Photos ({{ $report->photos->count() }})</h3>
        <div class="mt-[10px] flex flex-wrap gap-[12px]">
            @forelse ($report->photos as $photo)
                <a href="{{ route('damage.photos.show', $photo) }}" target="_blank" rel="noopener" class="block size-[140px] overflow-hidden rounded-[12px] border border-field">
                    <img src="{{ route('damage.photos.show', $photo) }}" alt="{{ $photo->original_name }}" class="size-full object-cover">
                </a>
            @empty
                <p class="text-[14px] font-medium text-muted">No photos were attached.</p>
            @endforelse
        </div>

        <div class="mt-[22px] flex flex-wrap items-center gap-[14px]">
            <a href="{{ route('damage.index') }}" class="border-gradient pill-button inline-flex h-[39px] w-[174px] items-center justify-center rounded-[50px] text-[18px] font-bold leading-[24px]">Back to List</a>
            @if ($canEdit)
                <a href="{{ route('damage.edit', $report) }}" class="border-gradient pill-button inline-flex h-[39px] w-[174px] items-center justify-center rounded-[50px] text-[18px] font-bold leading-[24px]">Edit</a>
            @endif
            @if ($canConfigure && $report->trashed())
                <form method="POST" action="{{ route('damage.restore', $report) }}">
                    @csrf
                    <button type="submit" class="border-gradient pill-button h-[39px] w-[174px] rounded-[50px] text-[18px] font-bold leading-[24px]">Restore</button>
                </form>
            @elseif ($canConfigure)
                <button type="button" x-data @click="$dispatch('open-modal', 'archive-damage')"
                        class="border-gradient pill-button h-[39px] w-[174px] rounded-[50px] text-[18px] font-bold leading-[24px]">Archive</button>
            @endif
        </div>
    </x-ui.card>

    @if ($canValidate)
        {{-- Spec rule 11: the AT can adjust the figures when validating. --}}
        <x-ui.card title="Validate Report">
            <p class="mt-[4px] text-[14px] font-medium leading-[18px] text-muted">Leave the figures blank to keep the computed loss and cost. If you change them, explain why.</p>
            <form method="POST" action="{{ route('damage.validate', $report) }}" class="mt-[16px] flex flex-col gap-[12px]" x-data="{ busy: false }" @submit="busy = true">
                @csrf
                <div class="grid grid-cols-2 gap-[12px]">
                    <x-ui.inline-field label="Production Loss (MT)" name="loss_mt" type="text" inputmode="decimal"
                                       :placeholder="\App\Services\DamageCalculator::decimal((float) $report->loss_mt)" :value="old('loss_mt')" :error="$damageErrors->first('loss_mt')" />
                    <x-ui.inline-field label="Cost of Damage (₱)" name="cost" type="text" inputmode="decimal"
                                       :placeholder="\App\Services\DamageCalculator::decimal((float) $report->cost)" :value="old('cost')" :error="$damageErrors->first('cost')" />
                </div>
                <x-ui.inline-field label="Adjustment Note" name="adjustment_note" maxlength="1000" placeholder="Only needed when you change the figures"
                                   :value="old('adjustment_note')" :error="$damageErrors->first('adjustment_note')" />
                <button type="submit" :disabled="busy" class="bg-brand-bar gradient-button h-[44px] rounded-[50px] text-[18px] font-bold text-white">Validate Report</button>
            </form>
        </x-ui.card>
    @endif

    @if ($canConfigure && ! $report->trashed())
        <x-ui.modal name="archive-damage" title="Archive Damage Report" :open="$damageErrors->has('delete_reason')" width="600">
            <form method="POST" action="{{ route('damage.archive', $report) }}" class="flex flex-col gap-[12px]">
                @csrf
                <x-ui.inline-field label="Reason" name="delete_reason" maxlength="255" required placeholder="e.g. Filed under the wrong farmer" :error="$damageErrors->first('delete_reason')" />
                <p class="text-[13px] font-medium text-muted">Archived reports leave the list and totals but can be restored from Status: Archived.</p>
                <button type="submit" class="bg-brand-bar gradient-button h-[44px] rounded-[50px] text-[18px] font-bold text-white">Archive Report</button>
            </form>
        </x-ui.modal>
    @endif
</x-layouts.app>
