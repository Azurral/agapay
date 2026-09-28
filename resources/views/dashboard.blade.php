<x-layouts.app title="HOME DASHBOARD">
    @if ($showsEncodingQueue)
        <x-ui.card title="Pending Encoding Queue">
            <div class="mt-[18px] grid grid-cols-[287px_250px_380px_1fr_155px] text-[14px] font-medium leading-[20px] text-muted">
                <span>Name</span><span>Source</span><span>Issue</span><span>Date Added</span><span class="text-center">Status</span>
            </div>
            <div class="divider mt-[13px]"></div>
            <p class="py-[14px] text-[14px] font-bold">No records are waiting to be encoded.</p>
        </x-ui.card>
    @else
        <x-ui.card title="All Beneficiaries">
            <div class="mt-[18px] grid grid-cols-[310px_261px_236px_234px_1fr_155px] text-[14px] font-medium leading-[20px] text-muted">
                <span>Name</span><span>RSBSA Number</span><span>Barangay</span><span>Household</span><span>Intervention</span><span class="text-center">Status</span>
            </div>
            <div class="divider mt-[13px]"></div>
            <p class="py-[14px] text-[14px] font-bold">No beneficiaries recorded yet.</p>
        </x-ui.card>
    @endif
</x-layouts.app>
