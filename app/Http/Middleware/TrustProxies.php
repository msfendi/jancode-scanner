<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * Proxy yang dipercaya: hanya reverse proxy lokal (dev TLS proxy di 8443,
     * atau nginx/php-fpm di host yang sama). Tanpa ini, `X-Forwarded-Proto`
     * diabaikan dan redirect/asset URL ikut skema http sehingga halaman HTTPS
     * gagal dimuat.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies = '127.0.0.1,::1';

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;
}
