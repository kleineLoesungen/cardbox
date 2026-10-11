<?php

namespace App\Services;

use App\Auth\TokenFormat;
use App\Enums\Scope;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * App-Tokens: Zugaenge fuer Maschinen, nicht fuer Menschen.
 *
 * Ein Token gehoert keinem Benutzer. Es steht fuer ein fremdes System, das die
 * dokumentierte JSON-Schnittstelle benutzt. Was es darf, steht ausschliesslich
 * in seinen Scopes.
 */
class ApiTokenService
{
    /**
     * Stellt ein Token aus. Der Klartext im Rueckgabewert ist die EINZIGE
     * Gelegenheit, das Token zu sehen — danach existiert nur noch der Hash.
     *
     * @param  array<string, mixed>  $input  name, scopes[], expires_in_days
     * @return array{token: ApiToken, plaintext: string}
     *
     * @throws ValidationException
     */
    public function create(User $creator, array $input): array
    {
        /** @var array{name: string, scopes: list<string>, expires_in_days?: int|string|null} $data */
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            // Ohne Scope kann ein Token nichts. Ein Tippfehler im Scope wuerde
            // sonst ein Token erzeugen, das still nichts darf.
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['string', Rule::enum(Scope::class)],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ], [
            'scopes.required' => 'Bitte mindestens eine Berechtigung auswählen — ohne Scope kann das Token nichts.',
            'scopes.*.enum' => 'Unbekannte Berechtigung.',
        ])->validate();

        $generated = TokenFormat::generate();
        $days = $data['expires_in_days'] ?? null;

        $token = ApiToken::create([
            'name' => $data['name'],
            'token_id' => $generated['token_id'],
            'token_hash' => $generated['token_hash'],
            'scopes' => array_values(array_unique($data['scopes'])),
            'created_by' => $creator->id,
            'expires_at' => $days ? now()->addDays((int) $days) : null,
        ]);

        return ['token' => $token, 'plaintext' => $generated['plaintext']];
    }

    /**
     * Prueft ein eingehendes Bearer-Token.
     *
     * Bewusst NICHT nach aussen unterscheidbar: unbekannt, widerrufen, abgelaufen
     * und falsches Geheimnis ergeben alle `null`.
     */
    public function authenticate(string $presented): ?ApiToken
    {
        $parts = TokenFormat::parse($presented);
        if ($parts === null) {
            return null;
        }

        [$tokenId, $secret] = $parts;
        $token = ApiToken::where('token_id', $tokenId)->first();

        if ($token === null || ! TokenFormat::verifySecret($secret, $token->token_hash) || ! $token->isValid()) {
            return null;
        }

        return $token;
    }

    /**
     * Vermerkt die Benutzung. Fehler werden bewusst nur geloggt — eine
     * fehlgeschlagene Statistik darf keinen API-Aufruf scheitern lassen.
     */
    public function touch(ApiToken $token): void
    {
        try {
            ApiToken::whereKey($token->id)->update(['last_used_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('last_used_at konnte nicht aktualisiert werden', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Alle Tokens der Installation. Tokens sind systemweit, nicht persoenlich —
     * jeder Administrator sieht deshalb dieselbe Liste.
     *
     * @return Collection<int, ApiToken>
     */
    public function list(): Collection
    {
        return ApiToken::query()
            ->orderByRaw('revoked_at IS NOT NULL')
            ->latest('created_at')
            ->get();
    }

    /**
     * Widerruft ein Token. Der Datensatz bleibt erhalten, damit die Historie
     * nachvollziehbar bleibt.
     *
     * @throws ModelNotFoundException<ApiToken>
     */
    public function revoke(string $id): ApiToken
    {
        $token = Str::isUuid($id) ? ApiToken::whereNull('revoked_at')->find($id) : null;
        if ($token === null) {
            throw (new ModelNotFoundException)->setModel(ApiToken::class, [$id]);
        }

        $token->forceFill(['revoked_at' => now()])->save();

        return $token;
    }
}
