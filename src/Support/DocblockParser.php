<?php

declare(strict_types=1);

namespace Rfpdl\WhatsUpDoc\Support;

use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

class DocblockParser
{
    /**
     * Regex patterns for docblock parsing
     */
    private const PATTERNS = [
        'description' => '/^\s*\/\*\*\s*\n\s*\*\s*([^@\n].+?)(?=\n\s*\*\s*@|\n\s*\*\/|\n\s*\*\s*\n)/s',
        'inline_description' => '/\/\*\*\s*(.+?)\s*\*\//s',
        'tag' => '/@(\w+)(?:[^\S\n]+([^\n]*?))?[^\S\n]*(?:\*\/)?$/m',
        'param' => '/@param[^\S\n]+(\S+)[^\S\n]+\$(\w+)(?:[^\S\n]+([^\n]*?))?[^\S\n]*(?:\*\/)?$/m',
        'return' => '/@return[^\S\n]+(\S+)(?:[^\S\n]+([^\n]*?))?[^\S\n]*(?:\*\/)?$/m',
        'response' => '/@response[^\S\n]+(\S+)(?:[^\S\n]+([^\n]*?))?[^\S\n]*(?:\*\/)?$/m',
        'var' => '/@var[^\S\n]+(\S+)(?:[^\S\n]+([^\n]*?))?[^\S\n]*(?:\*\/)?$/m',
        'property' => '/@property(?:-read|-write)?[^\S\n]+(\S+)[^\S\n]+\$(\w+)(?:[^\S\n]+([^\n]*?))?[^\S\n]*(?:\*\/)?$/m',
        'example' => '/@example[^\S\n]+([^\n]*?)[^\S\n]*(?:\*\/)?$/m',
        'deprecated' => '/@deprecated(?:[^\S\n]+([^\n]*?))?[^\S\n]*(?:\*\/)?$/m',
        'see' => '/@see[^\S\n]+([^\n]*?)[^\S\n]*(?:\*\/)?$/m',
        'throws' => '/@throws[^\S\n]+(\S+)(?:[^\S\n]+([^\n]*?))?[^\S\n]*(?:\*\/)?$/m',
    ];

    /**
     * Parse all information from a class docblock
     */
    public function parseClass(ReflectionClass $reflection): array
    {
        $docComment = $reflection->getDocComment();

        if (!$docComment) {
            return $this->emptyClassResult();
        }

        return [
            'description' => $this->extractDescription($docComment),
            'tags' => $this->extractAllTags($docComment),
            'properties' => $this->extractPropertyTags($docComment),
            'deprecated' => $this->extractDeprecated($docComment),
            'see' => $this->extractSee($docComment),
        ];
    }

    /**
     * Parse information from a property docblock
     */
    public function parseProperty(ReflectionProperty $property): array
    {
        $docComment = $property->getDocComment();

        if (!$docComment) {
            return $this->emptyPropertyResult();
        }

        return [
            'description' => $this->extractPropertyDescription($docComment),
            'var' => $this->extractVar($docComment),
            'example' => $this->extractExample($docComment),
            'deprecated' => $this->extractDeprecated($docComment),
            'tags' => $this->extractAllTags($docComment),
        ];
    }

    /**
     * Parse information from a method docblock
     */
    public function parseMethod(ReflectionMethod $method): array
    {
        $docComment = $method->getDocComment();

        if (!$docComment) {
            return $this->emptyMethodResult();
        }

        return [
            'description' => $this->extractDescription($docComment),
            'params' => $this->extractParams($docComment),
            'return' => $this->extractReturn($docComment),
            'response' => $this->extractResponse($docComment),
            'throws' => $this->extractThrows($docComment),
            'deprecated' => $this->extractDeprecated($docComment),
            'tags' => $this->extractAllTags($docComment),
        ];
    }

    /**
     * Extract the main description from a docblock
     */
    public function extractDescription(string $docComment): string
    {
        // Try multi-line description first
        if (preg_match(self::PATTERNS['description'], $docComment, $matches)) {
            $description = $matches[1];
            // Clean up multi-line descriptions
            $description = preg_replace('/\n\s*\*\s*/', ' ', $description);
            return trim($description);
        }

        // Try inline description (single line docblock)
        if (preg_match(self::PATTERNS['inline_description'], $docComment, $matches)) {
            $description = $matches[1];
            // Remove any tags
            if (str_contains($description, '@')) {
                $description = preg_replace('/@\w+.*$/', '', $description);
            }
            return trim($description);
        }

        return '';
    }

    /**
     * Extract description specifically for properties (often inline)
     */
    public function extractPropertyDescription(string $docComment): string
    {
        // Properties often have inline descriptions
        // Format: /** Description here */
        if (preg_match('/\/\*\*\s*([^@\*][^\n]+)\s*\*?\s*\*\//', $docComment, $matches)) {
            return trim($matches[1]);
        }

        // Try standard multi-line format
        return $this->extractDescription($docComment);
    }

    /**
     * Extract @param tags
     */
    public function extractParams(string $docComment): array
    {
        $params = [];

        if (preg_match_all(self::PATTERNS['param'], $docComment, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $params[$match[2]] = [
                    'type' => $match[1],
                    'name' => $match[2],
                    'description' => trim($match[3] ?? ''),
                ];
            }
        }

        return $params;
    }

    /**
     * Extract @return tag
     */
    public function extractReturn(string $docComment): ?array
    {
        if (preg_match(self::PATTERNS['return'], $docComment, $match)) {
            return [
                'type' => $match[1],
                'description' => trim($match[2] ?? ''),
            ];
        }

        return null;
    }

    /**
     * Extract @response tag (e.g., @response 201 BookData)
     */
    public function extractResponse(string $docComment): ?array
    {
        if (preg_match(self::PATTERNS['response'], $docComment, $match)) {
            return [
                'status' => (int) $match[1],
                'type' => $match[2] ?? null,
                'description' => trim($match[3] ?? ''),
            ];
        }

        return null;
    }

    /**
     * Extract @var tag
     */
    public function extractVar(string $docComment): ?array
    {
        if (preg_match(self::PATTERNS['var'], $docComment, $match)) {
            return [
                'type' => $match[1],
                'description' => trim($match[2] ?? ''),
            ];
        }

        return null;
    }

    /**
     * Extract @property tags (for magic properties)
     */
    public function extractPropertyTags(string $docComment): array
    {
        $properties = [];

        if (preg_match_all(self::PATTERNS['property'], $docComment, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $properties[$match[2]] = [
                    'type' => $match[1],
                    'name' => $match[2],
                    'description' => trim($match[3] ?? ''),
                ];
            }
        }

        return $properties;
    }

    /**
     * Extract @example tag
     */
    public function extractExample(string $docComment): ?string
    {
        if (preg_match(self::PATTERNS['example'], $docComment, $match)) {
            return trim($match[1]);
        }

        return null;
    }

    /**
     * Extract @deprecated tag
     */
    public function extractDeprecated(string $docComment): ?array
    {
        if (preg_match(self::PATTERNS['deprecated'], $docComment, $match)) {
            return [
                'deprecated' => true,
                'message' => trim($match[1] ?? ''),
            ];
        }

        return null;
    }

    /**
     * Extract @see tags
     */
    public function extractSee(string $docComment): array
    {
        $sees = [];

        if (preg_match_all(self::PATTERNS['see'], $docComment, $matches)) {
            foreach ($matches[1] as $see) {
                $sees[] = trim($see);
            }
        }

        return $sees;
    }

    /**
     * Extract @throws tags
     */
    public function extractThrows(string $docComment): array
    {
        $throws = [];

        if (preg_match_all(self::PATTERNS['throws'], $docComment, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $throws[] = [
                    'type' => $match[1],
                    'description' => trim($match[2] ?? ''),
                ];
            }
        }

        return $throws;
    }

    /**
     * Extract all tags as key-value pairs
     */
    public function extractAllTags(string $docComment): array
    {
        $tags = [];

        if (preg_match_all(self::PATTERNS['tag'], $docComment, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $tagName = $match[1];
                $tagValue = trim($match[2] ?? '');

                if (!isset($tags[$tagName])) {
                    $tags[$tagName] = [];
                }
                $tags[$tagName][] = $tagValue;
            }
        }

        return $tags;
    }

    /**
     * Parse a type string from docblock (handles unions, arrays, etc.)
     */
    public function parseTypeString(string $typeString): array
    {
        $typeString = trim($typeString);

        // Handle nullable shorthand (?Type)
        $nullable = str_starts_with($typeString, '?');
        if ($nullable) {
            $typeString = substr($typeString, 1);
        }

        // Handle array notation (Type[])
        $isArray = str_ends_with($typeString, '[]');
        if ($isArray) {
            $typeString = substr($typeString, 0, -2);
        }

        // Handle union types (Type1|Type2)
        $types = array_map('trim', explode('|', $typeString));

        // Check for null in union
        if (in_array('null', $types)) {
            $nullable = true;
            $types = array_filter($types, fn($t) => $t !== 'null');
        }

        return [
            'types' => array_values($types),
            'nullable' => $nullable,
            'isArray' => $isArray,
            'original' => $typeString,
        ];
    }

    /**
     * Extract generic type info (e.g., Collection<UserData>)
     */
    public function extractGenericType(string $typeString): ?array
    {
        if (preg_match('/^(\w+)<(.+)>$/', $typeString, $matches)) {
            return [
                'wrapper' => $matches[1],
                'inner' => $matches[2],
            ];
        }

        return null;
    }

    /**
     * Empty result for classes without docblocks
     */
    private function emptyClassResult(): array
    {
        return [
            'description' => '',
            'tags' => [],
            'properties' => [],
            'deprecated' => null,
            'see' => [],
        ];
    }

    /**
     * Empty result for properties without docblocks
     */
    private function emptyPropertyResult(): array
    {
        return [
            'description' => '',
            'var' => null,
            'example' => null,
            'deprecated' => null,
            'tags' => [],
        ];
    }

    /**
     * Empty result for methods without docblocks
     */
    private function emptyMethodResult(): array
    {
        return [
            'description' => '',
            'params' => [],
            'return' => null,
            'response' => null,
            'throws' => [],
            'deprecated' => null,
            'tags' => [],
        ];
    }
}
