@props(['details', 'overview'])
@php
    use App\Models\InventoryItem;
    use Illuminate\Support\Str;
    $pct = fn (int $part, int $whole) => $whole > 0 ? (int) round($part / $whole * 100).'%' : '–';
    $cycle = $overview['tiles']['cycle'] ?? 'this cycle';
    // A program's own list (DA list, or the LGU program records), filtered to that program.
    $programUrl = fn (array $p) => $p['source'] === 'lgu'
        ? route('interventions.lgu', ['tab' => 'records', 'intervention' => $p['id']])
        : route('interventions.da', ['intervention' => $p['id']]);
    $th = 'py-[6px] font-medium';
@endphp
{{-- The details windows of the home page (App\Support\DashboardDetails): opened from the tiles and from the chart rows.
     "dash-pick-*" events choose which barangay, program or item a window shows. --}}

@isset($details['barangays'])
    @php($all = array_sum(array_column($details['barangays'], 'farmers')))
    <x-ui.modal name="dash-farmers" title="Registered farmers" width="wide" top="40">
        <p class="-mt-[12px] text-[13px] text-muted">All {{ number_format($all) }} farmers in AGAPAY by barangay. Click a barangay for its details.</p>
        <div class="mt-[12px] grid max-h-[calc(var(--app-vh,100vh)-190px)] grid-cols-[2fr_1fr] gap-[22px] overflow-auto">
            <table class="w-full text-left text-[13px]">
                <thead class="sticky top-0 bg-white text-muted"><tr><th class="{{ $th }}">Barangay</th><th class="text-right">Farmers</th><th class="text-right">Share</th><th class="text-right">RSBSA-registered</th><th class="text-right">Records in {{ $cycle }}</th><th class="text-right">Claimed</th></tr></thead>
                <tbody>
                    @foreach ($details['barangays'] as $b)
                        <tr class="border-t border-black/5">
                            <td class="py-[5px]"><button type="button" class="font-bold hover:text-brand hover:underline" data-dash-pick="barangay"
                                    @click="open = false; $dispatch('dash-pick-barangay', {{ $b['id'] }}); $dispatch('open-modal', 'dash-barangay')">{{ $b['name'] }}</button></td>
                            <td class="text-right tabular-nums font-bold">{{ number_format($b['farmers']) }}</td>
                            <td class="text-right tabular-nums">{{ $pct($b['farmers'], $all) }}</td>
                            <td class="text-right tabular-nums">{{ number_format($b['rsbsa']) }}</td>
                            <td class="text-right tabular-nums">{{ number_format($b['records']) }}</td>
                            <td class="text-right tabular-nums">{{ number_format($b['claimed']) }} <span class="text-muted">({{ $pct($b['claimed'], $b['records']) }})</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="flex flex-col gap-[16px] text-[13px]">
                <div>
                    <p class="text-[14px] font-bold">By main crop</p>
                    <ul class="mt-[6px] flex flex-col gap-[4px]">
                        @forelse ($details['crops'] as $c)
                            <li class="flex justify-between border-t border-black/5 pt-[4px]"><span class="font-bold">{{ $c['name'] }}</span><span class="tabular-nums">{{ number_format($c['value']) }} <span class="text-muted">({{ $pct($c['value'], $all) }})</span></span></li>
                        @empty
                            <li class="text-muted">No crops recorded.</li>
                        @endforelse
                    </ul>
                </div>
                <div>
                    <p class="text-[14px] font-bold">Verification</p>
                    <ul class="mt-[6px] flex flex-col gap-[4px]">
                        @foreach (['verified' => 'Verified', 'pending' => 'Walk-ins waiting for approval', 'rejected' => 'Rejected'] as $key => $label)
                            <li class="flex justify-between border-t border-black/5 pt-[4px]"><span class="font-bold">{{ $label }}</span><span class="tabular-nums">{{ number_format($details['verification'][$key] ?? 0) }}</span></li>
                        @endforeach
                    </ul>
                </div>
                @can('beneficiaries.manage')
                    <a href="{{ route('beneficiaries.index') }}" class="font-bold text-brand hover:underline">Open Beneficiary Profiles &rsaquo;</a>
                @endcan
            </div>
        </div>
    </x-ui.modal>

    <x-ui.modal name="dash-barangay" title="Barangay details" width="760" top="60">
        <div x-data="{ id: null }" x-on:dash-pick-barangay.window="id = $event.detail" class="-mt-[10px] text-[13px]">
            @foreach ($details['barangays'] as $b)
                <div x-show="id === {{ $b['id'] }}" x-cloak data-dash-barangay="{{ $b['name'] }}">
                    <p class="text-[20px] font-bold">{{ $b['name'] }}</p>
                    <div class="mt-[10px] grid grid-cols-4 gap-[10px]">
                        @foreach ([['Farmers', number_format($b['farmers'])], ['RSBSA-registered', number_format($b['rsbsa'])], ['Records in '.$cycle, number_format($b['records'])], ['Claimed', number_format($b['claimed']).' ('.$pct($b['claimed'], $b['records']).')']] as [$label, $value])
                            <div class="rounded-[12px] bg-[#f7f7f9] px-[12px] py-[8px]"><p class="text-muted">{{ $label }}</p><p class="text-[18px] font-bold tabular-nums">{{ $value }}</p></div>
                        @endforeach
                    </div>
                    <p class="mt-[14px] text-[14px] font-bold">Farmers by main crop</p>
                    <ul class="mt-[6px] flex flex-col gap-[5px]">
                        @php($top = max(1, $b['crops'][0]['value'] ?? 1))
                        @forelse ($b['crops'] as $c)
                            <li class="grid grid-cols-[130px_1fr_60px] items-center gap-[10px]"><span class="font-bold">{{ $c['name'] }}</span>
                                <span class="h-[10px]"><span class="chart-seg chart-claimed block h-full" style="width: {{ max($c['value'] / $top * 100, 2) }}%"></span></span>
                                <span class="text-right tabular-nums">{{ $c['value'] }}</span></li>
                        @empty
                            <li class="text-muted">No farmers yet.</li>
                        @endforelse
                    </ul>
                    @can('beneficiaries.manage')
                        <a href="{{ route('beneficiaries.index', ['barangay' => $b['id']]) }}" class="mt-[12px] inline-block font-bold text-brand hover:underline">See the farmers of {{ $b['name'] }} &rsaquo;</a>
                    @endcan
                </div>
            @endforeach
        </div>
    </x-ui.modal>
@endisset

@isset($details['programs'])
    @php($sum = fn (string $k) => array_sum(array_column($details['programs'], $k)))
    <x-ui.modal name="dash-releases" title="Released in {{ $cycle }}" width="wide" top="40">
        <p class="-mt-[12px] text-[13px] text-muted">Every program record of the current cycle. <b>Waiting</b>: can still be claimed. <b>Not claimable</b>: e.g. OFW, bedridden, deceased, or forwarded. Click a program for its barangays.</p>
        <div class="mt-[12px] max-h-[calc(var(--app-vh,100vh)-190px)] overflow-auto">
            <table class="w-full text-left text-[13px]">
                <thead class="sticky top-0 bg-white text-muted"><tr><th class="{{ $th }}">Program</th><th>Kind</th><th class="text-right">Claimed</th><th class="text-right">Waiting</th><th class="text-right">Not claimable</th><th class="text-right">Total</th><th class="text-right">Claimed %</th></tr></thead>
                <tbody>
                    @forelse ($details['programs'] as $p)
                        <tr class="border-t border-black/5">
                            <td class="py-[5px]"><button type="button" class="text-left font-bold hover:text-brand hover:underline" data-dash-pick="program"
                                    @click="open = false; $dispatch('dash-pick-program', {{ $p['id'] }}); $dispatch('open-modal', 'dash-program')">{{ $p['name'] }}</button></td>
                            <td>{{ $p['kind'] }}</td>
                            <td class="text-right tabular-nums">{{ number_format($p['claimed']) }}</td><td class="text-right tabular-nums">{{ number_format($p['waiting']) }}</td>
                            <td class="text-right tabular-nums">{{ number_format($p['not_claimable']) }}</td><td class="text-right tabular-nums font-bold">{{ number_format($p['total']) }}</td>
                            <td class="text-right tabular-nums">{{ $pct($p['claimed'], $p['total']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-[10px] font-bold">No program records in the current cycle yet.</td></tr>
                    @endforelse
                </tbody>
                @if ($details['programs'] !== [])
                    <tfoot><tr class="border-t-2 border-black/10 font-bold"><td class="py-[6px]">All programs</td><td></td>
                        <td class="text-right tabular-nums">{{ number_format($sum('claimed')) }}</td><td class="text-right tabular-nums">{{ number_format($sum('waiting')) }}</td>
                        <td class="text-right tabular-nums">{{ number_format($sum('not_claimable')) }}</td><td class="text-right tabular-nums">{{ number_format($sum('total')) }}</td>
                        <td class="text-right tabular-nums">{{ $pct($sum('claimed'), $sum('total')) }}</td></tr></tfoot>
                @endif
            </table>
        </div>
    </x-ui.modal>

    <x-ui.modal name="dash-program" title="Program details" width="wide" top="40">
        <div x-data="{ id: null }" x-on:dash-pick-program.window="id = $event.detail" class="-mt-[10px] text-[13px]">
            @foreach ($details['programs'] as $p)
                <div x-show="id === {{ $p['id'] }}" x-cloak data-dash-program="{{ $p['name'] }}">
                    <p class="text-[20px] font-bold">{{ $p['name'] }} <span class="text-[13px] font-medium text-muted">&middot; {{ $p['kind'] }} &middot; {{ $cycle }}</span></p>
                    <p class="mt-[4px]">{{ number_format($p['claimed']) }} claimed, {{ number_format($p['waiting']) }} waiting, {{ number_format($p['not_claimable']) }} not claimable, of {{ number_format($p['total']) }} records ({{ $pct($p['claimed'], $p['total']) }} claimed).</p>
                    <div class="mt-[10px] max-h-[calc(var(--app-vh,100vh)-260px)] overflow-auto">
                        <table class="w-full text-left">
                            <thead class="sticky top-0 bg-white text-muted"><tr><th class="{{ $th }}">Barangay</th><th class="w-[40%]">Claimed / waiting / not claimable</th><th class="text-right">Claimed</th><th class="text-right">Waiting</th><th class="text-right">Not claimable</th><th class="text-right">Total</th></tr></thead>
                            <tbody>
                                @foreach ($p['barangays'] as $b)
                                    <tr class="border-t border-black/5">
                                        <td class="py-[5px] font-bold">{{ $b['name'] }}</td>
                                        <td><span class="flex h-[10px] gap-[2px]">
                                            @foreach ([['claimed', 'chart-claimed'], ['waiting', 'chart-waiting'], ['not_claimable', 'chart-notclaimable']] as [$key, $class])
                                                @if ($b[$key] > 0)<span class="chart-seg {{ $class }}" style="flex: {{ $b[$key] }}"></span>@endif
                                            @endforeach
                                        </span></td>
                                        <td class="text-right tabular-nums">{{ $b['claimed'] }}</td><td class="text-right tabular-nums">{{ $b['waiting'] }}</td>
                                        <td class="text-right tabular-nums">{{ $b['not_claimable'] }}</td><td class="text-right tabular-nums font-bold">{{ $b['total'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @can('interventions.view')
                        <a href="{{ $programUrl($p) }}" class="mt-[12px] inline-block font-bold text-brand hover:underline">Open the records of this program &rsaquo;</a>
                    @endcan
                </div>
            @endforeach
        </div>
    </x-ui.modal>
@endisset

@isset($details['requests'])
    <x-ui.modal name="dash-requests" title="Pending assistance requests" width="wide" top="40">
        <p class="-mt-[12px] text-[13px] text-muted">{{ number_format($details['requests']['total']) }} {{ Str::plural('request', $details['requests']['total']) }} waiting for a decision, oldest first{{ $details['requests']['total'] > \App\Support\DashboardDetails::REQUESTS ? ' (the first '.\App\Support\DashboardDetails::REQUESTS.' shown)' : '' }}.</p>
        <div class="mt-[12px] max-h-[calc(var(--app-vh,100vh)-190px)] overflow-auto">
            <table class="w-full text-left text-[13px]">
                <thead class="sticky top-0 bg-white text-muted"><tr><th class="{{ $th }}">Farmer</th><th>Barangay</th><th>Program</th><th>Crisis</th><th>Crop</th><th>Reason</th><th>Asked on</th></tr></thead>
                <tbody>
                    @forelse ($details['requests']['rows'] as $r)
                        <tr class="border-t border-black/5" data-dash-request>
                            <td class="py-[5px] font-bold">
                                @if ($r->beneficiary)<a href="{{ route('beneficiaries.show', $r->beneficiary) }}" class="hover:text-brand hover:underline">{{ $r->beneficiary->fullName() }}</a>@endif
                            </td>
                            <td>{{ $r->beneficiary?->barangay?->name }}</td>
                            <td>{{ $r->intervention ? strtoupper($r->intervention->source).' - '.$r->intervention->name : '' }}</td>
                            <td>{{ $r->disaster?->name ?? '–' }}</td><td>{{ $r->crop?->name ?? '–' }}</td>
                            <td class="max-w-[280px] truncate" title="{{ $r->reason }}">{{ $r->reason ?? '–' }}</td>
                            <td class="tabular-nums">{{ $r->created_at?->format('M j, Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-[10px] font-bold">No requests are waiting.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @can('interventions.view')
            <a href="{{ route('interventions.lgu', ['tab' => 'requests']) }}" class="mt-[12px] inline-block text-[13px] font-bold text-brand hover:underline">Open the LGU Requests tab to decide &rsaquo;</a>
        @endcan
    </x-ui.modal>
@endisset

@isset($details['stock'])
    <x-ui.modal name="dash-stock" title="Stock items" width="wide" top="40">
        <p class="-mt-[12px] text-[13px] text-muted">Every item, low ones first. Click an item for its programs and latest movements.</p>
        <div class="mt-[12px] max-h-[calc(var(--app-vh,100vh)-190px)] overflow-auto">
            <table class="w-full text-left text-[13px]">
                <thead class="sticky top-0 bg-white text-muted"><tr><th class="{{ $th }}">Item</th><th class="text-right">Received</th><th class="text-right">Released</th><th class="text-right">Balance</th><th class="text-right">Low at</th><th class="pl-[16px]">State</th></tr></thead>
                <tbody>
                    @forelse ($details['stock'] as $s)
                        <tr class="border-t border-black/5">
                            <td class="py-[5px]"><button type="button" class="font-bold hover:text-brand hover:underline" data-dash-pick="item"
                                    @click="open = false; $dispatch('dash-pick-item', {{ $s['id'] }}); $dispatch('open-modal', 'dash-item')">{{ $s['name'] }}</button></td>
                            <td class="text-right tabular-nums">{{ InventoryItem::quantity($s['in']) }}</td><td class="text-right tabular-nums">{{ InventoryItem::quantity($s['out']) }}</td>
                            <td class="text-right tabular-nums font-bold">{{ InventoryItem::quantity($s['balance']) }} {{ $s['unit'] }}</td>
                            <td class="text-right tabular-nums">{{ InventoryItem::quantity($s['threshold']) }}</td>
                            <td class="pl-[16px] {{ $s['low'] ? 'font-bold text-danger' : '' }}">{{ $s['low'] ? '⚠ Low' : 'OK' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-[10px] font-bold">No stock items yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.modal>

    <x-ui.modal name="dash-item" title="Stock item" width="760" top="60">
        <div x-data="{ id: null }" x-on:dash-pick-item.window="id = $event.detail" class="-mt-[10px] text-[13px]">
            @foreach ($details['stock'] as $s)
                <div x-show="id === {{ $s['id'] }}" x-cloak data-dash-item="{{ $s['name'] }}">
                    <p class="text-[20px] font-bold">{{ $s['name'] }} @if ($s['low'])<span class="text-[13px] text-danger">⚠ Low</span>@endif</p>
                    <div class="mt-[10px] grid grid-cols-4 gap-[10px]">
                        @foreach ([['Received', $s['in']], ['Released', $s['out']], ['Balance', $s['balance']], ['Low at', $s['threshold']]] as [$label, $value])
                            <div class="rounded-[12px] bg-[#f7f7f9] px-[12px] py-[8px]"><p class="text-muted">{{ $label }}</p><p class="text-[18px] font-bold tabular-nums">{{ InventoryItem::quantity($value) }}</p></div>
                        @endforeach
                    </div>
                    <p class="mt-[12px]"><b>Programs that release it:</b> {{ $s['programs'] !== [] ? implode(', ', $s['programs']) : 'none' }}</p>
                    <p class="mt-[12px] text-[14px] font-bold">Latest movements</p>
                    <ul class="mt-[6px] flex flex-col gap-[4px]">
                        @forelse ($s['movements'] as $m)
                            <li class="flex gap-[12px] border-t border-black/5 pt-[4px]"><span class="w-[95px] tabular-nums text-muted">{{ $m['date'] }}</span>
                                <span class="w-[90px] font-bold tabular-nums {{ $m['in'] ? 'text-[#1f7a35]' : '' }}">{{ $m['in'] ? '+' : '−' }}{{ InventoryItem::quantity($m['quantity']) }}</span>
                                <span class="truncate">{{ $m['notes'] ?? ($m['in'] ? 'Stock in' : 'Released') }}</span></li>
                        @empty
                            <li class="text-muted">No movements yet.</li>
                        @endforelse
                    </ul>
                    <a href="{{ route('inventory.index') }}" class="mt-[12px] inline-block font-bold text-brand hover:underline">Open Inventory &rsaquo;</a>
                </div>
            @endforeach
        </div>
    </x-ui.modal>
@endisset
