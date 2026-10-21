<?php

use App\Models\Card;
use App\Models\Deck;
use App\Models\User;
use App\Services\GuardianService;

/*
 * One rule for "Zurück" (docs/specs/navigation.md) and room at the bottom
 * edge (issue #19).
 */

function navDeck(User $owner): Deck
{
    $deck = Deck::factory()->for($owner)->create(['name' => 'Englisch Unit 3']);
    Card::factory()->for($deck)->create();

    return $deck;
}

it('führt von der Stapelseite dorthin zurück, woher man kam, und markiert diesen Bereich', function (string $from, string $label) {
    $deck = navDeck(actingAsUser());

    $this->get($from)->assertOk();
    $html = $this->get("/decks/{$deck->id}")
        ->assertSee('href="'.$from.'" class="back-link">← '.$label.'</a>', false)
        ->getContent();

    // The tab bar marks the section one came from, not "Stapel" by path.
    expect($html)->toContain('href="'.explode('?', $from)[0].'" class="tab tab-active"');
})->with([
    'Heute' => ['/learn', 'Heute'],
    'Stapel' => ['/decks', 'Stapel'],
    'Entdecken' => ['/decks?tab=discover', 'Stapel'],
    'Wörterbuch' => ['/dictionary', 'Wörterbuch'],
]);

it('führt ohne gemerkten Bereich zu „Stapel“', function () {
    $deck = navDeck(actingAsUser());

    $this->get("/decks/{$deck->id}")->assertSee('href="/decks" class="back-link">← Stapel</a>', false);
});

it('merkt sich keine Fragmente und keine Unterseiten', function () {
    $deck = navDeck(actingAsUser());

    $this->get('/learn');
    $this->withHeaders(['HX-Request' => 'true'])->get('/decks?tab=discover');
    $this->flushHeaders()->get("/decks/{$deck->id}/selection");

    $this->get("/decks/{$deck->id}")->assertSee('← Heute');
});

it('beendet eine Runde dort, wo sie gestartet wurde', function () {
    $deck = navDeck(actingAsUser());

    $this->post('/learn/round', ['mode' => 'test', 'deck' => $deck->id]);
    $this->get('/learn/round')->assertSee('href="/decks/'.$deck->id.'" class="btn btn-ghost btn-icon shrink-0" aria-label="Runde beenden"', false);

    $this->post('/learn/round');
    $this->get('/learn/round')->assertSee('href="/learn" class="btn btn-ghost btn-icon shrink-0" aria-label="Runde beenden"', false);
});

it('zeigt bei mehreren Kindern den Weg zurück zur Liste, bei einem nicht', function () {
    $mama = actingAsUser();
    $ben = User::factory()->create();
    $guardians = app(GuardianService::class);
    $code = fn (User $child) => str_replace('-', '', $guardians->createCode($child));
    $guardians->redeem($mama, $code($ben));

    $this->get("/children/{$ben->id}")->assertDontSee('← Meine Kinder');

    $guardians->redeem($mama, $code(User::factory()->create()));
    $this->get("/children/{$ben->id}")->assertSee('← Meine Kinder');
});

it('meldet iOS die Ränder, damit unten Abstand zur Wischleiste bleibt (Issue #19)', function () {
    actingAsUser();

    $this->get('/learn')->assertSee('viewport-fit=cover', false);
});

it('merkt sich keine Seite, die nicht angezeigt wurde', function () {
    $deck = navDeck(actingAsUser());

    $this->get('/decks');
    $this->get('/admin/users')->assertForbidden();

    $this->get("/decks/{$deck->id}")->assertSee('← Stapel')->assertDontSee('← Benutzer');
});

it('markiert „Mehr“ für Bereiche aus dem Menü und Seiten mit festem Platz', function () {
    $deck = navDeck(actingAsUser());

    $this->get('/help');
    expect($this->get("/decks/{$deck->id}")->getContent())->toContain('class="tab relative tab-active"');

    $this->get('/learn');
    expect($this->get('/learn/settings')->getContent())->toContain('class="tab relative tab-active"')
        ->not->toContain('href="/learn" class="tab tab-active"');
});

it('beendet auch „Fach üben“ und „Nur diesen Stapel lernen“ auf der Stapelseite', function () {
    $ich = actingAsUser();
    $deck = navDeck($ich);
    $exit = 'href="/decks/'.$deck->id.'" class="btn btn-ghost btn-icon shrink-0" aria-label="Runde beenden"';

    $this->post('/learn/round', ['deck' => $deck->id]);
    $this->get('/learn/round')->assertSee($exit, false);

    $this->post('/learn/round', ['mode' => 'practice', 'deck' => $deck->id, 'direction' => 'forward', 'box' => '0']);
    $this->get('/learn/round')->assertSee($exit, false);
});

it('bindet das Stylesheet mit Version ein, damit Handys nach einem Deploy das neue laden', function () {
    actingAsUser();

    $this->get('/learn')->assertSee('href="/css/app.css?v='.filemtime(public_path('css/app.css')).'"', false);
});
