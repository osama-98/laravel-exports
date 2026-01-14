<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Export Settings
    |--------------------------------------------------------------------------
    |
    | These settings will be used as defaults for all exports unless
    | overridden when creating an export.
    |
    */

    'default_chunk_size' => env('EXPORT_CHUNK_SIZE', 100),

    'default_file_disk' => env('EXPORT_FILE_DISK', 'local'),

    'default_queue_connection' => env('EXPORT_QUEUE_CONNECTION'),

    'default_queue_name' => env('EXPORT_QUEUE_NAME', 'default'),

    'default_batch_name' => env('EXPORT_BATCH_NAME', 'Exports'),

    /*
    |--------------------------------------------------------------------------
    | Export Formats
    |--------------------------------------------------------------------------
    |
    | Supported export formats. You can add custom formats here.
    |
    */

    'formats' => [
        'csv' => \Osama\LaravelExports\Exports\Enums\ExportFormat::Csv,
        'xlsx' => \Osama\LaravelExports\Exports\Enums\ExportFormat::Xlsx,
    ],

    /*
    |--------------------------------------------------------------------------
    | CSV Settings
    |--------------------------------------------------------------------------
    */

    'csv' => [
        'delimiter' => env('EXPORT_CSV_DELIMITER', ','),
        'enclosure' => '"',
        'escape' => '\\',
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Settings
    |--------------------------------------------------------------------------
    */

    'storage' => [
        'path' => 'exports',
        'visibility' => 'private',
    ],
];
