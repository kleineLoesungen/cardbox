<?php

use App\Models\Card;
use App\Models\CardProgress;
use App\Models\Deck;
use App\Models\Duel;
use App\Models\DuelPlayer;
use App\Models\User;
use App\Services\DuelService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/*
 * Duels with members and guests (docs/specs/duell.md).
 */

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-20 08:00:00', 'UTC'));
});

function duelDeck(User $owner, int $cards = 12, array $attributes = []): Deck
{
    $deck = Deck::factory()->for($owner)->create($attributes + ['name' => 'Tiere', 'front_language' => 'de', 'back_language' => 'en']);
    foreach (range(1, $cards) as $i) {
        Card::factory()->for($deck)->create(['front' => ["Wort {$i}"], 'back' => ["word {$i}", "term {$i}"], 'created_at' => now()->subMinutes(100 - $i)]);
    }

    return $deck;
}

/** Creates a duel as the logged-in person and returns it. */
function makeDuel(Deck $deck, int $players = 2, string $mode = 'choice', int $rounds = 1): Duel
{
    test()->post("/decks/{$deck->id}/duels", ['players' => (string) $players, 'mode' => $mode, 'rounds' => (string) $rounds])->assertRedirect();

    return Duel::latest('created_at')->firstOrFail();
}

function duelCode(Duel $duel): string
{
    return app(DuelService::class)->code($duel);
}

/** @return array{position: int, answers: list<string>, choices: list<string>|null, correct_choice: int|null} */
function duelCard(Duel $duel, int $position): array
{
    $row = DB::table('duel_cards')->where('duel_id', $duel->id)->where('position', $position)->first();

    return ['position' => $position, 'answers' => json_decode($row->answers, true), 'choices' => $row->choices ? json_decode($row->choices, true) : null, 'correct_choice' => $row->correct_choice];
}

/** Shows the card that is up and answers it; right or wrong for choices, text for typing. */
function duelAnswer(string $base, Duel $duel, int $step, bool|string $answer): TestResponse
{
    test()->get("{$base}/play")->assertOk();
    $card = duelCard($duel, $step);
    $input = is_string($answer)
        ? ['typed' => $answer]
        : ['choice' => (string) ($answer ? $card['correct_choice'] : ($card['correct_choice'] + 1) % 4)];

    return test()->withHeaders(['HX-Request' => 'true'])->post("{$base}/answer", ['step' => (string) $step] + $input);
}

/** Plays the round that is up: `$right` of its cards right, each after `$seconds`. */
function playDuel(string $base, Duel $duel, int $right, int $seconds = 2): void
{
    $round = $duel->fresh()->round;
    $positions = DB::table('duel_cards')->where('duel_id', $duel->id)->where('round', $round)->orderBy('position')->pluck('position')->all();
    foreach ($positions as $i => $step) {
        test()->get("{$base}/play");
        test()->travel($seconds)->seconds();
        duelAnswer($base, $duel, $step, $i < $right)->assertOk();
        test()->flushHeaders();
    }
}

describe('Anlegen', function () {
    it('legt ein Duell mit 10 Karten, Auswahl und Code an; die Erstellerin spielt mit', function () {
        $ich = actingAsUser();
        $duel = makeDuel(duelDeck($ich), 3);

        expect(DB::table('duel_cards')->where('duel_id', $duel->id)->count())->toBe(10)
            ->and($duel->player_count)->toBe(3)
            ->and($duel->players()->sole()->user_id)->toBe($ich->id);
        foreach (range(1, 10) as $p) {
            $card = duelCard($duel, $p);
            expect($card['choices'])->toHaveCount(4)
                ->and($card['choices'][$card['correct_choice']])->toBe($card['answers'][0])
                ->and(array_unique(array_map('mb_strtolower', $card['choices'])))->toHaveCount(4);
        }

        $this->get("/duels/{$duel->id}")
            ->assertSee(duelCode($duel))
            ->assertSee('Noch 2 Plätze frei')
            ->assertSee("Los geht's")
            ->assertSee('Auswahl aus 4');
    });

    it('nimmt bei kleinen Stapeln alle Karten und verlangt mindestens 4', function () {
        $ich = actingAsUser();
        $duel = makeDuel(duelDeck($ich, 5));
        expect(DB::table('duel_cards')->where('duel_id', $duel->id)->count())->toBe(5);

        $klein = duelDeck($ich, 3);
        $this->get("/decks/{$klein->id}")->assertDontSee('Duell starten');
        $this->post("/decks/{$klein->id}/duels", ['players' => '2', 'mode' => 'choice'])->assertSessionHasErrors('deck');
    });

    it('prüft Spielerzahl und Abfrageart', function (string $players, string $mode, string $field) {
        $ich = actingAsUser();
        $deck = duelDeck($ich);

        $this->post("/decks/{$deck->id}/duels", ['players' => $players, 'mode' => $mode])->assertSessionHasErrors($field);
        expect(Duel::count())->toBe(0);
    })->with([
        ['1', 'choice', 'players'],
        ['11', 'choice', 'players'],
        ['zwei', 'choice', 'players'],
        ['2', 'flip', 'mode'],
    ]);

    it('bremst das Anlegen nach 20 Duellen in der Stunde', function () {
        $ich = actingAsUser();
        $deck = duelDeck($ich, 4);
        foreach (range(1, 20) as $i) {
            $this->post("/decks/{$deck->id}/duels", ['players' => '2', 'mode' => 'choice'])->assertRedirect();
        }
        $this->post("/decks/{$deck->id}/duels", ['players' => '2', 'mode' => 'choice'])->assertStatus(429);
    });

    it('nimmt keine richtige Nebenbedeutung als falsche Auswahl', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create(['front_language' => 'de', 'back_language' => 'en']);
        Card::factory()->for($deck)->create(['front' => ['groß'], 'back' => ['big', 'large']]);
        foreach ([['riesig', 'large'], ['klein', 'small'], ['alt', 'old'], ['neu', 'new'], ['42', '42']] as [$front, $back]) {
            Card::factory()->for($deck)->create(['front' => [$front], 'back' => [$back]]);
        }

        $duel = makeDuel($deck, 2);
        foreach (range(1, 6) as $p) {
            $card = duelCard($duel, $p);
            $wrong = array_diff_key($card['choices'], [$card['correct_choice'] => true]);
            expect(array_intersect(array_map('mb_strtolower', $wrong), array_map('mb_strtolower', $card['answers'])))->toBe([]);
        }
    });

    it('verlangt für „Auswahl aus 4“ vier verschiedene Antworten', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();
        foreach (range(1, 5) as $i) {
            Card::factory()->for($deck)->create(['front' => ["Wort {$i}"], 'back' => [$i % 2 ? 'ja' : 'nein']]);
        }

        $this->post("/decks/{$deck->id}/duels", ['players' => '2', 'mode' => 'choice'])
            ->assertSessionHasErrors(['mode' => 'Für „Auswahl aus 4“ braucht der Stapel mindestens 4 verschiedene Antworten.']);
        $this->post("/decks/{$deck->id}/duels", ['players' => '2', 'mode' => 'typed'])->assertSessionHasNoErrors();
    });

    it('legt auch mit einem pausierten geteilten Stapel ein Duell an', function () {
        actingAsUser();
        $deck = duelDeck(User::factory()->create(), 6, ['is_shared' => true]);
        $this->post("/decks/{$deck->id}/follow");
        $this->post("/decks/{$deck->id}/learning", ['on' => '0']);

        $this->post("/decks/{$deck->id}/duels", ['players' => '2', 'mode' => 'choice'])->assertRedirect();
        expect(Duel::count())->toBe(1);
    });

    it('lässt nur bei eigenen und gelernten Stapeln ein Duell anlegen', function () {
        actingAsUser();
        $fremd = duelDeck(User::factory()->create(), 6, ['is_shared' => true]);
        $privat = duelDeck(User::factory()->create(), 6);

        $this->post("/decks/{$fremd->id}/duels", ['players' => '2', 'mode' => 'choice'])->assertNotFound();
        $this->post("/decks/{$privat->id}/duels", ['players' => '2', 'mode' => 'choice'])->assertNotFound();

        $this->post("/decks/{$fremd->id}/follow");
        $this->post("/decks/{$fremd->id}/duels", ['players' => '2', 'mode' => 'choice'])->assertRedirect();
    });
});

describe('Mitmachen', function () {
    it('nimmt Mitglieder per Code bis zur Spielerzahl auf; ein freier Platz kommt nach dem Entfernen wieder', function () {
        $anna = actingAsUser();
        $duel = makeDuel(duelDeck($anna), 2);
        $code = duelCode($duel);
        $ben = User::factory()->create();
        $clara = User::factory()->create();

        $this->actingAs($ben)->post('/duels/join', ['code' => strtolower(str_replace('-', ' ', $code))])->assertRedirect("/duels/{$duel->id}");
        // Again: just leads there.
        $this->post('/duels/join', ['code' => $code])->assertRedirect("/duels/{$duel->id}");

        $this->actingAs($clara)->post('/duels/join', ['code' => $code])
            ->assertRedirect('/duels')
            ->assertSessionHasErrors(['code' => 'Das Duell ist schon voll.'], null, 'duel');

        $player = $duel->players()->where('user_id', $ben->id)->sole();
        $this->actingAs($anna)->post("/duels/{$duel->id}/players/{$player->id}/delete")->assertRedirect("/duels/{$duel->id}");
        $this->actingAs($clara)->post('/duels/join', ['code' => $code])->assertRedirect("/duels/{$duel->id}");
        $this->actingAs($ben)->get("/duels/{$duel->id}")->assertNotFound();
    });

    it('weist falsche und abgelaufene Codes ab', function () {
        $anna = actingAsUser();
        $duel = makeDuel(duelDeck($anna));
        $code = duelCode($duel);
        $this->actingAs(User::factory()->create());

        $this->post('/duels/join', ['code' => 'ABCD-EFGH'])->assertSessionHasErrors('code', null, 'duel');
        // 24 hours to play, 24 hours to see the result: then the duel is gone.
        $this->travel(49)->hours();
        $this->post('/duels/join', ['code' => $code])->assertSessionHasErrors('code', null, 'duel');
    });

    it('bremst Code-Eingaben nach 10 Versuchen', function () {
        actingAsUser();
        foreach (range(1, 10) as $i) {
            $this->post('/duels/join', ['code' => 'ABCD-EFGH']);
        }
        $this->post('/duels/join', ['code' => 'ABCD-EFGH'])->assertStatus(429);
    });
});

describe('Spielen', function () {
    it('wertet Auswahl richtig und falsch und zeigt die richtige Antwort', function () {
        $ich = actingAsUser();
        $duel = makeDuel(duelDeck($ich));
        $base = "/duels/{$duel->id}";

        $this->get("{$base}/play")->assertSee('1 / 10')->assertSee(duelCard($duel, 1)['choices'][0]);
        duelAnswer($base, $duel, 1, true)->assertSee('Richtig!');
        duelAnswer($base, $duel, 2, false)->assertSee('Richtig ist:')->assertSee(duelCard($duel, 2)['answers'][0]);

        expect($duel->players()->sole())->correct->toBe(1)->position->toBe(2);
    });

    it('wertet beim Eintippen tolerant; „Fast!“ zählt', function () {
        $ich = actingAsUser();
        $duel = makeDuel(duelDeck($ich), 2, 'typed');
        $base = "/duels/{$duel->id}";
        $answer = duelCard($duel, 1)['answers'][0];

        duelAnswer($base, $duel, 1, strtoupper($answer))->assertSee('Richtig!');
        duelAnswer($base, $duel, 2, substr(duelCard($duel, 2)['answers'][1], 0, -1).'x')->assertSee('Fast! Zählt als richtig.');
        $this->withHeaders(['HX-Request' => 'true'])->post("{$base}/answer", ['step' => '3', 'skip' => '1'])->assertSee('Richtig ist:');

        expect($duel->players()->sole()->correct)->toBe(2);
    });

    it('wertet nur die Karte, die dran ist, und keine zweimal', function () {
        $ich = actingAsUser();
        $duel = makeDuel(duelDeck($ich));
        $base = "/duels/{$duel->id}";

        duelAnswer($base, $duel, 1, true);
        $this->withHeaders(['HX-Request' => 'true'])->post("{$base}/answer", ['step' => '1', 'choice' => '0']);
        $this->post("{$base}/answer", ['step' => '5', 'choice' => '0']);

        expect($duel->players()->sole())->correct->toBe(1)->position->toBe(1);
    });

    it('misst die Zeit je Karte auf dem Server, höchstens 60 Sekunden', function () {
        $ich = actingAsUser();
        $duel = makeDuel(duelDeck($ich));
        $base = "/duels/{$duel->id}";

        $this->get("{$base}/play");
        $this->travel(5)->seconds();
        duelAnswer($base, $duel, 1, true);
        $this->get("{$base}/play");
        $this->travel(10)->minutes();
        $this->withHeaders(['HX-Request' => 'true'])->post("{$base}/answer", ['step' => '2', 'choice' => (string) duelCard($duel, 2)['correct_choice']]);

        expect($duel->players()->sole()->time_ms)->toBe(65_000);
    });

    it('macht nach einem Abbruch an derselben Karte weiter', function () {
        $ich = actingAsUser();
        $duel = makeDuel(duelDeck($ich));
        $base = "/duels/{$duel->id}";

        duelAnswer($base, $duel, 1, true);
        duelAnswer($base, $duel, 2, true);
        $this->flushHeaders()->get($base)->assertSee('Weiterspielen (2 / 10)');
        $this->get("{$base}/play")->assertSee('3 / 10');
    });

    it('zählt richtige Antworten fürs Tagesziel, nicht für den Karteikasten', function () {
        $ich = actingAsUser();
        $duel = makeDuel(duelDeck($ich));

        playDuel("/duels/{$duel->id}", $duel, 7);

        expect(DB::table('study_days')->where('user_id', $ich->id)->value('answers'))->toBe(7)
            ->and(CardProgress::count())->toBe(0);
    });
});

describe('Ergebnis', function () {
    it('beendet das Duell, sobald alle fertig sind, und kürt nach Richtigen, dann nach Zeit', function () {
        $anna = actingAsUser(['name' => 'Anna']);
        $duel = makeDuel(duelDeck($anna), 3);
        $code = duelCode($duel);
        $ben = User::factory()->create(['name' => 'Ben']);
        $clara = User::factory()->create(['name' => 'Clara']);
        $this->actingAs($ben)->post('/duels/join', ['code' => $code]);
        $this->actingAs($clara)->post('/duels/join', ['code' => $code]);

        $this->actingAs($anna);
        playDuel("/duels/{$duel->id}", $duel, 8, 3);
        $this->flushHeaders()->get("/duels/{$duel->id}")->assertSee('Warten auf: Ben, Clara')->assertDontSee('gewinnt');

        $this->actingAs($ben);
        playDuel("/duels/{$duel->id}", $duel, 8, 2);
        $this->actingAs($clara);
        playDuel("/duels/{$duel->id}", $duel, 6, 1);

        expect($duel->fresh()->isFinished())->toBeTrue();
        $this->flushHeaders()->get("/duels/{$duel->id}")
            ->assertSee('🏆 Ben gewinnt!')
            ->assertSeeInOrder(['Ben', '8 / 10 richtig', 'Anna', '8 / 10 richtig', 'Clara', '6 / 10 richtig'])
            ->assertDontSee('Code zum Mitmachen')
            ->assertSee('auch mit dem Code');
        // The code no longer lets anybody in, it only shows the result.
        $this->actingAs(User::factory()->create())->post('/duels/join', ['code' => $code])->assertRedirect("/duell/ergebnis/{$duel->id}");
        $this->get("/duell/ergebnis/{$duel->id}")->assertOk()->assertSee('🏆 Ben gewinnt!');
        expect($duel->players()->count())->toBe(3);
    });

    it('endet nach 24 Stunden, auch wenn nicht alle gespielt haben', function () {
        $anna = actingAsUser(['name' => 'Anna']);
        $duel = makeDuel(duelDeck($anna), 3);
        $ben = User::factory()->create(['name' => 'Ben']);
        $this->actingAs($ben)->post('/duels/join', ['code' => duelCode($duel)]);
        $this->actingAs($anna);
        playDuel("/duels/{$duel->id}", $duel, 5);

        $this->travel(25)->hours();
        $this->flushHeaders()->get("/duels/{$duel->id}")->assertSee('Niemand hat mitgespielt.')->assertSee('nicht fertig');

        $this->actingAs($ben)->get("/duels/{$duel->id}/play")->assertRedirect("/duels/{$duel->id}");
    });

    it('kennt ein Unentschieden', function () {
        $anna = actingAsUser(['name' => 'Anna']);
        $duel = makeDuel(duelDeck($anna), 2);
        $ben = User::factory()->create(['name' => 'Ben']);
        $this->actingAs($ben)->post('/duels/join', ['code' => duelCode($duel)]);
        playDuel("/duels/{$duel->id}", $duel, 5, 2);
        $this->actingAs($anna);
        playDuel("/duels/{$duel->id}", $duel, 5, 2);

        $this->flushHeaders()->get("/duels/{$duel->id}")->assertSee('Unentschieden: ');
    });

    it('wertet eine abgebrochene Runde nicht: wer nicht fertig spielt, gewinnt nicht', function () {
        $anna = actingAsUser(['name' => 'Anna']);
        $duel = makeDuel(duelDeck($anna), 2);
        $ben = User::factory()->create(['name' => 'Ben']);
        $this->actingAs($ben)->post('/duels/join', ['code' => duelCode($duel)]);
        foreach (range(1, 5) as $step) {
            duelAnswer("/duels/{$duel->id}", $duel, $step, true);
            $this->flushHeaders();
        }
        $this->actingAs($anna);
        playDuel("/duels/{$duel->id}", $duel, 2);

        $this->travel(25)->hours();
        $this->flushHeaders()->get("/duels/{$duel->id}")
            ->assertSee('🏆 Anna gewinnt!')
            ->assertSeeInOrder(['Anna', '2 / 10 richtig', 'Ben', 'nicht fertig']);
    });

    it('lässt nach dem Ende niemanden mehr entfernen', function () {
        $anna = actingAsUser();
        $duel = makeDuel(duelDeck($anna), 2);
        $ben = User::factory()->create();
        $this->actingAs($ben)->post('/duels/join', ['code' => duelCode($duel)]);
        playDuel("/duels/{$duel->id}", $duel, 9);
        $this->actingAs($anna);
        playDuel("/duels/{$duel->id}", $duel, 2);
        $player = $duel->players()->where('user_id', $ben->id)->sole();

        $this->flushHeaders()->post("/duels/{$duel->id}/players/{$player->id}/delete")->assertNotFound();
        expect($duel->players()->count())->toBe(2);
    });

    it('führt Teilnehmende mit dem Code auch nach dem Ende zum Duell', function () {
        $anna = actingAsUser();
        $duel = makeDuel(duelDeck($anna), 2);
        $code = duelCode($duel);
        $this->travel(25)->hours();

        $this->post('/duels/join', ['code' => $code])->assertRedirect("/duels/{$duel->id}");
    });

});

describe('Runden', function () {
    it('spielt mehrere Runden ohne Wiederholung: Rangliste je Runde, die Erstellerin startet die nächste', function () {
        $anna = actingAsUser(['name' => 'Anna']);
        $deck = duelDeck($anna, 30);
        $duel = makeDuel($deck, 2, 'choice', 3);
        $ben = User::factory()->create(['name' => 'Ben']);
        $this->actingAs($ben)->post('/duels/join', ['code' => duelCode($duel)]);
        $base = "/duels/{$duel->id}";
        $cardIds = fn (int $round) => DB::table('duel_cards')->where('duel_id', $duel->id)->where('round', $round)->pluck('card_id')->all();

        expect(DB::table('duel_cards')->where('duel_id', $duel->id)->count())->toBe(30)
            ->and(array_unique([...$cardIds(1), ...$cardIds(2), ...$cardIds(3)]))->toHaveCount(30);

        // Round 1.
        $this->get($base)->assertSee('Runde 1 von 3')->assertSee("Los geht's – Runde 1");
        playDuel($base, $duel, 6, 2);
        $this->actingAs($anna);
        playDuel($base, $duel, 8, 3);
        $this->flushHeaders()->get($base)
            ->assertSee('Runde 1 ist fertig!')
            ->assertSee('Runde 2 starten')
            ->assertSeeInOrder(['Runde 1', 'Anna', '8 / 10 richtig', 'Ben', '6 / 10 richtig', 'Zwischenstand']);
        $this->actingAs($ben)->get($base)->assertSee('Anna startet gleich Runde 2.')->assertDontSee('Runde 2 starten');
        $this->post("{$base}/next-round")->assertNotFound();
        $this->get("{$base}/play")->assertRedirect($base);

        // Round 2: the creator starts it, the words are new, progress counts from 1.
        $this->actingAs($anna)->post("{$base}/next-round")->assertRedirect($base)
            ->assertSessionHas('toast.message', 'Runde 2 läuft – jetzt können alle spielen.');
        $this->get("{$base}/play")->assertSee('1 / 10')->assertSee('Runde 2 von 3');
        playDuel($base, $duel, 2, 2);
        $this->actingAs($ben);
        playDuel($base, $duel, 10, 2);
        $this->actingAs($anna)->post("{$base}/next-round");

        // Round 3, then the end: totals decide.
        playDuel($base, $duel, 5, 2);
        $this->actingAs($ben);
        playDuel($base, $duel, 5, 2);

        expect($duel->fresh()->isFinished())->toBeTrue();
        $this->flushHeaders()->get($base)
            ->assertSee('🏆 Ben gewinnt!')
            ->assertSeeInOrder(['Runde 3', 'Gesamt', 'Ben', '21 richtig', 'Anna', '15 richtig'])
            ->assertDontSee('Runde 4 starten');
        $this->actingAs($anna)->post("{$base}/next-round")->assertNotFound();
    });

    it('beendet eine Runde nach 24 Stunden und das Duell, wenn keine neue Runde startet', function () {
        $anna = actingAsUser(['name' => 'Anna']);
        $duel = makeDuel(duelDeck($anna, 30), 2, 'choice', 3);
        $ben = User::factory()->create(['name' => 'Ben']);
        $this->actingAs($ben)->post('/duels/join', ['code' => duelCode($duel)]);
        $base = "/duels/{$duel->id}";
        playDuel($base, $duel, 7);
        $this->actingAs($anna);
        duelAnswer($base, $duel, 1, true);
        $this->flushHeaders();

        // Round 1 ran out: Anna did not finish it.
        $this->travel(25)->hours();
        $this->get($base)->assertSee('Runde 1 ist fertig!')->assertSeeInOrder(['Ben', '7 / 10 richtig', 'Anna', 'nicht fertig']);

        // Round 2 starts: Anna skips the rest of round 1.
        $this->post("{$base}/next-round");
        $this->get("{$base}/play")->assertSee('1 / 10');
        expect($duel->players()->where('user_id', $anna->id)->value('position'))->toBe(10);

        // Nobody finishes round 2, and nobody starts round 3: the duel ends.
        $this->travel(25)->hours();
        $this->get($base)->assertSee('Runde 2 ist fertig!');
        $this->travel(25)->hours();
        $this->get($base)->assertSee('🏆 Ben gewinnt!');
        expect($duel->fresh()->isFinished())->toBeTrue();
    });

    it('lässt nur in Runde 1 einsteigen', function () {
        $anna = actingAsUser();
        $duel = makeDuel(duelDeck($anna, 30), 3, 'choice', 2);
        $code = duelCode($duel);
        $ben = User::factory()->create();
        $this->actingAs($ben)->post('/duels/join', ['code' => $code]);
        $this->actingAs($anna);
        $this->travel(25)->hours();
        $this->get("/duels/{$duel->id}");
        $this->post("/duels/{$duel->id}/next-round");

        $this->actingAs(User::factory()->create())->post('/duels/join', ['code' => $code])
            ->assertSessionHasErrors(['code' => 'Das Duell läuft schon – einsteigen geht nur in Runde 1.'], null, 'duel');
    });

    it('wiederholt Wörter erst, wenn der Stapel durch ist, und sagt das', function () {
        $anna = actingAsUser();
        $duel = makeDuel(duelDeck($anna, 15), 2, 'choice', 3);
        $round = fn (int $r) => DB::table('duel_cards')->where('duel_id', $duel->id)->where('round', $r)->pluck('card_id')->all();

        expect($duel->repeat_from_round)->toBe(2)
            ->and($round(2))->toHaveCount(10)
            ->and(array_diff(array_diff(Card::pluck('id')->all(), $round(1)), $round(2)))->toBe([])
            ->and(array_unique($round(2)))->toHaveCount(10);

        $this->travel(25)->hours();
        $this->get("/duels/{$duel->id}");
        $this->post("/duels/{$duel->id}/next-round");
        $this->get("/duels/{$duel->id}")->assertSee('ab Runde 2 kommen sie wieder');
    });

    it('prüft die Rundenzahl', function (string $rounds) {
        $ich = actingAsUser();
        $deck = duelDeck($ich);

        $this->post("/decks/{$deck->id}/duels", ['players' => '2', 'mode' => 'choice', 'rounds' => $rounds])->assertSessionHasErrors(['rounds' => 'Bitte 1 bis 5 Runden wählen.']);
    })->with(['0', '6', 'drei']);

    it('bleibt spielbar, wenn der Stapel nach dem Anlegen gelöscht wird', function () {
        $anna = actingAsUser();
        $deck = duelDeck($anna, 30);
        $duel = makeDuel($deck, 2, 'choice', 2);
        $this->post("/decks/{$deck->id}/delete");

        $this->travel(25)->hours();
        $this->get("/duels/{$duel->id}");
        $this->post("/duels/{$duel->id}/next-round")->assertRedirect();
        $this->get("/duels/{$duel->id}/play")->assertOk()->assertSee('Runde 2 von 2');
    });

    it('wertet ab Runde 2 nicht, wer nie gespielt hat', function () {
        $anna = actingAsUser(['name' => 'Anna']);
        $duel = makeDuel(duelDeck($anna, 30), 2, 'choice', 2);
        $this->actingAs(User::factory()->create(['name' => 'Ben']))->post('/duels/join', ['code' => duelCode($duel)]);
        $base = "/duels/{$duel->id}";
        $this->actingAs($anna);
        playDuel($base, $duel, 6);
        $this->travel(25)->hours();
        $this->flushHeaders()->get($base);
        $this->post("{$base}/next-round");
        playDuel($base, $duel, 6);

        $this->travel(25)->hours();
        $this->flushHeaders()->get($base)
            ->assertSee('Niemand hat mitgespielt.')
            ->assertSeeInOrder(['Anna', '12 richtig', 'Ben', 'nicht fertig'])
            ->assertDontSee('0:00 min');
    });

    it('beendet die Runde, wenn die Erstellerin den Letzten entfernt, der noch spielt', function () {
        $anna = actingAsUser(['name' => 'Anna']);
        $duel = makeDuel(duelDeck($anna, 30), 3, 'choice', 3);
        $code = duelCode($duel);
        $ben = User::factory()->create();
        $clara = User::factory()->create();
        $base = "/duels/{$duel->id}";
        $this->actingAs($ben)->post('/duels/join', ['code' => $code]);
        $this->actingAs($clara)->post('/duels/join', ['code' => $code]);
        foreach ([$ben, $clara, $anna] as $user) {
            $this->actingAs($user);
            playDuel($base, $duel, 5);
        }
        $this->flushHeaders()->post("{$base}/next-round");
        foreach ([$ben, $anna] as $user) {
            $this->actingAs($user);
            playDuel($base, $duel, 5);
        }
        expect($duel->fresh()->isBetweenRounds())->toBeFalse();

        $player = $duel->players()->where('user_id', $clara->id)->sole();
        $this->flushHeaders()->post("{$base}/players/{$player->id}/delete")->assertRedirect();
        expect($duel->fresh()->isBetweenRounds())->toBeTrue();
        $this->get($base)->assertSee('Runde 2 ist fertig!')->assertSee('Runde 3 starten');
    });

    it('meldet eine zu spät gestartete Runde, statt ins Leere zu führen', function () {
        $anna = actingAsUser();
        $duel = makeDuel(duelDeck($anna, 30), 2, 'choice', 2);
        $base = "/duels/{$duel->id}";
        $this->travel(25)->hours();
        $this->get($base)->assertSee('Runde 2 starten');

        // The page stays open while the time to start round 2 runs out.
        $this->travel(24)->hours();
        $this->post("{$base}/next-round")->assertRedirect($base)
            ->assertSessionHasErrors(['round' => 'Die Zeit für Runde 2 ist abgelaufen – das Duell ist beendet.']);
        $this->followingRedirects()->post("{$base}/next-round")
            ->assertSee('Die Zeit für Runde 2 ist abgelaufen')->assertDontSee('Runde 2 starten');
    });
});

describe('Gäste', function () {
    it('lässt Gäste ohne Konto mit Code und Name spielen und später über ihren Link zurückkommen', function () {
        $anna = actingAsUser(['name' => 'Anna']);
        $duel = makeDuel(duelDeck($anna), 2);
        $code = duelCode($duel);
        auth()->logout();
        $users = User::count();

        $this->get('/duell')->assertOk()->assertSee('Dein Name')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertHeader('Referrer-Policy', 'no-referrer');
        $response = $this->post('/duell', ['code' => $code, 'name' => '  Mia ']);
        $base = $response->headers->get('Location');
        expect($base)->toMatch('#/duell/[A-Za-z0-9_-]{43}$#')
            ->and(User::count())->toBe($users);
        $base = parse_url($base, PHP_URL_PATH);

        $this->get($base)->assertOk()->assertSee('Heb dir diesen Link auf')->assertSee('Mia (Gast)')->assertSee('Anna');
        // The session keeps the link only sealed.
        expect(session('duel_guest_links'))->toHaveCount(1)
            ->and(session('duel_guest_links')[0])->not->toContain(basename($base));
        playDuel($base, $duel, 6);
        $this->flushHeaders()->get($base)->assertSee('6 / 10 richtig');
        $this->get('/duell')->assertSee('Deine Duelle in diesem Browser')->assertSee('Tiere');

        // Somebody else, later, with the link only.
        $this->flushSession();
        $this->get($base)->assertOk()->assertSee('Mia (Gast)');
        $this->get('/duell/'.str_repeat('a', 43))->assertNotFound();
        expect(DB::table('study_days')->count())->toBe(0);
    });

    it('prüft den Namen', function (string $name) {
        $anna = actingAsUser();
        $code = duelCode(makeDuel(duelDeck($anna), 5));
        auth()->logout();

        $this->from('/duell')->post('/duell', ['code' => $code, 'name' => $name])->assertSessionHasErrors('name');
        expect(DuelPlayer::whereNotNull('guest_name')->count())->toBe(0);
    })->with(['M', str_repeat('x', 21), "Mi\u{0007}a"]);

    it('bremst Gäste nach 30 Versuchen je IP', function () {
        foreach (range(1, 30) as $i) {
            $this->post('/duell', ['code' => 'ABCD-EFGH', 'name' => 'Mia']);
        }
        $this->post('/duell', ['code' => 'ABCD-EFGH', 'name' => 'Mia'])->assertStatus(429);
    });

    it('legt bei doppeltem Tippen keinen zweiten Gast an und meldet ein volles Duell', function () {
        $anna = actingAsUser();
        $duel = makeDuel(duelDeck($anna), 2);
        $code = duelCode($duel);
        auth()->logout();

        $first = $this->post('/duell', ['code' => $code, 'name' => 'Mia'])->headers->get('Location');
        $second = $this->post('/duell', ['code' => $code, 'name' => 'Mia'])->headers->get('Location');
        expect($second)->toBe($first)->and(DuelPlayer::whereNotNull('guest_name')->count())->toBe(1);

        $this->flushSession();
        $this->from('/duell')->post('/duell', ['code' => $code, 'name' => 'Max'])
            ->assertSessionHasErrors(['code' => 'Das Duell ist schon voll.']);
    });

    it('lässt die Erstellerin Gäste entfernen; ihr Link führt danach ins Leere', function () {
        $anna = actingAsUser();
        $duel = makeDuel(duelDeck($anna), 3);
        $code = duelCode($duel);
        auth()->logout();
        $base = parse_url($this->post('/duell', ['code' => $code, 'name' => 'Fremd'])->headers->get('Location'), PHP_URL_PATH);
        $guest = DuelPlayer::whereNotNull('guest_name')->sole();

        $this->actingAs($anna)->get("/duels/{$duel->id}")->assertSee('Fremd (Gast)')->assertSee('Entfernen');
        $this->post("/duels/{$duel->id}/players/{$guest->id}/delete")
            ->assertSessionHas('toast.message', 'Fremd (Gast) ist nicht mehr dabei.');
        auth()->logout();
        $this->get($base)->assertNotFound();
    });

    it('schickt Angemeldete von /duell zu ihren Duellen', function () {
        actingAsUser();

        $this->get('/duell')->assertRedirect('/duels');
    });
});

describe('Trennung und Aufräumen', function () {
    it('zeigt Duelle nur Teilnehmenden, auch Admins nicht; ungültige IDs ergeben 404', function () {
        $anna = actingAsUser();
        $duel = makeDuel(duelDeck($anna));
        $ben = User::factory()->create();
        $this->actingAs($ben)->post('/duels/join', ['code' => duelCode($duel)]);
        $player = $duel->players()->where('user_id', $ben->id)->sole();

        $this->actingAs(User::factory()->admin()->create())->get("/duels/{$duel->id}")->assertNotFound();
        $this->get('/duels/abc')->assertNotFound();
        // Ben plays along but may not remove anybody, and does not see Anna's private deck.
        $this->actingAs($ben)->post("/duels/{$duel->id}/players/{$player->id}/delete")->assertNotFound();
        $this->get("/decks/{$duel->deck_id}")->assertNotFound();
    });

    it('zeigt das Ergebnis 24 Stunden nach dem Ende auch unter dem Code und löscht das Duell danach', function () {
        $anna = actingAsUser(['name' => 'Anna']);
        $duel = makeDuel(duelDeck($anna), 2);
        $code = duelCode($duel);
        $ben = User::factory()->create(['name' => 'Ben']);
        $this->actingAs($ben)->post('/duels/join', ['code' => $code]);
        playDuel("/duels/{$duel->id}", $duel, 7);
        $this->actingAs($anna);
        playDuel("/duels/{$duel->id}", $duel, 4);
        $this->flushHeaders();

        $this->travel(23)->hours();
        // Somebody else, with the code only: the result, without joining.
        $this->actingAs(User::factory()->create())->post('/duels/join', ['code' => $code])->assertRedirect("/duell/ergebnis/{$duel->id}");
        $this->get("/duell/ergebnis/{$duel->id}")
            ->assertOk()->assertSee('🏆 Ben gewinnt!')->assertSee('Das Ergebnis ist bis')
            ->assertDontSee("Los geht's")->assertDontSee('Entfernen')->assertDontSee('starten');
        expect($duel->players()->count())->toBe(2);
        auth()->logout();
        $this->flushSession();
        // Without entering the code in this session: nothing.
        $this->get("/duell/ergebnis/{$duel->id}")->assertNotFound();
        $this->post('/duell', ['code' => $code])->assertRedirect("/duell/ergebnis/{$duel->id}");
        $this->get("/duell/ergebnis/{$duel->id}")->assertOk()->assertSee('🏆 Ben gewinnt!')->assertDontSee('Heb dir diesen Link auf');
        $this->actingAs($anna)->get('/duels')->assertSee('Tiere');

        $this->travel(2)->hours();
        $this->get('/duels')->assertSee('Noch keine Duelle.');
        expect(Duel::find($duel->id))->toBeNull()
            ->and(DuelPlayer::count())->toBe(0)
            ->and(DB::table('duel_cards')->count())->toBe(0);
        $this->get("/duels/{$duel->id}")->assertNotFound();
        $this->post('/duels/join', ['code' => $code])->assertSessionHasErrors('code', null, 'duel');
    });

    it('zeigt ein abgelaufenes Duell noch bis 24 Stunden nach dem Ende', function () {
        $anna = actingAsUser();
        $duel = makeDuel(duelDeck($anna), 3);

        $this->travel(47)->hours();
        $this->get("/duels/{$duel->id}")->assertOk()->assertSee('Das Ergebnis ist bis');
        $this->travel(2)->hours();
        $this->get("/duels/{$duel->id}")->assertNotFound();
    });

    it('führt einen Gast mit dem Code seines beendeten Duells zu seinem Link', function () {
        $anna = actingAsUser();
        $duel = makeDuel(duelDeck($anna), 2);
        $code = duelCode($duel);
        auth()->logout();
        $link = parse_url($this->post('/duell', ['code' => $code, 'name' => 'Mia'])->headers->get('Location'), PHP_URL_PATH);

        $this->travel(25)->hours();
        $this->post('/duell', ['code' => $code])->assertRedirect(url($link));
    });

    it('löscht ein abgelaufenes Duell, das niemand mehr angesehen hat, 24 Stunden später', function () {
        $anna = actingAsUser();
        $duel = makeDuel(duelDeck($anna));

        $this->travel(49)->hours();
        $this->get('/duels')->assertOk()->assertSee('Noch keine Duelle.');
        expect(Duel::find($duel->id))->toBeNull();
    });

    it('löscht ein Duell mit Runden, das niemand mehr angesehen hat, erst nach Pause und Ergebniszeit', function () {
        $anna = actingAsUser();
        $unseen = makeDuel(duelDeck($anna, 30), 2, 'choice', 3);
        $waiting = makeDuel(duelDeck($anna, 30), 2, 'choice', 3);
        $this->actingAs(User::factory()->create())->post('/duels/join', ['code' => duelCode($waiting)]);
        playDuel("/duels/{$waiting->id}", $waiting, 5);
        $this->actingAs($anna);
        playDuel("/duels/{$waiting->id}", $waiting, 5);
        expect($waiting->fresh()->isBetweenRounds())->toBeTrue();
        // Somebody else's list prunes, but looks at neither duel.
        $this->actingAs(User::factory()->create());

        // Round 1 ends after 24 hours, then 24 to start round 2, then 24 for the result.
        $this->travel(47)->hours();
        $this->flushHeaders()->get('/duels');
        expect(Duel::find($waiting->id))->not->toBeNull();
        $this->travel(2)->hours();
        $this->get('/duels');
        expect(Duel::find($waiting->id))->toBeNull()
            ->and(Duel::find($unseen->id))->not->toBeNull();
        $this->travel(22)->hours();
        $this->get('/duels');
        expect(Duel::find($unseen->id))->not->toBeNull();
        $this->travel(2)->hours();
        $this->get('/duels');
        expect(Duel::find($unseen->id))->toBeNull();
    });

    it('lässt das Duell bestehen, wenn der Stapel gelöscht wird', function () {
        $anna = actingAsUser();
        $deck = duelDeck($anna);
        $duel = makeDuel($deck);

        $this->post("/decks/{$deck->id}/delete");

        $this->get("/duels/{$duel->id}")->assertOk()->assertSee('Duell: Tiere');
        $this->get("/duels/{$duel->id}/play")->assertOk();
    });
});
