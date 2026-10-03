@php
    $cols = 'grid-cols-[382px_160px_200px_200px_1fr]';
    $canManage = auth()->user()->can('inventory.manage');
    $failed = $errors->inventory->any();
    $box = 'flex h-[44px] items-center rounded-[10px] border bg-white px-[16px] text-[14px] font-bold leading-[20px]';
    $control = 'ml-[6px] h-full min-w-0 flex-1 bg-transparent font-bold outline-none placeholder:text-muted';
    $fieldError = fn (string $name) => $errors->inventory->first($name);
@endphp
<x-layouts.app :title="$title">
    {{-- Figma 470:785 (Admin) / 430:1745 (Data Encoder) --}}
    <x-intervention.flash />

    <x-ui.card title="Inventory Monitoring">
        <form method="GET" class="relative mt-[20.5px] flex h-[39px] items-center pl-[1.5px]">
            <x-ui.pill-input name="q" placeholder="Search item..." :value="request()->queryText('q')" width="230" />
            @foreach ([398.5 => 'Unit', 558.5 => 'Stock-In', 758.5 => 'Stock-Out', 958.5 => 'Balance'] as $left => $heading)
                <span class="absolute text-[14px] font-medium text-muted" style="left: {{ $left }}px">{{ $heading }}</span>
            @endforeach
            @if ($canManage)
                <button type="button" x-data @click="$dispatch('open-modal', 'record-movement')"
                        class="ml-auto -mr-[1.5px] flex h-[39px] w-[230px] items-center justify-center rounded-[8px] bg-brand-soft text-[14px] font-bold text-white transition-colors hover:bg-[#4b32c3]">+ Record Movement</button>
            @endif
            <button type="submit" class="sr-only">Search</button>
        </form>

        <div class="divider mt-[15px]"></div>

        <div class="mt-[3px]">
            @forelse ($items as $item)
                <div class="grid h-[45px] {{ $cols }} items-center pl-[16.5px] text-[14px] font-bold leading-[21px]">
                    <span class="truncate pr-[12px]">{{ $item->name }}</span>
                    <span>{{ $item->unit_label }}</span>
                    <span>{{ \App\Models\InventoryItem::quantity($item->stockIn()) }}</span>
                    <span>{{ \App\Models\InventoryItem::quantity($item->stockOut()) }}</span>
                    @if ($item->isLow())
                        <span class="text-danger" title="Low stock (threshold {{ \App\Models\InventoryItem::quantity($item->low_stock_threshold) }})">{{ \App\Models\InventoryItem::quantity($item->balance()) }} · Low</span>
                    @else
                        <span>{{ \App\Models\InventoryItem::quantity($item->balance()) }}</span>
                    @endif
                </div>
            @empty
                <p class="py-[14px] pl-[16.5px] text-[14px] font-bold">{{ request()->queryText('q') !== '' ? 'No items match your search.' : 'No inventory items yet.' }}</p>
            @endforelse
        </div>
    </x-ui.card>

    {{-- Figma 470:785 Recent Movements --}}
    <section class="mt-[3px] rounded-[20px] border-[1.5px] border-black/10 bg-white pt-[17px] pr-[26.5px] pb-[18px] pl-[21.5px]">
        <h2 class="text-[16px] font-bold leading-[20px]">Recent Movements</h2>
        <ul class="mt-[10px] flex flex-col gap-[8px]">
            @forelse ($movements as $movement)
                <li class="flex items-center gap-[10px] text-[14px] font-medium leading-[20px]">
                    <span class="w-[60px] shrink-0 rounded-[6px] bg-[#efeaff] py-[2px] text-center text-[12px] font-bold text-brand">{{ strtoupper($movement->source) }}</span>
                    <span>{{ $movement->line() }}</span>
                </li>
            @empty
                <li class="text-[14px] font-medium">No stock movements yet.</li>
            @endforelse
        </ul>
        <p class="mt-[14px] text-[12px] font-medium leading-[16px] text-muted">Stock-out from confirmed distributions is recorded automatically. Use + Record Movement for adjustments, deliveries, and manual corrections.</p>
    </section>

    @if ($canManage)
        {{-- Figma 470:986 --}}
        <x-ui.modal name="record-movement" title="Record Stock Movement" :open="$failed" width="700">
            <form method="POST" action="{{ route('inventory.movements.store') }}" class="flex flex-col gap-[12px]">
                @csrf
                <div class="grid grid-cols-2 gap-x-[12px] gap-y-[12px]">
                    @foreach ([
                        ['inventory_item_id', 'Item:', $allItems->mapWithKeys(fn ($i) => [$i->id => "{$i->name} ({$i->unit_label})"])->all(), 'Select'],
                        ['direction', 'Movement Type:', ['in' => 'Stock In', 'out' => 'Stock Out'], 'Stock In / Stock Out'],
                    ] as [$name, $label, $options, $placeholder])
                        <div>
                            <label @class([$box, 'border-field' => ! $fieldError($name), 'border-bad' => $fieldError($name)])>
                                <span class="shrink-0">{{ $label }}</span>
                                <select name="{{ $name }}" required class="{{ $control }} cursor-pointer">
                                    <option value="" disabled @selected(! old($name))>{{ $placeholder }}</option>
                                    @foreach ($options as $value => $text)
                                        <option value="{{ $value }}" @selected((string) old($name) === (string) $value)>{{ $text }}</option>
                                    @endforeach
                                </select>
                            </label>
                            @if ($fieldError($name))<p class="mt-[4px] pl-[4px] text-[12px] font-semibold text-danger">{{ $fieldError($name) }}</p>@endif
                        </div>
                    @endforeach
                    <x-ui.inline-field label="Quantity" name="quantity" type="number" step="0.01" min="0.01" required placeholder="e.g. 50"
                                       :value="old('quantity')" :error="$fieldError('quantity')" />
                    <x-ui.inline-field label="Date" name="movement_date" type="date" required max="{{ now()->toDateString() }}"
                                       :value="old('movement_date', now()->toDateString())" :error="$fieldError('movement_date')" />
                    <div class="col-span-2">
                        <x-ui.inline-field label="Notes (optional)" name="notes" maxlength="255" placeholder="e.g. Delivery from DA-RFO"
                                           :value="old('notes')" :error="$fieldError('notes')" />
                    </div>
                </div>
                <button type="submit" class="bg-brand-bar gradient-button mt-[12px] h-[44px] rounded-[50px] text-[18px] font-bold text-white">Save Stock Movement</button>
            </form>
        </x-ui.modal>
    @endif
</x-layouts.app>
