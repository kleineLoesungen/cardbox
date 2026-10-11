<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Testaufbau
|--------------------------------------------------------------------------
|
| Feature-Tests laufen gegen die echte Postgres-Datenbank `starter_test`.
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
