<?php

namespace App\Services;

/**
 * Sends Web Push messages. One seam, so tests can replace the real sender
 * (WebPushSender) and never talk to a push service.
 *
 * @phpstan-type PushMessage array{endpoint: string, public_key: string, auth_token: string, content_encoding: string, payload: string}
 */
interface PushSender
{
    /**
     * Never throws for a single bad device (it counts as gone). Throws when
     * nothing can be sent at all (keys, environment) — the caller keeps
     * everything for the next call then.
     *
     * @param  list<PushMessage>  $messages
     * @return array{gone: list<string>, untried: list<string>} endpoints the
     *                                                          push service no longer knows (404/410) or that could not
     *                                                          be prepared; endpoints left out for lack of time
     */
    public function send(array $messages): array;
}
