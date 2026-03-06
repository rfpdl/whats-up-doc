<?php

declare(strict_types=1);

namespace Rfpdl\WhatsUpDoc\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use Rfpdl\WhatsUpDoc\Support\DocblockParser;
use Rfpdl\WhatsUpDoc\Support\ErrorCollector;
use Rfpdl\WhatsUpDoc\Support\ScanError;
use Rfpdl\WhatsUpDoc\Support\ScanResult;
use Spatie\LaravelData\Data;

class DataClassScanner
{
    private ErrorCollector $errors;

    public function __construct(
        private readonly DocblockParser $docblockParser,
    ) {
        $this->errors = new ErrorCollector();
    }

    /**
     * Scan for Laravel Data classes and return a structured result
     */
    public function scan(): ScanResult
    {
        $this->errors->clear();
        $dataClasses = collect();

        $scanPaths = config('whats-up-doc.scan_paths', [app_path('Data')]);
        $excludePatterns = config('whats-up-doc.exclude_patterns', []);

        $scannedFiles = 0;

        foreach ($scanPaths as $path) {
            if (!File::exists($path)) {
                $this->errors->add(ScanError::fileNotReadable($path));
                continue;
            }

            $files = File::allFiles($path);

            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $scannedFiles++;
                $result = $this->processFile($file->getPathname(), $excludePatterns);

                if ($result !== null) {
                    $dataClasses->push($result);
                }
            }
        }

        return new ScanResult(
            dataClasses: $dataClasses,
            errors: $this->errors,
            metadata: [
                'scanned_at' => now()->toIso8601String(),
                'scanned_paths' => $scanPaths,
                'scanned_files' => $scannedFiles,
                'class_count' => $dataClasses->count(),
                'error_count' => $this->errors->count(),
            ]
        );
    }

    /**
     * Scan and return just the collection (backward compatible)
     */
    public function scanClasses(): Collection
    {
        return $this->scan()->dataClasses;
    }

    /**
     * Get errors from the last scan
     */
    public function getErrors(): ErrorCollector
    {
        return $this->errors;
    }

    /**
     * Process a single PHP file
     */
    private function processFile(string $filePath, array $excludePatterns): ?array
    {
        $className = $this->extractClassName($filePath);

        if ($className === null) {
            return null;
        }

        if ($this->shouldExclude($className, $excludePatterns)) {
            return null;
        }

        return $this->reflectClass($className, $filePath);
    }

    /**
     * Extract class name from a PHP file
     */
    private function extractClassName(string $filePath): ?string
    {
        try {
            $content = File::get($filePath);
        } catch (\Throwable $e) {
            $this->errors->add(ScanError::fileNotReadable($filePath));
            return null;
        }

        // Extract namespace
        $namespace = null;
        if (preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatches)) {
            $namespace = trim($namespaceMatches[1]);
        }

        if ($namespace === null) {
            return null;
        }

        // Extract class name (handles class, abstract class, final class, readonly class)
        if (preg_match('/(?:abstract\s+|final\s+|readonly\s+)*class\s+(\w+)/', $content, $classMatches)) {
            return $namespace . '\\' . $classMatches[1];
        }

        return null;
    }

    /**
     * Reflect a class and extract its information
     */
    private function reflectClass(string $className, string $filePath): ?array
    {
        try {
            // Ensure the class is loaded
            if (!class_exists($className)) {
                $this->errors->add(ScanError::classNotFound($className, $filePath));
                return null;
            }

            $reflection = new ReflectionClass($className);

            // Skip non-Data classes
            if (!$reflection->isSubclassOf(Data::class)) {
                return null;
            }

            // Skip abstract classes
            if ($reflection->isAbstract()) {
                return null;
            }

            // Parse the class docblock
            $docblock = $this->docblockParser->parseClass($reflection);

            return [
                'class' => $className,
                'reflection' => $reflection,
                'file' => $filePath,
                'shortName' => $reflection->getShortName(),
                'namespace' => $reflection->getNamespaceName(),
                'docblock' => $docblock,
            ];
        } catch (\Throwable $e) {
            $this->errors->add(ScanError::reflectionFailed($className, $e));
            return null;
        }
    }

    /**
     * Check if a class should be excluded based on patterns
     */
    private function shouldExclude(string $className, array $excludePatterns): bool
    {
        foreach ($excludePatterns as $pattern) {
            if (fnmatch($pattern, $className, FNM_NOESCAPE)) {
                return true;
            }
            // Also check the short class name
            $shortName = substr($className, strrpos($className, '\\') + 1);
            if (fnmatch($pattern, $shortName, FNM_NOESCAPE)) {
                return true;
            }
        }

        return false;
    }
}
