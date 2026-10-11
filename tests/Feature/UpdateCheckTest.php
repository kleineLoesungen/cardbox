<?php

use App\Models\Setting;
use App\Services\UpdateCheck;
use App\Support\Changelog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Changelog der Gegenstelle — eine Version neuer als die installierte. */
function entfernterChangelog(string $version = '2030.01.01'): string
{
    return "# Änderungen\n\n## {$version}\n- Etwas Neues\n- Migration: 2030_01_01_000000_create_dings_table.php\n";
}

beforeEach(function () {
    // Feste installierte Version, damit die Tests nicht an CHANGELOG.md hängen.
    Changelog::fake("# Änderungen\n\n## 2026.01.01\n- Erste Fassung\n");
    config(['updates.check_url' => 'https://example.test/CHANGELOG.md']);
});

afterEach(fn () => Changelog::fake(null));

it('fragt ohne Prüfadresse nichts ab', function () {
    Http::fake();
    config(['updates.check_url' => null]);

    $status = app(UpdateCheck::class)->status(config('updates.check_url'));

    expect($status->enabled)->toBeFalse()
        ->and($status->installed)->toBe('2026.01.01')
        ->and($status->updateAvailable())->toBeFalse();

    Http::assertNothingSent();
    expect(Setting::count())->toBe(0);
});

it('meldet eine neuere Version und merkt sich den Stand', function () {
    Http::fake(['example.test/*' => Http::response(entfernterChangelog())]);

    $status = app(UpdateCheck::class)->status(config('updates.check_url'));

    expect($status->enabled)->toBeTrue()
        ->and($status->ok)->toBeTrue()
        ->and($status->latest)->toBe('2030.01.01')
        ->and($status->latestLabel())->toBe('01.01.2030')
        ->and($status->updateAvailable())->toBeTrue()
        ->and($status->newer[0]->items)->toBe(['Etwas Neues'])
        ->and($status->newer[0]->migrations)->toBe(['2030_01_01_000000_create_dings_table.php'])
        ->and($status->checkedAt)->not->toBeNull()
        ->and(Setting::read('update_check'))->toContain('2030.01.01');
});

it('fragt innerhalb von 24 Stunden kein zweites Mal ab', function () {
    Http::fake(['example.test/*' => Http::response(entfernterChangelog())]);
    $check = app(UpdateCheck::class);

    $check->status(config('updates.check_url'));
    $this->travel(23)->hours();
    $check->status(config('updates.check_url'));

    Http::assertSentCount(1);

    // Nach 24 Stunden wieder.
    $this->travel(2)->hours();
    $check->status(config('updates.check_url'));
    Http::assertSentCount(2);
});

it('übergeht mit „Jetzt prüfen" den Zwischenspeicher', function () {
    Http::fake(['example.test/*' => Http::response(entfernterChangelog())]);
    $check = app(UpdateCheck::class);

    $check->status(config('updates.check_url'));
    $check->status(config('updates.check_url'), force: true);

    Http::assertSentCount(2);
});

it('behält bei einem Fehlschlag den zuletzt bekannten Stand', function (int $status) {
    // Zwei Antworten nacheinander: erst erreichbar, dann nicht mehr. Ein
    // zweites Http::fake() ersetzt das erste NICHT — der erste passende
    // Eintrag gewinnt. Dafür gibt es fakeSequence.
    Http::fakeSequence('example.test/*')
        ->push(entfernterChangelog(), 200)
        ->push('weg', $status);

    $check = app(UpdateCheck::class);
    $check->status(config('updates.check_url'));
    $nachher = $check->status(config('updates.check_url'), force: true);

    expect($nachher->ok)->toBeFalse()
        ->and($nachher->latest)->toBe('2030.01.01')
        ->and($nachher->updateAvailable())->toBeTrue();
})->with([404, 500]);

it('behandelt eine nicht erreichbare Gegenstelle als Fehlschlag, nicht als Fehler', function () {
    Http::fake(fn () => throw new ConnectionException('Zeitlimit'));

    $status = app(UpdateCheck::class)->status(config('updates.check_url'), force: true);

    expect($status->enabled)->toBeTrue()
        ->and($status->ok)->toBeFalse()
        ->and($status->latest)->toBeNull();
});

it('verwirft den Stand, wenn die Prüfadresse wechselt', function () {
    Http::fake(['example.test/*' => Http::response(entfernterChangelog())]);
    $check = app(UpdateCheck::class);
    $check->status('https://example.test/CHANGELOG.md');

    Http::fake(['andere.test/*' => Http::response('kein Changelog', 404)]);
    $status = $check->status('https://andere.test/CHANGELOG.md', force: true);

    expect($status->ok)->toBeFalse()
        ->and($status->latest)->toBeNull()
        ->and($status->updateAvailable())->toBeFalse();
});

it('meldet nichts Neues, wenn die installierte Version die neueste ist', function () {
    Http::fake(['example.test/*' => Http::response(entfernterChangelog('2026.01.01'))]);

    $status = app(UpdateCheck::class)->status(config('updates.check_url'));

    expect($status->ok)->toBeTrue()
        ->and($status->latest)->toBe('2026.01.01')
        ->and($status->updateAvailable())->toBeFalse();
});

it('verwirft eine übergroße Antwort', function () {
    Http::fake(['example.test/*' => Http::response(str_repeat('x', 300 * 1024))]);

    $status = app(UpdateCheck::class)->status(config('updates.check_url'), force: true);

    expect($status->ok)->toBeFalse();
});

it('nimmt höchstens 100 Einträge entgegen', function () {
    $viele = "# Änderungen\n\n";
    foreach (range(1, 120) as $i) {
        $viele .= sprintf("## 2030.01.%02d\n- Punkt %d\n\n", ($i % 28) + 1, $i);
    }
    Http::fake(['example.test/*' => Http::response($viele)]);

    app(UpdateCheck::class)->status(config('updates.check_url'), force: true);

    expect(substr_count((string) Setting::read('update_check'), '"version"'))->toBe(100);
});

describe('Systemseite', function () {
    it('zeigt Administratoren die installierte Version und die Änderungen', function () {
        Http::fake(['example.test/*' => Http::response(entfernterChangelog())]);
        actingAsAdmin();

        $this->get('/admin/system')
            ->assertOk()
            ->assertSee('Version')
            ->assertSee('01.01.2026')
            ->assertSee('Es gibt eine neuere Version')
            ->assertSee('Etwas Neues')
            ->assertSee('Jetzt prüfen');
    });

    it('zeigt ohne Prüfadresse nur die installierte Version', function () {
        Http::fake();
        config(['updates.check_url' => null]);
        actingAsAdmin();

        $this->get('/admin/system')
            ->assertOk()
            ->assertSee('01.01.2026')
            ->assertSee('UPDATE_CHECK_URL')
            ->assertDontSee('Jetzt prüfen');

        Http::assertNothingSent();
    });

    it('prüft auf Knopfdruck sofort', function () {
        Http::fake(['example.test/*' => Http::response(entfernterChangelog())]);
        actingAsAdmin();

        $this->from('/admin/system')->post('/admin/system/update-check')
            ->assertRedirect('/admin/system')
            ->assertSessionHas('checked', 'ok');

        Http::assertSentCount(1);
    });

    it('meldet eine fehlgeschlagene Prüfung als Hinweis', function () {
        Http::fake(['example.test/*' => Http::response('weg', 503)]);
        actingAsAdmin();

        $this->from('/admin/system')->post('/admin/system/update-check')
            ->assertSessionHas('checked', 'failed');
    });

    it('lässt nur Administratoren prüfen', function () {
        Http::fake();
        actingAsUser();

        $this->post('/admin/system/update-check')->assertForbidden();

        Http::assertNothingSent();
    });
});

describe('Fußzeile', function () {
    it('zeigt Angemeldeten die Version', function () {
        actingAsUser();

        $this->get('/dashboard')->assertOk()->assertSee('Version 01.01.2026');
    });

    it('zeigt sie Gästen nicht', function () {
        $this->get('/styleguide')->assertOk()->assertDontSee('Version 01.01.2026');
    });
});
