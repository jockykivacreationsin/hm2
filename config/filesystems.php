<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            // No unauthenticated GET /storage/{path} route: nothing here is meant to be fetched by URL.
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // Patient / staff photos and the hospital logos. Private: files are only reachable through the authenticated
        // `uploads.show` route (see App\Support\Uploads), never as static files under public/.
        'uploads' => [
            'driver' => 'local',
            'root' => env('UPLOADS_ROOT', storage_path('app/uploads')),
            'throw' => false,
            'report' => false,
        ],

        // PDFs generated on demand (collection reports ...). Private: served by the authenticated `pdf.static-file`
        // route, never as static files under public/.
        'reports' => [
            'driver' => 'local',
            'root' => env('REPORTS_ROOT', storage_path('app/reports')),
            'throw' => false,
            'report' => false,
        ],

        // Patient document uploads. Private: never served as static files; always streamed through
        // PatientDocumentController::download() after a permission check.
        'documents' => [
            'driver' => 'local',
            'root' => env('DOCUMENTS_ROOT', storage_path('app/documents')),
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
