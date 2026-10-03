{{-- Light pagination matching the app: white pills, field-grey border, brand indigo for the current page. --}}
@if ($paginator->hasPages())
    @php
        $shape = 'flex h-[36px] min-w-[36px] items-center justify-center rounded-[10px] border px-[10px] text-[14px] font-bold';
        $link = $shape.' border-field bg-white text-ink transition-colors hover:border-brand-soft hover:bg-[#efeaff] hover:text-brand';
        $off = $shape.' border-field bg-white cursor-not-allowed text-muted';
        $current = $shape.' border-brand bg-brand text-white';
    @endphp
    <nav data-agapay-pagination role="navigation" aria-label="Pagination" class="flex items-center justify-between gap-[12px]">
        <p class="text-[13px] font-medium text-muted">
            Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}
        </p>
        <div class="flex items-center gap-[6px]">
            @if ($paginator->onFirstPage())
                <span class="{{ $off }}" aria-disabled="true" aria-label="Previous page">&lsaquo;</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $link }}" aria-label="Previous page">&lsaquo;</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="{{ $off }}" aria-disabled="true">{{ $element }}</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" class="{{ $current }}">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="{{ $link }}" aria-label="Go to page {{ $page }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $link }}" aria-label="Next page">&rsaquo;</a>
            @else
                <span class="{{ $off }}" aria-disabled="true" aria-label="Next page">&rsaquo;</span>
            @endif
        </div>
    </nav>
@endif
