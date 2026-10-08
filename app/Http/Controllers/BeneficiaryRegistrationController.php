<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBeneficiaryRequest;
use App\Models\Barangay;
use App\Models\Beneficiary;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/** Add Beneficiary: profiles are created directly; the RSBSA number is optional. */
class BeneficiaryRegistrationController extends Controller
{
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

            $beneficiary = Beneficiary::create([
                ...$data,
                'rsbsa_number' => ($data['rsbsa_number'] ?? null) ?: null,
                'rsbsa_status' => Beneficiary::RSBSA_REGISTERED,
                'source' => Beneficiary::SOURCE_MANUAL,
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
            ->with('status', "{$beneficiary->fullName()} was added.");
    }
}
