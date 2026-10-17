<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Testaufbau
|--------------------------------------------------------------------------
|
| Feature-Tests laufen gegen die echte Postgres-Datenbank `cardbox_test`.
| RefreshDatabase migriert einmal und setzt jeden Test in eine Transaktion,
| die danach zurueckgerollt wird — jeder Test sieht eine leere Datenbank.
|
| Testnamen auf Deutsch, als Satz: it('…') liest sich als „es …".
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/** Legt einen Benutzer an und meldet ihn fuer die folgenden Anfragen an. */
function actingAsUser(array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    test()->actingAs($user);

    return $user;
}

function actingAsAdmin(array $attributes = []): User
{
    $user = User::factory()->admin()->create($attributes);
    test()->actingAs($user);

    return $user;
}

/**
 * The questions of the current round in order. Intros of new cards
 * (docs/specs/motivierender-start.md, A) are left out.
 *
 * @return list<array<string, mixed>>
 */
function roundQuestions(): array
{
    return array_values(array_filter(session('study_round')['queue'] ?? [], fn (array $item) => ! ($item['intro'] ?? false)));
}

/** Taps "Weiter" on intros until a question is up. */
function skipIntros(): void
{
    for ($taps = 0; (session('study_round')['queue'][0]['intro'] ?? false) === true; $taps++) {
        expect($taps)->toBeLessThan(100, 'An intro did not move on.');
        test()->withHeaders(['HX-Request' => 'true'])->post('/learn/round/answer', [
            'card' => session('study_round')['queue'][0]['card'],
            'step' => session('study_round')['step'],
        ])->assertOk();
    }
}
