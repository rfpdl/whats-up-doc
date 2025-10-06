<?php

declare(strict_types=1);

namespace Rfpdl\WhatsUpDoc\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Rfpdl\WhatsUpDoc\Services\DataClassScanner;
use Rfpdl\WhatsUpDoc\Services\DocumentationGenerator;

class GenerateDocsCommand extends Command
{
    protected $signature = 'data-doc:generate 
                            {--output= : Output directory}
                            {--format=html : Output format (html, json, openapi)}';

    protected $description = 'Generate API documentation from Laravel Data classes';

    public function handle(): void
    {
        $this->info('🚀 Generating What\'s Up Documentation...');

        $outputPath = $this->option('output') ?? config('whats-up-doc.output_path');
        $format = $this->option('format');

        // Ensure output directory exists
        File::ensureDirectoryExists($outputPath);

        // Scan for Data classes
        $scanner = new DataClassScanner();
        $dataClasses = $scanner->scan();

        if (empty($dataClasses)) {
            $this->warn('No Laravel Data classes found!');
            return;
        }

        $this->info("Found {$dataClasses->count()} Data classes");

        // Generate documentation
        $generator = new DocumentationGenerator();
        
        match ($format) {
            'json' => $generator->generateJson($dataClasses, $outputPath),
            'openapi' => $generator->generateOpenApi($dataClasses, $outputPath),
            default => $generator->generateHtml($dataClasses, $outputPath),
        };

        $this->info("✅ Documentation generated in: {$outputPath}");
        
        if ($format === 'html') {
            $this->info("📖 Open: {$outputPath}/index.html");
        }
    }
}
