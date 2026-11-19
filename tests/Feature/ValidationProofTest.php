<?php

use App\Models\AuditLog;
use App\Models\InterventionRecord;
use App\Models\User;
use App\Models\ValidationProof;
use App\Support\IdTextReader;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\GpsJpeg;

// Proof of eligibility (panel, sir Ginard): marking a farmer OFW, Bedridden, Deceased, Inactive, Relocated, Duplicate
// or Eligible again needs a proof file (a signed certification or validation sheet). The Agricultural Technologist's
// change waits for the Administrator, who sees the proof and AGAPAY's automatic checks before approving it.

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    Storage::fake('local');
    $this->admin = User::where('username', 'Admin_01')->sole();
    $this->agritech = User::where('username', 'Agritech_02')->sole();
    $this->carlos = InterventionRecord::whereHas('beneficiary', fn ($q) => $q->where(['first_name' => 'Carlos', 'last_name' => 'Ibanez']))->sole();
});

function proofFile(string $name = 'barangay-certification.jpg'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, GpsJpeg::bytes(null, null, 800, 1000));
}

it('needs a proof file to change the validation status', function () {
    $this->actingAs($this->agritech)->post(route('intervention-records.validate', $this->carlos), ['validation_status' => 'deceased'])
        ->assertSessionHasErrorsIn('intervention', ['proof' => 'Attach the proof for this status (e.g. a signed barangay certification) as a photo or PDF.']);

    expect($this->carlos->fresh()->validation_status)->toBe('eligible')
        ->and(ValidationProof::count())->toBe(0);
});

it("keeps the agri tech's change waiting for the administrator, with the proof", function () {
    $this->actingAs($this->agritech)->from('/interventions/da')
        ->post(route('intervention-records.validate', $this->carlos), ['validation_status' => 'deceased', 'proof' => proofFile()])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', "Carlos Ibanez - Complete Fertilizer (2026-Q3): Deceased sent with proof for the Administrator's approval.");

    $proof = ValidationProof::sole();
    expect($this->carlos->fresh()->validation_status)->toBe('eligible')
        ->and($proof)->status->toBe('pending')->from_status->toBe('eligible')->to_status->toBe('deceased')->submitted_by->toBe($this->agritech->id);
    Storage::disk('local')->assertExists($proof->path);

    // The row shows the waiting change instead of a dropdown, so it is not sent twice.
    $this->get('/interventions/da?name=Carlos')->assertOk()->assertSee('Pending: Deceased')
        ->assertDontSee('action="'.route('intervention-records.validate', $this->carlos).'"', false);
});

it('applies the change when the administrator approves the proof, and keeps it when rejected', function () {
    $this->actingAs($this->agritech)->post(route('intervention-records.validate', $this->carlos), ['validation_status' => 'deceased', 'proof' => proofFile()]);
    $proof = ValidationProof::sole();

    $this->actingAs($this->admin)->get('/beneficiaries/approvals')->assertOk()
        ->assertSeeInOrder(['Status Changes for Approval', 'Carlos Ibanez', 'Eligible', 'Deceased', 'Automatic checks'])
        ->assertSee(route('validation-proofs.file', $proof), false);
    $this->get(route('validation-proofs.file', $proof))->assertOk();

    $this->post(route('validation-proofs.approve', $proof))
        ->assertSessionHas('status', 'Approved: Carlos Ibanez - Complete Fertilizer (2026-Q3) is now Deceased.');
    expect($this->carlos->fresh()->validation_status)->toBe('deceased')
        ->and($proof->fresh())->status->toBe('approved')->reviewed_by->toBe($this->admin->id)
        ->and(AuditLog::where('action', 'Validated Intervention Record')->exists())->toBeTrue();

    // A second change, rejected: the status stays.
    $this->actingAs($this->agritech)->post(route('intervention-records.validate', $this->carlos), ['validation_status' => 'eligible', 'proof' => proofFile('again.jpg')]);
    $second = ValidationProof::latest('id')->first();
    $this->actingAs($this->admin)->post(route('validation-proofs.reject', $second), ['reason' => ' '])
        ->assertSessionHasErrors(['reason' => 'Say why the proof is rejected.']);
    $this->post(route('validation-proofs.reject', $second), ['reason' => 'Certification is not signed'])->assertSessionHasNoErrors();
    expect($this->carlos->fresh()->validation_status)->toBe('deceased')
        ->and($second->fresh())->status->toBe('rejected')->review_note->toBe('Certification is not signed');
});

it("applies the administrator's own change at once, with the proof kept", function () {
    $this->actingAs($this->admin)->post(route('intervention-records.validate', $this->carlos), ['validation_status' => 'ofw', 'proof' => proofFile('ofw.pdf')])
        ->assertSessionHas('status', 'Carlos Ibanez - Complete Fertilizer (2026-Q3): OFW.');

    expect($this->carlos->fresh()->validation_status)->toBe('ofw')
        ->and(ValidationProof::sole())->status->toBe('approved')->reviewed_by->toBe($this->admin->id);
});

it('refuses a proof file already used for another record, and a second change while one waits', function () {
    $file = GpsJpeg::bytes(null, null, 800, 1000);
    $this->actingAs($this->agritech)->post(route('intervention-records.validate', $this->carlos),
        ['validation_status' => 'deceased', 'proof' => UploadedFile::fake()->createWithContent('cert.jpg', $file)])->assertSessionHasNoErrors();

    $this->post(route('intervention-records.validate', $this->carlos), ['validation_status' => 'ofw', 'proof' => proofFile()])
        ->assertSessionHasErrorsIn('intervention', ['intervention' => 'A status change for this record is already waiting for the Administrator.']);

    $liza = InterventionRecord::whereHas('beneficiary', fn ($q) => $q->where(['first_name' => 'Liza', 'last_name' => 'Domingo']))->sole();
    $this->post(route('intervention-records.validate', $liza), ['validation_status' => 'eligible', 'proof' => UploadedFile::fake()->createWithContent('same.jpg', $file)])
        ->assertSessionHasErrorsIn('intervention', ['proof' => 'This proof file was already used for Carlos Ibanez. Attach the proof for this farmer.']);
});

it('reads the farmer name on the proof when the text reader is installed', function () {
    app()->instance(IdTextReader::class, new class extends IdTextReader
    {
        public function available(): bool
        {
            return true;
        }

        public function read(string $path): ?string
        {
            return "BARANGAY CERTIFICATION\nThis is to certify that CARLOS IBANEZ passed away on ...\nPunong Barangay";
        }
    });

    $this->actingAs($this->agritech)->post(route('intervention-records.validate', $this->carlos), ['validation_status' => 'deceased', 'proof' => proofFile()]);

    expect(collect(ValidationProof::sole()->checks)->pluck('result', 'check')->all())
        ->toMatchArray(['Name Carlos Ibanez printed on the proof' => 'pass']);
});
