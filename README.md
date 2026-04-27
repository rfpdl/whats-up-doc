# What's Up Doc

Generate API documentation from your Laravel Data DTOs automatically.

## Features

- **Nested Schema Traversal** - Automatically discovers and documents nested Data classes, even deeply nested (e.g. `FileData -> PageData[] -> DetectionData`)
- **OpenAPI 3.1.0** - Full spec generation with proper nullable types, servers, and security schemes
- **Interactive UI** - Browse docs at `/docs/api` with Stoplight Elements (search, try-it-out, dark mode)
- **Multiple Formats** - HTML, JSON, and OpenAPI output
- **Glob Scan Paths** - DDD-friendly with patterns like `app/Domains/*/DataObjects`
- **Custom Endpoint Docs** - Document non-Data endpoints with `#[DocEndpoint]`, `#[DocParam]`, `#[DocBody]`, `#[DocResponse]` attributes
- **Route Detection** - Auto-detects API endpoints using Laravel Data classes
- **Validation Mapping** - Spatie validation attributes become OpenAPI constraints

## Installation

```bash
composer require rfpdl/whats-up-doc
```

## Quick Start

```bash
# Generate HTML documentation
php artisan data-doc:generate

# Generate OpenAPI specification
php artisan data-doc:generate --format=openapi

# Generate JSON schema
php artisan data-doc:generate --format=json

# Custom output directory
php artisan data-doc:generate --output=/path/to/docs
```

Or visit `/docs/api` in your browser for the interactive UI (enabled by default).

## Configuration

```bash
php artisan vendor:publish --provider="Rfpdl\WhatsUpDoc\WhatsUpDocServiceProvider" --tag="config"
```

```php
return [
    'title' => 'My API Documentation',
    'description' => 'Generated from Laravel Data DTOs',
    'output_path' => storage_path('app/docs'),

    // Supports glob patterns for DDD layouts
    'scan_paths' => [
        app_path('Domains/*/DataObjects'),
    ],

    'route_prefixes' => ['api'],

    'scan' => [
        'follow_nested' => true,
        'max_nesting_depth' => 10,
    ],

    'openapi' => [
        'version' => '3.1.0',
        'servers' => [],
        'security_schemes' => [],
    ],

    'ui' => [
        'enabled' => true,
        'path' => 'docs/api',
        'middleware' => ['web'],
    ],
];
```

## Usage

### Data Classes

```php
use Spatie\LaravelData\Data;

/**
 * User data transfer object
 */
class UserData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?AddressData $address = null,
        /** @var RoleData[] */
        public array $roles = [],
    ) {}
}
```

Nested classes like `AddressData` and `RoleData` are automatically discovered and included in the OpenAPI spec with proper `$ref` links.

### Route Detection

```php
class UserController extends Controller
{
    public function show(int $id): UserData { /* ... */ }
    public function store(CreateUserData $data): UserData { /* ... */ }
}
```

Routes using Data classes are automatically documented with request/response schemas.

### Custom Endpoint Documentation

For endpoints that don't use Laravel Data:

```php
use Rfpdl\WhatsUpDoc\Attributes\{DocEndpoint, DocParam, DocBody, DocResponse};

class WebhookController extends Controller
{
    #[DocEndpoint(summary: 'Receive Stripe webhook', group: 'Webhooks')]
    #[DocParam(name: 'signature', in: 'header', type: 'string', required: true)]
    #[DocBody(schema: ['type' => 'object', 'properties' => ['event' => ['type' => 'string']]])]
    #[DocResponse(status: 200, description: 'Webhook processed')]
    #[DocResponse(status: 400, description: 'Invalid payload')]
    public function handleStripe(Request $request): JsonResponse
    {
        // ...
    }
}
```

## Requirements

- PHP 8.2+
- Laravel 10, 11, or 12
- Spatie Laravel Data 3 or 4

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
