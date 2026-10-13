<?php

use App\Enums\Direction;
use App\Enums\Role;
use App\Enums\SuggestionStatus;
use App\Enums\SuggestionType;
use App\Models\Card;
use App\Models\CardProgress;
use App\Models\CardSuggestion;
use App\Models\Deck;
use App\Models\DeckRun;
use App\Models\User;
use App\Services\CardMergeService;
use App\Services\CardService;
use App\Services\DeckRunService;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

/*
 * Duplicate cards in shared decks: finding and merging
 * (docs/specs/woerterbuch-und-bereinigung.md, stage 3).
 */

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-14 08:00:00', 'UTC'));
});

function sharedDeck(?User $owner = null, string $front = 'de', string $back = 'en', array $attributes = []): Deck
{
    return Deck::factory()->for($owner ?? User::factory()->create())->shared()
        ->create(['front_language' => $front, 'back_language' => $back] + $attributes);
}

function cardIn(Deck $deck, array $front, array $back, ?string $hint = null): Card
{
    return Card::factory()->for($deck)->create(['front' => $front, 'back' => $back, 'hint' => $hint]);
}

function progressOn(User $user, Card $card, int $box, ?string $due, int $reviews = 1): CardProgress
{
    $run = DeckRun::where('user_id', $user->id)->where('deck_id', $card->deck_id)->first()
        ?? app(DeckRunService::class)->forStandard($user, $card->deck);

    return CardProgress::create(['user_id' => $user->id, 'deck_run_id' => $run->id, 'card_id' => $card->id,
        'direction' => Direction::Forward, 'box' => $box, 'due_on' => $due, 'last_reviewed_at' => now()->subDay(),
        'review_count' => $reviews, 'correct_count' => $reviews]);
}

/** Merge a group as the current user: keep one card, with the given content. */
function mergeGroup(Card $any, Card $keep, array $content, ?array $seen = null): TestResponse
{
    $seen ??= app(CardMergeService::class)->group($any->id)->modelKeys();

    return test()->from("/moderation/duplicates/{$any->id}")
        ->post("/moderation/duplicates/{$any->id}/merge", ['keep' => $keep->id, 'cards' => $seen] + $content);
}

describe('Finden', function () {
    it('gruppiert gleiche Wortpaare in geteilten Stapeln, auch umgekehrt, nie in privaten', function () {
        actingAsUser(['role' => Role::Moderator]);
        $a = cardIn(sharedDeck(), ['Hund'], ['dog']);
        cardIn(sharedDeck(), [' hund '], ['Dog', 'hound']);
        cardIn(sharedDeck(null, 'en', 'de'), ['dog'], ['Hund']);
        cardIn(Deck::factory()->create(['front_language' => 'de', 'back_language' => 'en']), ['Hund'], ['dog']);
        cardIn(sharedDeck(), ['Hund'], ['hound']);

        $this->get('/moderation/duplicates')->assertOk()->assertSee('3 in 3 Stapeln')->assertDontSee('hound');
        $this->get("/moderation/duplicates/{$a->id}")->assertOk()->assertSee('Welche Karte bleibt?');
    });

    it('meldet „alles aufgeräumt“ ohne Doppelte und 404 für Karten ohne Gruppe oder in privaten Stapeln', function () {
        actingAsUser(['role' => Role::Moderator]);
        $einzeln = cardIn(sharedDeck(), ['Katze'], ['cat']);
        $privat = cardIn(Deck::factory()->create(), ['Katze'], ['cat']);

        $this->get('/moderation/duplicates')->assertSee('Alles aufgeräumt');
        $this->get("/moderation/duplicates/{$einzeln->id}")->assertNotFound();
        $this->get("/moderation/duplicates/{$privat->id}")->assertNotFound();
        $this->get('/moderation/duplicates/abc')->assertNotFound();
    });

    it('ist nur für Moderation und Verwaltung', function () {
        $a = cardIn(sharedDeck(), ['Hund'], ['dog']);
        $b = cardIn(sharedDeck(), ['Hund'], ['dog']);

        actingAsUser();
        $this->get('/moderation/duplicates')->assertForbidden();
        mergeGroup($a, $a, ['front' => ['Hund'], 'back' => ['dog']], [$a->id, $b->id])->assertForbidden();
        expect(Card::count())->toBe(2);

        actingAsUser(['role' => Role::Admin]);
        $this->get('/moderation/duplicates')->assertOk();
    });
});

describe('Zusammenführen im selben Stapel', function () {
    it('löscht die anderen Karten und nimmt den weiter fortgeschrittenen Lernstand', function () {
        actingAsUser(['role' => Role::Moderator]);
        $deck = sharedDeck();
        $bleibt = cardIn($deck, ['Hund'], ['dog']);
        $geht = cardIn($deck, ['hund'], ['dog', 'hound']);
        [$ben, $cleo] = User::factory()->count(2)->create()->all();
        progressOn($ben, $bleibt, 2, '2026-10-15', 2);
        progressOn($ben, $geht, 4, '2026-10-20', 5);
        progressOn($cleo, $geht, 3, '2026-10-16', 3);

        mergeGroup($bleibt, $bleibt, ['front' => ['Hund'], 'back' => ['dog', 'hound'], 'hint' => ''])
            ->assertRedirect('/moderation/duplicates')
            ->assertSessionHas('toast.message', 'Zusammengeführt: 1 Karte gelöscht, Lernstand von 2 Personen übernommen.');

        expect(Card::find($geht->id))->toBeNull()
            ->and($bleibt->fresh()->back)->toBe(['dog', 'hound'])
            ->and(CardProgress::where('user_id', $ben->id)->sole())
            ->card_id->toBe($bleibt->id)->box->toBe(4)->review_count->toBe(7)
            ->and(CardProgress::where('user_id', $cleo->id)->sole())->card_id->toBe($bleibt->id)->box->toBe(3);
        $this->get('/moderation/duplicates')->assertSee('Alles aufgeräumt');
    });

    it('hängt Kopien und offene Vorschläge um, Doppelte werden abgelehnt', function () {
        actingAsUser(['role' => Role::Moderator]);
        $deck = sharedDeck();
        $bleibt = cardIn($deck, ['Hund'], ['dog']);
        $geht = cardIn($deck, ['Hund'], ['dog']);
        $kopie = cardIn(Deck::factory()->create(), ['Hund'], ['dog']);
        $kopie->forceFill(['source_card_id' => $geht->id, 'source_updated_at' => now()])->save();
        [$ben, $cleo] = User::factory()->count(2)->create()->all();
        $vorschlag = fn (User $u, Card $c) => tap(new CardSuggestion(['type' => SuggestionType::Other, 'comment' => 'x']))
            ->forceFill(['card_id' => $c->id, 'user_id' => $u->id, 'status' => SuggestionStatus::Open, 'card_updated_at' => now()])->save();
        $vorschlag($ben, $geht);
        $vorschlag($cleo, $geht);
        $vorschlag($cleo, $bleibt);

        mergeGroup($bleibt, $bleibt, ['front' => ['Hund'], 'back' => ['dog']]);

        expect($kopie->fresh()->source_card_id)->toBe($bleibt->id)
            ->and(CardSuggestion::where('user_id', $ben->id)->sole()->card_id)->toBe($bleibt->id)
            ->and(CardSuggestion::where('user_id', $cleo->id)->where('status', SuggestionStatus::Open)->sole()->card_id)->toBe($bleibt->id);
    });
});

describe('Zusammenführen über Stapel hinweg', function () {
    it('gleicht Karten in anderen Stapeln an und verknüpft sie, Lernstand bleibt', function () {
        actingAsUser(['role' => Role::Moderator]);
        $anna = User::factory()->create();
        $bea = User::factory()->create();
        $bleibt = cardIn(sharedDeck($anna), ['Bahnhof'], ['station']);
        $andere = cardIn(sharedDeck($bea), ['Bahnhof'], ['station', 'railway station'], '(Zug)');
        $umgekehrt = cardIn(sharedDeck($bea, 'en', 'de'), ['station'], ['Bahnhof']);
        $ben = User::factory()->create();
        progressOn($ben, $andere, 3, '2026-10-17');

        mergeGroup($bleibt, $bleibt, ['front' => ['Bahnhof'], 'back' => ['station', 'railway station'], 'hint' => '(Zug)'])
            ->assertSessionHas('toast.message', 'Zusammengeführt: 2 Karten verknüpft.');

        expect($andere->fresh())->source_card_id->toBe($bleibt->id)->back->toBe(['station', 'railway station'])
            ->and($umgekehrt->fresh())->source_card_id->toBe($bleibt->id)
            ->front->toBe(['station', 'railway station'])->back->toBe(['Bahnhof'])->hint->toBe('(Zug)')
            ->and(CardProgress::where('user_id', $ben->id)->sole())->card_id->toBe($andere->id)->box->toBe(3);
        $this->get('/moderation/duplicates')->assertSee('Alles aufgeräumt');

        // Later corrections reach the linked copies as "Original geändert".
        $this->travel(1)->hour();
        $bleibt->update(['back' => ['station', 'railway station', 'depot']]);
        expect(app(CardService::class)->pendingOriginalCount($bea, $andere->deck))->toBe(1);
    });
});

describe('Grenzfälle', function () {
    it('bricht ab, wenn sich die Gruppe inzwischen geändert hat', function () {
        actingAsUser(['role' => Role::Moderator]);
        $a = cardIn(sharedDeck(), ['Hund'], ['dog']);
        $b = cardIn(sharedDeck(), ['Hund'], ['dog']);
        $seen = [$a->id, $b->id];
        $c = cardIn(sharedDeck(), ['Hund'], ['dog']);

        mergeGroup($a, $a, ['front' => ['Hund'], 'back' => ['dog']], $seen)
            ->assertSessionHasErrors(['keep' => 'Diese Gruppe hat sich geändert. Bitte neu laden.']);
        mergeGroup($a, cardIn(sharedDeck(), ['Katze'], ['cat']), ['front' => ['Hund'], 'back' => ['dog']])
            ->assertSessionHasErrors('keep');

        expect($b->fresh()->source_card_id)->toBeNull()->and($c->fresh()->source_card_id)->toBeNull();
    });

    it('prüft den Inhalt wie beim Bearbeiten und ändert bei Fehlern nichts', function () {
        actingAsUser(['role' => Role::Moderator]);
        $deck = sharedDeck();
        $a = cardIn($deck, ['Hund'], ['dog']);
        $b = cardIn($deck, ['Hund'], ['dog']);

        mergeGroup($a, $a, ['front' => ['Hund'], 'back' => ['  ']])->assertSessionHasErrors('back');

        expect(Card::whereKey([$a->id, $b->id])->count())->toBe(2);
    });

    it('zeigt, wie viele Personen jede Karte lernen', function () {
        actingAsUser(['role' => Role::Moderator]);
        $a = cardIn(sharedDeck(), ['Hund'], ['dog']);
        cardIn(sharedDeck(), ['Hund'], ['dog']);
        foreach (User::factory()->count(2)->create() as $u) {
            progressOn($u, $a, 1, '2026-10-15');
        }

        $this->get("/moderation/duplicates/{$a->id}")->assertSee('2 Personen lernen sie')->assertSee('0 Personen lernen sie');
    });
});
