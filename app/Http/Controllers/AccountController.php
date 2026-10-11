<?php

namespace App\Http\Controllers;

use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Eigenes Konto: Name, E-Mail und Passwort selbst aendern.
 *
 * Zwei Formulare auf einer Seite — deshalb getrennte Fehler-Taschen
 * ("profile", "password"), sonst erschiene ein Fehler bei beiden.
 */
class AccountController extends Controller
{
    public function __construct(private UserService $users) {}

    public function show(Request $request): View
    {
        return view('pages.account', ['user' => $request->user()]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        try {
            $this->users->updateProfile($request->user(), $request->only('name', 'email'));
        } catch (ValidationException $e) {
            throw $e->errorBag('profile');
        }

        return back()->with('saved', 'profile');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        try {
            if (! Hash::check((string) $request->input('current_password'), $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'Das aktuelle Passwort stimmt nicht.']);
            }
            if ($request->input('new_password') !== $request->input('new_password_repeat')) {
                throw ValidationException::withMessages(['new_password_repeat' => 'Die beiden neuen Passwörter stimmen nicht überein.']);
            }

            $this->users->changePassword($user, (string) $request->input('new_password'));
        } catch (ValidationException $e) {
            throw $e->errorBag('password');
        }

        return back()->with('saved', 'password');
    }
}
