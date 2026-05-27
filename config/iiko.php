<?php

return [
    'cloud_base_url' => env('IIKO_CLOUD_BASE_URL', 'https://api-ru.iiko.services/api'),
    'api_login' => env('IIKO_API_LOGIN'),
    'cloud_call_center_org_id' => env('IIKO_CLOUD_CALL_CENTER_ORG_ID', '84f0f1ff-1ba0-4857-bfc6-1ae23b0b4cb1'),
    'sync' => [
        'incremental_interval_minutes' => (int) env('IIKO_INCREMENTAL_SYNC_INTERVAL_MINUTES', 30),
        'stoplist_interval_minutes' => (int) env('IIKO_STOPLIST_SYNC_INTERVAL_MINUTES', 5),
        'request_delay_seconds' => (int) env('IIKO_SYNC_REQUEST_DELAY_SECONDS', 4),
    ],
];
