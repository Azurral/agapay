<?php

use Illuminate\Pagination\LengthAwarePaginator;

// User: after the "..." only the last page shows ("1 2 3 … 14", not "… 13 14"), and before it only the first ("1 …").

function pagesShown(int $current, int $last = 14): array
{
    $paginator = new LengthAwarePaginator(range(1, 10), $last * 10, 10, $current, ['path' => '/list']);
    preg_match_all('/aria-label="Go to page (\d+)"|aria-current="page"[^>]*>(\d+)</', (string) $paginator->links('pagination.agapay'), $m);

    return array_map('intval', array_map(fn ($a, $b) => $a !== '' ? $a : $b, $m[1], $m[2]));
}

it('shows one last page after the dots', function () {
    expect(pagesShown(1))->toBe([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 14]);
});

it('shows one first page before the dots at the end', function () {
    expect(pagesShown(14))->toBe([1, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14]);
});

it('shows one page on each side of the dots in the middle', function () {
    expect(pagesShown(30, 60))->toBe([1, 27, 28, 29, 30, 31, 32, 33, 60]);
});

it('shows every page when there are few', function () {
    expect(pagesShown(2, 5))->toBe([1, 2, 3, 4, 5]);
});
