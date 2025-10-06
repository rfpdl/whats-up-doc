<?php

declare(strict_types=1);

namespace Rfpdl\WhatsUpDoc\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use Spatie\LaravelData\Data;

class DataClassScanner
{
    public function scan(): Collection
    {
        $dataClasses = collect();
        $scanPaths = config('whats-up-doc.scan_paths', [app_path('Data')]);
        $excludePatterns = config('whats-up-doc.exclude_patterns', []);

        foreach ($scanPaths as $path) {
            if (!File::exists($path)) {
                continue;
            }

            $files = File::allFiles($path);

            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $className = $this->getClassNameFromFile($file->getPathname());
                
                if (!$className || $this->shouldExclude($className, $excludePatterns)) {
                    continue;
                }

                try {
                    $reflection = new ReflectionClass($className);
                    
                    if ($reflection->isSubclassOf(Data::class) && !$reflection->isAbstract()) {
                        $dataClasses->push([
                            'class' => $className,
                            'reflection' => $reflection,
                            'file' => $file->getPathname(),
                        ]);
                    }
                } catch (\Throwable $e) {
                    // Skip classes that can't be reflected
                    continue;
                }
            }
        }

        return $dataClasses;
    }

    private function getClassNameFromFile(string $filePath): ?string
    {
        $content = File::get($filePath);
        
        // Extract namespace
        if (preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatches)) {
            $namespace = $namespaceMatches[1];
        } else {
            return null;
        }

        // Extract class name
        if (preg_match('/class\s+(\w+)/', $content, $classMatches)) {
            $className = $classMatches[1];
            return $namespace . '\\' . $className;
        }

        return null;
    }

    private function shouldExclude(string $className, array $excludePatterns): bool
    {
        foreach ($excludePatterns as $pattern) {
            if (fnmatch($pattern, $className)) {
                return true;
            }
        }

        return false;
    }
}
