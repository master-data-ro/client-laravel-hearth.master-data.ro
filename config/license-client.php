<?php

return [
    // When true the package will register the middleware into the 'web' group
    // automatically and block the application (HTTP 403) until a valid
    // license is present. Set to false to opt-out and register middleware
    // manually.
    'enforce' => env('LICENSE_CLIENT_ENFORCE', true),

    // Array of route names or exact paths that should be excluded from
    // enforcement. You can list route names (as returned by Route::currentRouteName())
    // or raw paths (starting with '/'). Matching is exact for names and
    // prefix-match for paths.
    'except' => [
        // Common health endpoints
        '/health',
        '/status',
        '/ping',
        // allow the verification endpoint to be called by webhooks or the
        // local server when registering (if needed)
        '/api/verify',
    ],
];
