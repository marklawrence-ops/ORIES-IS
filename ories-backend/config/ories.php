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
        'credentials' => env('GOOGLE_DRIVE_CREDENTIALS', storage_path('app/google-credentials.json')),
        'folder_id' => env('GOOGLE_DRIVE_FOLDER_ID'),
        'import_folder_id' => env('GOOGLE_DRIVE_IMPORT_FOLDER_ID', env('GOOGLE_DRIVE_FOLDER_ID')),
    ],

    // Ongoing records with no verified update for this many days trigger a reminder
    'stale_after_days' => (int) env('ORIES_STALE_AFTER_DAYS', 90),

    'mail_notifications' => (bool) env('ORIES_MAIL_NOTIFICATIONS', false),

];
