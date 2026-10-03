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
@endphp
<x-layouts.app title="AGRICULTURAL DAMAGE REPORT">
    <x-intervention.flash />

    {{-- No Figma frame: uses the 423:786 card style (spec §2). --}}
    <x-ui.card :title="'Damage Report · '.$report->beneficiary->fullName()">
        <x-slot:actions>
            <span @class([
                'inline-flex h-[34px] w-[140px] items-center justify-center rounded-[10px] border-3 bg-white text-[13px] font-bold',
                'border-ok' => $report->statusTone() === 'ok', 'border-bad' => $report->statusTone() === 'bad',
            ])>{{ $report->statusLabel() }}</span>
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

        @if ($canEdit)
            <div class="mt-[22px]">
                <a href="{{ route('damage.edit', $report) }}" class="border-gradient pill-button inline-flex h-[39px] w-[174px] items-center justify-center rounded-[50px] text-[18px] font-bold leading-[24px]">Edit</a>
            </div>
        @endif
    </x-ui.card>
</x-layouts.app>
