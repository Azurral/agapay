<?php

namespace App\Console\Commands;

use App\Models\Beneficiary;
use Database\Seeders\BeneficiarySeeder;
use Illuminate\Console\Command;

/**
 * Demo copies only (AGAPAY_SAMPLE_DATA=true): sample farmers made before farm locations existed get a made-up farm
 * position near the centre of their barangay, so the photo GPS check can be shown. Existing locations stay as they are.
 */
class DemoFarmLocationsCommand extends Command
{
    protected $signature = 'agapay:demo-farm-locations {--force : Required: confirms this is a demo copy with sample records}';

    protected $description = 'Demo copy: give sample farmers without a farm location a made-up one in their barangay';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->error('This changes farm locations. Run it only on a demo copy with sample records, with --force.');

            return self::FAILURE;
        }

        $updated = 0;
        Beneficiary::withTrashed()->with('barangay:id,name')->whereNull('farm_latitude')->orderBy('id')
            ->each(function (Beneficiary $farmer) use (&$updated) {
                [$lat, $lng] = BeneficiarySeeder::FARMS[$farmer->barangay?->name] ?? BeneficiarySeeder::FARMS['Poblacion'];
                $step = $farmer->id % 12;
                $farmer->forceFill(['farm_latitude' => round($lat + $step * 0.0013, 6), 'farm_longitude' => round($lng + $step * 0.0009, 6)])->saveQuietly();
                $updated++;
            });

        $this->info("Farm locations added for {$updated} sample farmers.");

        return self::SUCCESS;
    }
}
