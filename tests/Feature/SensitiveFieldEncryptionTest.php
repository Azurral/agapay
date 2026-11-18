<?php

use App\Models\AuditLog;
use App\Models\Beneficiary;
use App\Models\User;
use Illuminate\Support\Facades\DB;

// On the cloud copy the database is online: a farmer's contact number is stored encrypted with the server's key,
// so a copy of the database alone does not reveal it.

it('stores the contact number encrypted and reads it back as typed', function () {
    $farmer = Beneficiary::factory()->create(['contact_number' => '0917-555-0101']);

    $raw = DB::table('beneficiaries')->where('id', $farmer->id)->value('contact_number');

    expect($raw)->not->toBe('0917-555-0101')
        ->and($raw)->not->toContain('0917')
        ->and($farmer->fresh()->contact_number)->toBe('0917-555-0101');
});

it('keeps the contact number out of the audit trail', function () {
    $this->actingAs(User::factory()->create());
    $farmer = Beneficiary::factory()->create(['contact_number' => '0917-555-0101']);
    $farmer->update(['contact_number' => '0918-222-3333']);

    $logged = AuditLog::where(['auditable_type' => $farmer->getMorphClass(), 'auditable_id' => $farmer->id])->get()
        ->map(fn (AuditLog $log) => json_encode([$log->old_values, $log->new_values]))->implode(' ');

    expect($logged)->not->toContain('555-0101')
        ->and($logged)->not->toContain('222-3333')
        ->and($logged)->toContain('(hidden)');
});

it('leaves an empty contact number empty', function () {
    $farmer = Beneficiary::factory()->create(['contact_number' => null]);

    expect(DB::table('beneficiaries')->where('id', $farmer->id)->value('contact_number'))->toBeNull();
});
