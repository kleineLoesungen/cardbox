<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Exception\MalformedUriException;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Http\Client\ClientInterface;

/**
 * PushSender over minishlink/web-push: encryption (RFC 8291) and VAPID
 * signature (RFC 8292).
 *
 * * One message at a time, each request with a timeout, the whole call
 *   within "time_budget" — the rest is returned as untried.
 * * A device the library rejects (bad keys or address) counts as gone. Only
 *   those few exception types: anything else is not the device's fault and
 *   is thrown, so no device is deleted for an environment problem.
 * * The log names host and status only; the endpoint is personal and works
 *   like a key.
 *
 * @phpstan-import-type PushMessage from PushSender
 */
class WebPushSender implements PushSender
{
    /** Tests pass a client with a mock handler; otherwise Guzzle with timeouts. */
    public function __construct(private ?ClientInterface $client = null) {}

    public function send(array $messages): array
    {
        $result = ['gone' => [], 'untried' => []];
        if ($messages === []) {
            return $result;
        }

        $timeout = (int) config('push.timeout');
        // Throws for keys that do not fit: nothing is sent, the caller keeps everything.
        $push = new WebPush(
            ['VAPID' => [
                'subject' => (string) config('push.vapid.subject'),
                'publicKey' => (string) config('push.vapid.public_key'),
                'privateKey' => (string) config('push.vapid.private_key'),
            ]],
            ['TTL' => 6 * 3600, 'urgency' => 'normal'],
            $this->client ?? new Client(['timeout' => $timeout, 'connect_timeout' => $timeout]),
        );

        $deadline = microtime(true) + (int) config('push.time_budget');
        $prepared = 0;
        $rejected = 0;
        foreach ($messages as $message) {
            if (microtime(true) > $deadline) {
                $result['untried'][] = $message['endpoint'];

                continue;
            }

            try {
                $push->queueNotification(new Subscription(
                    $message['endpoint'],
                    $message['public_key'],
                    $message['auth_token'],
                    $message['content_encoding'],
                ), $message['payload']);

                foreach ($push->flush() as $report) {
                    if ($report->isSubscriptionExpired()) {
                        $result['gone'][] = $report->getEndpoint();
                    } elseif (! $report->isSuccess()) {
                        Log::warning('Erinnerung nicht zugestellt', [
                            'host' => parse_url($report->getEndpoint(), PHP_URL_HOST),
                            'status' => $report->getResponse()?->getStatusCode(),
                        ]);
                    }
                }
                $prepared++;
            } catch (\InvalidArgumentException|\ErrorException|\ValueError|MalformedUriException $e) {
                Log::warning('Erinnerung nicht vorbereitet, Gerät entfernt', [
                    'host' => parse_url($message['endpoint'], PHP_URL_HOST),
                    'error' => $e::class,
                ]);
                $result['gone'][] = $message['endpoint'];

                // Three in a row and none ever worked: the environment, not the devices. Delete nothing.
                if (++$rejected >= 3 && $prepared === 0) {
                    throw new \RuntimeException('Erinnerungen: Geräte lassen sich nicht vorbereiten ('.$e::class.')', 0, $e);
                }
            }
        }

        return $result;
    }
}
