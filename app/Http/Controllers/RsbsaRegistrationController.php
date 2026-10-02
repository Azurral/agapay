<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidRsbsaTransition;
use App\Http\Requests\StoreRsbsaRegistrationRequest;
use App\Models\Barangay;
use App\Models\Beneficiary;
use App\Services\RsbsaWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RsbsaRegistrationController extends Controller
{
    public function create(Request $request): View
    {
        return view('rsbsa.register', [
            'barangays' => Barangay::orderBy('name')->get(['id', 'name']),
            'canRegister' => $request->user()->can('rsbsa.register'),
            'canProcess' => $request->user()->can('rsbsa.process'),
            'pending' => Beneficiary::with('barangay:id,name')
                ->whereIn('rsbsa_status', [...Beneficiary::RSBSA_IN_PROGRESS, Beneficiary::RSBSA_RETURNED])
                ->latest()->latest('id')
                ->get(),
        ]);
    }

    /** Lock name for one person's registration (same identity as Beneficiary::isAlreadyRegistered). */
    public static function lockKey(string $firstName, string $lastName, string $birthdate, int $barangayId): string
    {
        return 'rsbsa-register:'.sha1(mb_strtolower("{$firstName}|{$lastName}")."|{$birthdate}|{$barangayId}");
    }

    public function transition(Request $request, Beneficiary $beneficiary, string $action): RedirectResponse
    {
        try {
            RsbsaWorkflow::apply($beneficiary, $action, $request->only(['rsbsa_number', 'reason']));
        } catch (InvalidRsbsaTransition $e) {
            return back()->withErrors(['rsbsa' => $e->getMessage()], 'rsbsa');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors(), 'rsbsa')->with('rsbsa_failed', "{$action}-{$beneficiary->id}");
        }

        return back()->with('status', "{$beneficiary->fullName()}: ".Beneficiary::rsbsaStatusLabel($beneficiary->rsbsa_status).'.');
    }

    public function store(StoreRsbsaRegistrationRequest $request): RedirectResponse
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
                'rsbsa_status' => Beneficiary::RSBSA_PENDING,
                'source' => Beneficiary::SOURCE_MANUAL,
                'created_by' => $request->user()->id,
            ]);
        } finally {
            $lock->release();
        }

        return redirect()->route('rsbsa.register')
            ->with('status', "{$beneficiary->fullName()} was registered and is awaiting OMAG validation.");
    }
}
