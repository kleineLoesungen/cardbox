<?php

namespace App\Http\Middleware;

use Illuminate\Cache\RateLimiter;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;

/**
 * Rate limit for the deploy hook, kept outside the database.
 *
 * The regular `throttle` middleware counts in the default cache store, which
 * is the database on web space. On the first deploy the cache table does not
 * exist yet — the hook is what creates it — so the limiter itself would fail
 * with a 500 before the hook could run. Files under storage/ always work.
 */
class ThrottleDeployHook extends ThrottleRequests
{
    public function __construct()
    {
        parent::__construct(new RateLimiter(Cache::store(config('deploy.throttle_store'))));
    }
}
