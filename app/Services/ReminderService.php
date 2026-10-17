<?php

namespace App\Services;

use App\Http\Middleware\RequireReminderToken;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Minishlink\WebPush\VAPID;

/**
 * Daily reminders via Web Push (docs/specs/schreiben-und-erinnern.md, B).
 *
 * * Devices (push_subscriptions) belong to one account each; switching off
 *   removes all devices of the account.
 * * run() sends at most one reminder a day per account, at or after its
 *   time in the learning time zone, and only when cards wait and the daily
 *   goal is open. Called by POST /api/v1/reminders/run or reminders:send.
 *
 * @phpstan-import-type PushMessage from PushSender
 */
class ReminderService
{
    public function __construct(
        private PushSender $sender,
        private LeitnerService $leitner,
        private LearningOverviewService $overview,
    ) {}

    /** Both VAPID keys are configured; without them the feature is off. */
    public function enabled(): bool
    {
        return filled(config('push.vapid.public_key')) && filled(config('push.vapid.private_key'));
    }

    /**
     * What is wrong with the configuration, or null: keys that do not fit,
     * a contact the push services refuse (Apple wants mailto: or https:).
     */
    public function configError(): ?string
    {
        if (! $this->enabled()) {
            return null;
        }
        $subject = (string) config('push.vapid.subject');
        if (! str_starts_with($subject, 'mailto:') && ! str_starts_with($subject, 'https://')) {
            return 'VAPID_SUBJECT muss mit mailto: oder https:// beginnen.';
        }
        try {
            VAPID::validate([
                'subject' => $subject,
                'publicKey' => (string) config('push.vapid.public_key'),
                'privateKey' => (string) config('push.vapid.private_key'),
            ]);
        } catch (\Throwable) {
            return 'VAPID_PUBLIC_KEY oder VAPID_PRIVATE_KEY ist ungültig.';
        }

        return null;
    }

    /** The token for the call address is long enough to count. */
    public function tokenConfigured(): bool
    {
        return strlen((string) config('push.reminder_token')) >= RequireReminderToken::MIN_LENGTH;
    }

    /**
     * The person's reminder: time (HH:MM, null = off) and number of devices.
     *
     * @return array{time: string|null, devices: int}
     */
    public function status(User $user): array
    {
        $time = DB::table('learning_settings')->where('user_id', $user->id)->value('reminder_time');

        return [
            'time' => $time === null ? null : substr((string) $time, 0, 5),
            'devices' => DB::table('push_subscriptions')->where('user_id', $user->id)->count(),
        ];
    }

    /**
     * Times offered on the account page.
     *
     * @return list<string>
     */
    public function times(): array
    {
        $times = [];
        $from = CarbonImmutable::parse((string) config('push.reminder_from'));
        $to = CarbonImmutable::parse((string) config('push.reminder_to'));
        for ($t = $from; $t <= $to; $t = $t->addMinutes((int) config('push.reminder_step'))) {
            $times[] = $t->format('H:i');
        }

        return $times;
    }

    /**
     * Registers this browser for the person's reminders. A device already
     * registered for another account moves to this one. The first device
     * switches the reminder on at the default time.
     *
     * @param  array<string, mixed>  $input  endpoint, keys.p256dh, keys.auth, contentEncoding
     *
     * @throws ValidationException
     */
    public function subscribe(User $user, array $input): void
    {
        if (! $this->enabled()) {
            throw ValidationException::withMessages(['endpoint' => 'Erinnerungen sind auf dieser Installation nicht eingerichtet.']);
        }

        $data = Validator::make($input, [
            'endpoint' => ['required', 'string', 'max:2000', 'starts_with:https://'],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_-]+=*$/'],
            'keys.auth' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_-]+=*$/'],
            'contentEncoding' => ['nullable', 'string', Rule::in(['aesgcm', 'aes128gcm'])],
        ], [
            'endpoint.*' => 'Dieses Gerät kann keine Mitteilungen empfangen.',
            'keys.*' => 'Dieses Gerät kann keine Mitteilungen empfangen.',
        ])->validate();

        if (! self::validEndpoint($data['endpoint'])) {
            throw ValidationException::withMessages(['endpoint' => 'Dieser Browser wird für Erinnerungen nicht unterstützt.']);
        }
        // Keys as the encryption needs them: a P-256 point and 16 bytes.
        $p256dh = self::base64url($data['keys']['p256dh']);
        $auth = self::base64url($data['keys']['auth']);
        if ($p256dh === null || strlen($p256dh) !== 65 || $p256dh[0] !== "\x04" || $auth === null || strlen($auth) !== 16) {
            throw ValidationException::withMessages(['keys' => 'Dieses Gerät kann keine Mitteilungen empfangen.']);
        }

        DB::transaction(function () use ($user, $data) {
            DB::table('push_subscriptions')->upsert(
                [[
                    'id' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'endpoint' => $data['endpoint'],
                    'public_key' => $data['keys']['p256dh'],
                    'auth_token' => $data['keys']['auth'],
                    'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]],
                ['endpoint'],
                ['user_id', 'public_key', 'auth_token', 'content_encoding', 'updated_at'],
            );

            // A few devices per account; a new one replaces the oldest.
            $keep = DB::table('push_subscriptions')->where('user_id', $user->id)
                ->orderByDesc('updated_at')->orderByDesc('id')
                ->limit((int) config('push.max_devices'))->pluck('id');
            DB::table('push_subscriptions')->where('user_id', $user->id)->whereNotIn('id', $keep)->delete();

            if ($this->status($user)['time'] === null) {
                $this->storeTime($user, (string) config('push.reminder_default'));
            }
        });
    }

    /**
     * @throws ValidationException
     */
    public function setTime(User $user, mixed $time): void
    {
        Validator::make(['reminder_time' => $time], [
            'reminder_time' => ['required', 'string', Rule::in($this->times())],
        ], [
            'reminder_time.*' => 'Bitte eine Uhrzeit aus der Liste wählen.',
        ])->validate();

        $this->storeTime($user, (string) $time);
    }

    /** Off: no time, and every device of the account is removed. */
    public function turnOff(User $user): void
    {
        DB::transaction(function () use ($user) {
            DB::table('push_subscriptions')->where('user_id', $user->id)->delete();
            DB::table('learning_settings')->where('user_id', $user->id)->update(['reminder_time' => null]);
        });
    }

    /**
     * Sends the reminders that are due now.
     *
     * Each account is claimed for today before its message is built, so a
     * parallel run skips it. Accounts whose messages were not tried (time
     * budget) or failed as a whole (sender error) are released again and
     * come on the next call. A reminder more than "catch_up_hours" late —
     * the scheduler was down — is not sent any more that day.
     *
     * @return array{sent: int, skipped: int, removed: int, failed: int}
     */
    public function run(): array
    {
        $result = ['sent' => 0, 'skipped' => 0, 'removed' => 0, 'failed' => 0];
        if (! $this->enabled() || $this->configError() !== null) {
            return $result;
        }

        $now = CarbonImmutable::now((string) config('learning.timezone'));
        $today = $now->toDateString();
        $open = fn ($q) => $q->whereNull('reminder_sent_on')->orWhere('reminder_sent_on', '<', $today);

        $due = DB::table('learning_settings as s')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->whereNotNull('s.reminder_time')
            // A little early is fine: schedulers call on the quarter hour, exactly when reminders
            // are due, and a server clock a moment behind would otherwise miss every one.
            ->where('s.reminder_time', '<=', $now->addSeconds((int) config('push.early_seconds'))->format('H:i:s'))
            ->where(fn ($q) => $q->whereNull('s.reminder_sent_on')->orWhere('s.reminder_sent_on', '<', $today))
            // Locked or not yet approved accounts get nothing.
            ->whereNotNull('u.approved_at')
            ->where('u.is_active', true)
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('push_subscriptions as p')->whereColumn('p.user_id', 's.user_id'))
            ->orderBy('s.reminder_time')
            ->limit((int) config('push.batch_size'))
            ->pluck('s.reminder_time', 's.user_id');

        $messages = [];
        $claimed = [];   // user id => previous reminder_sent_on
        $owners = [];    // endpoint => user id
        $max = (int) config('push.max_messages');
        $lateAfter = $now->subHours((int) config('push.catch_up_hours'))->format('H:i:s');
        foreach ($due as $id => $time) {
            // The rest of the batch waits for the next call, unclaimed.
            if (count($messages) >= $max) {
                break;
            }

            // Claim today's reminder first: a second, parallel run gets 0 rows and skips.
            $previous = DB::table('learning_settings')->where('user_id', $id)->value('reminder_sent_on');
            if (DB::table('learning_settings')->where('user_id', $id)->where($open)->update(['reminder_sent_on' => $today]) === 0) {
                continue;
            }

            $user = User::find($id);
            $text = $user === null || (string) $time < $lateAfter ? null : $this->text($user);
            if ($text === null) {
                $result['skipped']++;

                continue;
            }

            $claimed[$id] = $previous;
            $payload = (string) json_encode([
                'title' => (string) config('app.name'),
                'body' => $text,
                'url' => '/learn',
            ], JSON_UNESCAPED_UNICODE);
            foreach (DB::table('push_subscriptions')->where('user_id', $id)->get() as $device) {
                $owners[(string) $device->endpoint] = $id;
                $messages[] = [
                    'endpoint' => (string) $device->endpoint,
                    'public_key' => (string) $device->public_key,
                    'auth_token' => (string) $device->auth_token,
                    'content_encoding' => (string) $device->content_encoding,
                    'payload' => $payload,
                ];
            }
        }

        try {
            ['gone' => $gone, 'untried' => $untried] = $this->sender->send($messages);
        } catch (\Throwable $e) {
            // Not one device's fault (keys, library, environment): nothing is lost, all come again.
            report($e);
            $gone = [];
            $untried = array_keys($owners);
        }

        // Accounts none of whose devices was tried are released for the next call.
        $tried = array_unique(array_values(array_diff_key($owners, array_flip($untried))));
        foreach (array_diff(array_keys($claimed), $tried) as $id) {
            DB::table('learning_settings')->where('user_id', $id)->update(['reminder_sent_on' => $claimed[$id]]);
            $result['failed']++;
        }
        $result['sent'] = count($claimed) - $result['failed'];

        if ($gone !== []) {
            $result['removed'] = DB::table('push_subscriptions')->whereIn('endpoint', $gone)->delete();
        }

        DB::table('settings')->upsert(
            [['key' => 'reminders_last_run', 'value' => (string) json_encode(['at' => now()->toIso8601String()] + $result), 'updated_at' => now()]],
            ['key'],
            ['value', 'updated_at'],
        );

        return $result;
    }

    /**
     * Accounts that get reminders at all (time set, a device, approved and
     * active) — a count for the scheduler's log, to tell "nobody signed up"
     * from "nobody due now".
     */
    public function scheduledCount(): int
    {
        return DB::table('learning_settings as s')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->whereNotNull('s.reminder_time')
            ->whereNotNull('u.approved_at')
            ->where('u.is_active', true)
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('push_subscriptions as p')->whereColumn('p.user_id', 's.user_id'))
            ->count();
    }

    /**
     * Last run for the system page.
     *
     * @return array{at: string, sent: int, skipped: int, removed: int, failed?: int}|null
     */
    public function lastRun(): ?array
    {
        $value = DB::table('settings')->where('key', 'reminders_last_run')->value('value');
        $data = is_string($value) ? json_decode($value, true) : null;

        return is_array($data) && isset($data['at']) ? $data : null;
    }

    /**
     * The reminder's text, or null when there is nothing to remind of: the
     * goal is reached or no card waits. No names, no words — only numbers.
     */
    private function text(User $user): ?string
    {
        $today = $this->overview->today($user);
        $waiting = $today['due'] + $today['new'];
        if ($waiting === 0 || $today['answers'] >= $today['goal']) {
            return null;
        }

        $cards = $waiting === 1 ? '1 Karte wartet auf dich' : "{$waiting} Karten warten auf dich";

        return $today['streak'] > 0
            ? '🔥 '.($today['streak'] === 1 ? '1 Tag' : "{$today['streak']} Tage")." in Folge – mach weiter! {$cards}."
            : "{$cards} – ein paar Minuten reichen.";
    }

    /**
     * Only an address at one of the browsers' push services, written plainly:
     * https, a host of letters, digits, dots and dashes on the allow list,
     * no port, no user, no fragment (a query is fine: Windows uses one).
     * Anything else could make the server post elsewhere (SSRF) or break a
     * whole run.
     */
    private static function validEndpoint(string $endpoint): bool
    {
        if (preg_match('#^https://([a-z0-9.-]+)/[A-Za-z0-9._~:/%+=-]*(\?[A-Za-z0-9._~:/%+=&-]*)?$#', $endpoint, $m) !== 1) {
            return false;
        }

        return collect((array) config('push.endpoint_hosts'))
            ->contains(fn (string $h) => $m[1] === $h || str_ends_with($m[1], '.'.$h));
    }

    private static function base64url(string $value): ?string
    {
        $decoded = base64_decode(strtr(rtrim($value, '='), '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }

    /** Settings row with the current plan if there is none yet, then the time. */
    private function storeTime(User $user, string $time): void
    {
        $plan = $this->leitner->plan($user);
        DB::table('learning_settings')->insertOrIgnore([
            'user_id' => $user->id,
            'box_count' => $plan['box_count'],
            'day_gaps' => json_encode($plan['day_gaps']),
            'daily_goal' => $this->leitner->dailyGoal($user),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('learning_settings')->where('user_id', $user->id)->update(['reminder_time' => $time, 'updated_at' => now()]);
    }
}
