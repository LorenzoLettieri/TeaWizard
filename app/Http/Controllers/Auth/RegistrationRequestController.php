<?php

namespace App\Http\Controllers\Auth;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Http\Controllers\Controller;
use App\Models\RegistrationRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class RegistrationRequestController extends Controller
{
    use PasswordValidationRules, ProfileValidationRules;

    public function create(): View
    {
        return view('pages.auth.register');
    }

    public function store(): RedirectResponse
    {
        $input = Validator::make(request()->all(), [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $email = mb_strtolower($input['email']);

        if (User::query()->where('email', $email)->exists()) {
            return back()
                ->withErrors(['email' => 'An account with this email already exists.'])
                ->withInput();
        }

        if (RegistrationRequest::query()
            ->where('email', $email)
            ->where('status', RegistrationRequest::STATUS_PENDING)
            ->exists()) {
            return back()
                ->withErrors(['email' => 'A registration request for this email is already pending.'])
                ->withInput();
        }

        RegistrationRequest::create([
            'name' => $input['name'],
            'email' => $email,
            'password' => $input['password'],
            'status' => RegistrationRequest::STATUS_PENDING,
        ]);

        return redirect()
            ->route('login')
            ->with('status', 'Your access request has been submitted. An admin will review it soon.');
    }
}
