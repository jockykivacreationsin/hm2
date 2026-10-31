<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Initial administrator
    |--------------------------------------------------------------------------
    |
    | Used only by Database\Seeders\AdminUserSeeder to create the first login on a fresh install. Nothing here is
    | a default credential: with no ADMIN_PASSWORD set the seeder generates a random one and prints it once.
    |
    */

    'admin' => [
        'name' => env('ADMIN_NAME', 'Administrator'),
        'email' => env('ADMIN_EMAIL', 'admin@example.com'),
        'mobile' => env('ADMIN_MOBILE', ''),
        'password' => env('ADMIN_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Hospital identity (initial values only)
    |--------------------------------------------------------------------------
    |
    | Seeded into the `settings` row once; afterwards the Settings screen is the source of truth.
    |
    */

    'name' => env('HOSPITAL_NAME', 'My Hospital'),

];
