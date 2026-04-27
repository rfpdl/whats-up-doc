<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Documentation Title
    |--------------------------------------------------------------------------
    */
    'title' => env('WHATS_UP_DOC_TITLE', 'API Documentation'),

    /*
    |--------------------------------------------------------------------------
    | Documentation Description
    |--------------------------------------------------------------------------
    */
    'description' => env('WHATS_UP_DOC_DESCRIPTION', 'Generated from Laravel Data DTOs'),

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
    | Directories to scan for Laravel Data classes.
    | Glob patterns are supported for DDD layouts.
    */
    // Example: app_path('Domains') . '/' . '*' . '/DataObjects'
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
    | Scan Settings
    |--------------------------------------------------------------------------
    */
    'scan' => [
        'follow_nested' => true,
        'max_nesting_depth' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAPI Settings
    |--------------------------------------------------------------------------
    */
    'openapi' => [
        'version' => '3.1.0',
        'info_version' => '1.0.0',
        'servers' => [],
        'security_schemes' => [],
        'contact' => null,
        'license' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Interactive UI Settings
    |--------------------------------------------------------------------------
    */
    'ui' => [
        'enabled' => true,
        'path' => 'docs/api',
        'middleware' => ['web'],
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
