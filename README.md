# What's Up Doc

Generate beautiful API documentation from your Laravel Data DTOs automatically.

## Features

- 🚀 **Zero Configuration** - Works out of the box
- 📊 **Multiple Formats** - HTML, JSON, OpenAPI
- 🎨 **Beautiful UI** - Clean, responsive documentation
- ⚡ **Fast Generation** - Scans and generates docs quickly
- 🔧 **Customizable** - Themes, colors, and branding
- 🛣️ **Route Integration** - Auto-detects API endpoints using Laravel Data
- 📋 **Request/Response Documentation** - Shows Data classes used in routes

## Installation

```bash
composer require rfpdl/whats-up-doc
```

## Quick Start

```bash
# Generate HTML documentation
php artisan data-doc:generate

# Generate JSON schema
php artisan data-doc:generate --format=json

# Generate OpenAPI specification
php artisan data-doc:generate --format=openapi

# Custom output directory
php artisan data-doc:generate --output=/path/to/docs
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --provider="Rfpdl\WhatsUpDoc\WhatsUpDocServiceProvider" --tag="config"
```

### Configuration Options

```php
return [
    'title' => 'My API Documentation',
    'description' => 'Generated from Laravel Data DTOs',
    'output_path' => storage_path('app/docs'),
    'scan_paths' => [
        app_path('Data'),
    ],
    'route_prefixes' => [
        'api/v1',
        'api',
    ],
    'routes' => [
        'enabled' => true,
        'include_middleware' => false,
        'group_by_prefix' => true,
    ],
    'template' => [
        'theme' => 'default',
        'primary_color' => '#3b82f6',
        'logo_url' => null,
    ],
];
```

## Usage Example

Given a Laravel Data class:

```php
<?php

namespace App\Data;

use Spatie\LaravelData\Data;

/**
 * User data transfer object
 */
class UserData extends Data
{
    public function __construct(
        /** User's unique identifier */
        public int $id,
        
        /** User's full name */
        public string $name,
        
        /** User's email address */
        public string $email,
        
        /** User's profile picture URL */
        public ?string $avatar = null,
    ) {}
}
```

The generated documentation will include:
- Property types and descriptions
- Nullable indicators
- JSON examples
- Clean, searchable interface

## Route Integration

What's Up Doc automatically detects API routes that use Laravel Data classes and generates endpoint documentation.

### Controller Example

```php
<?php

namespace App\Http\Controllers\Api;

use App\Data\UserData;
use App\Data\CreateUserData;

class UserController extends Controller
{
    /**
     * Get a user by ID
     */
    public function show(int $id): UserData
    {
        // Your implementation
    }

    /**
     * Create a new user
     */
    public function store(CreateUserData $userData): UserData
    {
        // Your implementation
    }
}
```

### Route Definition

```php
Route::prefix('api/v1')->group(function () {
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::post('/users', [UserController::class, 'store']);
});
```

### Generated Documentation

The package will automatically:
- Detect routes using Laravel Data classes
- Show HTTP methods and endpoints
- Document request/response Data classes
- Extract route parameters
- Include controller method descriptions

## Roadmap

### Phase 1 (Complete - MVP)
- [x] Basic Laravel Data scanning
- [x] HTML documentation generation
- [x] JSON schema export
- [x] OpenAPI specification
- [x] Configurable themes

### Phase 2 (Current - Enhanced Features)
- [x] Route integration (auto-detect endpoints using Data classes)
- [x] Request/Response documentation
- [x] Route parameter extraction
- [ ] Webhook documentation
- [ ] Custom annotations support
- [ ] Multiple theme options

### Phase 3 (Future)
- [ ] Interactive API testing
- [ ] Postman collection export
- [ ] Custom templates
- [ ] Team collaboration features

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
