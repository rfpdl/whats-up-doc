<?php

declare(strict_types=1);

namespace Rfpdl\WhatsUpDoc\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Rfpdl\WhatsUpDoc\Services\DataClassScanner;
use Rfpdl\WhatsUpDoc\Services\DocumentationGenerator;
use Rfpdl\WhatsUpDoc\Support\ScanError;

class GenerateDocsCommand extends Command
{
    protected $signature = 'data-doc:generate
                            {--output= : Output directory}
                            {--format=html : Output format (html, json, openapi)}
                            {--verbose : Show detailed output including warnings}';

    protected $description = 'Generate API documentation from Laravel Data classes';

    public function __construct(
        private readonly DataClassScanner $scanner,
        private readonly DocumentationGenerator $generator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Generating What\'s Up Documentation...');
        $this->newLine();

        $outputPath = $this->option('output') ?? config('whats-up-doc.output_path');
        $format = $this->option('format');

        // Ensure output directory exists
        File::ensureDirectoryExists($outputPath);

        // Scan for Data classes
        $this->info('Scanning for Laravel Data classes...');
        $scanResult = $this->scanner->scan();

        // Show scan statistics
        $this->displayScanResults($scanResult);

        if (!$scanResult->hasClasses()) {
            $this->warn('No Laravel Data classes found!');
            $this->newLine();
            $this->line('Make sure your scan paths are configured correctly in config/whats-up-doc.php');
            return Command::FAILURE;
        }

        // Generate documentation
        $this->newLine();
        $this->info("Generating {$format} documentation...");

        try {
            match ($format) {
                'json' => $this->generator->generateJson($scanResult->dataClasses, $outputPath),
                'openapi' => $this->generator->generateOpenApi($scanResult->dataClasses, $outputPath),
                default => $this->generator->generateHtml($scanResult->dataClasses, $outputPath),
            };
        } catch (\Throwable $e) {
            $this->error("Failed to generate documentation: {$e->getMessage()}");
            return Command::FAILURE;
        }

        // Show generator errors/warnings
        $generatorErrors = $this->generator->getErrors();
        if ($generatorErrors->hasAny()) {
            $this->displayErrors($generatorErrors->all(), 'Generation');
        }

        // Success output
        $this->newLine();
        $this->info("Documentation generated successfully!");
        $this->line("  Output: {$outputPath}");

        if ($format === 'html') {
            $this->line("  Open: {$outputPath}/index.html");
        } elseif ($format === 'json') {
            $this->line("  File: {$outputPath}/documentation.json");
        } elseif ($format === 'openapi') {
            $this->line("  File: {$outputPath}/openapi.json");
        }

        return Command::SUCCESS;
    }

    /**
     * Display scan results
     */
    private function displayScanResults($scanResult): void
    {
        $metadata = $scanResult->metadata;

        $this->table(
            ['Metric', 'Value'],
            [
                ['Files Scanned', $metadata['scanned_files'] ?? 0],
                ['Data Classes Found', $metadata['class_count'] ?? 0],
                ['Errors', $metadata['error_count'] ?? 0],
            ]
        );

        // Show scanned paths
        if ($this->option('verbose')) {
            $this->newLine();
            $this->line('Scanned paths:');
            foreach ($metadata['scanned_paths'] ?? [] as $path) {
                $this->line("  - {$path}");
            }
        }

        // Show errors and warnings
        if ($scanResult->errors->hasAny()) {
            $this->displayErrors($scanResult->errors->all(), 'Scan');
        }
    }

    /**
     * Display errors and warnings
     */
    private function displayErrors($errors, string $phase): void
    {
        $actualErrors = $errors->filter(fn(ScanError $e) => $e->severity === ScanError::SEVERITY_ERROR);
        $warnings = $errors->filter(fn(ScanError $e) => $e->severity === ScanError::SEVERITY_WARNING);

        if ($actualErrors->isNotEmpty()) {
            $this->newLine();
            $this->error("{$phase} Errors:");
            foreach ($actualErrors as $error) {
                $this->line("  x {$error->message}");
            }
        }

        if ($warnings->isNotEmpty() && $this->option('verbose')) {
            $this->newLine();
            $this->warn("{$phase} Warnings:");
            foreach ($warnings as $warning) {
                $this->line("  ! {$warning->message}");
            }
        }
    }
}
