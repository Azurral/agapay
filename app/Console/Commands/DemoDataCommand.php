<?php

namespace App\Console\Commands;

use App\Models\AssistanceRequest;
use App\Models\Barangay;
use App\Models\Beneficiary;
use App\Models\Crop;
use App\Models\DamageReport;
use App\Models\Disaster;
use App\Models\DistributionCycle;
use App\Models\Intervention;
use App\Models\InterventionRecord;
use App\Models\User;
use App\Services\DamageCalculator;
use App\Services\HouseholdService;
use Database\Seeders\BeneficiarySeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Demo copies only: about 120 made-up farmers across every barangay, every field filled (address parts, contact,
 * RSBSA No., farm area, crop, farm GPS, valid ID, consent), with program records in two cycles, claims and assistance
 * requests, so lists, charts and reports look like a working office. Runs once; the people are fictitious.
 */
class DemoDataCommand extends Command
{
    protected $signature = 'agapay:demo-data {--force : Required: confirms this is a demo copy with sample records} {--count=120 : How many demo farmers there should be in all (e.g. 1000 or 5000 for the scalability test)}';

    protected $description = 'Demo copy: add made-up farmers with complete records, up to --count (runs once per count)';

    public const COUNT = 120;

    private const FIRST = ['Mario', 'Rosalinda', 'Benito', 'Corazon', 'Danilo', 'Evelyn', 'Fernando', 'Gina', 'Hilario', 'Imelda',
        'Jose', 'Katherine', 'Leonardo', 'Marites', 'Nestor', 'Ofelia', 'Pablo', 'Querubin', 'Ramon', 'Soledad', 'Teodoro',
        'Ursula', 'Victor', 'Wilma', 'Ximena', 'Yolanda', 'Zaldy', 'Arnel', 'Bernadette', 'Cesar'];

    private const LAST = ['Agpawa', 'Bacwaden', 'Chalsiw', 'Dumalo', 'Fagyan', 'Gaongen', 'Kiwang', 'Lumiwes', 'Mangili', 'Ngalob',
        'Otom', 'Pagteng', 'Sagudang', 'Tayaban', 'Wakit', 'Bangsoy', 'Fomagas', 'Kinaw', 'Lagman', 'Sakai'];

    private const CROPS = ['Rice', 'Corn', 'Cabbage', 'Sweet Potato', 'Carrots', 'Beans', 'Potato'];

    private const STREETS = ['Rizal St.', 'Mabini St.', 'Bonifacio St.', 'Luna St.', 'Del Pilar St.', 'Session Rd.'];

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->error('This adds made-up farmers. Run it only on a demo copy with sample records, with --force.');

            return self::FAILURE;
        }
        $target = max(0, (int) $this->option('count'));
        $existing = Beneficiary::withTrashed()->where('source', 'demo')->count();
        if ($existing >= $target) {
            $this->info("The {$existing} demo farmers are already there; nothing was added.");

            return self::SUCCESS;
        }

        $barangays = Barangay::orderBy('name')->get();
        $cycles = DistributionCycle::whereNotNull('schedule_date')->orderByDesc('schedule_date')->limit(2)->get();
        $programs = Intervention::where('is_active', true)->orderBy('id')->get();
        $admin = User::where('username', 'Admin_01')->value('id') ?? User::value('id');
        $crisis = Disaster::latest('occurred_on')->first();
        $crops = Crop::all()->keyBy('name');
        if ($barangays->isEmpty() || $cycles->isEmpty() || $programs->isEmpty()) {
            $this->error('Load the sample records first (barangays, programs and cycles).');

            return self::FAILURE;
        }

        // Added in batches of 250 per transaction, so 5,000 farmers do not hold one huge transaction open.
        foreach (array_chunk(range($existing, $target - 1), 250) as $batch) {
            DB::transaction(function () use ($batch, $barangays, $cycles, $programs, $admin, $crisis, $crops) {
                foreach ($batch as $i) {
                    $barangay = $barangays[$i % $barangays->count()];
                    [$lat, $lng] = BeneficiarySeeder::FARMS[$barangay->name] ?? [17.0890 + ($i % 16) * 0.004 - 0.03, 120.9775 + ($i % 7) * 0.004 - 0.012];
                    $hasRsbsa = $i % 6 !== 5;

                    $farmer = new Beneficiary;
                    $farmer->forceFill([
                        'first_name' => self::FIRST[$i % count(self::FIRST)],
                        'middle_name' => null,
                        'last_name' => self::LAST[intdiv($i, 3) % count(self::LAST)],
                        'birthdate' => now()->subYears(22 + ($i * 7) % 48)->subDays(($i * 37) % 360)->toDateString(),
                        'house_no' => (string) (10 + $i),
                        'street' => self::STREETS[$i % count(self::STREETS)],
                        'sitio' => 'Purok '.(1 + $i % 7),
                        'barangay_id' => $barangay->id,
                        'contact_number' => sprintf('0918-%03d-%04d', 400 + $i, 2000 + $i * 13),
                        'rsbsa_number' => $hasRsbsa ? sprintf('RSBSA-D%05d', $i) : null,
                        'rsbsa_status' => Beneficiary::RSBSA_REGISTERED,
                        'farm_area_ha' => round(0.25 + (($i * 13) % 30) / 10, 2),
                        'crop_type' => self::CROPS[$i % count(self::CROPS)],
                        'farm_latitude' => round($lat + (($i % 9) - 4) * 0.0011, 6),
                        'farm_longitude' => round($lng + (($i % 5) - 2) * 0.0013, 6),
                        'valid_id_type' => array_keys(Beneficiary::VALID_IDS)[$i % count(Beneficiary::VALID_IDS)],
                        'valid_id_number' => sprintf('ID-%06d', 100000 + $i * 7),
                        'verification_status' => Beneficiary::VERIFICATION_VERIFIED,
                        'source' => 'demo',
                        'created_by' => $admin,
                        'consent_confirmed_by' => $admin,
                        'consent_confirmed_at' => now(),
                    ]);
                    $farmer->address = Beneficiary::joinAddress($farmer->only(['house_no', 'street', 'sitio']));
                    HouseholdService::assign($farmer);
                    $farmer->saveQuietly();

                    $this->records($farmer, $i, $cycles, $programs, $admin);

                    // Every tenth farmer has a crop damage report from the latest crisis (no photo: made-up records).
                    $crop = $crops[$farmer->crop_type] ?? $crops->first();
                    if ($crisis && $crop && $i % 10 === 4) {
                        $total = round(0.2 + ($i % 9) / 10, 2);
                        $partial = round(($i % 4) / 10, 2);
                        DamageReport::forceCreate([
                            'disaster_id' => $crisis->id, 'beneficiary_id' => $farmer->id, 'barangay_id' => $farmer->barangay_id, 'crop_id' => $crop->id,
                            'farm_location' => $farmer->address, 'crop_stage' => array_keys(DamageReport::STAGES)[$i % count(DamageReport::STAGES)],
                            'total_area_ha' => $total, 'partial_area_ha' => $partial,
                            ...DamageCalculator::forCrop($crop, $total, $partial),
                            'latitude' => $farmer->farm_latitude, 'longitude' => $farmer->farm_longitude,
                            'status' => $i % 3 === 0 ? DamageReport::FOR_VALIDATION : DamageReport::VALIDATED,
                            'reported_by' => $admin, 'validated_by' => $i % 3 === 0 ? null : $admin, 'validated_at' => $i % 3 === 0 ? null : now(),
                        ]);
                    }

                    if ($crisis && $i % 8 === 3) {
                        $seedlings = $programs->first(fn ($p) => $p->source === Intervention::SOURCE_LGU && $p->name === 'Emergency Seedlings') ?? $programs->first();
                        AssistanceRequest::create([
                            'beneficiary_id' => $farmer->id, 'intervention_id' => $seedlings->id, 'disaster_id' => $crisis->id,
                            'quantity' => 5, 'reason' => "Crops damaged by the {$crisis->name}", 'status' => AssistanceRequest::PENDING, 'created_by' => $admin,
                        ]);
                    }
                }
            });
            $this->line('  '.(end($batch) + 1)." of {$target} demo farmers...");
        }

        $this->info('Added '.($target - $existing)." demo farmers with their records ({$target} in all).");

        return self::SUCCESS;
    }

    /** One or two program records per cycle; claimed only for programs without a stock item, so stock stays right. */
    private function records(Beneficiary $farmer, int $i, $cycles, $programs, ?int $admin): void
    {
        $eligible = $programs->filter(fn (Intervention $p) => $p->source === Intervention::SOURCE_LGU || $farmer->rsbsa_number)->values();
        foreach ($cycles as $c => $cycle) {
            $picks = $eligible->slice(($i + $c * 3) % max(1, $eligible->count() - 1), $i % 3 === 0 ? 2 : 1);
            foreach ($picks as $program) {
                $claimable = $program->inventory_item_id === null;
                $claimed = $claimable && ($c > 0 || $i % 2 === 0);
                InterventionRecord::forceCreate([
                    'beneficiary_id' => $farmer->id,
                    'intervention_id' => $program->id,
                    'distribution_cycle_id' => $cycle->id,
                    'quantity' => $program->isCash() ? 5000 : 1 + $i % 3,
                    'validation_status' => $i % 23 === 7 ? InterventionRecord::VALIDATION_OFW : InterventionRecord::VALIDATION_ELIGIBLE,
                    'claim_status' => $claimed && $i % 23 !== 7 ? InterventionRecord::CLAIM_CLAIMED : InterventionRecord::CLAIM_UNCLAIMED,
                    'date_distributed' => $claimed && $i % 23 !== 7 ? ($cycle->schedule_date ?? now())->toDateString() : null,
                    'claimed_by' => $claimed && $i % 23 !== 7 ? $admin : null,
                    'created_by' => $admin,
                ]);
            }
        }
    }
}
