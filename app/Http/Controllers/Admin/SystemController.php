<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\TestMail;
use App\Services\UpdateCheck;
use App\Support\Changelog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * Systemseite unter /admin/system — nur Administratoren.
 *
 * Zeigt die WIRKSAME Konfiguration, aendert aber nichts daran. Die Quelle der
 * Wahrheit bleiben die Umgebungsvariablen: Name, Farben und Mailserver sind
 * Betriebsentscheidungen, die mit dem Deployment versioniert gehoeren — nicht
 * still per Klick in der Datenbank.
 */
class SystemController extends Controller
{
    public function __construct(private UpdateCheck $updates) {}

    public function index(Request $request): View
    {
        $mailer = (string) config('mail.default');
        /** @var array<string, mixed> $settings */
        $settings = config("mail.mailers.{$mailer}", []);

        return view('admin.system', [
            // Ruft hoechstens alle 24 Stunden wirklich ab; sonst aus dem Zwischenspeicher.
            'update' => $this->updates->status(config('updates.check_url')),
            // Installierte Version und die drei davor.
            'changelog' => array_slice(Changelog::entries(), 0, 4),
            'mail' => [
                'mailer' => $mailer,
                'host' => $settings['host'] ?? null,
                'port' => $settings['port'] ?? null,
                'scheme' => $settings['scheme'] ?? null,
                'username' => $settings['username'] ?? null,
                // Nie das Passwort zeigen — nur, ob eines gesetzt ist.
                'has_password' => ! empty($settings['password']),
                'uses_url' => ! empty($settings['url']),
                'from' => config('mail.from.name').' <'.config('mail.from.address').'>',
            ],
            'appUrl' => config('app.url'),
            'secureCookie' => (bool) config('session.secure'),
            'trustProxy' => (bool) config('app.trust_proxy'),
            'debug' => (bool) config('app.debug'),
            'testMailTo' => old('to', $request->user()->email),
        ]);
    }

    /** „Jetzt pruefen": uebergeht den Zwischenspeicher. */
    public function updateCheck(Request $request): RedirectResponse
    {
        $status = $this->updates->status(config('updates.check_url'), force: true);

        Log::info('Update-Prüfung ausgelöst', ['by' => $request->user()->id, 'erreichbar' => $status->ok]);

        return back()->with('checked', $status->ok ? 'ok' : 'failed');
    }

    public function testMail(Request $request): RedirectResponse
    {
        $data = $request->validate(['to' => ['required', 'email']]);
        $admin = $request->user();

        try {
            // Hier bewusst send() und nicht queue()/defer(): Der Administrator
            // will wissen, ob der Mailserver die Nachricht angenommen hat.
            Mail::to($data['to'])->send(new TestMail(triggeredBy: $admin->name));
        } catch (\Throwable $e) {
            Log::warning('Testmail fehlgeschlagen', ['error' => $e->getMessage()]);

            // Ausnahmsweise die interne Meldung zeigen: Nur Administratoren sehen
            // die Seite, und „Connection refused" ist genau die Auskunft, die sie brauchen.
            return back()->withInput()->with('mail_error', $e->getMessage());
        }

        Log::info('Testmail verschickt', ['by' => $admin->id]);

        // Die Adresse nicht in die URL — sie landete sonst in Verlauf und Logs.
        return back()->with('mail_sent', true);
    }
}
