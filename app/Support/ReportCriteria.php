<?php

namespace App\Support;

use App\Models\DistributionCycle;
use Carbon\CarbonImmutable;

/**
 * What a distribution report covers (spec rule 12). A cycle is always required: the paper restricts reports to
 * scheduled distribution events (§1.4.1). The date range narrows claimed records by distribution date.
 */
final readonly class ReportCriteria
{
    public const PROGRAMS = ['all' => 'All (DA & LGU)', 'da' => 'DA', 'lgu' => 'LGU'];

    public const FORMATS = ['pdf' => 'PDF', 'xlsx' => 'Excel (.xlsx)'];

    public function __construct(
        public DistributionCycle $cycle,
        public string $program = 'all',
        public ?CarbonImmutable $start = null,
        public ?CarbonImmutable $end = null,
        public string $format = 'pdf',
    ) {}

    /** @return array{cycle: string, program: string, dates: string, format: string} */
    public function labels(): array
    {
        return [
            'cycle' => $this->cycle->label,
            'program' => self::PROGRAMS[$this->program] ?? $this->program,
            'dates' => match (true) {
                $this->start && $this->end => $this->start->format('M j, Y').' – '.$this->end->format('M j, Y'),
                $this->start !== null => 'From '.$this->start->format('M j, Y'),
                $this->end !== null => 'Up to '.$this->end->format('M j, Y'),
                default => 'All dates',
            },
            'format' => self::FORMATS[$this->format] ?? $this->format,
        ];
    }

    /** "Cycle: 2026-Q3 Dry Season · Program: DA · Dates: All dates" */
    public function describe(): string
    {
        $labels = $this->labels();

        return "Cycle: {$labels['cycle']} · Program: {$labels['program']} · Dates: {$labels['dates']}";
    }
}
