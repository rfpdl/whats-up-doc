<?php

declare(strict_types=1);

use Rfpdl\WhatsUpDoc\Support\ScanError;

test('classNotFound creates error with correct type and severity', function () {
    $error = ScanError::classNotFound('App\\Data\\UserData', '/app/Data/UserData.php');

    expect($error->type)->toBe(ScanError::TYPE_CLASS_NOT_FOUND)
        ->and($error->severity)->toBe(ScanError::SEVERITY_ERROR)
        ->and($error->class)->toBe('App\\Data\\UserData')
        ->and($error->file)->toBe('/app/Data/UserData.php')
        ->and($error->message)->toContain('UserData');
});

test('reflectionFailed captures exception', function () {
    $exception = new \RuntimeException('Reflection failed');
    $error = ScanError::reflectionFailed('App\\Data\\UserData', $exception);

    expect($error->type)->toBe(ScanError::TYPE_REFLECTION_FAILED)
        ->and($error->severity)->toBe(ScanError::SEVERITY_ERROR)
        ->and($error->exception)->toBe($exception)
        ->and($error->message)->toContain('Reflection failed');
});

test('parseError includes file path', function () {
    $error = ScanError::parseError('/app/Data/Bad.php', 'syntax error');

    expect($error->type)->toBe(ScanError::TYPE_PARSE_ERROR)
        ->and($error->file)->toBe('/app/Data/Bad.php')
        ->and($error->message)->toContain('syntax error');
});

test('fileNotReadable creates error', function () {
    $error = ScanError::fileNotReadable('/nonexistent/path');

    expect($error->type)->toBe(ScanError::TYPE_FILE_NOT_READABLE)
        ->and($error->severity)->toBe(ScanError::SEVERITY_ERROR)
        ->and($error->file)->toBe('/nonexistent/path');
});

test('invalidDataClass has warning severity', function () {
    $error = ScanError::invalidDataClass('App\\Data\\Bad', 'not a Data subclass');

    expect($error->type)->toBe(ScanError::TYPE_INVALID_DATA_CLASS)
        ->and($error->severity)->toBe(ScanError::SEVERITY_WARNING);
});

test('routeAnalysisFailed captures exception', function () {
    $exception = new \RuntimeException('reflection failed');
    $error = ScanError::routeAnalysisFailed('api/users', $exception);

    expect($error->type)->toBe(ScanError::TYPE_ROUTE_ANALYSIS_FAILED)
        ->and($error->severity)->toBe(ScanError::SEVERITY_WARNING)
        ->and($error->message)->toContain('api/users');
});

test('typeResolutionFailed includes class and property', function () {
    $exception = new \RuntimeException('unknown type');
    $error = ScanError::typeResolutionFailed('App\\Data\\UserData', 'email', $exception);

    expect($error->type)->toBe(ScanError::TYPE_TYPE_RESOLUTION_FAILED)
        ->and($error->class)->toBe('App\\Data\\UserData')
        ->and($error->property)->toBe('email')
        ->and($error->message)->toContain('email');
});

test('warning factory creates warning', function () {
    $error = ScanError::warning('glob matched nothing');

    expect($error->severity)->toBe(ScanError::SEVERITY_WARNING)
        ->and($error->message)->toBe('glob matched nothing');
});

test('toArray includes all fields', function () {
    $exception = new \RuntimeException('test');
    $error = ScanError::reflectionFailed('MyClass', $exception);
    $array = $error->toArray();

    expect($array)->toHaveKeys(['type', 'severity', 'message', 'file', 'class', 'property', 'exception'])
        ->and($array['exception'])->toBe('test');
});
