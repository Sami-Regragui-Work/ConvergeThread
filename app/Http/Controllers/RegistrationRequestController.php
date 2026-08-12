<?php

namespace App\Http\Controllers;

use App\Models\RegistrationRequest;
use App\Services\RegistrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class RegistrationRequestController extends Controller
{
    public function __construct(private readonly RegistrationService $registrationService)
    {
    }

    public function approve(Request $request, RegistrationRequest $registration)
    {
        $data = $request->validate([
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
        ]);

        try {
            $this->registrationService->approve(
                Auth::user(),
                $registration,
                $data['tenant_id'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['email' => $e->getMessage()]);
        }

        return back()->with('success', "{$registration->email} approved — they can now sign in.");
    }

    public function reject(RegistrationRequest $registration)
    {
        $this->registrationService->reject(Auth::user(), $registration);

        return back()->with('success', 'Registration request rejected.');
    }
}
