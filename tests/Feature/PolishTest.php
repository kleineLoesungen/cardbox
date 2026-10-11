<?php

use App\Models\Card;
use App\Models\Deck;
use App\Models\User;
use App\Services\LeitnerService;
use Illuminate\Support\Facades\DB;

describe('Lernplan', function () {
    it('zeigt den aktuellen Plan und speichert eine Vorgabe', function () {
        $ich = actingAsUser();

        $this->get('/learn/settings')->assertOk()
            ->assertSee('Normal')->assertSee('Schnell')->assertSee('Gründlich')
            ->assertSee('value="normal" checked', false);

        $this->post('/learn/settings', ['plan' => 'quick'])->assertRedirect('/learn/settings');

        $row = DB::table('learning_settings')->where('user_id', $ich->id)->first();
        expect($row->box_count)->toBe(4)->and(json_decode($row->day_gaps))->toBe([1, 1, 2]);
        $this->get('/learn/settings')->assertSee('value="quick" checked', false);
    });

    it('speichert einen eigenen Plan und nimmt nur die ersten N−1 Abstände', function () {
        $ich = actingAsUser();

        $this->post('/learn/settings', [
            'plan' => 'custom', 'box_count' => '3', 'day_gaps' => ['2', '9', '99', '99'],
        ])->assertRedirect('/learn/settings');

        $row = DB::table('learning_settings')->where('user_id', $ich->id)->first();
        expect($row->box_count)->toBe(3)->and(json_decode($row->day_gaps))->toBe([2, 9]);
        $this->get('/learn/settings')->assertSee('value="custom" checked', false);
    });

    it('prüft den eigenen Plan', function (array $input, string $field) {
        actingAsUser();

        $this->from('/learn/settings')->post('/learn/settings', ['plan' => 'custom', ...$input])
            ->assertSessionHasErrors($field);
    })->with([
        'zu wenige Fächer' => [['box_count' => '1', 'day_gaps' => []], 'box_count'],
        'zu viele Fächer' => [['box_count' => '11', 'day_gaps' => array_fill(0, 10, '1')], 'box_count'],
        'Abstände fehlen' => [['box_count' => '4', 'day_gaps' => ['1', '2']], 'day_gaps'],
        'Abstand 0' => [['box_count' => '3', 'day_gaps' => ['0', '2']], 'day_gaps.0'],
        'Abstand zu groß' => [['box_count' => '3', 'day_gaps' => ['1', '366']], 'day_gaps.1'],
        'keine Zahl' => [['box_count' => '3', 'day_gaps' => ['eins', '2']], 'day_gaps.0'],
        'Liste statt Zahl' => [['box_count' => ['3'], 'day_gaps' => ['1', '2']], 'box_count'],
    ]);

    it('lehnt unbekannte Pläne ab', function () {
        actingAsUser();

        $this->from('/learn/settings')->post('/learn/settings', ['plan' => 'turbo'])->assertSessionHasErrors('plan');
    });

    it('wirkt auf die nächste Bewertung', function () {
        $ich = actingAsUser();
        $card = Card::factory()->for(Deck::factory()->for($ich))->create();
        $this->post('/learn/settings', ['plan' => 'custom', 'box_count' => '3', 'day_gaps' => ['5', '9']]);

        $this->post('/learn/round', ['deck' => $card->deck->id]);
        $this->withHeaders(['HX-Request' => 'true'])
            ->post('/learn/round/answer', ['card' => $card->id, 'step' => 0, 'known' => '1']);

        $due = DB::table('card_progress')->where('user_id', $ich->id)->value('due_on');
        expect(substr((string) $due, 0, 10))->toBe(app(LeitnerService::class)->today()->addDays(5)->toDateString());
    });
});

describe('Hilfe', function () {
    it('erklärt den Karteikasten mit dem eigenen Plan', function () {
        $ich = actingAsUser();
        DB::table('learning_settings')->insert(['user_id' => $ich->id, 'box_count' => 3, 'day_gaps' => json_encode([2, 9])]);

        $this->get('/help')->assertOk()
            ->assertSee('So funktioniert der Karteikasten')
            ->assertSee('9 Tage')
            ->assertSee('Folgen oder kopieren?')
            ->assertSee('Zum Home-Bildschirm');
    });

    it('ist nur für Angemeldete', function () {
        $this->get('/help')->assertRedirect('/login');
    });

    it('steht mit dem Lernplan im Menü', function () {
        actingAsUser();

        $this->get('/learn')->assertSee('href="/learn/settings"', false)->assertSee('href="/help"', false);
    });
});

describe('Rückfragen und Lernrunde', function () {
    it('benennt bei jeder Rückfrage die Handlung', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();
        $card = Card::factory()->for($deck)->create();

        $this->get("/decks/{$deck->id}")
            ->assertSee('data-confirm-action="Stapel löschen"', false)
            ->assertSee('data-confirm-action="Zurücksetzen"', false)
            ->assertSee('id="confirm-sheet"', false);
        $this->get("/decks/{$deck->id}/cards/{$card->id}/edit")
            ->assertSee('data-confirm-action="Karte löschen"', false);
    });

    it('bietet in der Lernrunde Vorlesen mit Sprache an, aber nicht bei Rechenkarten', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();
        Card::factory()->for($deck)->create(['front' => ['Haus'], 'back' => ['house', 'home']]);
        $rechnen = Deck::factory()->for($ich)->create(['front_language' => 'formula', 'back_language' => 'result']);
        Card::factory()->for($rechnen)->create(['front' => ['3·4'], 'back' => ['12']]);

        $this->post('/learn/round', ['deck' => $deck->id]);
        $this->get('/learn/round')
            ->assertSee('data-question="Haus" data-question-lang="de"', false)
            ->assertSee('data-answer="house, home" data-answer-lang="en"', false);

        $this->post('/learn/round', ['deck' => $rechnen->id]);
        $this->get('/learn/round')->assertSee('data-question-lang=""', false);
    });
});

describe('Liste einfügen', function () {
    it('behält den Text für „Zurück zur Liste“ und vergisst ihn nach dem Anlegen', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();

        $anderer = Deck::factory()->for($ich)->create();

        $this->post("/decks/{$deck->id}/import/preview", ['text' => "Haus = house\nkaputt"]);
        $this->get("/decks/{$deck->id}/import?back=1")->assertSee('kaputt');
        $this->get("/decks/{$deck->id}/import")->assertDontSee('kaputt');
        $this->get("/decks/{$anderer->id}/import?back=1")->assertDontSee('kaputt');

        $this->post("/decks/{$deck->id}/import", ['text' => 'Haus = house']);
        $this->get("/decks/{$deck->id}/import?back=1")->assertDontSee('kaputt');
    });
});

describe('Lernplan – Grenzfälle', function () {
    it('speichert genau an den Grenzen', function (string $count, array $gaps) {
        $ich = actingAsUser();

        $this->post('/learn/settings', ['plan' => 'custom', 'box_count' => $count, 'day_gaps' => $gaps])
            ->assertRedirect('/learn/settings')->assertSessionHasNoErrors();

        expect(DB::table('learning_settings')->where('user_id', $ich->id)->value('box_count'))->toBe((int) $count);
    })->with([
        '2 Fächer' => ['2', ['365']],
        '10 Fächer' => ['10', array_fill(0, 9, '1')],
    ]);

    it('verlangt einen Plan und meldet Fehler auf Deutsch', function () {
        actingAsUser();

        $this->from('/learn/settings')->post('/learn/settings', [])
            ->assertSessionHasErrors(['plan' => 'Bitte einen Lernplan wählen.']);
        $this->from('/learn/settings')->post('/learn/settings', ['plan' => 'custom', 'box_count' => ['3'], 'day_gaps' => ['1', '2']])
            ->assertSessionHasErrors(['box_count' => 'Die Anzahl der Fächer muss eine ganze Zahl sein.']);
    });

    it('nimmt bei einer Vorgabe deren Werte, egal was sonst mitkommt', function () {
        $ich = actingAsUser();

        $this->post('/learn/settings', ['plan' => 'thorough', 'box_count' => '99', 'day_gaps' => ['x']])
            ->assertSessionHasNoErrors();

        expect(json_decode(DB::table('learning_settings')->where('user_id', $ich->id)->value('day_gaps')))
            ->toBe([1, 2, 4, 7, 14]);
    });

    it('ändert nur den eigenen Plan', function () {
        $andere = User::factory()->create();
        DB::table('learning_settings')->insert(['user_id' => $andere->id, 'box_count' => 3, 'day_gaps' => json_encode([2, 9])]);
        actingAsUser();

        $this->post('/learn/settings', ['plan' => 'quick']);

        expect(DB::table('learning_settings')->where('user_id', $andere->id)->value('box_count'))->toBe(3);
    });

    it('ist nur für Angemeldete', function () {
        $this->get('/learn/settings')->assertRedirect('/login');
        $this->post('/learn/settings', ['plan' => 'quick'])->assertRedirect('/login');
    });

    it('zeigt nach einer verschachtelten Eingabe die Seite statt eines Serverfehlers', function () {
        actingAsUser();

        $this->from('/learn/settings')->post('/learn/settings', ['plan' => 'custom', 'box_count' => '3', 'day_gaps' => [['1'], '2']])
            ->assertRedirect('/learn/settings');
        $this->get('/learn/settings')->assertOk();
    });

    it('funktioniert ohne JavaScript mit einem Zahlenfeld für die Fächer', function () {
        actingAsUser();

        $this->get('/learn/settings')
            ->assertSee('type="number" inputmode="numeric" id="box-count" name="box_count"', false)
            ->assertSee('data-min="2" data-max="10"', false);
    });
});

describe('Hilfe – Zeichnung', function () {
    it('kommt mit zwei und mit zehn Fächern zurecht', function () {
        $ich = actingAsUser();
        DB::table('learning_settings')->insert(['user_id' => $ich->id, 'box_count' => 2, 'day_gaps' => json_encode([3])]);
        $this->get('/help')->assertOk()->assertDontSee('url(#arrow-back)', false);

        DB::table('learning_settings')->where('user_id', $ich->id)->update(['box_count' => 10, 'day_gaps' => json_encode(range(1, 9))]);
        app(LeitnerService::class)->forgetPlan($ich);
        $this->get('/help')->assertOk()->assertSee('Fach 9: 9 Tage')->assertSee('Fach 10: Kann ich');
    });
});

describe('Hilfe zum Vorlesen', function () {
    it('erklärt, wie man fehlende Stimmen installiert', function () {
        actingAsUser();

        $this->get('/help')
            ->assertSee('id="vorlesen"', false)
            ->assertSee('Vorlesen funktioniert nicht?')
            ->assertSee('Gesprochene Inhalte')
            ->assertSee('Sprachdaten installieren');
    });

    it('verlinkt aus der Lernrunde dorthin, falls eine Stimme fehlt', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create(['front_language' => 'la', 'back_language' => 'de']);
        Card::factory()->for($deck)->create(['front' => ['amicus'], 'back' => ['Freund']]);

        $this->post('/learn/round', ['deck' => $deck->id]);
        $this->get('/learn/round')->assertSee('href="/help#vorlesen"', false);
    });
});
