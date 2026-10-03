<?php

namespace App\Support;

use App\Models\Barangay;
use App\Models\DamageReport;
use App\Models\Disaster;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The Disaster / Barangay / Status filters of the damage list (Figma 423:125–130), shared by the list,
 * the Excel export and the PDF so all three always show the same reports. Unknown values fall back to the default.
 */
final readonly class DamageReportFilters
{
    public const ALL = 'all';

    public const ARCHIVED = 'archived';

    /**
     * @param  Collection<int, Disaster>  $disasters  newest event first
     * @param  Collection<int, string>  $barangays  id => name
     */
    public function __construct(
        public Collection $disasters,
        public Collection $barangays,
        public ?int $disasterId,
        public ?int $barangayId,
        public string $status,
        public bool $canSeeArchived,
    ) {}

    public static function fromRequest(Request $request, ?User $user = null): self
    {
        $user ??= $request->user();
        $disasters = Disaster::orderByDesc('occurred_on')->orderByDesc('id')->get();
        $barangays = Barangay::orderBy('name')->pluck('name', 'id');
        $canSeeArchived = $user->can('damage.configure');

        $disaster = $request->query('disaster');
        $disasterId = match (true) {
            $disaster === self::ALL => null,
            is_string($disaster) && ctype_digit($disaster) && $disasters->contains('id', (int) $disaster) => (int) $disaster,
            default => $disasters->first()?->id,   // the most recent event
        };

        $barangay = $request->query('barangay');
        $barangayId = is_string($barangay) && ctype_digit($barangay) && $barangays->has((int) $barangay) ? (int) $barangay : null;

        $status = $request->query('status');
        $statuses = [DamageReport::FOR_VALIDATION, DamageReport::VALIDATED, ...($canSeeArchived ? [self::ARCHIVED] : [])];
        $status = is_string($status) && in_array($status, $statuses, true) ? $status : self::ALL;

        return new self($disasters, $barangays, $disasterId, $barangayId, $status, $canSeeArchived);
    }

    /** @return Builder<DamageReport> */
    public function query(): Builder
    {
        return ($this->status === self::ARCHIVED ? DamageReport::onlyTrashed() : DamageReport::query())
            ->when($this->disasterId, fn (Builder $query) => $query->where('disaster_id', $this->disasterId))
            ->when($this->barangayId, fn (Builder $query) => $query->where('barangay_id', $this->barangayId))
            ->when(in_array($this->status, [DamageReport::FOR_VALIDATION, DamageReport::VALIDATED], true),
                fn (Builder $query) => $query->where('status', $this->status));
    }

    public function disaster(): ?Disaster
    {
        return $this->disasters->firstWhere('id', $this->disasterId);
    }

    /** @return array<string, string> status value => label for the Status filter */
    public function statusOptions(): array
    {
        return [self::ALL => 'All', ...DamageReport::STATUS_LABELS, ...($this->canSeeArchived ? [self::ARCHIVED => 'Archived'] : [])];
    }

    /** @return array{disaster: string, barangay: string, status: string} the filters as query parameters */
    public function toQuery(): array
    {
        return [
            'disaster' => (string) ($this->disasterId ?? self::ALL),
            'barangay' => (string) ($this->barangayId ?? self::ALL),
            'status' => $this->status,
        ];
    }

    /** "Typhoon Cristina (Jul 20, 2026) · Barangay: All · Status: All" for the PDF and audit rows. */
    public function describe(): string
    {
        $disaster = $this->disaster();

        return ($disaster ? "{$disaster->name} ({$disaster->occurred_on->format('M j, Y')})" : 'All disasters')
            .' · Barangay: '.($this->barangayId !== null ? $this->barangays[$this->barangayId] : 'All')
            .' · Status: '.$this->statusOptions()[$this->status];
    }
}
