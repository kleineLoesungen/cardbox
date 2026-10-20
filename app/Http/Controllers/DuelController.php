<?php

namespace App\Http\Controllers;

use App\Models\Duel;
use App\Models\DuelPlayer;
use App\Services\DuelService;
use Illuminate\Contracts\Encryption\DecryptException;
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
            $duel = $this->duels->create($request->user(), $deck, $request->input('players'), $request->input('mode'));
        } catch (ValidationException $e) {
            throw $e->redirectTo("/decks/{$deck}");
        }

        return redirect("/duels/{$duel->id}");
    }

    public function join(Request $request): RedirectResponse
    {
        try {
            $duel = $this->duels->join($request->user(), $request->input('code'));
        } catch (ValidationException $e) {
            throw $e->errorBag('duel')->redirectTo('/duels');
        }

        return redirect("/duels/{$duel->id}");
    }

    public function show(Request $request, string $duel): View
    {
        ['duel' => $model, 'player' => $player] = $this->duels->getForMember($request->user(), $duel);

        return $this->page($model, $player, "/duels/{$model->id}", [
            'canManage' => $this->duels->canManage($request->user(), $model),
            'canRematch' => $model->isFinished() && $this->duels->canRematch($request->user(), $model),
        ]);
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

    public function rematch(Request $request, string $duel): RedirectResponse
    {
        ['duel' => $model] = $this->duels->getForMember($request->user(), $duel);
        try {
            $new = $this->duels->rematch($request->user(), $model);
        } catch (ValidationException $e) {
            throw $e->redirectTo("/duels/{$model->id}");
        }

        return redirect("/duels/{$new->id}");
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

    public function guestJoin(Request $request): RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect('/duels');
        }
        $known = $this->guestLinks($request);
        ['token' => $token] = $this->duels->joinAsGuest($request->input('code'), $request->input('name'), $known);
        if (! in_array($token, $known, true)) {
            // Sealed: the session row must not hold a working link in plain text.
            $request->session()->push(self::GUEST_SESSION, Crypt::encryptString($token));
        }

        return redirect("/duell/{$token}");
    }

    public function guestShow(string $token): View
    {
        ['duel' => $model, 'player' => $player] = $this->duels->getForGuest($token);

        return $this->page($model, $player, "/duell/{$token}", ['guestLink' => url("/duell/{$token}")]);
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

    // --- Shared ---------------------------------------------------------------

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

    /** @param  array<string, mixed>  $extra */
    private function page(Duel $duel, DuelPlayer $player, string $base, array $extra): View
    {
        $free = $this->duels->freeSlots($duel);

        return view('duels.show', $extra + [
            'duel' => $duel,
            'me' => $player,
            'base' => $base,
            'standings' => $this->duels->standings($duel),
            'cards' => $this->duels->cardCount($duel),
            'free' => $free,
            'code' => $free > 0 ? $this->duels->code($duel) : null,
            'canManage' => false,
            'canRematch' => false,
            'guestLink' => null,
        ]);
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
                : view('duels.partials.play-body', ['duel' => $duel, 'card' => $card, 'cards' => $this->duels->cardCount($duel), 'base' => $base, 'result' => null]);
        }

        return view('duels.partials.play-body', [
            'duel' => $duel,
            'card' => null,
            'cards' => $this->duels->cardCount($duel),
            'base' => $base,
            'result' => $result + ['step' => $step],
        ]);
    }
}
