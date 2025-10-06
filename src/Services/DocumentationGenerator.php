<?php

declare(strict_types=1);

namespace Rfpdl\WhatsUpDoc\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use ReflectionClass;
use ReflectionProperty;
use Rfpdl\WhatsUpDoc\Services\RouteScanner;

class DocumentationGenerator
{
    public function generateHtml(Collection $dataClasses, string $outputPath): void
    {
        $documentation = $this->buildDocumentation($dataClasses);
        $routes = $this->scanRoutes($dataClasses);

        $html = View::make('whats-up-doc::documentation', [
            'title' => config('whats-up-doc.title'),
            'description' => config('whats-up-doc.description'),
            'documentation' => $documentation,
            'routes' => $routes,
            'config' => config('whats-up-doc.template'),
        ])->render();

        File::put($outputPath . '/index.html', $html);
    }

    public function generateJson(Collection $dataClasses, string $outputPath): void
    {
        $documentation = $this->buildDocumentation($dataClasses);
        
        File::put(
            $outputPath . '/documentation.json',
            json_encode($documentation, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    public function generateOpenApi(Collection $dataClasses, string $outputPath): void
    {
        $documentation = $this->buildDocumentation($dataClasses);
        
        $openApi = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => config('whats-up-doc.title'),
                'description' => config('whats-up-doc.description'),
                'version' => '1.0.0',
            ],
            'components' => [
                'schemas' => $this->buildOpenApiSchemas($documentation),
            ],
        ];

        File::put(
            $outputPath . '/openapi.json',
            json_encode($openApi, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    private function buildDocumentation(Collection $dataClasses): array
    {
        $documentation = [];

        foreach ($dataClasses as $dataClass) {
            $reflection = $dataClass['reflection'];
            $className = $dataClass['class'];

            $documentation[$className] = [
                'name' => $reflection->getShortName(),
                'namespace' => $reflection->getNamespaceName(),
                'description' => $this->getClassDescription($reflection),
                'properties' => $this->getProperties($reflection),
                'example' => $this->generateExample($reflection),
            ];
        }

        return $documentation;
    }

    private function getClassDescription(ReflectionClass $reflection): string
    {
        $docComment = $reflection->getDocComment();
        
        if (!$docComment) {
            return '';
        }

        // Extract description from docblock
        preg_match('/\/\*\*\s*\n\s*\*\s*(.+?)\n/', $docComment, $matches);
        
        return $matches[1] ?? '';
    }

    private function getProperties(ReflectionClass $reflection): array
    {
        $properties = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $type = $property->getType();
            
            $properties[$property->getName()] = [
                'name' => $property->getName(),
                'type' => $type ? $type->__toString() : 'mixed',
                'nullable' => $type && $type->allowsNull(),
                'description' => $this->getPropertyDescription($property),
            ];
        }

        return $properties;
    }

    private function getPropertyDescription(ReflectionProperty $property): string
    {
        $docComment = $property->getDocComment();
        
        if (!$docComment) {
            return '';
        }

        preg_match('/\/\*\*\s*\n\s*\*\s*(.+?)\n/', $docComment, $matches);
        
        return $matches[1] ?? '';
    }

    private function generateExample(ReflectionClass $reflection): array
    {
        $example = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $type = $property->getType();
            $example[$property->getName()] = $this->getExampleValue($type?->__toString() ?? 'mixed');
        }

        return $example;
    }

    private function getExampleValue(string $type): mixed
    {
        return match ($type) {
            'string' => 'example string',
            'int', 'integer' => 123,
            'float', 'double' => 123.45,
            'bool', 'boolean' => true,
            'array' => [],
            default => null,
        };
    }

    private function buildOpenApiSchemas(array $documentation): array
    {
        $schemas = [];

        foreach ($documentation as $className => $data) {
            $properties = [];
            $required = [];

            foreach ($data['properties'] as $property) {
                $properties[$property['name']] = [
                    'type' => $this->mapTypeToOpenApi($property['type']),
                    'description' => $property['description'],
                ];

                if (!$property['nullable']) {
                    $required[] = $property['name'];
                }
            }

            $schemas[$data['name']] = [
                'type' => 'object',
                'description' => $data['description'],
                'properties' => $properties,
                'required' => $required,
            ];
        }

        return $schemas;
    }

    private function mapTypeToOpenApi(string $type): string
    {
        return match ($type) {
            'int', 'integer' => 'integer',
            'float', 'double' => 'number',
            'bool', 'boolean' => 'boolean',
            'array' => 'array',
            default => 'string',
        };
    }

    private function scanRoutes(Collection $dataClasses): Collection
    {
        $routeScanner = app(RouteScanner::class);
        return $routeScanner->scanRoutes($dataClasses);
    }
}
