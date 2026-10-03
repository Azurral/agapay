<?php

use App\Models\AuditLog;
use App\Models\Crop;
use App\Models\DamageReport;
use App\Models\Disaster;
use App\Models\Role;
use App\Models\User;
use App\Services\DamageCalculator;
use App\Support\DashboardStats;
use Database\Seeders\DatabaseSeeder;

it('computes loss and cost from crop values', function () {
    expect(DamageCalculator::calculate(1.20, 0.30, 0.50, 4.00, 20000))->toBe(['loss_mt' => 5.4, 'cost' => 108000.0]);
});

it('weights partial damage by the crop factor', function () {
    expect(DamageCalculator::calculate(0, 2.00, 0.25, 4.00, 20000)['loss_mt'])->toBe(2.0);
});

it('rounds cost from the rounded loss', function () {
    expect(DamageCalculator::calculate(1, 0, 0.5, 3.333, 1000))->toBe(['loss_mt' => 3.33, 'cost' => 3330.0]);
});

it('formats card numbers', function (float $value, string $text) {
    expect(DamageCalculator::compact($value))->toBe($text);
})->with([
    [842, '842'],
    [1.35, '1.35'],
    [54000, '54,000'],
    [4200000, '4.2M'],
    [1204, '1,204'],
    [0, '0'],
]);

it('snapshots the crop values with the result', function () {
    $crop = new Crop(['name' => 'Rice', 'yield_mt_per_ha' => 4, 'price_per_mt' => 20000, 'partial_loss_factor' => 0.5]);

    expect(DamageCalculator::forCrop($crop, 1.20, 0.30))->toBe([
        'yield_mt_per_ha' => 4.0, 'price_per_mt' => 20000.0, 'partial_loss_factor' => 0.5, 'loss_mt' => 5.4, 'cost' => 108000.0,
    ]);
});

describe('seeded data', function () {
    beforeEach(fn () => $this->seed(DatabaseSeeder::class));

    it('seeds crops, disasters and sample reports', function () {
        expect(Crop::count())->toBe(7)
            ->and(Crop::where('name', 'Rice')->sole())->yield_mt_per_ha->toEqual('4.00')->price_per_mt->toEqual('20000.00')
            ->and(Disaster::where('name', 'Typhoon Cristina')->sole()->occurred_on->toDateString())->toBe('2026-07-20')
            ->and(DamageReport::count())->toBe(6)
            ->and(DamageReport::where('status', DamageReport::FOR_VALIDATION)->count())->toBe(2);

        DamageReport::all()->each(function (DamageReport $report) {
            $expected = DamageCalculator::calculate(
                (float) $report->total_area_ha, (float) $report->partial_area_ha, (float) $report->partial_loss_factor,
                (float) $report->yield_mt_per_ha, (float) $report->price_per_mt,
            );
            expect((float) $report->loss_mt)->toBe($expected['loss_mt'])->and((float) $report->cost)->toBe($expected['cost']);
        });
    });

    it('labels a report for the audit trail', function () {
        $report = DamageReport::whereHas('beneficiary', fn ($q) => $q->where('first_name', 'Juan'))->sole();

        expect($report->auditRecordLabel())->toBe('Juan Dela Cruz · Rice · Typhoon Cristina')
            ->and($report->areaLabel())->toBe('1.20 ha / 0.30 ha')
            ->and($report->statusLabel())->toBe('Validated')
            ->and($report->statusTone())->toBe('ok');
    });

    it('counts damage reports filed this month for the agri tech stat', function () {
        $agritech = User::where('username', 'Agritech_02')->sole();
        DamageReport::factory()->count(2)->create();
        DamageReport::factory()->create(['created_at' => now()->subMonth()]);
        DamageReport::factory()->create()->delete();

        expect(DashboardStats::value('reports_filed_this_month', $agritech))->toBe(2);
    });

    it('gives damage.configure to the administrator only', function () {
        expect(User::where('username', 'Admin_01')->sole()->can('damage.configure'))->toBeTrue()
            ->and(User::where('username', 'Agritech_02')->sole()->can('damage.configure'))->toBeFalse()
            ->and(Role::where('slug', Role::ENCODER)->sole()->permissions()->where('slug', 'damage.configure')->exists())->toBeFalse();
    });
});

it('audits a new disaster', function () {
    $this->actingAs(User::factory()->create());

    Disaster::create(['name' => 'Typhoon Egay', 'occurred_on' => '2026-08-01']);

    expect(AuditLog::where('action', 'Added Disaster')->sole()->record_label)->toBe('Typhoon Egay (Aug 1, 2026)');
});
