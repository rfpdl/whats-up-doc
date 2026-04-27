<?php

declare(strict_types=1);

namespace Rfpdl\WhatsUpDoc\Support;

class SchemaRegistry
{
    private array $schemas = [];
    private array $pending = [];
    private array $resolving = [];

    public function register(string $className, array $schema): void
    {
        $this->schemas[$className] = $schema;
        unset($this->pending[$className]);
    }

    public function has(string $className): bool
    {
        return isset($this->schemas[$className]);
    }

    public function get(string $className): ?array
    {
        return $this->schemas[$className] ?? null;
    }

    public function queueForResolution(string $className): void
    {
        if (!$this->has($className) && !isset($this->pending[$className])) {
            $this->pending[$className] = true;
        }
    }

    public function getPending(): array
    {
        return array_keys($this->pending);
    }

    public function hasPending(): bool
    {
        return !empty($this->pending);
    }

    public function markResolving(string $className): void
    {
        $this->resolving[$className] = true;
    }

    public function isResolving(string $className): bool
    {
        return isset($this->resolving[$className]);
    }

    public function unmarkResolving(string $className): void
    {
        unset($this->resolving[$className]);
    }

    public function allSchemas(): array
    {
        return $this->schemas;
    }

    public function clear(): void
    {
        $this->schemas = [];
        $this->pending = [];
        $this->resolving = [];
    }
}
