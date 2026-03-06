<?php

declare(strict_types=1);

namespace Rfpdl\WhatsUpDoc\Support;

use ReflectionClass;
use ReflectionProperty;
use ReflectionMethod;
use ReflectionAttribute;

class AttributeReader
{
    /**
     * Known Spatie Laravel Data attributes
     */
    private const SPATIE_ATTRIBUTES = [
        'Spatie\LaravelData\Attributes\Validation\Min',
        'Spatie\LaravelData\Attributes\Validation\Max',
        'Spatie\LaravelData\Attributes\Validation\Required',
        'Spatie\LaravelData\Attributes\Validation\Email',
        'Spatie\LaravelData\Attributes\Validation\Url',
        'Spatie\LaravelData\Attributes\Validation\Regex',
        'Spatie\LaravelData\Attributes\Validation\In',
        'Spatie\LaravelData\Attributes\Validation\NotIn',
        'Spatie\LaravelData\Attributes\Validation\Unique',
        'Spatie\LaravelData\Attributes\Validation\Exists',
        'Spatie\LaravelData\Attributes\Validation\Date',
        'Spatie\LaravelData\Attributes\Validation\DateFormat',
        'Spatie\LaravelData\Attributes\Validation\After',
        'Spatie\LaravelData\Attributes\Validation\Before',
        'Spatie\LaravelData\Attributes\Validation\Numeric',
        'Spatie\LaravelData\Attributes\Validation\Integer',
        'Spatie\LaravelData\Attributes\Validation\Boolean',
        'Spatie\LaravelData\Attributes\Validation\Array',
        'Spatie\LaravelData\Attributes\Validation\String',
        'Spatie\LaravelData\Attributes\Hidden',
        'Spatie\LaravelData\Attributes\WithCast',
        'Spatie\LaravelData\Attributes\WithTransformer',
        'Spatie\LaravelData\Attributes\DataCollectionOf',
        'Spatie\LaravelData\Attributes\MapInputName',
        'Spatie\LaravelData\Attributes\MapOutputName',
    ];

    /**
     * Read all attributes from a class
     */
    public function readClassAttributes(ReflectionClass $reflection): array
    {
        $attributes = [];

        foreach ($reflection->getAttributes() as $attribute) {
            $parsed = $this->parseAttribute($attribute);
            if ($parsed) {
                $attributes[] = $parsed;
            }
        }

        return $attributes;
    }

    /**
     * Read all attributes from a property
     */
    public function readPropertyAttributes(ReflectionProperty $property): array
    {
        $attributes = [];

        foreach ($property->getAttributes() as $attribute) {
            $parsed = $this->parseAttribute($attribute);
            if ($parsed) {
                $attributes[] = $parsed;
            }
        }

        return $attributes;
    }

    /**
     * Read all attributes from a method
     */
    public function readMethodAttributes(ReflectionMethod $method): array
    {
        $attributes = [];

        foreach ($method->getAttributes() as $attribute) {
            $parsed = $this->parseAttribute($attribute);
            if ($parsed) {
                $attributes[] = $parsed;
            }
        }

        return $attributes;
    }

    /**
     * Extract validation rules from property attributes
     */
    public function extractValidationRules(ReflectionProperty $property): array
    {
        $rules = [];

        foreach ($property->getAttributes() as $attribute) {
            $name = $attribute->getName();

            // Check if it's a Spatie validation attribute
            if (str_contains($name, 'Validation\\')) {
                $rule = $this->parseValidationAttribute($attribute);
                if ($rule) {
                    $rules[] = $rule;
                }
            }
        }

        return $rules;
    }

    /**
     * Check if a property is marked as hidden
     */
    public function isHidden(ReflectionProperty $property): bool
    {
        foreach ($property->getAttributes() as $attribute) {
            if (str_ends_with($attribute->getName(), 'Hidden')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the collection item type from DataCollectionOf attribute
     */
    public function getCollectionItemType(ReflectionProperty $property): ?string
    {
        foreach ($property->getAttributes() as $attribute) {
            if (str_ends_with($attribute->getName(), 'DataCollectionOf')) {
                try {
                    $instance = $attribute->newInstance();
                    // The first argument is the class name
                    $args = $attribute->getArguments();
                    return $args[0] ?? null;
                } catch (\Throwable) {
                    // Try to get from arguments directly
                    $args = $attribute->getArguments();
                    return $args[0] ?? null;
                }
            }
        }

        return null;
    }

    /**
     * Get input name mapping
     */
    public function getInputNameMapping(ReflectionProperty $property): ?string
    {
        foreach ($property->getAttributes() as $attribute) {
            if (str_ends_with($attribute->getName(), 'MapInputName')) {
                $args = $attribute->getArguments();
                return $args[0] ?? null;
            }
        }

        return null;
    }

    /**
     * Get output name mapping
     */
    public function getOutputNameMapping(ReflectionProperty $property): ?string
    {
        foreach ($property->getAttributes() as $attribute) {
            if (str_ends_with($attribute->getName(), 'MapOutputName')) {
                $args = $attribute->getArguments();
                return $args[0] ?? null;
            }
        }

        return null;
    }

    /**
     * Parse a single attribute into a structured array
     */
    private function parseAttribute(ReflectionAttribute $attribute): ?array
    {
        $name = $attribute->getName();
        $shortName = $this->getShortAttributeName($name);

        try {
            $arguments = $attribute->getArguments();
        } catch (\Throwable) {
            $arguments = [];
        }

        return [
            'name' => $name,
            'shortName' => $shortName,
            'arguments' => $arguments,
            'isValidation' => str_contains($name, 'Validation\\'),
            'isSpatie' => str_starts_with($name, 'Spatie\\'),
        ];
    }

    /**
     * Parse a validation attribute into a rule description
     */
    private function parseValidationAttribute(ReflectionAttribute $attribute): ?array
    {
        $name = $attribute->getName();
        $shortName = $this->getShortAttributeName($name);

        try {
            $arguments = $attribute->getArguments();
        } catch (\Throwable) {
            $arguments = [];
        }

        $rule = [
            'name' => $shortName,
            'constraint' => null,
            'description' => null,
        ];

        // Map common validation rules to human-readable descriptions
        switch ($shortName) {
            case 'Min':
                $value = $arguments[0] ?? $arguments['min'] ?? null;
                $rule['constraint'] = "min:{$value}";
                $rule['description'] = "Minimum: {$value}";
                break;

            case 'Max':
                $value = $arguments[0] ?? $arguments['max'] ?? null;
                $rule['constraint'] = "max:{$value}";
                $rule['description'] = "Maximum: {$value}";
                break;

            case 'Required':
                $rule['constraint'] = 'required';
                $rule['description'] = 'Required';
                break;

            case 'Email':
                $rule['constraint'] = 'email';
                $rule['description'] = 'Must be a valid email address';
                break;

            case 'Url':
                $rule['constraint'] = 'url';
                $rule['description'] = 'Must be a valid URL';
                break;

            case 'Regex':
                $pattern = $arguments[0] ?? $arguments['pattern'] ?? '';
                $rule['constraint'] = "regex:{$pattern}";
                $rule['description'] = "Must match pattern: {$pattern}";
                break;

            case 'In':
                $values = $arguments[0] ?? $arguments['values'] ?? [];
                if (is_array($values)) {
                    $values = implode(', ', $values);
                }
                $rule['constraint'] = "in:{$values}";
                $rule['description'] = "Must be one of: {$values}";
                break;

            case 'Numeric':
                $rule['constraint'] = 'numeric';
                $rule['description'] = 'Must be numeric';
                break;

            case 'Integer':
                $rule['constraint'] = 'integer';
                $rule['description'] = 'Must be an integer';
                break;

            case 'Boolean':
                $rule['constraint'] = 'boolean';
                $rule['description'] = 'Must be true or false';
                break;

            case 'Date':
                $rule['constraint'] = 'date';
                $rule['description'] = 'Must be a valid date';
                break;

            case 'DateFormat':
                $format = $arguments[0] ?? $arguments['format'] ?? '';
                $rule['constraint'] = "date_format:{$format}";
                $rule['description'] = "Date format: {$format}";
                break;

            case 'After':
                $date = $arguments[0] ?? '';
                $rule['constraint'] = "after:{$date}";
                $rule['description'] = "Must be after: {$date}";
                break;

            case 'Before':
                $date = $arguments[0] ?? '';
                $rule['constraint'] = "before:{$date}";
                $rule['description'] = "Must be before: {$date}";
                break;

            case 'Unique':
                $rule['constraint'] = 'unique';
                $rule['description'] = 'Must be unique';
                break;

            case 'Exists':
                $rule['constraint'] = 'exists';
                $rule['description'] = 'Must exist in database';
                break;

            default:
                $rule['constraint'] = strtolower($shortName);
                $rule['description'] = $shortName;
        }

        return $rule;
    }

    /**
     * Get the short name from a fully qualified attribute name
     */
    private function getShortAttributeName(string $fullName): string
    {
        $parts = explode('\\', $fullName);
        return end($parts);
    }

    /**
     * Get all attributes of a specific type
     */
    public function getAttributesOfType(ReflectionProperty|ReflectionClass|ReflectionMethod $reflection, string $attributeClass): array
    {
        $found = [];

        foreach ($reflection->getAttributes($attributeClass, ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            try {
                $found[] = $attribute->newInstance();
            } catch (\Throwable) {
                // Skip if can't instantiate
            }
        }

        return $found;
    }

    /**
     * Check if reflection target has a specific attribute
     */
    public function hasAttribute(ReflectionProperty|ReflectionClass|ReflectionMethod $reflection, string $attributeClass): bool
    {
        return !empty($reflection->getAttributes($attributeClass, ReflectionAttribute::IS_INSTANCEOF));
    }
}
