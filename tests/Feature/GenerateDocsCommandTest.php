<?php

declare(strict_types=1);

uses(Rfpdl\WhatsUpDoc\Tests\TestCase::class);

it('command generates JSON docs with fixture data', function () {
    $outputPath = sys_get_temp_dir() . '/whats-up-doc-cmd-' . uniqid();

    config([
        'whats-up-doc.scan_paths' => [$this->getFixturePath('Data')],
        'whats-up-doc.exclude_patterns' => ['*Circular*'],
        'whats-up-doc.route_prefixes' => [],
    ]);

    $this->artisan('data-doc:generate', [
        '--output' => $outputPath,
        '--format' => 'json',
    ])->assertExitCode(0);

    expect(file_exists($outputPath . '/documentation.json'))->toBeTrue();

    // Clean up
    unlink($outputPath . '/documentation.json');
    rmdir($outputPath);
});

it('command generates OpenAPI docs with fixture data', function () {
    $outputPath = sys_get_temp_dir() . '/whats-up-doc-cmd-' . uniqid();

    config([
        'whats-up-doc.scan_paths' => [$this->getFixturePath('Data')],
        'whats-up-doc.exclude_patterns' => ['*Circular*'],
        'whats-up-doc.route_prefixes' => [],
    ]);

    $this->artisan('data-doc:generate', [
        '--output' => $outputPath,
        '--format' => 'openapi',
    ])->assertExitCode(0);

    $file = $outputPath . '/openapi.json';
    expect(file_exists($file))->toBeTrue();

    $content = json_decode(file_get_contents($file), true);
    expect($content['openapi'])->toBe('3.1.0');

    // Clean up
    unlink($file);
    rmdir($outputPath);
});

it('command fails with no scan paths finding classes', function () {
    config([
        'whats-up-doc.scan_paths' => ['/nonexistent/path'],
        'whats-up-doc.exclude_patterns' => [],
    ]);

    $this->artisan('data-doc:generate', [
        '--format' => 'json',
    ])->assertExitCode(1);
});
