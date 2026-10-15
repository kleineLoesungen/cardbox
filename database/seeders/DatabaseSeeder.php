<?php

namespace Database\Seeders;

use App\Enums\Direction;
use App\Enums\StudyPlan;
use App\Enums\SuggestionStatus;
use App\Enums\SuggestionType;
use App\Models\Card;
use App\Models\CardProgress;
use App\Models\CardSuggestion;
use App\Models\Deck;
use App\Models\DeckRun;
use App\Models\User;
use App\Services\DeckRunService;
use App\Services\DeckService;
use App\Services\LeitnerService;
use Database\Factories\UserFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Sample data for local development:  php artisan db:seed
 *
 * Never run in production. Every account has the password from
 * UserFactory::PASSWORD. Shows each feature once:
 *
 * * anna (admin): shared decks "Reisen" and "Einmaleins".
 * * bea (user): private "Englisch Unit 3" with two plans (Normal with due
 *   cards, Schnell), main meanings and hints, a streak; follows
 *   "Einmaleins"; a copy of "Reisen" whose original changed since.
 * * moritz (moderator): follows "Reisen" and has an open suggestion there.
 * * neu.nutzer: waits for approval.
 *
 * Stops if the accounts exist already; reset the database first.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (User::where('username', 'anna')->exists()) {
            $this->command->warn('Beispieldaten sind schon da (Konto anna). Erst die Datenbank zurücksetzen: just db-reset');

            return;
        }

        $anna = User::factory()->admin()->create(['name' => 'Anna Admin', 'username' => 'anna']);
        $moritz = User::factory()->moderator()->create(['name' => 'Moritz Moderator', 'username' => 'moritz']);
        $bea = User::factory()->create(['name' => 'Bea Benutzer', 'username' => 'bea']);
        User::factory()->pending()->create(['name' => 'Neu Nutzer', 'username' => 'neu.nutzer']);

        // --- anna: two shared decks -----------------------------------------
        $reisen = $this->deck($anna, 'Reisen', 'de', 'en', shared: true, cards: [
            [['verpassen'], ['miss'], '(Zug)'],
            [['Fahrkarte'], ['ticket']],
            [['Gepäck'], ['luggage', 'baggage']],
            [['Bahnhof'], ['station']],
            [['Abfahrt'], ['departure']],
        ]);
        $einmaleins = $this->deck($anna, 'Einmaleins', 'formula', 'result', shared: true, cards: [
            [['3 · 4'], ['12']],
            [['6 · 7'], ['42']],
            [['8 · 8'], ['64']],
            [['9 · 6'], ['54']],
        ]);

        // --- bea: own deck with two plans ------------------------------------
        $englisch = $this->deck($bea, 'Englisch Unit 3', 'de', 'en', shared: false, cards: [
            [['Hund'], ['dog']],
            [['Katze'], ['cat']],
            [['schnell'], ['fast', 'quick', 'rapid']],
            [['verpassen'], ['miss'], '(Zug)'],
            [['vermissen'], ['miss'], '(Person)'],
            [['Haus'], ['house', 'home']],
            [['Baum'], ['tree']],
            [['Schule'], ['school']],
        ]);
        $runs = app(DeckRunService::class);
        $normal = $runs->forStandard($bea, $englisch);
        // A second plan as started before plans moved to the account; the deck page still switches between them.
        $schnell = DeckRun::create(['user_id' => $bea->id, 'deck_id' => $englisch->id, 'plan' => StudyPlan::Quick->value,
            'box_count' => StudyPlan::Quick->boxCount(), 'day_gaps' => StudyPlan::Quick->dayGaps()]);
        $cards = $englisch->cards()->orderBy('created_at')->get();
        // Normal: two due today, one tomorrow, one later, one mastered; the rest new.
        $this->progress($normal, $cards[0], 1, 0);
        $this->progress($normal, $cards[1], 2, 0);
        $this->progress($normal, $cards[2], 2, 1);
        $this->progress($normal, $cards[3], 3, 4);
        $this->progress($normal, $cards[4], 5, null);
        // Schnell: just started, one due today.
        $this->progress($schnell, $cards[0], 1, 0);
        $this->progress($schnell, $cards[2], 2, 1);
        $this->streak($bea, 3);

        // --- bea follows "Einmaleins" and has learned a bit -----------------
        $bea->followedDecks()->attach($einmaleins);
        $mathe = $runs->forStandard($bea, $einmaleins);
        foreach ($einmaleins->cards()->orderBy('created_at')->take(2)->get() as $i => $card) {
            $this->progress($mathe, $card, $i + 1, $i);
        }

        // --- bea copied "Reisen"; anna changed "Gepäck" afterwards ----------
        $kopie = app(DeckService::class)->copy($bea, $reisen->id);
        $gepaeck = $reisen->cards()->where('front', json_encode(['Gepäck']))->sole();
        $gepaeck->update(['back' => ['luggage', 'baggage', 'bags'], 'hint' => '(Koffer)']);
        // The copy remembers the original as it was a day ago, so the change shows.
        $kopie->cards()->where('source_card_id', $gepaeck->id)->update(['source_updated_at' => now()->subDay()]);

        // --- moritz follows "Reisen" and suggests a missing meaning ---------
        $moritz->followedDecks()->attach($reisen);
        $bahnhof = $reisen->cards()->where('front', json_encode(['Bahnhof']))->sole();
        $suggestion = new CardSuggestion([
            'type' => SuggestionType::Missing,
            'proposed_back' => ['station', 'railway station'],
            'comment' => 'In England sagt man oft „railway station“.',
        ]);
        $suggestion->forceFill([
            'card_id' => $bahnhof->id,
            'user_id' => $moritz->id,
            'status' => SuggestionStatus::Open,
            'card_updated_at' => $bahnhof->updated_at,
        ])->save();

        $this->command->info('Konten anna, moritz, bea (und wartend: neu.nutzer), Passwort jeweils: '.UserFactory::PASSWORD);
    }

    /**
     * @param  list<array{0: list<string>, 1: list<string>, 2?: string}>  $cards  front, back, hint
     */
    private function deck(User $owner, string $name, string $front, string $back, bool $shared, array $cards): Deck
    {
        $deck = Deck::factory()->for($owner)->create([
            'name' => $name,
            'front_language' => $front,
            'back_language' => $back,
            'is_shared' => $shared,
        ]);

        foreach ($cards as $i => $card) {
            // One second apart, so "oldest first" is the order above.
            Card::factory()->for($deck)->create([
                'front' => $card[0],
                'back' => $card[1],
                'hint' => $card[2] ?? null,
                'created_at' => now()->subMinutes(10)->addSeconds($i),
            ]);
        }

        return $deck;
    }

    /** One progress row (forward); `$dueInDays` null = mastered. */
    private function progress(DeckRun $run, Card $card, int $box, ?int $dueInDays): void
    {
        CardProgress::create([
            'user_id' => $run->user_id,
            'deck_run_id' => $run->id,
            'card_id' => $card->id,
            'direction' => Direction::Forward,
            'box' => $box,
            'due_on' => $dueInDays === null ? null : app(LeitnerService::class)->today()->addDays($dueInDays)->toDateString(),
            'last_reviewed_at' => now()->subDay(),
            'review_count' => $box,
            'correct_count' => $box,
        ]);
    }

    /** Learned on each of the last `$days` days before today. */
    private function streak(User $user, int $days): void
    {
        $today = app(LeitnerService::class)->today();
        foreach (range(1, $days) as $back) {
            DB::table('study_days')->insert([
                'user_id' => $user->id,
                'day' => $today->subDays($back)->toDateString(),
                'reviews' => 10,
                'correct' => 8,
            ]);
        }
    }
}
