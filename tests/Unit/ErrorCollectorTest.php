<?php

declare(strict_types=1);

use Rfpdl\WhatsUpDoc\Support\ErrorCollector;
use Rfpdl\WhatsUpDoc\Support\ScanError;

beforeEach(function () {
    $this->collector = new ErrorCollector();
});

test('starts empty', function () {
    expect($this->collector->count())->toBe(0)
        ->and($this->collector->hasAny())->toBeFalse()
        ->and($this->collector->hasErrors())->toBeFalse()
        ->and($this->collector->hasWarnings())->toBeFalse();
});

test('add stores error', function () {
    $error = ScanError::fileNotReadable('/path');
    $this->collector->add($error);

    expect($this->collector->count())->toBe(1)
        ->and($this->collector->hasAny())->toBeTrue()
        ->and($this->collector->all()->first())->toBe($error);
});

test('add returns self for chaining', function () {
    $result = $this->collector->add(ScanError::fileNotReadable('/path'));
    expect($result)->toBe($this->collector);
});

test('addMany filters non-ScanError items', function () {
    $error = ScanError::fileNotReadable('/path');
    $this->collector->addMany([$error, 'not an error', 123]);

    expect($this->collector->count())->toBe(1);
});

test('bySeverity filters correctly', function () {
    $this->collector->add(ScanError::fileNotReadable('/path'));
    $this->collector->add(ScanError::invalidDataClass('Cls', 'reason'));
    $this->collector->add(ScanError::fileNotReadable('/other'));

    expect($this->collector->bySeverity(ScanError::SEVERITY_ERROR)->count())->toBe(2)
        ->and($this->collector->bySeverity(ScanError::SEVERITY_WARNING)->count())->toBe(1);
});

test('errors returns only errors', function () {
    $this->collector->add(ScanError::fileNotReadable('/path'));
    $this->collector->add(ScanError::invalidDataClass('Cls', 'reason'));

    expect($this->collector->errors()->count())->toBe(1)
        ->and($this->collector->warnings()->count())->toBe(1);
});

test('byType filters by error type', function () {
    $this->collector->add(ScanError::fileNotReadable('/path'));
    $this->collector->add(ScanError::classNotFound('Cls', '/file'));

    expect($this->collector->byType(ScanError::TYPE_FILE_NOT_READABLE)->count())->toBe(1)
        ->and($this->collector->byType(ScanError::TYPE_CLASS_NOT_FOUND)->count())->toBe(1);
});

test('forFile filters by file path', function () {
    $this->collector->add(ScanError::fileNotReadable('/a.php'));
    $this->collector->add(ScanError::fileNotReadable('/b.php'));

    expect($this->collector->forFile('/a.php')->count())->toBe(1);
});

test('forClass filters by class name', function () {
    $this->collector->add(ScanError::classNotFound('Foo', '/f'));
    $this->collector->add(ScanError::classNotFound('Bar', '/b'));

    expect($this->collector->forClass('Foo')->count())->toBe(1);
});

test('clear empties collection', function () {
    $this->collector->add(ScanError::fileNotReadable('/path'));
    $this->collector->clear();

    expect($this->collector->count())->toBe(0)
        ->and($this->collector->hasAny())->toBeFalse();
});

test('merge combines two collectors', function () {
    $other = new ErrorCollector();
    $this->collector->add(ScanError::fileNotReadable('/a'));
    $other->add(ScanError::fileNotReadable('/b'));

    $this->collector->merge($other);

    expect($this->collector->count())->toBe(2);
});

test('countBySeverity returns breakdown', function () {
    $this->collector->add(ScanError::fileNotReadable('/a'));
    $this->collector->add(ScanError::invalidDataClass('C', 'r'));

    $counts = $this->collector->countBySeverity();

    expect($counts['errors'])->toBe(1)
        ->and($counts['warnings'])->toBe(1)
        ->and($counts['info'])->toBe(0);
});

test('toArray converts all errors', function () {
    $this->collector->add(ScanError::fileNotReadable('/a'));
    $array = $this->collector->toArray();

    expect($array)->toBeArray()->toHaveCount(1)
        ->and($array[0])->toHaveKeys(['type', 'severity', 'message']);
});

test('summary returns statistics', function () {
    $this->collector->add(ScanError::fileNotReadable('/a'));
    $summary = $this->collector->summary();

    expect($summary['total'])->toBe(1)
        ->and($summary['has_errors'])->toBeTrue()
        ->and($summary)->toHaveKeys(['counts', 'by_type']);
});

test('formatForConsole returns formatted lines', function () {
    $this->collector->add(ScanError::fileNotReadable('/a'));
    $this->collector->add(ScanError::invalidDataClass('C', 'r'));

    $lines = $this->collector->formatForConsole();

    expect($lines)->toHaveCount(2);
});
