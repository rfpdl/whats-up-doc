<?php

declare(strict_types=1);

use Rfpdl\WhatsUpDoc\Services\DataClassScanner;
use Rfpdl\WhatsUpDoc\Tests\Fixtures\Data\SimpleData;
use Rfpdl\WhatsUpDoc\Tests\Fixtures\Data\NestedData;
use Rfpdl\WhatsUpDoc\Tests\Fixtures\Data\ChildData;

uses(Rfpdl\WhatsUpDoc\Tests\TestCase::class);

beforeEach(function () {
    $this->scanner = app(DataClassScanner::class);
});

it('scans fixture directory and finds Data classes', function () {
    config(['whats-up-doc.scan_paths' => [$this->getFixturePath('Data')]]);
    config(['whats-up-doc.exclude_patterns' => []]);

    $result = $this->scanner->scan();

    expect($result->dataClasses)->not->toBeEmpty();
    expect($result->hasClasses())->toBeTrue();
});

it('returns ScanResult with correct metadata', function () {
    config(['whats-up-doc.scan_paths' => [$this->getFixturePath('Data')]]);
    config(['whats-up-doc.exclude_patterns' => []]);

    $result = $this->scanner->scan();

    expect($result->metadata)->toHaveKey('scanned_at');
    expect($result->metadata)->toHaveKey('scanned_files');
    expect($result->metadata)->toHaveKey('class_count');
    expect($result->metadata['scanned_files'])->toBeGreaterThan(0);
    expect($result->metadata['class_count'])->toBeGreaterThan(0);
});

it('finds specific known fixture classes', function () {
    config(['whats-up-doc.scan_paths' => [$this->getFixturePath('Data')]]);
    config(['whats-up-doc.exclude_patterns' => []]);

    $result = $this->scanner->scan();
    $classNames = $result->classNames()->toArray();

    expect($classNames)->toContain(SimpleData::class);
    expect($classNames)->toContain(NestedData::class);
    expect($classNames)->toContain(ChildData::class);
});

it('skips non-PHP files', function () {
    config(['whats-up-doc.scan_paths' => [$this->getFixturePath('Data')]]);
    config(['whats-up-doc.exclude_patterns' => []]);

    $result = $this->scanner->scan();
    $errors = $result->errors;

    expect($errors->hasErrors())->toBeFalse();
});

it('skips classes matching exclude patterns', function () {
    config(['whats-up-doc.scan_paths' => [$this->getFixturePath('Data')]]);
    config(['whats-up-doc.exclude_patterns' => ['*Circular*']]);

    $result = $this->scanner->scan();
    $classNames = $result->classNames()->toArray();

    foreach ($classNames as $name) {
        expect($name)->not->toContain('Circular');
    }
});

it('reports error for missing directory', function () {
    config(['whats-up-doc.scan_paths' => ['/nonexistent/path/data']]);
    config(['whats-up-doc.exclude_patterns' => []]);

    $result = $this->scanner->scan();

    expect($result->errors->hasErrors())->toBeTrue();
    expect($result->dataClasses)->toBeEmpty();
});

it('includes reflection data for each scanned class', function () {
    config(['whats-up-doc.scan_paths' => [$this->getFixturePath('Data')]]);
    config(['whats-up-doc.exclude_patterns' => []]);

    $result = $this->scanner->scan();
    $first = $result->dataClasses->first();

    expect($first)->toHaveKeys(['class', 'reflection', 'file', 'shortName', 'namespace', 'docblock']);
    expect($first['reflection'])->toBeInstanceOf(ReflectionClass::class);
});

it('includes parsed docblock for each class', function () {
    config(['whats-up-doc.scan_paths' => [$this->getFixturePath('Data')]]);
    config(['whats-up-doc.exclude_patterns' => []]);

    $result = $this->scanner->scan();
    $simpleData = $result->findClass(SimpleData::class);

    expect($simpleData)->not->toBeNull();
    expect($simpleData['docblock'])->toHaveKey('description');
    expect($simpleData['docblock']['description'])->toContain('simple data class');
});

it('scanClasses returns just the collection', function () {
    config(['whats-up-doc.scan_paths' => [$this->getFixturePath('Data')]]);
    config(['whats-up-doc.exclude_patterns' => []]);

    $classes = $this->scanner->scanClasses();

    expect($classes)->toBeInstanceOf(Illuminate\Support\Collection::class);
    expect($classes)->not->toBeEmpty();
});

it('expands glob patterns in scan paths', function () {
    $fixturesPath = $this->getFixturePath();

    // Create a temporary glob-testable directory structure
    $basePath = sys_get_temp_dir() . '/whats-up-doc-test-' . uniqid();
    $domainA = $basePath . '/DomainA/Data';
    $domainB = $basePath . '/DomainB/Data';

    mkdir($domainA, 0777, true);
    mkdir($domainB, 0777, true);

    config(['whats-up-doc.scan_paths' => [$basePath . '/*/Data']]);
    config(['whats-up-doc.exclude_patterns' => []]);

    $result = $this->scanner->scan();

    // Clean up
    rmdir($domainA);
    rmdir($basePath . '/DomainA');
    rmdir($domainB);
    rmdir($basePath . '/DomainB');
    rmdir($basePath);

    expect($result->errors->hasErrors())->toBeFalse();
});

it('warns when glob pattern matches nothing', function () {
    config(['whats-up-doc.scan_paths' => ['/tmp/nonexistent-*/data']]);
    config(['whats-up-doc.exclude_patterns' => []]);

    $result = $this->scanner->scan();

    $warnings = $result->errors->warnings();
    expect($warnings)->not->toBeEmpty();
});

it('does not scan abstract Data classes', function () {
    config(['whats-up-doc.scan_paths' => [$this->getFixturePath('Data')]]);
    config(['whats-up-doc.exclude_patterns' => []]);

    $result = $this->scanner->scan();
    $classNames = $result->classNames()->toArray();

    foreach ($classNames as $name) {
        if (class_exists($name)) {
            $ref = new ReflectionClass($name);
            expect($ref->isAbstract())->toBeFalse();
        }
    }
});
