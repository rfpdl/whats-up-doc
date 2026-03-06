<?php

declare(strict_types=1);

namespace Rfpdl\WhatsUpDoc\Support;

use Illuminate\Support\Collection;

class ScanResult
{
    public function __construct(
        /** @var Collection Successfully scanned Data classes */
        public readonly Collection $dataClasses,
        /** @var ErrorCollector Any errors encountered during scanning */
        public readonly ErrorCollector $errors,
        /** @var array Metadata about the scan */
        public readonly array $metadata = [],
    ) {}

    /**
     * Create an empty result
     */
    public static function empty(): self
    {
        return new self(
            dataClasses: collect(),
            errors: new ErrorCollector(),
            metadata: ['scanned_at' => now()->toIso8601String()],
        );
    }

    /**
     * Create a result with data classes
     */
    public static function withClasses(Collection $classes, ?ErrorCollector $errors = null): self
    {
        return new self(
            dataClasses: $classes,
            errors: $errors ?? new ErrorCollector(),
            metadata: [
                'scanned_at' => now()->toIso8601String(),
                'class_count' => $classes->count(),
            ],
        );
    }

    /**
     * Check if the scan was successful (no errors)
     */
    public function isSuccessful(): bool
    {
        return !$this->errors->hasErrors();
    }

    /**
     * Check if any classes were found
     */
    public function hasClasses(): bool
    {
        return $this->dataClasses->isNotEmpty();
    }

    /**
     * Get the number of classes found
     */
    public function classCount(): int
    {
        return $this->dataClasses->count();
    }

    /**
     * Get class names only
     */
    public function classNames(): Collection
    {
        return $this->dataClasses->pluck('class');
    }

    /**
     * Find a specific class by name
     */
    public function findClass(string $className): ?array
    {
        return $this->dataClasses->firstWhere('class', $className);
    }

    /**
     * Convert to array for serialization
     */
    public function toArray(): array
    {
        return [
            'success' => $this->isSuccessful(),
            'class_count' => $this->classCount(),
            'classes' => $this->classNames()->all(),
            'errors' => $this->errors->toArray(),
            'metadata' => $this->metadata,
        ];
    }
}
