<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Documentation Title
    |--------------------------------------------------------------------------
    */
    'title' => env('LARAVEL_DATA_DOC_TITLE', 'API Documentation'),

    /*
    |--------------------------------------------------------------------------
    | Documentation Description
    |--------------------------------------------------------------------------
    */
    'description' => env('LARAVEL_DATA_DOC_DESCRIPTION', 'Generated from Laravel Data DTOs'),

    /*
    |--------------------------------------------------------------------------
    | Output Path
    |--------------------------------------------------------------------------
    */
    'output_path' => storage_path('app/docs'),

    /*
    |--------------------------------------------------------------------------
    | Data Class Paths
    |--------------------------------------------------------------------------
    | Directories to scan for Laravel Data classes
    */
    'scan_paths' => [
        app_path('Data'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Prefixes to Document
    |--------------------------------------------------------------------------
    | Only routes with these prefixes will be included in documentation.
    | Leave empty to include all routes.
    */
    'route_prefixes' => [
        'api/v1',
        'api',
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Documentation Settings
    |--------------------------------------------------------------------------
    */
    'routes' => [
        'enabled' => true,
        'include_middleware' => false,
        'group_by_prefix' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Exclude Patterns
    |--------------------------------------------------------------------------
    */
    'exclude_patterns' => [
        '*Test*',
        '*Stub*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Template Settings
    |--------------------------------------------------------------------------
    */
    'template' => [
        'theme' => 'default', // default, dark, custom
        'logo_url' => null,
        'primary_color' => '#3b82f6',
    ],
];
