<?php

declare(strict_types=1);

use Rfpdl\WhatsUpDoc\Services\DataClassScanner;
use Rfpdl\WhatsUpDoc\Services\DocumentationGenerator;
use Rfpdl\WhatsUpDoc\Tests\Fixtures\Data\ChildData;
use Rfpdl\WhatsUpDoc\Tests\Fixtures\Data\CollectionData;
use Rfpdl\WhatsUpDoc\Tests\Fixtures\Data\DeepNestedData;
use Rfpdl\WhatsUpDoc\Tests\Fixtures\Data\EnumPropertyData;
use Rfpdl\WhatsUpDoc\Tests\Fixtures\Data\NestedData;
use Rfpdl\WhatsUpDoc\Tests\Fixtures\Data\SimpleData;
use Rfpdl\WhatsUpDoc\Tests\Fixtures\Data\ValidationData;

uses(Rfpdl\WhatsUpDoc\Tests\TestCase::class);

it('full workflow: scan -> generate OpenAPI -> validate output', function () {
    config([
        'whats-up-doc.scan_paths' => [$this->getFixturePath('Data')],
        'whats-up-doc.exclude_patterns' => ['*Circular*'],
        'whats-up-doc.route_prefixes' => [],
    ]);

    $scanner = app(DataClassScanner::class);
    $result = $scanner->scan();

    expect($result->hasClasses())->toBeTrue();
    expect($result->isSuccessful())->toBeTrue();

    $generator = app(DocumentationGenerator::class);
    $openApi = $generator->buildOpenApiArray($result->dataClasses);

    // Validate top-level structure
    expect($openApi['openapi'])->toBe('3.1.0');
    expect($openApi['info']['title'])->toBe('API Documentation');
    expect($openApi['components']['schemas'])->not->toBeEmpty();

    $schemas = $openApi['components']['schemas'];

    // All scanned Data classes should be in schemas
    expect($schemas)->toHaveKey('SimpleData');
    expect($schemas)->toHaveKey('NestedData');
    expect($schemas)->toHaveKey('CollectionData');
    expect($schemas)->toHaveKey('DeepNestedData');
    expect($schemas)->toHaveKey('EnumPropertyData');
    expect($schemas)->toHaveKey('ValidationData');
    expect($schemas)->toHaveKey('NullableData');

    // Nested classes should be discovered and included
    expect($schemas)->toHaveKey('ChildData');
});

it('nested schemas have valid $ref links', function () {
    config([
        'whats-up-doc.scan_paths' => [$this->getFixturePath('Data')],
        'whats-up-doc.exclude_patterns' => ['*Circular*'],
        'whats-up-doc.route_prefixes' => [],
    ]);

    $scanner = app(DataClassScanner::class);
    $generator = app(DocumentationGenerator::class);
    $openApi = $generator->buildOpenApiArray($scanner->scanClasses());
    $schemas = $openApi['components']['schemas'];

    $json = json_encode($openApi, JSON_UNESCAPED_SLASHES);
    preg_match_all('/"\$ref"\s*:\s*"#\/components\/schemas\/([^"]+)"/', $json, $matches);

    $refs = array_unique($matches[1]);
    expect($refs)->not->toBeEmpty();

    foreach ($refs as $refName) {
        expect($schemas)->toHaveKey($refName);
    }
});

it('enum values are correctly captured in schemas', function () {
    config([
        'whats-up-doc.scan_paths' => [$this->getFixturePath('Data')],
        'whats-up-doc.exclude_patterns' => ['*Circular*'],
        'whats-up-doc.route_prefixes' => [],
    ]);

    $scanner = app(DataClassScanner::class);
    $generator = app(DocumentationGenerator::class);
    $openApi = $generator->buildOpenApiArray($scanner->scanClasses());
    $schemas = $openApi['components']['schemas'];

    $enumProps = $schemas['EnumPropertyData']['properties'];

    // String-backed enum
    expect($enumProps['status']['enum'])->toContain('active');
    expect($enumProps['status']['enum'])->toContain('inactive');
    expect($enumProps['status']['enum'])->toContain('pending');

    // Int-backed enum
    expect($enumProps['priority']['enum'])->toContain(1);
    expect($enumProps['priority']['enum'])->toContain(2);
    expect($enumProps['priority']['enum'])->toContain(3);
    expect($enumProps['priority']['enum'])->toContain(4);

    // Unit enum (nullable)
    expect($enumProps['color']['enum'])->toContain('Red');
    expect($enumProps['color']['enum'])->toContain('Green');
    expect($enumProps['color']['enum'])->toContain('Blue');
});

it('validation rules are mapped to OpenAPI constraints', function () {
    config([
        'whats-up-doc.scan_paths' => [$this->getFixturePath('Data')],
        'whats-up-doc.exclude_patterns' => ['*Circular*'],
        'whats-up-doc.route_prefixes' => [],
    ]);

    $scanner = app(DataClassScanner::class);
    $generator = app(DocumentationGenerator::class);
    $openApi = $generator->buildOpenApiArray($scanner->scanClasses());
    $schemas = $openApi['components']['schemas'];

    $validationProps = $schemas['ValidationData']['properties'];

    expect($validationProps['email']['format'])->toBe('email');
});

it('generates valid JSON output file', function () {
    config([
        'whats-up-doc.scan_paths' => [$this->getFixturePath('Data')],
        'whats-up-doc.exclude_patterns' => ['*Circular*'],
        'whats-up-doc.route_prefixes' => [],
    ]);

    $outputPath = sys_get_temp_dir() . '/whats-up-doc-e2e-' . uniqid();
    mkdir($outputPath, 0777, true);

    $scanner = app(DataClassScanner::class);
    $generator = app(DocumentationGenerator::class);

    $generator->generateOpenApi($scanner->scanClasses(), $outputPath);

    $file = $outputPath . '/openapi.json';
    expect(file_exists($file))->toBeTrue();

    $content = file_get_contents($file);
    $parsed = json_decode($content, true);

    expect(json_last_error())->toBe(JSON_ERROR_NONE);
    expect($parsed['openapi'])->toBe('3.1.0');
    expect($parsed['components']['schemas'])->not->toBeEmpty();

    // Clean up
    unlink($file);
    rmdir($outputPath);
});
