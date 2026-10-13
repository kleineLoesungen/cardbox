<?php

use App\Models\Deck;
use App\Models\User;

/*
 * Searching decks by name and description, e.g. an ISBN (issue #8), and the
 * user documentation of the CSV format (issue #7).
 */

describe('Stapel suchen', function () {
    it('findet in Entdecken die ISBN aus der Beschreibung, egal mit oder ohne Bindestriche', function (string $term) {
        actingAsUser();
        Deck::factory()->shared()->create(['name' => 'Green Line 1', 'description' => 'Vokabeln zum Buch, ISBN 978-3-12-834211-5']);
        Deck::factory()->shared()->create(['name' => 'Anderes Buch', 'description' => 'ISBN 978-3-12-000000-0']);

        $this->get('/decks?tab=discover&q='.urlencode($term))
            ->assertSee('Green Line 1')
            ->assertSee('ISBN 978-3-12-834211-5')
            ->assertDontSee('Anderes Buch');
    })->with(['978-3-12-834211-5', '9783128342115', '978 3 12 834211 5', '834211']);

    it('sucht weiter wörtlich, Platzhalter bleiben Text', function () {
        actingAsUser();
        Deck::factory()->shared()->create(['name' => '100 % Englisch']);
        Deck::factory()->shared()->create(['name' => '1000 Wörter']);

        $this->get('/decks?tab=discover&q='.urlencode('0 %'))->assertSee('100 % Englisch')->assertDontSee('1000 Wörter');
    });

    it('durchsucht unter Meine eigene und gefolgte Stapel nach Name und Beschreibung', function () {
        $ich = actingAsUser();
        Deck::factory()->for($ich)->create(['name' => 'Unit 3', 'description' => 'ISBN 978-3-12-834211-5']);
        Deck::factory()->for($ich)->create(['name' => 'Unit 4']);
        $gefolgt = Deck::factory()->shared()->create(['name' => 'Gefolgt', 'description' => 'ISBN 9783128342115']);
        $ich->followedDecks()->attach($gefolgt);
        Deck::factory()->shared()->create(['name' => 'Fremd', 'description' => 'ISBN 9783128342115']);

        $this->get('/decks?q=9783128342115')
            ->assertSee('Unit 3')->assertSee('Gefolgt')
            ->assertDontSee('Unit 4')->assertDontSee('Fremd');
        $this->get('/decks?q=gibtsnicht')->assertSee('Kein Stapel passt zu „gibtsnicht“', false);
    });

    it('zeigt das Suchfeld unter Meine erst, wenn es Stapel gibt', function () {
        $ich = actingAsUser();

        $this->get('/decks')->assertDontSee('Meine Stapel durchsuchen');
        Deck::factory()->for($ich)->create();
        $this->get('/decks')->assertSee('Meine Stapel durchsuchen');
    });
});

describe('Hilfe zum CSV-Format', function () {
    it('erklärt Kopfzeile, IDs, Bedeutungen und alle Sprachkürzel', function () {
        actingAsUser();

        $this->get('/help')
            ->assertSee('id="csv"', false)
            ->assertSee('ID;de;en;Hinweis')
            ->assertSee('Nichts wird gelöscht')
            ->assertSee('<code class="code">formula</code>', false)
            ->assertSee('<code class="code">ja</code>', false);
    });

    it('ist von „Liste einfügen“ aus verlinkt', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();

        $this->get("/decks/{$deck->id}/import")->assertSee('href="/help#csv"', false);
    });
});
