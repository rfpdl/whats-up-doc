# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased] - 2025-01-02

### Added - Route Integration (Phase 2)
- **RouteScanner Service**: Automatically detects API routes that use Laravel Data classes
- **Route Documentation**: Generates endpoint documentation with HTTP methods, parameters, and Data class usage
- **Request/Response Mapping**: Identifies Laravel Data classes used in controller method parameters and return types
- **Enhanced UI**: Updated documentation template to display API endpoints alongside Data classes
- **Route Configuration**: Added configurable route prefixes and documentation settings
- **Controller Analysis**: Extracts method descriptions from docblocks
- **Parameter Detection**: Automatically identifies route parameters and their types

### Enhanced
- **Navigation**: Improved sidebar with separate sections for API endpoints and Data classes
- **Configuration**: Added route-specific settings in config file
- **Service Provider**: Registered RouteScanner as singleton service
- **Documentation Template**: Color-coded HTTP method badges and improved layout

### Technical Improvements
- **Examples**: Added comprehensive example files showing controller and route patterns
- **Tests**: Created basic test structure with route integration tests
- **Documentation**: Updated README with route integration examples and usage

### Configuration Changes
```php
'routes' => [
    'enabled' => true,
    'include_middleware' => false,
    'group_by_prefix' => true,
],
```

## [1.0.0] - 2025-01-01

### Added - MVP (Phase 1)
- **Core Functionality**: Laravel Data class scanning and documentation generation
- **Multiple Output Formats**: HTML, JSON, and OpenAPI specification support
- **Beautiful UI**: TailwindCSS-based responsive documentation interface
- **Configuration System**: Flexible configuration with environment variable support
- **Service Provider**: Proper Laravel package integration
- **Artisan Command**: `data-doc:generate` command with multiple format options
- **Theme Support**: Configurable colors and branding options
