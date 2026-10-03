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

    {{-- Not in Figma: kept below the mirrored cards (spec §2). --}}
    @if ($canManage)
        <div class="-mt-[2px]">
            <button type="button" x-data @click="$dispatch('open-modal', 'manage-items')"
                    class="border-gradient pill-button h-[39px] w-[174px] rounded-[50px] text-[18px] font-bold leading-[24px]">Manage Items</button>
        </div>
    @endif


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

        {{-- No Figma frame: add items, set low-stock thresholds and choose which programs hand each item out. --}}
        @php
            $itemData = $allItems->map(fn ($i) => [
                'id' => $i->id, 'name' => $i->name, 'unit' => $i->unit, 'unit_label' => $i->unit_label,
                'low_stock_threshold' => \App\Models\InventoryItem::quantity($i->low_stock_threshold),
                'interventions' => $i->interventions->pluck('id')->map(fn ($id) => (string) $id)->values(),
                'url' => route('inventory.items.update', $i),
            ])->values();
            $itemFailed = $errors->item->any();
            $oldForm = [
                'id' => old('editing_id') ? (int) old('editing_id') : null, 'name' => old('name', ''), 'unit' => old('unit', ''),
                'unit_label' => old('unit_label', ''), 'low_stock_threshold' => old('low_stock_threshold', '0'),
                'interventions' => array_map('strval', (array) old('interventions', [])),
            ];
            $itemError = fn (string $name) => $errors->item->first($name);
        @endphp
        <x-ui.modal name="manage-items" title="Manage Inventory Items" :open="$itemFailed" width="760">
            <div x-data="{
                    items: @js($itemData), storeUrl: @js(route('inventory.items.store')),
                    blank: { id: null, name: '', unit: '', unit_label: '', low_stock_threshold: '0', interventions: [] },
                    form: @js($itemFailed ? $oldForm : null),
                    init() { if (! this.form) this.form = { ...this.blank }; },
                    edit(item) { this.form = { ...item, interventions: [...item.interventions] }; },
                }" class="flex flex-col gap-[14px]">
                <ul class="flex max-h-[180px] flex-col overflow-auto rounded-[10px] border border-field">
                    <template x-for="item in items" :key="item.id">
                        <li class="flex items-center justify-between border-b border-field px-[14px] py-[8px] text-[14px] font-bold last:border-b-0">
                            <span><span x-text="item.name"></span> <span class="font-medium text-muted" x-text="'· ' + item.unit_label + ' · low at ' + item.low_stock_threshold"></span></span>
                            <button type="button" @click="edit(item)" class="hover-tint rounded-[8px] px-[10px] py-[4px] text-brand">Edit</button>
                        </li>
                    </template>
                </ul>

                <form method="POST" :action="form.id ? items.find(i => i.id === form.id)?.url : storeUrl" class="flex flex-col gap-[12px]">
                    @csrf
                    <template x-if="form.id"><input type="hidden" name="_method" value="PUT"></template>
                    <input type="hidden" name="editing_id" :value="form.id ?? ''">
                    <p class="text-[14px] font-bold" x-text="form.id ? 'Edit ' + form.name : 'Add Item'"></p>
                    <div class="grid grid-cols-2 gap-[12px]">
                        @foreach ([['name', 'Name', 'e.g. Molasses'], ['unit', 'Unit (singular)', 'e.g. liter'], ['unit_label', 'Unit label', 'e.g. liters'], ['low_stock_threshold', 'Low stock at', '0 = never']] as [$field, $label, $hint])
                            <div>
                                <label @class([$box, 'border-field' => ! $itemError($field), 'border-bad' => $itemError($field)])>
                                    <span class="shrink-0">{{ $label }}:</span>
                                    <input type="{{ $field === 'low_stock_threshold' ? 'number' : 'text' }}" name="{{ $field }}" x-model="form.{{ $field }}"
                                           placeholder="{{ $hint }}" required @if ($field === 'low_stock_threshold') min="0" step="0.01" @endif class="{{ $control }}">
                                </label>
                                @if ($itemError($field))<p class="mt-[4px] pl-[4px] text-[12px] font-semibold text-danger">{{ $itemError($field) }}</p>@endif
                            </div>
                        @endforeach
                    </div>
                    <fieldset class="rounded-[10px] border border-field px-[14px] py-[10px]">
                        <legend class="px-[4px] text-[14px] font-bold">Programs that hand out this item</legend>
                        <div class="grid grid-cols-2 gap-x-[12px] gap-y-[6px] text-[14px] font-medium">
                            @foreach (\App\Models\Intervention::with('inventoryItem:id,name')->orderBy('source')->orderBy('name')->get() as $program)
                                <label class="flex cursor-pointer items-center gap-[8px]">
                                    <input type="checkbox" name="interventions[]" value="{{ $program->id }}" x-model="form.interventions" class="size-[16px] accent-brand">
                                    <span>{{ $program->sourcedName() }}</span>
                                    @if ($program->inventoryItem)<span class="text-[12px] text-muted">(now: {{ $program->inventoryItem->name }})</span>@endif
                                </label>
                            @endforeach
                        </div>
                        @if ($errors->item->has('interventions.*'))<p class="mt-[4px] text-[12px] font-semibold text-danger">{{ $errors->item->first('interventions.*') }}</p>@endif
                    </fieldset>
                    <div class="flex items-center gap-[12px]">
                        <button type="submit" class="bg-brand-bar gradient-button h-[44px] flex-1 rounded-[50px] text-[16px] font-bold text-white" x-text="form.id ? 'Save Changes' : 'Add Item'">Add Item</button>
                        <button type="button" x-show="form.id" @click="form = { ...blank }" class="text-[14px] font-medium text-muted hover:text-brand">New item instead</button>
                    </div>
                </form>
            </div>
        </x-ui.modal>
    @endif
</x-layouts.app>
