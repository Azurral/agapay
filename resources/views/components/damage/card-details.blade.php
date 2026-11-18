@props(['breakdown'])
@php
    use App\Services\DamageCalculator;
    $money = fn ($v) => '₱'.DamageCalculator::decimal((float) $v);
    $ha = fn ($v) => DamageCalculator::decimal((float) $v).' ha';
@endphp
{{-- The details windows of the Crisis Reports summary cards (App\Support\DamageBreakdown), for the page's filters. --}}

{{-- A simple horizontal bar row: name, bar, the number in words. --}}
@php
    // $by ('barangay' or 'crop'): the row is a button that opens "Farmers affected" for that barangay or crop.
    $bar = function (string $name, float $percent, string $label, string $note = '', ?string $by = null, array $except = []) {
        $cells = '<span class="truncate font-bold">'.e($name).'</span>'
            .'<span class="h-[12px] overflow-hidden rounded-full bg-black/5"><span class="damage-bar block h-full rounded-full" style="width: '.max($percent, 1).'%"></span></span>'
            .'<span class="text-right tabular-nums"><b>'.e($label).'</b>'.($note !== '' ? '<span class="text-muted"> · '.e($note).'</span>' : '').'</span>';
        $grid = 'grid grid-cols-[170px_1fr_230px] items-center gap-[10px] text-[13px] leading-[17px]';
        if ($by === null) {
            return '<li class="'.$grid.'">'.$cells.'</li>';
        }
        $detail = e(json_encode(['by' => $by, 'name' => $name, 'except' => $except]));

        return '<li><button type="button" data-damage-filter="'.e($by).'" class="damage-row '.$grid.' w-full cursor-pointer rounded-[8px] px-[6px] py-[3px] text-left"'
            .' title="Show the farmers affected" @click="open = false; $dispatch(&quot;damage-filter&quot;, '.$detail.'); $dispatch(&quot;open-modal&quot;, &quot;damage-farmers&quot;)">'.$cells.'</button></li>';
    };
    $names = fn (array $rows) => array_values(array_filter(array_column($rows, 'name'), fn ($n) => $n !== 'Others'));
@endphp

<x-ui.modal name="damage-farmers" title="Farmers affected" width="wide" top="40">
    {{-- Filter set by a click on a barangay or crop bar ("damage-filter" event); "Others" = every name not on its own bar. --}}
    <div x-data="{
            by: null, name: null, except: [],
            show(barangay, crop) {
                if (! this.by) return true;
                const value = this.by === 'barangay' ? barangay : crop;
                return this.name === 'Others' ? ! this.except.includes(value) : value === this.name;
            },
        }"
         x-on:damage-filter.window="by = $event.detail?.by ?? null; name = $event.detail?.name ?? null; except = $event.detail?.except ?? []">
        <p class="-mt-[12px] text-[13px] text-muted">
            <span x-show="! by">Every farmer with a damage report for these filters, largest cost first.</span>
            <span x-show="by" x-cloak>Only <b class="text-ink" x-text="(by === 'barangay' ? 'barangay ' : 'crop ') + name"></b>.
                <button type="button" class="font-bold text-brand hover:underline" @click="by = null">Show every farmer</button></span>
            Click a name to open the report.
        </p>
        <div class="mt-[12px] max-h-[calc(100vh-190px)] overflow-auto">
            <table class="w-full text-left text-[13px]">
                <thead class="sticky top-0 bg-white text-muted"><tr><th class="py-[6px]">Farmer</th><th>Barangay</th><th>Crop</th><th class="text-right">Area</th><th class="text-right">Cost</th><th class="pl-[16px]">Status</th></tr></thead>
                <tbody>
                    @forelse ($breakdown['farmers'] as $f)
                        <tr class="border-t border-black/5" data-farmer-row x-show="show(@js($f['barangay']), @js($f['crop']))">
                            <td class="py-[6px]"><a href="{{ route('damage.show', $f['report']) }}" class="font-bold hover:text-brand hover:underline">{{ $f['name'] }}</a></td>
                            <td>{{ $f['barangay'] }}</td><td>{{ $f['crop'] }}</td>
                            <td class="text-right tabular-nums">{{ $ha($f['area']) }}</td>
                            <td class="text-right tabular-nums">{{ $money($f['cost']) }}</td>
                            <td class="pl-[16px]">{{ $f['status'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-[10px] font-bold">No damage reports for these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-ui.modal>

<x-ui.modal name="damage-area" title="Area damaged" width="wide" top="40">
    <p class="-mt-[12px] text-[13px] text-muted">Hectares totally and partially damaged. A partly damaged hectare counts at the crop's partial-loss factor in the production loss.</p>
    <div class="mt-[12px] grid max-h-[calc(100vh-190px)] grid-cols-2 gap-[18px] overflow-auto">
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

<x-ui.modal name="damage-loss" title="Production loss" width="wide" top="40">
    <p class="-mt-[12px] text-[13px] text-muted">Metric tons lost per crop: (totally damaged + factor &times; partly damaged area) &times; the crop's yield per hectare.</p>
    <ul class="mt-[14px] flex max-h-[calc(100vh-190px)] flex-col gap-[9px] overflow-auto">
        @forelse ($breakdown['loss_by_crop'] as $r)
            {!! $bar($r['name'], $r['percent'], DamageCalculator::decimal($r['value']).' MT', $r['farmers'].' '.\Illuminate\Support\Str::plural('farmer', $r['farmers']).', '.DamageCalculator::decimal($r['yield']).' MT/ha', 'crop') !!}
        @empty
            <li class="text-[13px]">No damage reports for these filters.</li>
        @endforelse
    </ul>
</x-ui.modal>

<x-ui.modal name="damage-cost" title="Estimated cost of damage" width="wide" top="40">
    <p class="-mt-[12px] text-[13px] text-muted">Production loss &times; the crop's farmgate price per metric ton.</p>
    <div class="mt-[12px] flex max-h-[calc(100vh-190px)] flex-col gap-[16px] overflow-auto">
        @foreach ([['By barangay', $breakdown['cost_by_barangay'], 'barangay'], ['By crop', $breakdown['cost_by_crop'], 'crop'], ['By crisis', $breakdown['cost_by_crisis'], null]] as [$heading, $rows, $by])
            <div>
                <p class="text-[14px] font-bold">{{ $heading }}</p>
                <ul class="mt-[6px] flex flex-col gap-[7px]">
                    @forelse ($rows as $r)
                        {!! $bar($r['name'], $r['percent'], $money($r['value']), $r['farmers'].' '.\Illuminate\Support\Str::plural('farmer', $r['farmers']), $by, $names($rows)) !!}
                    @empty
                        <li class="text-[13px]">None.</li>
                    @endforelse
                </ul>
            </div>
        @endforeach
    </div>
</x-ui.modal>
