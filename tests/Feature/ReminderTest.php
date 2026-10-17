<?php

use App\Models\Card;
use App\Models\Deck;
use App\Models\User;
use App\Services\PushSender;
use App\Services\WebPushSender;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Minishlink\WebPush\VAPID;

/*
 * Daily reminders via Web Push (docs/specs/schreiben-und-erinnern.md, B).
 * The push services are never called: a fake sender records the messages.
 */

const REMINDER_TOKEN = 'reminder-token-for-tests-0123456789abcdef';

/**
 * A fake PushSender: records messages; endpoints in $gone are "expired",
 * in $untried "out of time"; $throw makes the whole call fail.
 */
function fakePush(array $gone = [], array $untried = [], bool $throw = false): object
{
    $fake = new class($gone, $untried, $throw) implements PushSender
    {
        /** @var list<array<string, string>> */
        public array $sent = [];

        public function __construct(private array $gone, private array $untried, private bool $throw) {}

        public function send(array $messages): array
        {
            if ($this->throw) {
                throw new RuntimeException('push service down');
            }
            $endpoints = array_column($messages, 'endpoint');
            array_push($this->sent, ...array_values(array_filter($messages, fn ($m) => ! in_array($m['endpoint'], $this->untried, true))));

            return [
                'gone' => array_values(array_intersect($this->gone, $endpoints)),
                'untried' => array_values(array_intersect($this->untried, $endpoints)),
            ];
        }
    };
    app()->instance(PushSender::class, $fake);

    return $fake;
}

/** A device as a browser registers it: a P-256 public key and 16 random bytes. */
function device(string $endpoint = 'https://fcm.googleapis.com/fcm/send/abc'): array
{
    $b64 = fn (string $bytes) => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    $ec = openssl_pkey_get_details(openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]))['ec'];
    $point = "\x04".str_pad($ec['x'], 32, "\0", STR_PAD_LEFT).str_pad($ec['y'], 32, "\0", STR_PAD_LEFT);

    return ['endpoint' => $endpoint, 'keys' => ['p256dh' => $b64($point), 'auth' => $b64(random_bytes(16))], 'contentEncoding' => 'aes128gcm'];
}

function subscribe(array $device): TestResponse
{
    return test()->postJson('/account/push-subscriptions', $device);
}

function runReminders(?string $token = REMINDER_TOKEN): TestResponse
{
    return test()->postJson('/api/v1/reminders/run', [], $token === null ? [] : ['X-Cron-Token' => $token]);
}

/** Someone with cards waiting and a reminder at 17:00 on one device. */
function remindable(array $attributes = []): User
{
    $user = actingAsUser($attributes);
    $deck = Deck::factory()->for($user)->create();
    Card::factory()->for($deck)->count(3)->create();
    subscribe(device('https://fcm.googleapis.com/fcm/send/'.$user->id))->assertCreated();

    return $user;
}

beforeEach(function () {
    static $keys = null;
    $keys ??= VAPID::createVapidKeys();
    config([
        'push.vapid.public_key' => $keys['publicKey'],
        'push.vapid.private_key' => $keys['privateKey'],
        'push.vapid.subject' => 'mailto:admin@example.com',
        'push.reminder_token' => REMINDER_TOKEN,
    ]);
    // 15:00 UTC = 17:00 in Berlin.
    $this->travelTo(Carbon::parse('2026-10-14 15:00:00', 'UTC'));
});

describe('Einrichten', function () {
    it('meldet ein Gerät an und schaltet die Erinnerung um 17:00 ein', function () {
        $ich = actingAsUser();

        $this->get('/account')->assertSee('Erinnerung einschalten')->assertSee('data-public-key="'.config('push.vapid.public_key').'"', false);
        subscribe(device())->assertCreated();

        expect(DB::table('push_subscriptions')->where('user_id', $ich->id)->count())->toBe(1);
        $this->get('/account')->assertSee('Jeden Tag um')->assertSee('<option value="17:00" selected>', false)->assertSee('Ein Gerät');
    });

    it('lehnt ungültige Geräte und fremde Adressen ab', function (array $device) {
        actingAsUser();

        subscribe($device)->assertStatus(422);
        expect(DB::table('push_subscriptions')->count())->toBe(0);
    })->with([
        'ohne Schlüssel' => [['endpoint' => 'https://fcm.googleapis.com/fcm/send/x']],
        'ohne https' => [device('http://fcm.googleapis.com/fcm/send/x')],
        'fremder Rechner' => [device('https://intranet.example.com/push')],
        'ähnlicher Name' => [device('https://evilfcm.googleapis.com.attacker.net/x')],
        'falsche Kodierung' => [['contentEncoding' => 'gzip'] + device()],
        'kaputte Schlüssel' => [['keys' => ['p256dh' => 'A', 'auth' => 'A']] + device()],
        'Port' => [device('https://fcm.googleapis.com:22/x')],
        'Benutzer' => [device('https://x:y@fcm.googleapis.com/x')],
        'Leerzeichen im Namen' => [device('https://evil.com .fcm.googleapis.com/x')],
        'Anker' => [device('https://fcm.googleapis.com/x#y')],
    ]);

    it('hält höchstens fünf Geräte je Konto und ersetzt das älteste', function () {
        $ich = actingAsUser();
        foreach (range(1, 6) as $i) {
            $this->travel(1)->minutes();
            subscribe(device("https://fcm.googleapis.com/fcm/send/{$i}"))->assertCreated();
        }

        expect(DB::table('push_subscriptions')->where('user_id', $ich->id)->pluck('endpoint')->sort()->values()->all())
            ->toBe(array_map(fn ($i) => "https://fcm.googleapis.com/fcm/send/{$i}", range(2, 6)));
    });

    it('lässt das Gerät beim Konto wechseln, das es zuletzt angemeldet hat', function () {
        $anna = actingAsUser();
        subscribe(device())->assertCreated();
        $ben = actingAsUser();
        subscribe(device())->assertCreated();

        expect(DB::table('push_subscriptions')->sole()->user_id)->toBe($ben->id);
    });

    it('stellt die Uhrzeit um und schaltet ganz aus', function () {
        $ich = actingAsUser();
        subscribe(device())->assertCreated();

        $this->post('/account/reminder', ['reminder_time' => '06:15'])->assertRedirect('/account');
        $this->get('/account')->assertSee('<option value="06:15" selected>', false);
        $this->from('/account')->post('/account/reminder', ['reminder_time' => '05:00'])->assertSessionHasErrors('reminder_time', null, 'reminder');
        $this->from('/account')->post('/account/reminder', ['reminder_time' => ['17:00']])->assertSessionHasErrors('reminder_time', null, 'reminder');

        $this->post('/account/reminder', ['off' => '1'])->assertSessionHas('toast.message', 'Erinnerung ausgeschaltet.');
        expect(DB::table('push_subscriptions')->count())->toBe(0)
            ->and(DB::table('learning_settings')->where('user_id', $ich->id)->value('reminder_time'))->toBeNull();
    });

    it('zeigt ohne Schlüssel nichts und nimmt keine Geräte an', function () {
        config(['push.vapid.public_key' => null]);
        actingAsUser();

        $this->get('/account')->assertDontSee('Erinnerung einschalten');
        subscribe(device())->assertStatus(422);
    });
});

describe('Verschicken', function () {
    it('erinnert einmal am Tag zur Uhrzeit, mit Kartenzahl und ohne Namen', function () {
        $push = fakePush();
        $ich = remindable(['name' => 'Anna Geheim']);

        $this->travelTo(Carbon::parse('2026-10-14 14:50:00', 'UTC'));   // 16:50 Berlin
        runReminders()->assertOk()->assertJson(['sent' => 0, 'skipped' => 0, 'removed' => 0, 'failed' => 0]);

        $this->travelTo(Carbon::parse('2026-10-14 15:05:00', 'UTC'));   // 17:05 Berlin
        runReminders()->assertJson(['sent' => 1, 'skipped' => 0, 'removed' => 0, 'failed' => 0]);
        runReminders()->assertJson(['sent' => 0, 'skipped' => 0, 'removed' => 0, 'failed' => 0]);

        expect($push->sent)->toHaveCount(1);
        $payload = json_decode($push->sent[0]['payload'], true);
        expect($payload)->toMatchArray(['title' => config('app.name'), 'url' => '/learn'])
            ->and($payload['body'])->toBe('3 Karten warten auf dich – ein paar Minuten reichen.')
            ->and($push->sent[0]['payload'])->not->toContain('Anna');

        // Next day again.
        $this->travelTo(Carbon::parse('2026-10-15 15:05:00', 'UTC'));
        runReminders()->assertJson(['sent' => 1]);
    });

    it('verschickt auch, wenn der Aufruf einen Moment vor der Uhrzeit ankommt', function () {
        $push = fakePush();
        $ich = remindable();
        $this->post('/account/reminder', ['reminder_time' => '13:30']);

        // Cronicle calls at 13:30:00, the server clock says 13:29:59 (Berlin).
        $this->travelTo(Carbon::parse('2026-10-14 11:29:59', 'UTC'));
        runReminders()->assertJson(['sent' => 1]);

        // Too early is still too early.
        $this->post('/account/reminder', ['reminder_time' => '17:00']);
        $this->travelTo(Carbon::parse('2026-10-15 14:50:00', 'UTC'));   // 16:50 Berlin
        runReminders()->assertJson(['sent' => 0]);
        expect($push->sent)->toHaveCount(1);
    });

    it('nennt eine laufende Lernserie', function () {
        $push = fakePush();
        $ich = remindable();
        DB::table('study_days')->insert(['user_id' => $ich->id, 'day' => '2026-10-13', 'reviews' => 5, 'correct' => 5, 'answers' => 20, 'goal_met' => true]);

        runReminders();

        expect(json_decode($push->sent[0]['payload'], true)['body'])->toBe('🔥 1 Tag in Folge – mach weiter! 3 Karten warten auf dich.');
    });

    it('schickt nichts, wenn das Ziel erreicht ist oder nichts wartet, und hakt den Tag ab', function () {
        $push = fakePush();
        $ich = remindable();
        DB::table('study_days')->insert(['user_id' => $ich->id, 'day' => '2026-10-14', 'reviews' => 0, 'correct' => 0, 'answers' => 20, 'goal_met' => true]);

        runReminders()->assertJson(['sent' => 0, 'skipped' => 1, 'removed' => 0, 'failed' => 0]);
        expect($push->sent)->toBe([])
            ->and(DB::table('learning_settings')->where('user_id', $ich->id)->value('reminder_sent_on'))->toStartWith('2026-10-14');
    });

    it('lässt gesperrte und nicht freigegebene Konten aus', function () {
        $push = fakePush();
        $gesperrt = remindable();
        $gesperrt->forceFill(['is_active' => false])->save();
        $wartend = remindable();
        $wartend->forceFill(['approved_at' => null])->save();

        runReminders()->assertJson(['sent' => 0, 'skipped' => 0, 'removed' => 0, 'failed' => 0]);
        expect($push->sent)->toBe([]);
    });

    it('entfernt Geräte, die der Push-Dienst nicht mehr kennt', function () {
        $ich = remindable();
        $push = fakePush(['https://fcm.googleapis.com/fcm/send/'.$ich->id]);

        runReminders()->assertJson(['sent' => 1, 'skipped' => 0, 'removed' => 1, 'failed' => 0]);
        expect(DB::table('push_subscriptions')->count())->toBe(0);
    });

    it('schickt an jedes Gerät des Kontos', function () {
        $push = fakePush();
        $ich = remindable();
        subscribe(device('https://web.push.apple.com/QWERTY'))->assertCreated();

        runReminders()->assertJson(['sent' => 1]);
        expect(array_column($push->sent, 'endpoint'))->toEqualCanonicalizing([
            'https://fcm.googleapis.com/fcm/send/'.$ich->id, 'https://web.push.apple.com/QWERTY',
        ]);
    });
});

describe('Grenzen', function () {
    it('verschickt höchstens max_messages je Aufruf, den Rest beim nächsten', function () {
        config(['push.max_messages' => 2]);
        $push = fakePush();
        foreach (range(1, 3) as $i) {
            remindable();
        }

        runReminders()->assertJson(['sent' => 2]);
        runReminders()->assertJson(['sent' => 1]);
        expect($push->sent)->toHaveCount(3);
    });

    it('bricht bei einem Gerät, das die Bibliothek nicht vorbereiten kann, nicht ab', function () {
        $keys = VAPID::createVapidKeys();
        config(['push.vapid.public_key' => $keys['publicKey'], 'push.vapid.private_key' => $keys['privateKey'], 'push.vapid.subject' => 'mailto:a@b.de']);

        $result = app(WebPushSender::class)->send([[
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/kaputt', 'public_key' => 'A', 'auth_token' => 'A',
            'content_encoding' => 'aes128gcm', 'payload' => '{}',
        ]]);

        expect($result)->toBe(['gone' => ['https://fcm.googleapis.com/fcm/send/kaputt'], 'untried' => []]);
    });
});

describe('Echter Versand (Push-Dienst nachgestellt)', function () {
    function realSender(array $responses): WebPushSender
    {
        $mock = new MockHandler($responses);

        return new WebPushSender(new Client(['handler' => HandlerStack::create($mock)]));
    }

    function message(string $endpoint): array
    {
        $device = device($endpoint);

        return ['endpoint' => $endpoint, 'public_key' => $device['keys']['p256dh'], 'auth_token' => $device['keys']['auth'],
            'content_encoding' => 'aes128gcm', 'payload' => '{"title":"x"}'];
    }

    it('verschlüsselt, schickt und entfernt nur Geräte, die es nicht mehr gibt', function () {
        $sender = realSender([
            new Response(201),
            new Response(410),
            new Response(500),
        ]);

        $result = $sender->send([message('https://fcm.googleapis.com/a'), message('https://fcm.googleapis.com/b'), message('https://fcm.googleapis.com/c')]);

        expect($result)->toBe(['gone' => ['https://fcm.googleapis.com/b'], 'untried' => []]);
    });

    it('lässt Geräte über die Zeitgrenze hinaus für den nächsten Aufruf', function () {
        config(['push.time_budget' => -1]);

        $result = realSender([])->send([message('https://fcm.googleapis.com/a')]);

        expect($result)->toBe(['gone' => [], 'untried' => ['https://fcm.googleapis.com/a']]);
    });

    it('löscht nichts, wenn kein einziges Gerät vorbereitet werden kann', function () {
        $broken = fn ($e) => ['endpoint' => $e, 'public_key' => 'A', 'auth_token' => 'A', 'content_encoding' => 'aes128gcm', 'payload' => '{}'];

        expect(fn () => realSender([])->send([$broken('https://fcm.googleapis.com/1'), $broken('https://fcm.googleapis.com/2'), $broken('https://fcm.googleapis.com/3')]))
            ->toThrow(RuntimeException::class);
    });
});

describe('Fehler und Grenzfälle', function () {
    it('gibt bei einem Fehler des Versands alle Konten für den nächsten Aufruf frei', function () {
        fakePush(throw: true);
        remindable();

        runReminders()->assertJson(['sent' => 0, 'skipped' => 0, 'removed' => 0, 'failed' => 1]);

        $push = fakePush();
        runReminders()->assertJson(['sent' => 1]);
        expect($push->sent)->toHaveCount(1);
    });

    it('gibt Konten frei, deren Geräte aus Zeitmangel nicht dran waren', function () {
        $ich = remindable();
        fakePush(untried: ['https://fcm.googleapis.com/fcm/send/'.$ich->id]);

        runReminders()->assertJson(['sent' => 0, 'failed' => 1]);
        expect(DB::table('learning_settings')->where('user_id', $ich->id)->value('reminder_sent_on'))->toBeNull();
    });

    it('holt Erinnerungen nicht mehr als zwei Stunden später nach', function () {
        $push = fakePush();
        remindable();

        $this->travelTo(Carbon::parse('2026-10-14 17:30:00', 'UTC'));   // 19:30 Berlin, 2½ h zu spät
        runReminders()->assertJson(['sent' => 0, 'skipped' => 1]);
        expect($push->sent)->toBe([]);
    });

    it('schreibt eine einzelne Karte in der Einzahl', function () {
        $push = fakePush();
        $ich = actingAsUser();
        Card::factory()->for(Deck::factory()->for($ich))->create();
        subscribe(device())->assertCreated();

        runReminders();

        expect(json_decode($push->sent[0]['payload'], true)['body'])->toBe('1 Karte wartet auf dich – ein paar Minuten reichen.');
    });

    it('behält die Uhrzeit, wenn ein weiteres Gerät dazukommt', function () {
        actingAsUser();
        subscribe(device('https://fcm.googleapis.com/a'))->assertCreated();
        $this->post('/account/reminder', ['reminder_time' => '06:30']);
        subscribe(device('https://fcm.googleapis.com/b'))->assertCreated();

        $this->get('/account')->assertSee('<option value="06:30" selected>', false)->assertSee('2 Geräte');
    });

    it('nimmt je Aufruf höchstens batch_size Konten', function () {
        config(['push.batch_size' => 1]);
        $push = fakePush();
        remindable();
        remindable();

        runReminders()->assertJson(['sent' => 1]);
        runReminders()->assertJson(['sent' => 1]);
    });

    it('meldet ungültige Schlüssel oder Kontakt und schickt dann nichts', function () {
        $push = fakePush();
        remindable();
        config(['push.vapid.subject' => '']);

        runReminders()->assertStatus(503)->assertJson(['error' => 'VAPID_SUBJECT muss mit mailto: oder https:// beginnen.']);
        $this->artisan('reminders:send')->expectsOutputToContain('VAPID_SUBJECT')->assertFailed();
        actingAsAdmin();
        $this->get('/admin/system')->assertSee('ungültig')->assertSee('VAPID_SUBJECT muss mit mailto: oder https:// beginnen.');
        expect($push->sent)->toBe([]);
    });
});

describe('Aufruf', function () {
    it('antwortet ohne oder mit falschem Token und ohne Einrichtung mit 404', function () {
        fakePush();
        remindable();

        runReminders(null)->assertNotFound();
        runReminders('falsch')->assertNotFound();
        config(['push.reminder_token' => 'zu-kurz']);
        runReminders('zu-kurz')->assertNotFound();

        expect(DB::table('learning_settings')->whereNotNull('reminder_sent_on')->count())->toBe(0);
    });

    it('meldet fehlende Schlüssel als Fehler, damit der Aufrufer es merkt', function () {
        $push = fakePush();
        remindable();
        config(['push.vapid.private_key' => null]);

        runReminders()->assertStatus(503)->assertExactJson(['error' => 'VAPID_PUBLIC_KEY und VAPID_PRIVATE_KEY fehlen.']);
        expect($push->sent)->toBe([]);
    });

    it('nennt, wie viele Konten überhaupt Erinnerungen bekommen', function () {
        fakePush();
        remindable();
        $this->travelTo(Carbon::parse('2026-10-14 14:00:00', 'UTC'));   // 16:00 Berlin, vor 17:00

        runReminders()->assertExactJson(['sent' => 0, 'skipped' => 0, 'removed' => 0, 'failed' => 0, 'scheduled' => 1]);
    });

    it('macht mit reminders:send dasselbe und zeigt den letzten Lauf in der Verwaltung', function () {
        $push = fakePush();
        remindable();

        $this->artisan('reminders:send')->expectsOutputToContain('1 Erinnerungen verschickt')->assertSuccessful();
        expect($push->sent)->toHaveCount(1);

        actingAsAdmin();
        $this->get('/admin/system')->assertSee('Erinnerungen')->assertSee('1 verschickt, 0 übersprungen');
    });

    it('erzeugt Schlüssel für die .env', function () {
        $this->artisan('push:keys')->expectsOutputToContain('VAPID_PUBLIC_KEY=')->assertSuccessful();
    });
});
