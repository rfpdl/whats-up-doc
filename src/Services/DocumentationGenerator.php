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
use Rfpdl\WhatsUpDoc\Support\SchemaRegistry;
use Rfpdl\WhatsUpDoc\Support\TypeResolver;
use Spatie\LaravelData\Data;

class DocumentationGenerator
{
    private ErrorCollector $errors;

    public function __construct(
        private readonly TypeResolver $typeResolver,
        private readonly DocblockParser $docblockParser,
        private readonly AttributeReader $attributeReader,
        private readonly RouteScanner $routeScanner,
        private readonly SchemaRegistry $schemaRegistry,
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
        $openApi = $this->buildOpenApiArray($dataClasses);

        File::put(
            $outputPath . '/openapi.json',
            json_encode($openApi, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Build the full OpenAPI array without writing to disk
     */
    public function buildOpenApiArray(Collection $dataClasses): array
    {
        $documentation = $this->buildDocumentation($dataClasses);
        $routes = $this->routeScanner->scanRoutes($dataClasses);

        $openApi = [
            'openapi' => config('whats-up-doc.openapi.version', '3.1.0'),
            'info' => $this->buildOpenApiInfo(),
            'paths' => $this->buildOpenApiPaths($routes, $documentation),
            'components' => [
                'schemas' => $this->buildOpenApiSchemas($documentation),
            ],
        ];

        $servers = config('whats-up-doc.openapi.servers', []);
        if (!empty($servers)) {
            $openApi['servers'] = $servers;
        } elseif ($appUrl = config('app.url')) {
            $openApi['servers'] = [['url' => $appUrl]];
        }

        $securitySchemes = config('whats-up-doc.openapi.security_schemes', []);
        if (!empty($securitySchemes)) {
            $openApi['components']['securitySchemes'] = $securitySchemes;
        }

        return $openApi;
    }

    private function buildOpenApiInfo(): array
    {
        $info = [
            'title' => config('whats-up-doc.title', 'API Documentation'),
            'description' => config('whats-up-doc.description', ''),
            'version' => config('whats-up-doc.openapi.info_version', '1.0.0'),
        ];

        if ($contact = config('whats-up-doc.openapi.contact')) {
            $info['contact'] = $contact;
        }

        if ($license = config('whats-up-doc.openapi.license')) {
            $info['license'] = $license;
        }

        return $info;
    }

    /**
     * Build documentation structure from scanned classes, discovering nested Data classes
     */
    private function buildDocumentation(Collection $dataClasses): array
    {
        $this->schemaRegistry->clear();
        $documentation = [];
        $maxDepth = (int) config('whats-up-doc.scan.max_nesting_depth', 10);

        foreach ($dataClasses as $dataClass) {
            $reflection = $dataClass['reflection'];
            $className = $dataClass['class'];

            try {
                $documentation[$className] = $this->buildClassDocumentation($reflection, $dataClass);
                $this->schemaRegistry->register($className, $documentation[$className]);
            } catch (\Throwable $e) {
                $this->errors->add(ScanError::reflectionFailed($className, $e));
            }
        }

        if (config('whats-up-doc.scan.follow_nested', true)) {
            $documentation = $this->resolveNestedSchemas($documentation, $maxDepth);
        }

        return $documentation;
    }

    /**
     * Process the SchemaRegistry pending queue to discover and build nested Data class schemas
     */
    private function resolveNestedSchemas(array $documentation, int $maxDepth): array
    {
        $depth = 0;

        while ($this->schemaRegistry->hasPending() && $depth < $maxDepth) {
            $pending = $this->schemaRegistry->getPending();
            $depth++;

            foreach ($pending as $className) {
                if ($this->schemaRegistry->isResolving($className)) {
                    continue;
                }

                if (!class_exists($className)) {
                    $this->schemaRegistry->register($className, []);
                    continue;
                }

                $this->schemaRegistry->markResolving($className);

                try {
                    $reflection = new ReflectionClass($className);
                    $dataClass = [
                        'class' => $className,
                        'reflection' => $reflection,
                    ];
                    $classDoc = $this->buildClassDocumentation($reflection, $dataClass);
                    $documentation[$className] = $classDoc;
                    $this->schemaRegistry->register($className, $classDoc);
                } catch (\Throwable $e) {
                    $this->errors->add(ScanError::reflectionFailed($className, $e));
                    $this->schemaRegistry->register($className, []);
                } finally {
                    $this->schemaRegistry->unmarkResolving($className);
                }
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

        // Queue referenced Data classes for nested resolution
        if ($typeInfo['isDataClass'] && $typeInfo['reference'] && !$this->schemaRegistry->has($typeInfo['reference'])) {
            $this->schemaRegistry->queueForResolution($typeInfo['reference']);
        }
        if ($typeInfo['isArray'] && $typeInfo['nestedType'] && $this->typeResolver->isDataClass($typeInfo['nestedType'])) {
            if (!$this->schemaRegistry->has($typeInfo['nestedType'])) {
                $this->schemaRegistry->queueForResolution($typeInfo['nestedType']);
            }
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
            'hasDefault' => $this->propertyHasDefault($property),
            'default' => $this->propertyHasDefault($property) ? $this->getPropertyDefault($property) : null,
        ];
    }

    /**
     * Generate an example JSON object for a class
     */
    private function generateExample(ReflectionClass $reflection, array $visited = []): array
    {
        $example = [];
        $className = $reflection->getName();

        if (in_array($className, $visited)) {
            return ['...' => '(circular reference)'];
        }

        $visited[] = $className;

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

                if (!empty($docblock['example'])) {
                    $example[$property->getName()] = $this->parseExampleValue($docblock['example']);
                } elseif ($typeInfo['isDataClass'] && $typeInfo['reference'] && class_exists($typeInfo['reference'])) {
                    $nestedReflection = new ReflectionClass($typeInfo['reference']);
                    $nestedExample = $this->generateExample($nestedReflection, $visited);
                    $example[$property->getName()] = $typeInfo['nullable'] ? $nestedExample : $nestedExample;
                } elseif ($typeInfo['isArray'] && $typeInfo['nestedType'] && $this->typeResolver->isDataClass($typeInfo['nestedType'])) {
                    $nestedReflection = new ReflectionClass($typeInfo['nestedType']);
                    $example[$property->getName()] = [$this->generateExample($nestedReflection, $visited)];
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

    private function propertyHasDefault(ReflectionProperty $property): bool
    {
        if ($property->hasDefaultValue()) {
            return true;
        }

        if ($property->isPromoted()) {
            $constructor = $property->getDeclaringClass()->getConstructor();
            if ($constructor) {
                foreach ($constructor->getParameters() as $param) {
                    if ($param->getName() === $property->getName() && $param->isDefaultValueAvailable()) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function getPropertyDefault(ReflectionProperty $property): mixed
    {
        if ($property->hasDefaultValue()) {
            return $property->getDefaultValue();
        }

        if ($property->isPromoted()) {
            $constructor = $property->getDeclaringClass()->getConstructor();
            if ($constructor) {
                foreach ($constructor->getParameters() as $param) {
                    if ($param->getName() === $property->getName() && $param->isDefaultValueAvailable()) {
                        return $param->getDefaultValue();
                    }
                }
            }
        }

        return null;
    }

    private function isOpenApi31(): bool
    {
        return version_compare(config('whats-up-doc.openapi.version', '3.1.0'), '3.1.0', '>=');
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

                if ($method === 'head') {
                    continue;
                }

                $docEndpoint = $route['doc_endpoint'] ?? null;

                $tags = $docEndpoint?->tags
                    ?? ($docEndpoint?->group ? [$docEndpoint->group] : null)
                    ?? [$this->extractRouteTag($route['uri'])];

                $operation = [
                    'summary' => $route['description'] ?? '',
                    'operationId' => $route['name'] ?? "{$method}_{$route['uri']}",
                    'tags' => $tags,
                    'responses' => [
                        '200' => [
                            'description' => 'Successful response',
                        ],
                    ],
                ];

                if ($docEndpoint?->description) {
                    $operation['description'] = $docEndpoint->description;
                }

                // Add path parameters
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

                // Merge custom parameters from #[DocParam] attributes
                $customParams = $route['custom_params'] ?? [];
                foreach ($customParams as $docParam) {
                    $paramSchema = ['type' => $docParam->type];
                    $paramEntry = [
                        'name' => $docParam->name,
                        'in' => $docParam->in,
                        'required' => $docParam->required,
                        'schema' => $paramSchema,
                    ];
                    if ($docParam->description) {
                        $paramEntry['description'] = $docParam->description;
                    }
                    if ($docParam->example !== null) {
                        $paramEntry['example'] = $docParam->example;
                    }
                    $operation['parameters'][] = $paramEntry;
                }

                // Add request body from Data class or #[DocBody]
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
                } elseif ($customBody = $route['custom_body'] ?? null) {
                    $operation['requestBody'] = [
                        'required' => $customBody->required,
                        'content' => [
                            $customBody->mediaType => [
                                'schema' => $customBody->schema,
                            ],
                        ],
                    ];
                    if ($customBody->description) {
                        $operation['requestBody']['description'] = $customBody->description;
                    }
                }

                // Add response from Data class
                if ($route['response_data'] && isset($documentation[$route['response_data']])) {
                    $schemaName = $documentation[$route['response_data']]['name'];
                    
                    // Link schema to default 200 response
                    if (isset($operation['responses']['200'])) {
                        $operation['responses']['200']['content'] = [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => "#/components/schemas/{$schemaName}",
                                ],
                            ],
                        ];
                    }
                    
                    // Also link schema to custom status code responses (201, 204, etc.)
                    foreach ($operation['responses'] as $code => $response) {
                        if ($code != '200' && !isset($response['content'])) {
                            $operation['responses'][$code]['content'] = [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => "#/components/schemas/{$schemaName}",
                                    ],
                                ],
                            ];
                        }
                    }
                }

                // Merge custom responses from #[DocResponse] attributes
                $customResponses = $route['custom_responses'] ?? [];
                $hasReplace = false;
                foreach ($customResponses as $docResponse) {
                    $statusCode = (string) $docResponse->status;
                    $responseEntry = [
                        'description' => $docResponse->description ?? 'Response',
                    ];

                    // Auto-link schema from response_data if not explicitly provided
                    if (!$docResponse->schema && !$docResponse->ref && $route['response_data'] && isset($documentation[$route['response_data']])) {
                        $schemaName = $documentation[$route['response_data']]['name'];
                        $responseEntry['content'] = [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => "#/components/schemas/{$schemaName}",
                                ],
                            ],
                        ];
                    } elseif ($docResponse->schema) {
                        $responseEntry['content'] = [
                            'application/json' => [
                                'schema' => $docResponse->schema,
                            ],
                        ];
                    } elseif ($docResponse->ref) {
                        $responseEntry['content'] = [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => "#/components/schemas/{$docResponse->ref}",
                                ],
                            ],
                        ];
                    }

                    $operation['responses'][$statusCode] = $responseEntry;

                    if ($docResponse->replace) {
                        $hasReplace = true;
                    }
                }
                
                // Auto-link schema to custom responses that don't have one
                if ($route['response_data'] && isset($documentation[$route['response_data']])) {
                    $schemaName = $documentation[$route['response_data']]['name'];
                    foreach ($operation['responses'] as $code => &$resp) {
                        if (!isset($resp['content'])) {
                            $refSchema = $schemaName;
                            // If custom DocResponse has ref, prefer that
                            foreach ($customResponses as $docResponse) {
                                if ((string)$docResponse->status === $code && $docResponse->ref) {
                                    $refSchema = $docResponse->ref;
                                }
                            }
                            $resp['content'] = [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => "#/components/schemas/{$refSchema}",
                                    ],
                                ],
                            ];
                        }
                    }
                }

                // If replace mode is enabled, remove default 200 response
                if ($hasReplace) {
                    unset($operation['responses']['200']);
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
            if ($this->isOpenApi31()) {
                if (isset($schema['type']) && is_string($schema['type'])) {
                    $schema['type'] = [$schema['type'], 'null'];
                } elseif (isset($schema['$ref'])) {
                    $schema = [
                        'oneOf' => [
                            ['$ref' => $schema['$ref']],
                            ['type' => 'null'],
                        ],
                    ] + array_diff_key($schema, ['$ref' => true]);
                }
            } else {
                $schema['nullable'] = true;
            }
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
