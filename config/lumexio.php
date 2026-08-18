<?php

return [
    'api_url' => env('LUMEXIO_API_URL', 'https://lumexio.tech/api/v1'),
    'asset_url' => env('LUMEXIO_ASSET_URL', 'https://lumexio.tech'),
    'timeout' => env('LUMEXIO_TIMEOUT', 30),

    /*
     * How long a GET response is served from cache before a fresh request is
     * made. Balances snappy tab-switching against data freshness; kept short
     * since dashboard/stock figures change frequently.
     */
    'cache_ttl' => (int) env('LUMEXIO_API_CACHE_TTL', 15),
];
