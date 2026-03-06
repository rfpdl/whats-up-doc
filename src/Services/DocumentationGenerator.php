<?php

declare(strict_types=1);

namespace Rfpdl\WhatsUpDoc\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use ReflectionClass;
use ReflectionProperty;
use Rfpdl\WhatsUpDoc\Support\AttributeReader;
use Rfpdl\WhatsUpDoc\Support\DocblockParser;
use Rfpdl\WhatsUpDoc\Support\ErrorCollector;
use Rfpdl\WhatsUpDoc\Support\ScanError;
use Rfpdl\WhatsUpDoc\Support\TypeResolver;

class DocumentationGenerator
{
    private ErrorCollector $errors;

    public function __construct(
        private readonly TypeResolver $typeResolver,
        private readonly DocblockParser $docblockParser,
        private readonly AttributeReader $attributeReader,
        private readonly RouteScanner $routeScanner,
    ) {
        $this->errors = new ErrorCollector();
    }

    /**
     * Get errors from the last generation
     */
    public function getErrors(): ErrorCollector
    {
        return $this->errors;
    }

    /**
     * Generate HTML documentation
     */
    public function generateHtml(Collection $dataClasses, string $outputPath): void
    {
        $documentation = $this->buildDocumentation($dataClasses);
        $routes = $this->routeScanner->scanRoutes($dataClasses);

        $html = View::make('whats-up-doc::documentation', [
            'title' => config('whats-up-doc.title'),
            'description' => config('whats-up-doc.description'),
            'documentation' => $documentation,
            'routes' => $routes,
            'config' => config('whats-up-doc.template'),
        ])->render();

        File::put($outputPath . '/index.html', $html);
    }

    /**
     * Generate JSON schema documentation
     */
    public function generateJson(Collection $dataClasses, string $outputPath): void
    {
        $documentation = $this->buildDocumentation($dataClasses);

        File::put(
            $outputPath . '/documentation.json',
            json_encode($documentation, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Generate OpenAPI specification
     */
    public function generateOpenApi(Collection $dataClasses, string $outputPath): void
    {
        $documentation = $this->buildDocumentation($dataClasses);
        $routes = $this->routeScanner->scanRoutes($dataClasses);

        $openApi = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => config('whats-up-doc.title'),
                'description' => config('whats-up-doc.description'),
                'version' => '1.0.0',
            ],
            'paths' => $this->buildOpenApiPaths($routes, $documentation),
            'components' => [
                'schemas' => $this->buildOpenApiSchemas($documentation),
            ],
        ];

        File::put(
            $outputPath . '/openapi.json',
            json_encode($openApi, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Build documentation structure from scanned classes
     */
    private function buildDocumentation(Collection $dataClasses): array
    {
        $documentation = [];

        foreach ($dataClasses as $dataClass) {
            $reflection = $dataClass['reflection'];
            $className = $dataClass['class'];

            try {
                $documentation[$className] = $this->buildClassDocumentation($reflection, $dataClass);
            } catch (\Throwable $e) {
                $this->errors->add(ScanError::reflectionFailed($className, $e));
            }
        }

        return $documentation;
    }

    /**
     * Build documentation for a single class
     */
    private function buildClassDocumentation(ReflectionClass $reflection, array $dataClass): array
    {
        $classDocblock = $dataClass['docblock'] ?? $this->docblockParser->parseClass($reflection);
        $classAttributes = $this->attributeReader->readClassAttributes($reflection);

        return [
            'name' => $reflection->getShortName(),
            'namespace' => $reflection->getNamespaceName(),
            'fullName' => $reflection->getName(),
            'description' => $classDocblock['description'] ?? '',
            'deprecated' => $classDocblock['deprecated'] ?? null,
            'properties' => $this->buildProperties($reflection),
            'example' => $this->generateExample($reflection),
            'attributes' => $classAttributes,
            'see' => $classDocblock['see'] ?? [],
        ];
    }

    /**
     * Build property documentation
     */
    private function buildProperties(ReflectionClass $reflection): array
    {
        $properties = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            // Skip static properties
            if ($property->isStatic()) {
                continue;
            }

            // Check if property is hidden via attribute
            if ($this->attributeReader->isHidden($property)) {
                continue;
            }

            try {
                $properties[$property->getName()] = $this->buildPropertyDocumentation($property);
            } catch (\Throwable $e) {
                $this->errors->add(ScanError::typeResolutionFailed(
                    $reflection->getName(),
                    $property->getName(),
                    $e
                ));
            }
        }

        return $properties;
    }

    /**
     * Build documentation for a single property
     */
    private function buildPropertyDocumentation(ReflectionProperty $property): array
    {
        // Resolve the type
        $typeInfo = $this->typeResolver->resolveFromProperty($property);

        // Parse the docblock
        $docblock = $this->docblockParser->parseProperty($property);

        // Get validation rules from attributes
        $validationRules = $this->attributeReader->extractValidationRules($property);

        // Get name mappings
        $inputName = $this->attributeReader->getInputNameMapping($property);
        $outputName = $this->attributeReader->getOutputNameMapping($property);

        // Check for collection item type
        $collectionItemType = $this->attributeReader->getCollectionItemType($property);
        if ($collectionItemType && $typeInfo['isArray']) {
            $typeInfo['nestedType'] = $collectionItemType;
        }

        return [
            'name' => $property->getName(),
            'type' => $typeInfo,
            'description' => $docblock['description'] ?? '',
            'example' => $docblock['example'] ?? null,
            'deprecated' => $docblock['deprecated'] ?? null,
            'validation' => $validationRules,
            'inputName' => $inputName,
            'outputName' => $outputName,
            'hasDefault' => $property->hasDefaultValue(),
            'default' => $property->hasDefaultValue() ? $property->getDefaultValue() : null,
        ];
    }

    /**
     * Generate an example JSON object for a class
     */
    private function generateExample(ReflectionClass $reflection): array
    {
        $example = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic()) {
                continue;
            }

            if ($this->attributeReader->isHidden($property)) {
                continue;
            }

            try {
                $typeInfo = $this->typeResolver->resolveFromProperty($property);
                $docblock = $this->docblockParser->parseProperty($property);

                // Use docblock example if available
                if (!empty($docblock['example'])) {
                    $example[$property->getName()] = $this->parseExampleValue($docblock['example']);
                } else {
                    $example[$property->getName()] = $this->typeResolver->generateExampleValue($typeInfo);
                }
            } catch (\Throwable) {
                $example[$property->getName()] = null;
            }
        }

        return $example;
    }

    /**
     * Parse an example value from a docblock string
     */
    private function parseExampleValue(string $example): mixed
    {
        $example = trim($example);

        // Try to decode as JSON
        $decoded = json_decode($example, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // Check for boolean strings
        if (strtolower($example) === 'true') {
            return true;
        }
        if (strtolower($example) === 'false') {
            return false;
        }
        if (strtolower($example) === 'null') {
            return null;
        }

        // Check for numeric
        if (is_numeric($example)) {
            return str_contains($example, '.') ? (float) $example : (int) $example;
        }

        // Remove quotes if present
        if (preg_match('/^["\'](.+)["\']$/', $example, $matches)) {
            return $matches[1];
        }

        return $example;
    }

    /**
     * Build OpenAPI paths from routes
     */
    private function buildOpenApiPaths(Collection $routes, array $documentation): array
    {
        $paths = [];

        foreach ($routes as $route) {
            $uri = '/' . ltrim($route['uri'], '/');

            if (!isset($paths[$uri])) {
                $paths[$uri] = [];
            }

            foreach ($route['methods'] as $method) {
                $method = strtolower($method);

                // Skip HEAD method
                if ($method === 'head') {
                    continue;
                }

                $operation = [
                    'summary' => $route['description'] ?? '',
                    'operationId' => $route['name'] ?? "{$method}_{$route['uri']}",
                    'tags' => [$this->extractRouteTag($route['uri'])],
                    'responses' => [
                        '200' => [
                            'description' => 'Successful response',
                        ],
                    ],
                ];

                // Add parameters
                if (!empty($route['parameters'])) {
                    $operation['parameters'] = array_map(function ($param) {
                        return [
                            'name' => $param['name'],
                            'in' => 'path',
                            'required' => $param['required'],
                            'schema' => [
                                'type' => $param['type'],
                            ],
                        ];
                    }, $route['parameters']);
                }

                // Add request body if there's request data
                if ($route['request_data'] && isset($documentation[$route['request_data']])) {
                    $schemaName = $documentation[$route['request_data']]['name'];
                    $operation['requestBody'] = [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => "#/components/schemas/{$schemaName}",
                                ],
                            ],
                        ],
                    ];
                }

                // Add response schema if there's response data
                if ($route['response_data'] && isset($documentation[$route['response_data']])) {
                    $schemaName = $documentation[$route['response_data']]['name'];
                    $operation['responses']['200']['content'] = [
                        'application/json' => [
                            'schema' => [
                                '$ref' => "#/components/schemas/{$schemaName}",
                            ],
                        ],
                    ];
                }

                $paths[$uri][$method] = $operation;
            }
        }

        return $paths;
    }

    /**
     * Extract a tag from a route URI (first segment)
     */
    private function extractRouteTag(string $uri): string
    {
        $segments = array_filter(explode('/', $uri));
        foreach ($segments as $segment) {
            if (!str_starts_with($segment, '{')) {
                return ucfirst($segment);
            }
        }
        return 'General';
    }

    /**
     * Build OpenAPI schema components
     */
    private function buildOpenApiSchemas(array $documentation): array
    {
        $schemas = [];

        foreach ($documentation as $className => $data) {
            $properties = [];
            $required = [];

            foreach ($data['properties'] as $property) {
                $propSchema = $this->buildOpenApiPropertySchema($property);
                $properties[$property['name']] = $propSchema;

                // Property is required if it's not nullable and has no default
                if (!$property['type']['nullable'] && !$property['hasDefault']) {
                    $required[] = $property['name'];
                }
            }

            $schema = [
                'type' => 'object',
                'description' => $data['description'],
                'properties' => $properties,
            ];

            if (!empty($required)) {
                $schema['required'] = $required;
            }

            if ($data['deprecated']) {
                $schema['deprecated'] = true;
            }

            $schemas[$data['name']] = $schema;
        }

        return $schemas;
    }

    /**
     * Build OpenAPI schema for a single property
     */
    private function buildOpenApiPropertySchema(array $property): array
    {
        $typeInfo = $property['type'];
        $schema = [];

        // Handle enums
        if ($typeInfo['isEnum'] && !empty($typeInfo['enumValues'])) {
            $schema['type'] = $typeInfo['base'];
            $schema['enum'] = $typeInfo['enumValues'];
        }
        // Handle Data class references
        elseif ($typeInfo['isDataClass'] && $typeInfo['reference']) {
            $refName = $this->typeResolver->getShortClassName($typeInfo['reference']);
            $schema['$ref'] = "#/components/schemas/{$refName}";
        }
        // Handle arrays
        elseif ($typeInfo['isArray']) {
            $schema['type'] = 'array';
            if ($typeInfo['nestedType']) {
                $itemRefName = $this->typeResolver->getShortClassName($typeInfo['nestedType']);
                $schema['items'] = ['$ref' => "#/components/schemas/{$itemRefName}"];
            } else {
                $schema['items'] = ['type' => 'object'];
            }
        }
        // Handle basic types
        else {
            $schema['type'] = $typeInfo['base'];

            if ($typeInfo['format'] ?? null) {
                $schema['format'] = $typeInfo['format'];
            }
        }

        // Add description
        if (!empty($property['description'])) {
            $schema['description'] = $property['description'];
        }

        // Add example
        if ($property['example'] !== null) {
            $schema['example'] = $property['example'];
        }

        // Add validation constraints
        foreach ($property['validation'] as $rule) {
            $this->applyValidationToSchema($schema, $rule);
        }

        // Handle nullable
        if ($typeInfo['nullable']) {
            $schema['nullable'] = true;
        }

        // Handle deprecated
        if ($property['deprecated']) {
            $schema['deprecated'] = true;
        }

        return $schema;
    }

    /**
     * Apply validation rules to an OpenAPI schema
     */
    private function applyValidationToSchema(array &$schema, array $rule): void
    {
        switch ($rule['name']) {
            case 'Min':
                if (($schema['type'] ?? '') === 'string') {
                    $schema['minLength'] = (int) ($rule['constraint'] ? explode(':', $rule['constraint'])[1] ?? 0 : 0);
                } else {
                    $schema['minimum'] = (int) ($rule['constraint'] ? explode(':', $rule['constraint'])[1] ?? 0 : 0);
                }
                break;

            case 'Max':
                if (($schema['type'] ?? '') === 'string') {
                    $schema['maxLength'] = (int) ($rule['constraint'] ? explode(':', $rule['constraint'])[1] ?? 0 : 0);
                } else {
                    $schema['maximum'] = (int) ($rule['constraint'] ? explode(':', $rule['constraint'])[1] ?? 0 : 0);
                }
                break;

            case 'Email':
                $schema['format'] = 'email';
                break;

            case 'Url':
                $schema['format'] = 'uri';
                break;

            case 'Date':
                $schema['format'] = 'date';
                break;

            case 'Regex':
                if (preg_match('/regex:(.+)/', $rule['constraint'] ?? '', $matches)) {
                    $schema['pattern'] = $matches[1];
                }
                break;
        }
    }
}
