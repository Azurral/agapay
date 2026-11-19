<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBeneficiaryRequest;
use App\Models\Barangay;
use App\Models\Beneficiary;
use App\Models\DamagePhoto;
use App\Support\IdCheck;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/** Add Beneficiary: profiles are created directly; the RSBSA number is optional. */
class BeneficiaryRegistrationController extends Controller
{
    public function __construct(private readonly IdCheck $idCheck) {}

    /**
     * Automatic checks on the ID photos (clear photo, name and number printed on them), kept with the profile for the
     * Administrator who approves the walk-in.
     *
     * @return list<array{check: string, result: string}>
     */
    private function idChecks(StoreBeneficiaryRequest $request, array $data): array
    {
        $checks = $this->idCheck->check($request->file('valid_id_photo')->getRealPath(), Beneficiary::VALID_IDS[$data['valid_id_type']],
            $data['first_name'], $data['last_name'], $data['valid_id_number']);
        if ($rsbsaId = $request->file('rsbsa_id_photo')) {
            $checks = [...$checks, ...$this->idCheck->check($rsbsaId->getRealPath(), 'RSBSA / agricultural ID',
                $data['first_name'], $data['last_name'], (string) $data['rsbsa_number'])];
        }

        return $checks;
    }

    public function create(): View
    {
        return view('beneficiaries.create', [
            'barangays' => Barangay::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Lock name for one person's registration (same identity as Beneficiary::isAlreadyRegistered). */
    public static function lockKey(string $firstName, string $lastName, string $birthdate, int $barangayId): string
    {
        return 'beneficiary-register:'.sha1(mb_strtolower("{$firstName}|{$lastName}")."|{$birthdate}|{$barangayId}");
    }

    public function store(StoreBeneficiaryRequest $request): RedirectResponse
    {
        $data = $request->validated();
        // Built lazily: withErrors() flashes to the session as soon as it is called.
        $duplicate = fn () => back()->withInput()->withErrors(['first_name' => 'This person is already registered.']);

        // A double-clicked submit sends two requests that both pass validation: only one may create the record.
        $lock = Cache::lock(self::lockKey($data['first_name'], $data['last_name'], $data['birthdate'], (int) $data['barangay_id']), 10);
        if (! $lock->get()) {
            return $duplicate();
        }

        try {
            if (Beneficiary::isAlreadyRegistered($data['first_name'], $data['last_name'], $data['birthdate'], (int) $data['barangay_id'])) {
                return $duplicate();
            }

            // The ID photo is a private file (like damage photos): served only to signed-in staff.
            $photo = $request->file('valid_id_photo')->store('valid-ids', DamagePhoto::disk());
            $rsbsaId = $request->file('rsbsa_id_photo')?->store('valid-ids', DamagePhoto::disk());
            $checks = $this->idChecks($request, $data);
            // The Administrator approves walk-ins, so their own registrations are verified at once.
            $approver = $request->user()->can('beneficiaries.approve');

            $beneficiary = Beneficiary::create([
                ...Arr::except($data, ['valid_id_photo', 'rsbsa_id_photo', 'consent']),
                'valid_id_photo' => $photo,
                'rsbsa_id_photo' => $rsbsaId,
                'valid_id_key' => Beneficiary::validIdKey($data['valid_id_type'], $data['valid_id_number']),
                'valid_id_sha256' => hash_file('sha256', $request->file('valid_id_photo')->getRealPath()),
                'rsbsa_id_sha256' => $request->file('rsbsa_id_photo') ? hash_file('sha256', $request->file('rsbsa_id_photo')->getRealPath()) : null,
                'id_checks' => $checks,
                'verification_status' => $approver ? Beneficiary::VERIFICATION_VERIFIED : Beneficiary::VERIFICATION_PENDING,
                'verified_by' => $approver ? $request->user()->id : null,
                'verified_at' => $approver ? now() : null,
                'rsbsa_number' => ($data['rsbsa_number'] ?? null) ?: null,
                'rsbsa_status' => Beneficiary::RSBSA_REGISTERED,
                'source' => Beneficiary::SOURCE_MANUAL,
                'consent_confirmed_at' => now(),
                'consent_confirmed_by' => $request->user()->id,
                'created_by' => $request->user()->id,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            if (! Beneficiary::isRsbsaClash($e)) {
                throw $e;
            }

            // Another profile got the same number after it was checked.
            return back()->withInput()->withErrors(['rsbsa_number' => 'That RSBSA No. is already used by another profile.']);
        } finally {
            $lock->release();
        }

        return redirect()->route('beneficiaries.show', $beneficiary)
            ->with('status', $beneficiary->isVerified()
                ? "{$beneficiary->fullName()} was added."
                : "{$beneficiary->fullName()} was added and is waiting for the Administrator's approval.");
    }
}
