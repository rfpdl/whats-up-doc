<?php

declare(strict_types=1);

namespace Rfpdl\WhatsUpDoc\Support;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;
use ReflectionIntersectionType;
use ReflectionType;
use Spatie\LaravelData\Data;

class TypeResolver
{
    /**
     * Resolved type information structure
     */
    public const TYPE_INFO_KEYS = [
        'name',        // Display name (e.g., "string", "UserData", "string|int")
        'base',        // Base type for OpenAPI (e.g., "string", "object", "array")
        'nullable',    // Whether the type allows null
        'isBuiltin',   // Whether it's a PHP builtin type
        'isEnum',      // Whether it's an enum
        'isDataClass', // Whether it's a Laravel Data class
        'isArray',     // Whether it's an array/collection
        'enumValues',  // Enum cases if applicable
        'nestedType',  // For arrays/collections, the item type
        'reference',   // For Data classes, the class name for $ref
    ];

    private array $dataClassCache = [];

    /**
     * Resolve type information from a ReflectionProperty, enriched with docblock info
     */
    public function resolveFromProperty(ReflectionProperty $property): array
    {
        $type = $property->getType();

        if ($type === null) {
            return $this->buildTypeInfo('mixed', 'string', true);
        }

        $result = $this->resolveReflectionType($type);

        if ($result['isArray'] && $result['nestedType'] === null) {
            $docblockType = $this->resolveNestedTypeFromDocblock($property);
            if ($docblockType !== null) {
                $result['nestedType'] = $docblockType;
                if ($this->isDataClass($docblockType)) {
                    $result['isDataClass'] = true;
                }
            }
        }

        return $result;
    }

    /**
     * Try to resolve the nested type of an array property from its @var docblock
     */
    private function resolveNestedTypeFromDocblock(ReflectionProperty $property): ?string
    {
        $docComment = $property->getDocComment();
        if (!$docComment) {
            return null;
        }

        if (!preg_match('/@var\s+(\w+)\[\]/', $docComment, $matches)) {
            return null;
        }

        $shortName = $matches[1];
        $declaringClass = $property->getDeclaringClass();

        return $this->resolveShortClassName($shortName, $declaringClass);
    }

    /**
     * Resolve a short class name to FQCN using the declaring class's use statements
     */
    public function resolveShortClassName(string $shortName, ReflectionClass $declaringClass): ?string
    {
        $fqcn = $declaringClass->getNamespaceName() . '\\' . $shortName;
        if (class_exists($fqcn)) {
            return $fqcn;
        }

        $fileName = $declaringClass->getFileName();
        if (!$fileName || !file_exists($fileName)) {
            return null;
        }

        $content = file_get_contents($fileName);
        if ($content === false) {
            return null;
        }

        if (preg_match('/use\s+([^\s;]*\\\\' . preg_quote($shortName, '/') . ')\s*;/', $content, $matches)) {
            $resolved = $matches[1];
            if (class_exists($resolved)) {
                return $resolved;
            }
        }

        return class_exists($shortName) ? $shortName : null;
    }

    /**
     * Resolve type information from a ReflectionType
     */
    public function resolveReflectionType(?ReflectionType $type): array
    {
        if ($type === null) {
            return $this->buildTypeInfo('mixed', 'string', true);
        }

        if ($type instanceof ReflectionUnionType) {
            return $this->resolveUnionType($type);
        }

        if ($type instanceof ReflectionIntersectionType) {
            return $this->resolveIntersectionType($type);
        }

        if ($type instanceof ReflectionNamedType) {
            return $this->resolveNamedType($type);
        }

        return $this->buildTypeInfo('mixed', 'string', true);
    }

    /**
     * Resolve a named type (single type)
     */
    private function resolveNamedType(ReflectionNamedType $type): array
    {
        $typeName = $type->getName();
        $nullable = $type->allowsNull();

        // Handle builtin types
        if ($type->isBuiltin()) {
            return $this->resolveBuiltinType($typeName, $nullable);
        }

        // Handle class types
        return $this->resolveClassType($typeName, $nullable);
    }

    /**
     * Resolve builtin PHP types
     */
    private function resolveBuiltinType(string $typeName, bool $nullable): array
    {
        $openApiType = match ($typeName) {
            'int', 'integer' => 'integer',
            'float', 'double' => 'number',
            'bool', 'boolean' => 'boolean',
            'array' => 'array',
            'object' => 'object',
            'null' => 'null',
            default => 'string',
        };

        return $this->buildTypeInfo($typeName, $openApiType, $nullable, isBuiltin: true, isArray: $typeName === 'array');
    }

    /**
     * Resolve class-based types (Data classes, Enums, other objects)
     */
    private function resolveClassType(string $className, bool $nullable): array
    {
        // Check if class exists
        if (!class_exists($className) && !interface_exists($className) && !enum_exists($className)) {
            return $this->buildTypeInfo($className, 'object', $nullable);
        }

        // Handle enums
        if (enum_exists($className)) {
            return $this->resolveEnumType($className, $nullable);
        }

        // Handle Laravel Data classes
        if ($this->isDataClass($className)) {
            return $this->resolveDataClassType($className, $nullable);
        }

        // Handle collections
        if ($this->isCollectionClass($className)) {
            return $this->buildTypeInfo(
                $this->getShortClassName($className),
                'array',
                $nullable,
                isArray: true
            );
        }

        // Handle Carbon/DateTime
        if ($this->isDateTimeClass($className)) {
            return $this->buildTypeInfo('datetime', 'string', $nullable, format: 'date-time');
        }

        // Generic object
        return $this->buildTypeInfo(
            $this->getShortClassName($className),
            'object',
            $nullable,
            reference: $className
        );
    }

    /**
     * Resolve enum types
     */
    private function resolveEnumType(string $enumClass, bool $nullable): array
    {
        $reflection = new ReflectionClass($enumClass);
        $cases = [];
        $backingType = 'string';

        if ($reflection->implementsInterface(\BackedEnum::class)) {
            foreach ($enumClass::cases() as $case) {
                $cases[] = $case->value;
            }
            // Determine backing type
            $backingType = is_int($enumClass::cases()[0]->value ?? null) ? 'integer' : 'string';
        } else {
            // Unit enum - use case names
            foreach ($enumClass::cases() as $case) {
                $cases[] = $case->name;
            }
        }

        return $this->buildTypeInfo(
            $this->getShortClassName($enumClass),
            $backingType,
            $nullable,
            isEnum: true,
            enumValues: $cases,
            reference: $enumClass
        );
    }

    /**
     * Resolve Laravel Data class types
     */
    private function resolveDataClassType(string $className, bool $nullable): array
    {
        return $this->buildTypeInfo(
            $this->getShortClassName($className),
            'object',
            $nullable,
            isDataClass: true,
            reference: $className
        );
    }

    /**
     * Resolve union types (e.g., string|int)
     */
    private function resolveUnionType(ReflectionUnionType $type): array
    {
        $types = [];
        $nullable = false;
        $hasDataClass = false;
        $dataClassRef = null;

        foreach ($type->getTypes() as $innerType) {
            if ($innerType instanceof ReflectionNamedType) {
                if ($innerType->getName() === 'null') {
                    $nullable = true;
                    continue;
                }

                $resolved = $this->resolveNamedType($innerType);
                $types[] = $resolved['name'];

                if ($resolved['isDataClass']) {
                    $hasDataClass = true;
                    $dataClassRef = $resolved['reference'];
                }
            }
        }

        $displayName = implode('|', $types);
        if ($nullable && !in_array('null', $types)) {
            $displayName .= '|null';
        }

        // If there's only one non-null type, use its base type
        $baseType = count($types) === 1 ? $this->getOpenApiType($types[0]) : 'string';

        return $this->buildTypeInfo(
            $displayName,
            $baseType,
            $nullable,
            isDataClass: $hasDataClass,
            reference: $dataClassRef
        );
    }

    /**
     * Resolve intersection types (e.g., Foo&Bar)
     */
    private function resolveIntersectionType(ReflectionIntersectionType $type): array
    {
        $types = [];

        foreach ($type->getTypes() as $innerType) {
            if ($innerType instanceof ReflectionNamedType) {
                $types[] = $this->getShortClassName($innerType->getName());
            }
        }

        return $this->buildTypeInfo(
            implode('&', $types),
            'object',
            false
        );
    }

    /**
     * Check if a class is a Laravel Data class
     */
    public function isDataClass(string $className): bool
    {
        if (isset($this->dataClassCache[$className])) {
            return $this->dataClassCache[$className];
        }

        if (!class_exists($className)) {
            return $this->dataClassCache[$className] = false;
        }

        try {
            $reflection = new ReflectionClass($className);
            $isData = $reflection->isSubclassOf(Data::class);
            return $this->dataClassCache[$className] = $isData;
        } catch (\Throwable) {
            return $this->dataClassCache[$className] = false;
        }
    }

    /**
     * Check if a class is a collection type
     */
    private function isCollectionClass(string $className): bool
    {
        $collectionClasses = [
            \Illuminate\Support\Collection::class,
            \Illuminate\Database\Eloquent\Collection::class,
            \Spatie\LaravelData\DataCollection::class,
        ];

        if (!class_exists($className)) {
            return false;
        }

        foreach ($collectionClasses as $collectionClass) {
            if ($className === $collectionClass || is_subclass_of($className, $collectionClass)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a class is a DateTime type
     */
    private function isDateTimeClass(string $className): bool
    {
        $dateClasses = [
            \DateTime::class,
            \DateTimeImmutable::class,
            \Carbon\Carbon::class,
            \Carbon\CarbonImmutable::class,
            \Illuminate\Support\Carbon::class,
        ];

        if (!class_exists($className)) {
            return false;
        }

        foreach ($dateClasses as $dateClass) {
            if ($className === $dateClass || is_subclass_of($className, $dateClass)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the short class name from a fully qualified name
     */
    public function getShortClassName(string $className): string
    {
        $parts = explode('\\', $className);
        return end($parts);
    }

    /**
     * Map a type name to OpenAPI type
     */
    public function getOpenApiType(string $typeName): string
    {
        return match (strtolower($typeName)) {
            'int', 'integer' => 'integer',
            'float', 'double', 'number' => 'number',
            'bool', 'boolean' => 'boolean',
            'array' => 'array',
            'null' => 'null',
            'string', 'mixed' => 'string',
            default => 'object',
        };
    }

    /**
     * Generate an example value for a type
     */
    public function generateExampleValue(array $typeInfo): mixed
    {
        if ($typeInfo['nullable'] && rand(0, 1) === 0) {
            return null;
        }

        if ($typeInfo['isEnum'] && !empty($typeInfo['enumValues'])) {
            return $typeInfo['enumValues'][0];
        }

        if ($typeInfo['isDataClass']) {
            return '{ ... }'; // Placeholder for nested object
        }

        if ($typeInfo['isArray']) {
            return [];
        }

        return match ($typeInfo['base']) {
            'integer' => 123,
            'number' => 123.45,
            'boolean' => true,
            'array' => [],
            'object' => new \stdClass(),
            default => 'example_string',
        };
    }

    /**
     * Build a standardized type info array
     */
    private function buildTypeInfo(
        string $name,
        string $base,
        bool $nullable,
        bool $isBuiltin = false,
        bool $isEnum = false,
        bool $isDataClass = false,
        bool $isArray = false,
        ?array $enumValues = null,
        ?string $nestedType = null,
        ?string $reference = null,
        ?string $format = null,
    ): array {
        return [
            'name' => $name,
            'base' => $base,
            'nullable' => $nullable,
            'isBuiltin' => $isBuiltin,
            'isEnum' => $isEnum,
            'isDataClass' => $isDataClass,
            'isArray' => $isArray,
            'enumValues' => $enumValues,
            'nestedType' => $nestedType,
            'reference' => $reference,
            'format' => $format,
        ];
    }
}
