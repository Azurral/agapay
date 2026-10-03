<?php

use App\Models\Beneficiary;
use App\Models\DistributionCycle;
use App\Models\Intervention;
use App\Models\InterventionRecord;
use Database\Seeders\BarangaySeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\InterventionSeeder;

it('seeds the DA and LGU programs and the 2026 cycles', function () {
    $this->seed(InterventionSeeder::class);

    expect(Intervention::count())->toBe(11)
        ->and(Intervention::where('source', 'da')->count())->toBe(8)
        ->and(Intervention::where(['source' => 'lgu', 'name' => 'Complete Fertilizer'])->exists())->toBeTrue()
        ->and(Intervention::where('name', 'PAFF')->value('one_per_household'))->toBeTrue()
        ->and(Intervention::where('name', 'Emergency Seedlings')->value('allow_repeat'))->toBeTrue()
        ->and(DistributionCycle::count())->toBe(3)
        ->and(DistributionCycle::current()->code)->toBe('2026-Q3');
});

it('picks the current cycle: ongoing, else latest scheduled, else latest by code', function () {
    DistributionCycle::create(['code' => '2026-Q1', 'label' => 'Q1', 'status' => 'completed']);
    DistributionCycle::create(['code' => '2026-Q2', 'label' => 'Q2', 'status' => 'completed']);
    expect(DistributionCycle::current()->code)->toBe('2026-Q2');

    DistributionCycle::create(['code' => '2026-Q4', 'label' => 'Q4', 'status' => 'scheduled']);
    expect(DistributionCycle::current()->code)->toBe('2026-Q4');

    DistributionCycle::create(['code' => '2026-Q3', 'label' => 'Q3', 'status' => 'ongoing']);
    expect(DistributionCycle::current()->code)->toBe('2026-Q3');
});

it('labels validation and claim states as in Figma', function (string $status, string $label, string $tone) {
    expect(InterventionRecord::validationLabel($status))->toBe($label)
        ->and(InterventionRecord::validationTone($status))->toBe($tone);
})->with([
    ['pending', 'Validate', 'bad'],
    ['eligible', 'Eligible', 'ok'],
    ['ofw', 'OFW', 'ok'],
    ['bedridden', 'Bedridden', 'ok'],
    ['deceased', 'Deceased', 'ok'],
    ['inactive', 'Inactive', 'ok'],
    ['relocated', 'Relocated', 'ok'],
    ['duplicate', 'Duplicate', 'ok'],
]);

it('formats quantities, claim chips and DE labels', function () {
    $this->seed([BarangaySeeder::class, InterventionSeeder::class]);
    $seeds = Intervention::where(['source' => 'da', 'name' => 'Certified Rice Seeds'])->first();
    $oil = Intervention::where(['source' => 'da', 'name' => 'Organic Liquid Fertilizer'])->first();
    $paff = Intervention::where(['source' => 'da', 'name' => 'PAFF'])->first();
    $record = fn (Intervention $i, $qty, array $extra = []) => InterventionRecord::factory()->create(['intervention_id' => $i->id, 'quantity' => $qty, ...$extra]);

    expect($record($seeds, 2)->quantityDisplay())->toBe('2 sacks')
        ->and($record($seeds, 1)->quantityDisplay())->toBe('1 sack')
        ->and($record($oil, 5)->quantityDisplay())->toBe('5 L')
        ->and($record($oil, 2.5)->quantityDisplay())->toBe('2.5 L')
        ->and($record($paff, null)->quantityDisplay())->toBe('-')
        ->and($record($seeds, 1)->deLabel())->toBe('DA - Certified Rice Seeds (Batch 2026-Q3)');

    $claimed = $record($seeds, 2, ['claim_status' => 'claimed']);
    expect($claimed->claimLabel())->toBe('Claimed')->and($claimed->claimTone())->toBe('ok')
        ->and($record($seeds, 2)->claimLabel())->toBe('Unclaimed');
});

it('derives the LGU registration label', function (string $status, string $label) {
    $this->seed(BarangaySeeder::class);

    expect(Beneficiary::factory()->make(['rsbsa_status' => $status])->registrationLabel())->toBe($label);
})->with([
    ['registered', 'Registered'],
    ['pending_validation', 'Registered (New)'],
    ['endorsed', 'Registered (New)'],
    ['returned', 'Unregistered (Eligible)'],
    ['rejected', 'Unregistered (Eligible)'],
]);

it('seeds the Figma sample records idempotently', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    $juan = Beneficiary::where(['first_name' => 'Juan', 'last_name' => 'Dela Cruz'])->sole();
    $federico = InterventionRecord::onlyTrashed()->whereHas('beneficiary', fn ($q) => $q->where('first_name', 'Federico'))->sole();

    expect(InterventionRecord::count())->toBe(7)
        ->and(InterventionRecord::onlyTrashed()->count())->toBe(4)
        ->and($juan->interventionRecords()->sole())
        ->claim_status->toBe('claimed')
        ->validation_status->toBe('eligible')
        ->and($federico->delete_reason)->toBe('Deceased - confirmed by barangay')
        ->and($federico->deleter->username)->toBe('Agritech_02')
        ->and(Beneficiary::where(['first_name' => 'Ana', 'last_name' => 'Gomez'])->value('rsbsa_status'))->toBe('rejected');
});
