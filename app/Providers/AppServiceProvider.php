<?php

namespace App\Providers;

use App\Services\UserService;
use App\Support\Branding;
use App\Support\Changelog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
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
    }
}
