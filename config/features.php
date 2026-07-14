<?php

return [
    'flight_release' => [
        'enabled' => env('FEATURES_FLIGHT_RELEASE_ENABLED', true),
        'for_all_users' => env('FEATURES_FLIGHT_RELEASE_FOR_ALL_USERS', false),
    ],
    'schedule_parser' => [
        'enabled' => env('FEATURES_SCHEDULE_PARSER_ENABLED', true),
        'for_all_users' => env('FEATURES_SCHEDULE_PARSER_FOR_ALL_USERS', true),
        'duty_export_for_all_users' => env('FEATURES_SCHEDULE_PARSER_DUTY_EXPORT_FOR_ALL_USERS', false),
    ],
];
