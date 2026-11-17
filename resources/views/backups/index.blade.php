<x-layouts.app title="BACKUPS">
    @if (session('status'))
        <p class="flash mb-[14px] rounded-[10px] border-[1.5px] border-ok bg-white px-[16px] py-[10px] text-[14px] font-bold" role="status">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="mb-[14px] rounded-[10px] border-[1.5px] border-bad bg-white px-[16px] py-[10px] text-[14px] font-bold text-danger" role="alert">{{ session('error') }}</p>
    @endif

    <x-ui.card title="Backups">
        <x-slot:actions>
            <form method="POST" action="{{ route('backups.store') }}" x-data="{ busy: false }" @submit="busy = true">
                @csrf
                <button type="submit" :disabled="busy"
                    class="bg-brand-button gradient-button h-[36px] rounded-[50px] px-[22px] text-[14px] font-bold text-white disabled:opacity-60">
                    <span x-show="! busy">Back up now</span><span x-cloak x-show="busy">Backing up&hellip;</span>
                </button>
            </form>
        </x-slot:actions>

        <div class="mt-[14px] grid grid-cols-3 gap-[12px] text-[13px] leading-[19px]">
            <div class="rounded-[14px] border-[1.5px] border-black/10 p-[12px]">
                <p class="font-bold">Copy 1 &middot; Live database</p>
                <p class="text-arrow">The records everyone works on, on the cloud server.</p>
            </div>
            <div class="rounded-[14px] border-[1.5px] border-black/10 p-[12px]">
                <p class="font-bold">Copy 2 &middot; On the server</p>
                <p class="text-arrow">An encrypted backup every day at noon on the server's own storage; the newest 30 days are kept.</p>
            </div>
            <div class="rounded-[14px] border-[1.5px] p-[12px] {{ $offsite ? 'border-black/10' : 'border-bad' }}">
                <p class="font-bold">Copy 3 &middot; Off-site storage</p>
                <p class="text-arrow">
                    @if ($offsite)
                        The same encrypted backup at a second storage provider, so one failure cannot lose the records.
                    @else
                        Off-site storage is not set up yet (BACKUP_OFFSITE_* settings).
                    @endif
                </p>
            </div>
        </div>
        <p class="mt-[10px] text-[12px] text-arrow">3-2-1 rule: 3 copies, on 2 different storage systems, 1 kept off-site. Every backup is an
            Encrypted zip (AES-256): without the backup password nobody, including the storage provider, can open it.</p>

        <div class="divider mt-[14px]"></div>

        <table class="mt-[4px] w-full table-fixed text-left text-[14px] leading-[21px]">
            <colgroup><col class="w-[360px]"><col class="w-[220px]"><col class="w-[110px]"><col></colgroup>
            <thead><tr class="h-[40px] text-[12px] text-arrow"><th class="pl-[8px]">Backup</th><th>Made on</th><th>Size</th><th>Kept at (click to download)</th></tr></thead>
            <tbody>
                @forelse ($backups as $backup)
                    <tr class="h-[44px] border-t border-black/5 font-bold">
                        <td class="truncate pl-[8px]">{{ $backup['name'] }}</td>
                        <td>{{ $backup['date']?->format('M j, Y - g:i A') }}</td>
                        <td>{{ \Illuminate\Support\Number::fileSize($backup['size'], 1) }}</td>
                        <td class="flex h-[44px] items-center gap-[8px]">
                            @foreach ($backup['copies'] as $copy)
                                <a href="{{ route('backups.download', [$copy['disk'], $copy['name']]) }}"
                                    class="rounded-full border-[1.5px] border-brand-soft px-[12px] py-[2px] text-[12px] text-brand hover:bg-canvas">{{ $copy['location'] }}</a>
                            @endforeach
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-[12px] pl-[8px]">No backups yet. Press Back up now to make the first one.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.card>
</x-layouts.app>
