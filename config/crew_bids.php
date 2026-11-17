<?php

return [
    'vault' => [
        'enabled' => env('CREW_BIDS_VAULT_ENABLED', false),
        'idle_seconds' => 900,
        'absolute_seconds' => 7200,
    ],
    'secrets' => [
        'host' => env('CREW_BIDS_SECRETS_HOST', 'crew-bid-secrets'),
        'port' => (int) env('CREW_BIDS_SECRETS_PORT', 6379),
        'username' => env('CREW_BIDS_SECRETS_USERNAME', 'default'),
        'password' => env('CREW_BIDS_SECRETS_PASSWORD'),
        'tls' => env('CREW_BIDS_SECRETS_TLS', false),
        'runtime_attested' => env('CREW_BIDS_SECRETS_RUNTIME_ATTESTED', false),
    ],
    'submission' => [
        'enabled' => env('CREW_BIDS_SUBMISSION_ENABLED', false),
    ],
    'portal' => [
        'enabled' => env('CREW_BIDS_PORTAL_IMPORT_ENABLED', false),
        'imported_by' => env('CREW_BIDS_PORTAL_IMPORT_ADMIN_ID'),
    ],
];
