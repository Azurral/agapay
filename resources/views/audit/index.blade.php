<x-layouts.app title="AUDIT TRAIL">
    <x-ui.card title="Audit Trail">
        <form method="GET" class="mt-[18.5px] grid grid-cols-[297px_250px_427px_1fr] items-center pl-[4.5px]">
            <x-ui.pill-input name="timestamp" placeholder="Search timestamp..." :value="request('timestamp')" width="223" />
            <x-ui.pill-input name="user" placeholder="Search by user..." :value="request('user')" width="223" />
            <x-ui.pill-select name="action" label="Action" :options="['' => 'All'] + $actions" :selected="request('action')" width="300" />
            <span class="text-[14px] font-medium text-muted">Record Affected</span>
            <button type="submit" class="sr-only">Apply filters</button>
        </form>

        <div class="divider mt-[15px]"></div>

        @if ($invalidDate)
            <p class="mt-[14px] pl-[16.5px] text-[14px] font-bold text-danger">Enter a date such as Jul 17, 2026.</p>
        @endif

        <table class="mt-[4px] w-full table-fixed text-left text-[14px] font-bold leading-[21px]">
            <colgroup><col class="w-[302px]"><col class="w-[250px]"><col class="w-[410px]"><col></colgroup>
            <thead class="sr-only"><tr><th>Timestamp</th><th>User</th><th>Action</th><th>Record Affected</th></tr></thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr class="h-[45px]">
                        <td class="pl-[16.5px]">{{ $log->created_at->format('M j, Y - g:i A') }}</td>
                        <td>{{ $log->user?->username ?? 'System' }}</td>
                        <td>{{ $log->action }}</td>
                        <td>{{ $log->record_label }}</td>
                    </tr>
                @empty
                    @unless ($invalidDate)
                        <tr><td colspan="4" class="py-[12px] pl-[16.5px]">No activity matches these filters.</td></tr>
                    @endunless
                @endforelse
            </tbody>
        </table>

        @if ($logs->hasPages())
            <div class="mt-[12px] text-[14px]">{{ $logs->links() }}</div>
        @endif
    </x-ui.card>
</x-layouts.app>
