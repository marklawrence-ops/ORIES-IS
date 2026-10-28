<?php

return [

    // Jaro-Winkler score at or above which a proposal title is flagged as a possible duplicate
    'duplicate_threshold' => (float) env('ORIES_DUPLICATE_THRESHOLD', 0.85),

    // Local disk that holds the primary copy of every uploaded Means of Verification
    'mov_disk' => env('ORIES_MOV_DISK', 'local'),

    'max_upload_kb' => (int) env('ORIES_MAX_UPLOAD_KB', 20480),

    'allowed_mimes' => 'pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png',

    'drive' => [
        // Secondary cloud backup; uploads stay available locally when Drive is disabled or unreachable
        'backup_enabled' => (bool) env('GOOGLE_DRIVE_BACKUP_ENABLED', false),

        // How the system signs in to Drive: 'service_account' (needs a Shared Drive) or 'oauth'
        // (uploads into a regular Google account's My Drive after a one-time `php artisan ories:drive-connect`)
        'auth' => env('GOOGLE_DRIVE_AUTH', 'service_account'),
        'credentials' => env('GOOGLE_DRIVE_CREDENTIALS', storage_path('app/google-credentials.json')),
        'oauth_client' => env('GOOGLE_DRIVE_OAUTH_CLIENT', storage_path('app/google-oauth-client.json')),
        'oauth_token' => env('GOOGLE_DRIVE_OAUTH_TOKEN', storage_path('app/google-oauth-token.json')),

        'folder_id' => env('GOOGLE_DRIVE_FOLDER_ID'),
        'import_folder_id' => env('GOOGLE_DRIVE_IMPORT_FOLDER_ID', env('GOOGLE_DRIVE_FOLDER_ID')),
    ],

    // Ongoing records with no verified update for this many days trigger a reminder
    'stale_after_days' => (int) env('ORIES_STALE_AFTER_DAYS', 90),

    // Ongoing records ending within this many days are flagged as "ending soon" on dashboards and lists
    'ending_soon_days' => (int) env('ORIES_ENDING_SOON_DAYS', 30),

    // Submissions waiting longer than this many working days are highlighted and remind the verifiers
    'verify_within_days' => (int) env('ORIES_VERIFY_WITHIN_DAYS', 5),

    'mail_notifications' => (bool) env('ORIES_MAIL_NOTIFICATIONS', false),

    // Password given to every seeded account; always override it on a server reachable from the Internet
    'seed_password' => env('ORIES_SEED_PASSWORD', 'password123'),

];
