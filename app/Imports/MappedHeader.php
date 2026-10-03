<?php

namespace App\Imports;

/** Where the header row is and which column holds which field. */
final readonly class MappedHeader
{
    /**
     * @param  array<int, string>  $columns  column index => field
     * @param  list<array{header: string, field: string}>  $corrections  headers that were not already the canonical label
     */
    public function __construct(
        public int $headerIndex,
        public array $columns,
        public array $corrections,
    ) {}

    public function has(string $field): bool
    {
        return in_array($field, $this->columns, true);
    }
}
