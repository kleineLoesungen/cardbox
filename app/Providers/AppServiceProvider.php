<?php

namespace App\Providers;

use App\Services\GuardianService;
use App\Services\LeitnerService;
use App\Services\PushSender;
use App\Services\UserService;
use App\Services\WebPushSender;
use App\Support\Branding;
use App\Support\Changelog;
use App\Support\Navigation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One instance per request: caches the learning plan between services.
        $this->app->scoped(LeitnerService::class);

        // Daily reminders; tests swap in a fake sender.
        $this->app->bind(PushSender::class, WebPushSender::class);
    }

    public function boot(): void
    {
        // Passwoerter: nur eine Mindestlaenge, keine Zeichenklassen-Regeln.
        Password::defaults(fn () => Password::min(UserService::MIN_PASSWORD_LENGTH));

        // Beim Entwickeln und Testen laut scheitern statt still falsch:
        // Nachladen in Schleifen (N+1), Tippfehler in Feldnamen, nicht
        // freigegebene Felder bei create()/update().
        Model::shouldBeStrict(! $this->app->isProduction());

        // Fehler beim Start, nicht im Betrieb: Eine falsch geschriebene Farbe
        // landete sonst unbemerkt im Manifest und in einem Meta-Tag.
        Branding::assertValid();

        // Name und Farben in jedem Template als $branding.
        View::share('branding', Branding::current());

        // Version fuer die Fusszeile — nur dort, deshalb kein share().
        View::composer('layouts.app', fn (\Illuminate\View\View $view) => $view->with(
            'appVersion', Changelog::installedLabel(),
        ));

        // Badge with waiting registrations — admins only, so nobody else
        // pays for the query.
        View::composer('layouts.app', fn (\Illuminate\View\View $view) => $view->with(
            'pendingUserCount',
            auth()->user()?->isAdmin() ? app(UserService::class)->pendingCount() : 0,
        ));

        // The section to mark in the tab bar and menu: below a section, the one
        // the person came from (docs/specs/navigation.md).
        View::composer('layouts.app', fn (\Illuminate\View\View $view) => $view->with(
            'navSection', Navigation::current(request()),
        ));

        // "Meine Kinder" in the menu only for accounts with children (eltern-ansicht.md, B).
        View::composer('layouts.app', fn (\Illuminate\View\View $view) => $view->with(
            'hasChildren',
            auth()->user() !== null && app(GuardianService::class)->hasChildren(auth()->user()),
        ));
    }
}
