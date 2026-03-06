<?php

declare(strict_types=1);

namespace Rfpdl\WhatsUpDoc\Support;

use Illuminate\Support\Collection;

class ErrorCollector
{
    /** @var Collection<int, ScanError> */
    private Collection $errors;

    public function __construct()
    {
        $this->errors = collect();
    }

    /**
     * Add an error to the collection
     */
    public function add(ScanError $error): self
    {
        $this->errors->push($error);
        return $this;
    }

    /**
     * Add multiple errors
     */
    public function addMany(array $errors): self
    {
        foreach ($errors as $error) {
            if ($error instanceof ScanError) {
                $this->errors->push($error);
            }
        }
        return $this;
    }

    /**
     * Get all errors
     */
    public function all(): Collection
    {
        return $this->errors;
    }

    /**
     * Get errors by severity
     */
    public function bySeverity(string $severity): Collection
    {
        return $this->errors->filter(fn(ScanError $e) => $e->severity === $severity);
    }

    /**
     * Get all actual errors (not warnings or info)
     */
    public function errors(): Collection
    {
        return $this->bySeverity(ScanError::SEVERITY_ERROR);
    }

    /**
     * Get all warnings
     */
    public function warnings(): Collection
    {
        return $this->bySeverity(ScanError::SEVERITY_WARNING);
    }

    /**
     * Get errors by type
     */
    public function byType(string $type): Collection
    {
        return $this->errors->filter(fn(ScanError $e) => $e->type === $type);
    }

    /**
     * Get errors for a specific file
     */
    public function forFile(string $file): Collection
    {
        return $this->errors->filter(fn(ScanError $e) => $e->file === $file);
    }

    /**
     * Get errors for a specific class
     */
    public function forClass(string $class): Collection
    {
        return $this->errors->filter(fn(ScanError $e) => $e->class === $class);
    }

    /**
     * Check if there are any errors
     */
    public function hasErrors(): bool
    {
        return $this->errors()->isNotEmpty();
    }

    /**
     * Check if there are any warnings
     */
    public function hasWarnings(): bool
    {
        return $this->warnings()->isNotEmpty();
    }

    /**
     * Check if there are any issues at all
     */
    public function hasAny(): bool
    {
        return $this->errors->isNotEmpty();
    }

    /**
     * Get error count
     */
    public function count(): int
    {
        return $this->errors->count();
    }

    /**
     * Get error count by severity
     */
    public function countBySeverity(): array
    {
        return [
            'errors' => $this->errors()->count(),
            'warnings' => $this->warnings()->count(),
            'info' => $this->bySeverity(ScanError::SEVERITY_INFO)->count(),
        ];
    }

    /**
     * Clear all errors
     */
    public function clear(): self
    {
        $this->errors = collect();
        return $this;
    }

    /**
     * Merge another error collector
     */
    public function merge(ErrorCollector $other): self
    {
        $this->errors = $this->errors->merge($other->all());
        return $this;
    }

    /**
     * Convert all errors to array
     */
    public function toArray(): array
    {
        return $this->errors->map(fn(ScanError $e) => $e->toArray())->all();
    }

    /**
     * Get a summary of all errors
     */
    public function summary(): array
    {
        $counts = $this->countBySeverity();

        return [
            'total' => $this->count(),
            'counts' => $counts,
            'has_errors' => $this->hasErrors(),
            'has_warnings' => $this->hasWarnings(),
            'by_type' => $this->errors
                ->groupBy(fn(ScanError $e) => $e->type)
                ->map(fn($group) => $group->count())
                ->all(),
        ];
    }

    /**
     * Format errors for console output
     */
    public function formatForConsole(): array
    {
        $lines = [];

        foreach ($this->errors as $error) {
            $prefix = match ($error->severity) {
                ScanError::SEVERITY_ERROR => '✗',
                ScanError::SEVERITY_WARNING => '⚠',
                ScanError::SEVERITY_INFO => 'ℹ',
            };

            $location = '';
            if ($error->file) {
                $location = " ({$error->file}";
                if ($error->class) {
                    $location .= "::{$error->class}";
                }
                if ($error->property) {
                    $location .= "::\${$error->property}";
                }
                $location .= ')';
            }

            $lines[] = "{$prefix} {$error->message}{$location}";
        }

        return $lines;
    }
}
