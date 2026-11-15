<?php

return [
    'submission' => [
        'enabled' => env('CREW_BIDS_SUBMISSION_ENABLED', false),
    ],
    'portal' => [
        'enabled' => env('CREW_BIDS_PORTAL_IMPORT_ENABLED', false),
        'imported_by' => env('CREW_BIDS_PORTAL_IMPORT_ADMIN_ID'),
    ],
];
