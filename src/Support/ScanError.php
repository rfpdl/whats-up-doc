<?php

declare(strict_types=1);

namespace Rfpdl\WhatsUpDoc\Support;

class ScanError
{
    public const SEVERITY_ERROR = 'error';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_INFO = 'info';

    public const TYPE_CLASS_NOT_FOUND = 'class_not_found';
    public const TYPE_REFLECTION_FAILED = 'reflection_failed';
    public const TYPE_PARSE_ERROR = 'parse_error';
    public const TYPE_FILE_NOT_READABLE = 'file_not_readable';
    public const TYPE_INVALID_DATA_CLASS = 'invalid_data_class';
    public const TYPE_ROUTE_ANALYSIS_FAILED = 'route_analysis_failed';
    public const TYPE_TYPE_RESOLUTION_FAILED = 'type_resolution_failed';

    public function __construct(
        public readonly string $type,
        public readonly string $message,
        public readonly string $severity = self::SEVERITY_ERROR,
        public readonly ?string $file = null,
        public readonly ?string $class = null,
        public readonly ?string $property = null,
        public readonly ?\Throwable $exception = null,
    ) {}

    /**
     * Create a generic warning
     */
    public static function warning(string $message): self
    {
        return new self(
            type: 'warning',
            message: $message,
            severity: self::SEVERITY_WARNING,
        );
    }

    /**
     * Create an error for a class that couldn't be found
     */
    public static function classNotFound(string $className, string $file): self
    {
        return new self(
            type: self::TYPE_CLASS_NOT_FOUND,
            message: "Class '{$className}' could not be loaded",
            severity: self::SEVERITY_ERROR,
            file: $file,
            class: $className,
        );
    }

    /**
     * Create an error for failed reflection
     */
    public static function reflectionFailed(string $className, \Throwable $e): self
    {
        return new self(
            type: self::TYPE_REFLECTION_FAILED,
            message: "Failed to reflect class '{$className}': {$e->getMessage()}",
            severity: self::SEVERITY_ERROR,
            class: $className,
            exception: $e,
        );
    }

    /**
     * Create an error for parse failures
     */
    public static function parseError(string $file, string $message): self
    {
        return new self(
            type: self::TYPE_PARSE_ERROR,
            message: "Failed to parse file: {$message}",
            severity: self::SEVERITY_ERROR,
            file: $file,
        );
    }

    /**
     * Create an error for unreadable files
     */
    public static function fileNotReadable(string $file): self
    {
        return new self(
            type: self::TYPE_FILE_NOT_READABLE,
            message: "File is not readable: {$file}",
            severity: self::SEVERITY_ERROR,
            file: $file,
        );
    }

    /**
     * Create an error for invalid Data classes
     */
    public static function invalidDataClass(string $className, string $reason): self
    {
        return new self(
            type: self::TYPE_INVALID_DATA_CLASS,
            message: "Invalid Data class '{$className}': {$reason}",
            severity: self::SEVERITY_WARNING,
            class: $className,
        );
    }

    /**
     * Create an error for route analysis failures
     */
    public static function routeAnalysisFailed(string $uri, \Throwable $e): self
    {
        return new self(
            type: self::TYPE_ROUTE_ANALYSIS_FAILED,
            message: "Failed to analyze route '{$uri}': {$e->getMessage()}",
            severity: self::SEVERITY_WARNING,
            exception: $e,
        );
    }

    /**
     * Create an error for type resolution failures
     */
    public static function typeResolutionFailed(string $className, string $property, \Throwable $e): self
    {
        return new self(
            type: self::TYPE_TYPE_RESOLUTION_FAILED,
            message: "Failed to resolve type for {$className}::\${$property}: {$e->getMessage()}",
            severity: self::SEVERITY_WARNING,
            class: $className,
            property: $property,
            exception: $e,
        );
    }

    /**
     * Convert to array for logging/display
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'severity' => $this->severity,
            'message' => $this->message,
            'file' => $this->file,
            'class' => $this->class,
            'property' => $this->property,
            'exception' => $this->exception?->getMessage(),
        ];
    }
}
