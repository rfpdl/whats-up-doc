<?php

declare(strict_types=1);

use Rfpdl\WhatsUpDoc\Support\ErrorCollector;
use Rfpdl\WhatsUpDoc\Support\ScanError;
use Rfpdl\WhatsUpDoc\Support\ScanResult;

test('empty factory creates empty result', function () {
    $result = ScanResult::empty();

    expect($result->hasClasses())->toBeFalse()
        ->and($result->classCount())->toBe(0)
        ->and($result->isSuccessful())->toBeTrue()
        ->and($result->dataClasses)->toBeEmpty();
});

test('withClasses populates correctly', function () {
    $classes = collect([
        ['class' => 'App\\Data\\Foo', 'shortName' => 'Foo'],
        ['class' => 'App\\Data\\Bar', 'shortName' => 'Bar'],
    ]);

    $result = ScanResult::withClasses($classes);

    expect($result->hasClasses())->toBeTrue()
        ->and($result->classCount())->toBe(2);
});

test('isSuccessful true with no errors', function () {
    $result = new ScanResult(
        dataClasses: collect(),
        errors: new ErrorCollector(),
    );

    expect($result->isSuccessful())->toBeTrue();
});

test('isSuccessful false with errors', function () {
    $errors = new ErrorCollector();
    $errors->add(ScanError::fileNotReadable('/path'));

    $result = new ScanResult(
        dataClasses: collect(),
        errors: $errors,
    );

    expect($result->isSuccessful())->toBeFalse();
});

test('classNames extracts only class names', function () {
    $classes = collect([
        ['class' => 'App\\Data\\Foo', 'shortName' => 'Foo'],
        ['class' => 'App\\Data\\Bar', 'shortName' => 'Bar'],
    ]);

    $result = ScanResult::withClasses($classes);

    expect($result->classNames()->all())->toBe(['App\\Data\\Foo', 'App\\Data\\Bar']);
});

test('findClass finds by name', function () {
    $classes = collect([
        ['class' => 'App\\Data\\Foo', 'shortName' => 'Foo'],
        ['class' => 'App\\Data\\Bar', 'shortName' => 'Bar'],
    ]);

    $result = ScanResult::withClasses($classes);

    expect($result->findClass('App\\Data\\Foo'))->not->toBeNull()
        ->and($result->findClass('App\\Data\\Foo')['shortName'])->toBe('Foo');
});

test('findClass returns null for missing', function () {
    $result = ScanResult::withClasses(collect([
        ['class' => 'App\\Data\\Foo'],
    ]));

    expect($result->findClass('App\\Data\\Missing'))->toBeNull();
});

test('toArray has correct structure', function () {
    $classes = collect([
        ['class' => 'App\\Data\\Foo'],
    ]);

    $result = ScanResult::withClasses($classes);
    $array = $result->toArray();

    expect($array)->toHaveKeys(['success', 'class_count', 'classes', 'errors', 'metadata'])
        ->and($array['success'])->toBeTrue()
        ->and($array['class_count'])->toBe(1)
        ->and($array['classes'])->toBe(['App\\Data\\Foo']);
});

test('metadata is accessible', function () {
    $result = new ScanResult(
        dataClasses: collect(),
        errors: new ErrorCollector(),
        metadata: ['scanned_files' => 10, 'class_count' => 3],
    );

    expect($result->metadata['scanned_files'])->toBe(10)
        ->and($result->metadata['class_count'])->toBe(3);
});
