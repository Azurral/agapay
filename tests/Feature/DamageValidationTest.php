<?php

use App\Models\AuditLog;
use App\Models\Crop;
use App\Models\DamageReport;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('username', 'Admin_01')->sole();
    $this->agritech = User::where('username', 'Agritech_02')->sole();
    $this->encoder = User::where('username', 'Encoder_03')->sole();
    // Carlos Ibanez · Rice · 1.50 ha → 6 MT, ₱120,000, For Validation.
    $this->report = DamageReport::whereHas('beneficiary', fn ($q) => $q->where('first_name', 'Carlos'))->sole();
});

it('shows the report with its figures and the validation form', function () {
    $this->actingAs($this->agritech)->get(route('damage.show', $this->report))->assertOk()
        ->assertSeeInOrder(['Carlos Ibanez', 'Typhoon Cristina', 'Bontoc Ili', 'Rice', 'Maturing', '1.50 ha / 0.00 ha', '6 MT', '₱120,000'])
        ->assertSee('Rice · 4 MT/ha · ₱20,000/MT · partial ×0.5')
        ->assertSee('For Validation')
        ->assertSee('Validate Report');

    $this->actingAs($this->encoder)->get(route('damage.show', $this->report))->assertOk()->assertDontSee('Validate Report');
});

it('validates a report as filed', function () {
    $this->actingAs($this->agritech)->post(route('damage.validate', $this->report))
        ->assertRedirect(route('damage.show', $this->report))->assertSessionHas('status', 'Damage report validated.');

    expect($this->report->fresh())->status->toBe(DamageReport::VALIDATED)->validated_by->toBe($this->agritech->id)
        ->validated_at->not->toBeNull()->loss_mt->toEqual('6.00')->cost->toEqual('120000.00')->adjustment_note->toBeNull();
});

it('validates with adjusted figures and a note', function () {
    $this->actingAs($this->agritech)->post(route('damage.validate', $this->report), ['loss_mt' => '5.5', 'cost' => '110000', 'adjustment_note' => 'Part of the field was replanted.'])
        ->assertSessionHasNoErrors();

    expect($this->report->fresh())->loss_mt->toEqual('5.50')->cost->toEqual('110000.00')->adjustment_note->toBe('Part of the field was replanted.')
        ->and(AuditLog::where('action', 'Validated Damage Report')->sole())
        ->old_values->toMatchArray(['loss_mt' => '6.00', 'cost' => '120000.00'])
        ->new_values->toMatchArray(['loss_mt' => '5.50', 'cost' => '110000.00', 'status' => 'validated']);
});

it('asks why the figures were adjusted', function () {
    $this->actingAs($this->agritech)->post(route('damage.validate', $this->report), ['loss_mt' => '5.5'])
        ->assertSessionHasErrorsIn('damage', ['adjustment_note' => 'Explain why the figures were adjusted.']);

    $this->post(route('damage.validate', $this->report), ['cost' => '-5', 'adjustment_note' => 'x'])
        ->assertSessionHasErrorsIn('damage', ['cost' => 'Enter the cost in pesos, e.g. 110000.']);

    expect($this->report->fresh()->status)->toBe(DamageReport::FOR_VALIDATION);
});

it('validates only once', function () {
    $this->actingAs($this->agritech)->post(route('damage.validate', $this->report));
    $this->post(route('damage.validate', $this->report))
        ->assertSessionHasErrorsIn('damage', ['report' => 'This report is already validated.']);

    expect(AuditLog::where('action', 'Validated Damage Report')->count())->toBe(1);
});

it('forbids encoders from validating', function () {
    $this->actingAs($this->encoder)->post(route('damage.validate', $this->report))->assertForbidden();
});

it('archives with a reason and restores', function () {
    $this->actingAs($this->admin)->post(route('damage.archive', $this->report))
        ->assertSessionHasErrorsIn('damage', ['delete_reason' => 'Give a reason for archiving this report.']);

    $this->post(route('damage.archive', $this->report), ['delete_reason' => 'Filed under the wrong farmer'])
        ->assertRedirect(route('damage.index'));
    $archived = DamageReport::onlyTrashed()->findOrFail($this->report->id);
    expect($archived)->delete_reason->toBe('Filed under the wrong farmer')->deleted_by->toBe($this->admin->id)
        ->and(AuditLog::where('action', 'Archived Damage Report')->exists())->toBeTrue();

    $this->get(route('damage.show', $this->report))->assertOk()->assertSee('Filed under the wrong farmer')->assertSee('Restore');
    $this->actingAs($this->agritech)->get(route('damage.show', $this->report))->assertNotFound();

    $this->actingAs($this->admin)->post(route('damage.restore', $this->report))->assertRedirect(route('damage.show', $this->report));
    expect($this->report->fresh())->trashed()->toBeFalse()->delete_reason->toBeNull()
        ->and(AuditLog::where('action', 'Restored Damage Report')->exists())->toBeTrue();
});

it('refuses to restore a report that would duplicate a newer one', function () {
    $this->report->update(['delete_reason' => 'Duplicate']);
    $this->report->delete();
    DamageReport::factory()->create([
        'disaster_id' => $this->report->disaster_id, 'beneficiary_id' => $this->report->beneficiary_id, 'crop_id' => $this->report->crop_id,
    ]);

    $this->actingAs($this->admin)->post(route('damage.restore', $this->report))
        ->assertSessionHasErrorsIn('damage', ['report' => 'Carlos Ibanez already has a Rice damage report for Typhoon Cristina.']);
    expect(DamageReport::onlyTrashed()->whereKey($this->report->id)->exists())->toBeTrue();
});

it('lets only configurers archive and restore', function () {
    $this->actingAs($this->agritech)->post(route('damage.archive', $this->report), ['delete_reason' => 'x'])->assertForbidden();
});

it('keeps filed figures when crop values change', function () {
    Crop::where('name', 'Rice')->update(['price_per_mt' => 25000]);

    $this->actingAs($this->agritech)->get(route('damage.show', $this->report))->assertSee('₱120,000')->assertSee('₱20,000/MT');
    $this->post(route('damage.validate', $this->report));

    expect($this->report->fresh()->cost)->toEqual('120000.00');
});
