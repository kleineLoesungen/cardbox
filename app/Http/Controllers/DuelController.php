<?php

namespace App\Http\Controllers;

use App\Models\Duel;
use App\Models\DuelPlayer;
use App\Services\DuelService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Duels (docs/specs/duell.md). Members reach a duel by its id, guests by
 * their secret link; playing is the same for both. All rules are in
 * DuelService.
 */
class DuelController extends Controller
{
    /** Secret links of guests in this browser (encrypted), so /duell leads back without the link. */
    private const GUEST_SESSION = 'duel_guest_links';

    /** Finished duels whose code this session entered: their result may be shown. */
    private const RESULT_SESSION = 'duel_results';

    public function __construct(private DuelService $duels) {}

    // --- Members ----------------------------------------------------------

    public function index(Request $request): View
    {
        $user = $request->user();
        $duels = $this->duels->listFor($user);

        return view('duels.index', [
            'duels' => $duels,
            'standings' => $duels->mapWithKeys(fn (Duel $d) => [$d->id => $this->duels->standings($d)])->all(),
        ]);
    }

    public function store(Request $request, string $deck): RedirectResponse
    {
        try {
            $duel = $this->duels->create($request->user(), $deck, $request->input('players'), $request->input('mode'), $request->input('rounds', 1));
        } catch (ValidationException $e) {
            throw $e->redirectTo("/decks/{$deck}");
        }

        return redirect("/duels/{$duel->id}");
    }

    /** Joins, leads to the own duel, or — for a finished one — to its result. */
    public function join(Request $request): RedirectResponse
    {
        try {
            ['duel' => $duel, 'player' => $player] = $this->duels->join($request->user(), $request->input('code'));
        } catch (ValidationException $e) {
            throw $e->errorBag('duel')->redirectTo('/duels');
        }

        return $player !== null ? redirect("/duels/{$duel->id}") : $this->toResult($request, $duel);
    }

    public function show(Request $request, string $duel): View
    {
        ['duel' => $model, 'player' => $player] = $this->duels->getForMember($request->user(), $duel);

        return view('duels.show', $this->memberPage($request, $model, $player));
    }

    /** The changing part of the duel page, asked for every few seconds while it runs. */
    public function live(Request $request, string $duel): View|Response
    {
        try {
            ['duel' => $model, 'player' => $player] = $this->duels->getForMember($request->user(), $duel);
        } catch (ModelNotFoundException $e) {
            return $this->gone($request, $e, '/duels');
        }

        return $this->livePart($request, $model, fn (string $state) => $this->memberPage($request, $model, $player, $state));
    }

    public function play(Request $request, string $duel): View|Response|RedirectResponse
    {
        ['duel' => $model, 'player' => $player] = $this->duels->getForMember($request->user(), $duel);

        return $this->playScreen($request, $model, $player, "/duels/{$model->id}");
    }

    public function answer(Request $request, string $duel): View|Response
    {
        ['duel' => $model, 'player' => $player] = $this->duels->getForMember($request->user(), $duel);

        return $this->rate($request, $model, $player, "/duels/{$model->id}");
    }

    /** "Runde N starten" (creator, between rounds). */
    public function nextRound(Request $request, string $duel): RedirectResponse
    {
        try {
            $model = $this->duels->nextRound($request->user(), $duel);
        } catch (ValidationException $e) {
            throw $e->redirectTo("/duels/{$duel}");
        }

        return redirect("/duels/{$model->id}")->with('toast', ['kind' => 'success', 'message' => "Runde {$model->round} läuft – jetzt können alle spielen."]);
    }

    public function removePlayer(Request $request, string $duel, string $player): RedirectResponse
    {
        $removed = $this->duels->removePlayer($request->user(), $duel, $player);

        return redirect("/duels/{$duel}")->with('toast', ['kind' => 'success', 'message' => $removed->displayName().' ist nicht mehr dabei.']);
    }

    // --- Guests (public) --------------------------------------------------

    /** Code and name; members are sent to their duels, guests see their earlier duels in this browser. */
    public function guestEntry(Request $request): View|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect('/duels');
        }

        $links = [];
        foreach ($this->guestLinks($request) as $token) {
            try {
                $links[] = ['token' => $token, 'duel' => $this->duels->getForGuest($token)['duel']];
            } catch (ModelNotFoundException) {
                // Removed or cleaned up: leave it out.
            }
        }

        return view('duels.guest', ['links' => $links]);
    }

    /** Joins as a guest, or — for a finished duel — leads to its result. */
    public function guestJoin(Request $request): RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect('/duels');
        }
        $known = $this->guestLinks($request);
        ['duel' => $duel, 'token' => $token] = $this->duels->joinAsGuest($request->input('code'), $request->input('name'), $known);
        if ($token === null) {
            return $this->toResult($request, $duel);
        }
        if (! in_array($token, $known, true)) {
            // Sealed: the session row must not hold a working link in plain text.
            $request->session()->push(self::GUEST_SESSION, Crypt::encryptString($token));
        }

        return redirect("/duell/{$token}");
    }

    public function guestShow(string $token): View
    {
        ['duel' => $model, 'player' => $player] = $this->duels->getForGuest($token);

        return view('duels.show', $this->guestPage($token, $model, $player));
    }

    public function guestLive(Request $request, string $token): View|Response
    {
        try {
            ['duel' => $model, 'player' => $player] = $this->duels->getForGuest($token);
        } catch (ModelNotFoundException $e) {
            return $this->gone($request, $e, '/duell');
        }

        return $this->livePart($request, $model, fn (string $state) => $this->guestPage($token, $model, $player, $state));
    }

    public function guestPlay(Request $request, string $token): View|Response|RedirectResponse
    {
        ['duel' => $model, 'player' => $player] = $this->duels->getForGuest($token);

        return $this->playScreen($request, $model, $player, "/duell/{$token}");
    }

    public function guestAnswer(Request $request, string $token): View|Response
    {
        ['duel' => $model, 'player' => $player] = $this->duels->getForGuest($token);

        return $this->rate($request, $model, $player, "/duell/{$token}");
    }

    /**
     * The result of a finished duel for somebody who entered its code but
     * does not take part. A GET page, so reloading sends nothing again; it
     * opens only in the session that entered the code.
     */
    public function result(Request $request, string $duel): View
    {
        if (! in_array($duel, (array) $request->session()->get(self::RESULT_SESSION, []), true)) {
            abort(404);
        }

        return view('duels.show', $this->page($this->duels->getResult($duel), null, '', ['guest' => $request->user() === null]));
    }

    // --- Shared ---------------------------------------------------------------

    /**
     * The changing part of the duel page; 204 (htmx keeps what it has) when
     * nothing changed since the state the page sent along.
     *
     * @param  \Closure(string): array<string, mixed>  $data  page data without the state
     */
    private function livePart(Request $request, Duel $duel, \Closure $data): View|Response
    {
        $state = $this->duels->state($duel);
        if ($request->query('state') === $state) {
            return response()->noContent();
        }

        return view('duels.partials.live', $data($state));
    }

    /**
     * The duel page asks for its state, but the duel is gone or the player
     * was removed: off to the duel list instead of asking on and on.
     *
     * @param  ModelNotFoundException<Model>  $e
     */
    private function gone(Request $request, ModelNotFoundException $e, string $to): Response
    {
        if (! $request->headers->has('HX-Request')) {
            throw $e;
        }

        return response('', 200)->header('HX-Redirect', $to);
    }

    private function toResult(Request $request, Duel $duel): RedirectResponse
    {
        $request->session()->push(self::RESULT_SESSION, $duel->id);

        return redirect("/duell/ergebnis/{$duel->id}");
    }

    /**
     * The guest links this browser holds (sealed in the session).
     *
     * @return list<string>
     */
    private function guestLinks(Request $request): array
    {
        $tokens = [];
        foreach ((array) $request->session()->get(self::GUEST_SESSION, []) as $sealed) {
            try {
                $tokens[] = Crypt::decryptString((string) $sealed);
            } catch (DecryptException) {
                // Sealed with an old key: leave it out.
            }
        }

        return $tokens;
    }

    /** @return array<string, mixed> */
    private function memberPage(Request $request, Duel $model, DuelPlayer $player, ?string $state = null): array
    {
        return $this->page($model, $player, "/duels/{$model->id}", [
            'canManage' => $this->duels->canManage($request->user(), $model),
            'canStartNextRound' => $this->duels->canStartNextRound($request->user(), $model),
        ], $state);
    }

    /** @return array<string, mixed> */
    private function guestPage(string $token, Duel $model, DuelPlayer $player, ?string $state = null): array
    {
        return $this->page($model, $player, "/duell/{$token}", ['guest' => true, 'guestLink' => url("/duell/{$token}")], $state);
    }

    /**
     * The data of the duel page; without `$player` only the result (a
     * finished duel seen by the code).
     *
     * @param  array<string, mixed>  $extra
     * @param  string|null  $state  DuelService::state, when already known
     * @return array<string, mixed>
     */
    private function page(Duel $duel, ?DuelPlayer $player, string $base, array $extra, ?string $state = null): array
    {
        $free = $this->duels->freeSlots($duel);

        return $extra + [
            'duel' => $duel,
            'me' => $player,
            'base' => $base,
            'standings' => $this->duels->standings($duel),
            'cards' => $this->duels->cardCount($duel),
            'free' => $free,
            // While places are free to join, and after the end to see the result.
            'code' => $free > 0 || $duel->isFinished() ? $this->duels->code($duel) : null,
            'resultUntil' => $this->duels->resultUntil($duel),
            'nextRoundUntil' => $this->duels->nextRoundUntil($duel),
            'roundStart' => $this->duels->roundStart($duel),
            'creatorName' => $duel->creator()->value('name') ?? '',
            // The ranking of the round that is up, once it is over.
            'roundStandings' => $duel->rounds > 1 && ($duel->isBetweenRounds() || $duel->isFinished()) ? $this->duels->roundStandings($duel, $duel->round) : null,
            'guest' => false,
            'canManage' => false,
            'canStartNextRound' => false,
            'guestLink' => null,
            'state' => $state ?? $this->duels->state($duel),
        ];
    }

    /** The card that is up in full screen; for htmx ("Weiter") only the card, or off to the result. */
    private function playScreen(Request $request, Duel $duel, DuelPlayer $player, string $base): View|Response|RedirectResponse
    {
        $card = $this->duels->current($duel, $player);
        if ($card === null) {
            return $request->headers->has('HX-Request')
                ? response('', 200)->header('HX-Redirect', $base)
                : redirect($base);
        }

        return view($request->headers->has('HX-Request') ? 'duels.partials.play-body' : 'duels.play', [
            'duel' => $duel,
            'card' => $card,
            'cards' => $this->duels->cardCount($duel),
            'offset' => $this->duels->roundStart($duel),
            'base' => $base,
            'result' => null,
        ]);
    }

    /** htmx: rates the answer and shows right or wrong; a stale answer just shows the card that is up. */
    private function rate(Request $request, Duel $duel, DuelPlayer $player, string $base): View|Response
    {
        $step = is_numeric($step = $request->input('step')) ? (int) $step : -1;
        $typed = $request->boolean('skip') ? '' : $request->input('typed');
        $result = $this->duels->answer($duel, $player, $step, $request->input('choice'), $typed);
        if ($result === null) {
            $card = $this->duels->current($duel, $player->fresh() ?? $player);

            return $card === null
                ? response('', 200)->header('HX-Redirect', $base)
                : view('duels.partials.play-body', ['duel' => $duel, 'card' => $card, 'cards' => $this->duels->cardCount($duel), 'offset' => $this->duels->roundStart($duel), 'base' => $base, 'result' => null]);
        }

        return view('duels.partials.play-body', [
            'duel' => $duel,
            'card' => null,
            'cards' => $this->duels->cardCount($duel),
            'offset' => $this->duels->roundStart($duel),
            'base' => $base,
            'result' => $result + ['step' => $step],
        ]);
    }
}
