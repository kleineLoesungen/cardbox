<?php

namespace App\Http\Controllers;

use App\Services\LearningSettingsService;
use App\Services\LeitnerService;
use App\Services\ReminderService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Own account: change display name and password, link to the learning
 * plan. The username is shown but only an administrator may change it.
 *
 * Zwei Formulare auf einer Seite — deshalb getrennte Fehler-Taschen
 * ("profile", "password"), sonst erschiene ein Fehler bei beiden.
 */
class AccountController extends Controller
{
    public function __construct(
        private UserService $users,
        private LearningSettingsService $learning,
        private ReminderService $reminders,
        private LeitnerService $leitner,
    ) {}

    public function show(Request $request): View
    {
        return view('pages.account', [
            'user' => $request->user(),
            // "Lernplan" lives here since it left the menu (docs/specs/einfacher-lernen.md, D).
            'plan' => $this->learning->current($request->user()),
            'roundSize' => $this->leitner->roundSize($request->user()),
            'reminder' => $this->reminders->enabled() ? [
                'times' => $this->reminders->times(),
                'publicKey' => (string) config('push.vapid.public_key'),
                ...$this->reminders->status($request->user()),
            ] : null,
        ]);
    }

    /** Cards per round. */
    public function updateRoundSize(Request $request): RedirectResponse
    {
        try {
            $size = $this->learning->setRoundSize($request->user(), $request->input('round_size'));
        } catch (ValidationException $e) {
            throw $e->errorBag('round');
        }

        return redirect('/account')->with('toast', ['kind' => 'success', 'message' => "Eine Runde hat jetzt {$size} Karten."]);
    }

    /** "Speichern" with a time, or "Ausschalten". */
    public function updateReminder(Request $request): RedirectResponse
    {
        if ($request->boolean('off')) {
            $this->reminders->turnOff($request->user());

            return redirect('/account')->with('toast', ['kind' => 'success', 'message' => 'Erinnerung ausgeschaltet.']);
        }

        try {
            $this->reminders->setTime($request->user(), $request->input('reminder_time'));
        } catch (ValidationException $e) {
            throw $e->errorBag('reminder');
        }

        return redirect('/account')->with('toast', ['kind' => 'success', 'message' => 'Erinnerung gespeichert.']);
    }

    /**
     * JSON from the page's script: this browser's push subscription. Errors
     * as JSON too — web routes would otherwise redirect, and fetch() would
     * follow the redirect as if all went well.
     */
    public function storePushSubscription(Request $request): JsonResponse
    {
        try {
            $this->reminders->subscribe($request->user(), $request->all());
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->validator->errors()->first()], 422);
        }

        return response()->json(['ok' => true], 201);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        try {
            $this->users->updateProfile($request->user(), $request->only('name'));
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
