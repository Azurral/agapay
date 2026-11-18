@props(['breakdown'])
@php
    use App\Services\DamageCalculator;
    $money = fn ($v) => '₱'.DamageCalculator::decimal((float) $v);
    $ha = fn ($v) => DamageCalculator::decimal((float) $v).' ha';
@endphp
{{-- The details windows of the Crisis Reports summary cards (App\Support\DamageBreakdown), for the page's filters. --}}

{{-- A simple horizontal bar row: name, bar, the number in words. --}}
@php
    $bar = function (string $name, float $percent, string $label, string $note = '') {
        return '<li class="grid grid-cols-[150px_1fr_190px] items-center gap-[10px] text-[13px] leading-[17px]">'
            .'<span class="truncate font-bold">'.e($name).'</span>'
            .'<span class="h-[12px] overflow-hidden rounded-full bg-black/5"><span class="damage-bar block h-full rounded-full" style="width: '.max($percent, 1).'%"></span></span>'
            .'<span class="text-right tabular-nums"><b>'.e($label).'</b>'.($note !== '' ? '<span class="text-muted"> · '.e($note).'</span>' : '').'</span></li>';
    };
@endphp

<x-ui.modal name="damage-farmers" title="Farmers affected" width="900">
    <p class="-mt-[12px] text-[13px] text-muted">Every farmer with a damage report for these filters, largest cost first. Click a name to open the report.</p>
    <div class="mt-[12px] max-h-[55vh] overflow-auto">
        <table class="w-full text-left text-[13px]">
            <thead class="sticky top-0 bg-white text-muted"><tr><th class="py-[6px]">Farmer</th><th>Barangay</th><th>Crop</th><th class="text-right">Area</th><th class="text-right">Cost</th><th class="pl-[12px]">Status</th></tr></thead>
            <tbody>
                @forelse ($breakdown['farmers'] as $f)
                    <tr class="border-t border-black/5">
                        <td class="py-[6px]"><a href="{{ route('damage.show', $f['report']) }}" class="font-bold hover:text-brand hover:underline">{{ $f['name'] }}</a></td>
                        <td>{{ $f['barangay'] }}</td><td>{{ $f['crop'] }}</td>
                        <td class="text-right tabular-nums">{{ $ha($f['area']) }}</td>
                        <td class="text-right tabular-nums">{{ $money($f['cost']) }}</td>
                        <td class="pl-[12px]">{{ $f['status'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-[10px] font-bold">No damage reports for these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-ui.modal>

<x-ui.modal name="damage-area" title="Area damaged" width="820">
    <p class="-mt-[12px] text-[13px] text-muted">Hectares totally and partially damaged. A partly damaged hectare counts at the crop's partial-loss factor in the production loss.</p>
    <div class="mt-[12px] grid max-h-[55vh] grid-cols-2 gap-[18px] overflow-auto">
        @foreach (['By barangay' => $breakdown['area_by_barangay'], 'By crop' => $breakdown['area_by_crop']] as $heading => $rows)
            <div>
                <p class="text-[14px] font-bold">{{ $heading }}</p>
                <table class="mt-[6px] w-full text-[13px]">
                    <thead class="text-muted"><tr><th class="text-left">Name</th><th class="text-right">Totally</th><th class="text-right">Partly</th><th class="text-right">All</th></tr></thead>
                    <tbody>
                        @forelse ($rows as $r)
                            <tr class="border-t border-black/5"><td class="py-[5px] font-bold">{{ $r['name'] }}</td>
                                <td class="text-right tabular-nums">{{ $ha($r['total']) }}</td><td class="text-right tabular-nums">{{ $ha($r['partial']) }}</td>
                                <td class="text-right tabular-nums font-bold">{{ $ha($r['value']) }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="py-[8px]">None.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endforeach
    </div>
</x-ui.modal>

<x-ui.modal name="damage-loss" title="Production loss" width="760">
    <p class="-mt-[12px] text-[13px] text-muted">Metric tons lost per crop: (totally damaged + factor &times; partly damaged area) &times; the crop's yield per hectare.</p>
    <ul class="mt-[14px] flex max-h-[55vh] flex-col gap-[9px] overflow-auto">
        @forelse ($breakdown['loss_by_crop'] as $r)
            {!! $bar($r['name'], $r['percent'], DamageCalculator::decimal($r['value']).' MT', $r['farmers'].' '.\Illuminate\Support\Str::plural('farmer', $r['farmers']).', '.DamageCalculator::decimal($r['yield']).' MT/ha') !!}
        @empty
            <li class="text-[13px]">No damage reports for these filters.</li>
        @endforelse
    </ul>
</x-ui.modal>

<x-ui.modal name="damage-cost" title="Estimated cost of damage" width="820">
    <p class="-mt-[12px] text-[13px] text-muted">Production loss &times; the crop's farmgate price per metric ton.</p>
    <div class="mt-[12px] flex max-h-[55vh] flex-col gap-[16px] overflow-auto">
        @foreach (['By barangay' => $breakdown['cost_by_barangay'], 'By crop' => $breakdown['cost_by_crop'], 'By crisis' => $breakdown['cost_by_crisis']] as $heading => $rows)
            <div>
                <p class="text-[14px] font-bold">{{ $heading }}</p>
                <ul class="mt-[6px] flex flex-col gap-[7px]">
                    @forelse ($rows as $r)
                        {!! $bar($r['name'], $r['percent'], $money($r['value']), $r['farmers'].' '.\Illuminate\Support\Str::plural('farmer', $r['farmers'])) !!}
                    @empty
                        <li class="text-[13px]">None.</li>
                    @endforelse
                </ul>
            </div>
        @endforeach
    </div>
</x-ui.modal>
