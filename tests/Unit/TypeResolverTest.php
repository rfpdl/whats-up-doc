<?php

declare(strict_types=1);

use Rfpdl\WhatsUpDoc\Support\TypeResolver;
use Rfpdl\WhatsUpDoc\Tests\Fixtures\Data\SimpleData;
use Rfpdl\WhatsUpDoc\Tests\Fixtures\Data\NullableData;
use Rfpdl\WhatsUpDoc\Tests\Fixtures\Data\EnumPropertyData;
use Rfpdl\WhatsUpDoc\Tests\Fixtures\Enums\StatusEnum;
use Rfpdl\WhatsUpDoc\Tests\Fixtures\Enums\PriorityEnum;
use Rfpdl\WhatsUpDoc\Tests\Fixtures\Enums\ColorEnum;

beforeEach(function () {
    $this->resolver = new TypeResolver();
});

// --- Builtin types ---

test('resolves string property', function () {
    $reflection = new ReflectionClass(SimpleData::class);
    $property = $reflection->getProperty('name');
    $type = $this->resolver->resolveFromProperty($property);

    expect($type['name'])->toBe('string')
        ->and($type['base'])->toBe('string')
        ->and($type['isBuiltin'])->toBeTrue()
        ->and($type['nullable'])->toBeFalse();
});

test('resolves int property', function () {
    $reflection = new ReflectionClass(SimpleData::class);
    $property = $reflection->getProperty('id');
    $type = $this->resolver->resolveFromProperty($property);

    expect($type['name'])->toBe('int')
        ->and($type['base'])->toBe('integer')
        ->and($type['isBuiltin'])->toBeTrue();
});

test('resolves bool property', function () {
    $reflection = new ReflectionClass(SimpleData::class);
    $property = $reflection->getProperty('is_active');
    $type = $this->resolver->resolveFromProperty($property);

    expect($type['name'])->toBe('bool')
        ->and($type['base'])->toBe('boolean')
        ->and($type['isBuiltin'])->toBeTrue();
});

// --- Nullable types ---

test('resolves nullable string', function () {
    $reflection = new ReflectionClass(NullableData::class);
    $property = $reflection->getProperty('nickname');
    $type = $this->resolver->resolveFromProperty($property);

    expect($type['nullable'])->toBeTrue()
        ->and($type['base'])->toBe('string');
});

test('resolves nullable int', function () {
    $reflection = new ReflectionClass(NullableData::class);
    $property = $reflection->getProperty('age');
    $type = $this->resolver->resolveFromProperty($property);

    expect($type['nullable'])->toBeTrue()
        ->and($type['base'])->toBe('integer');
});

// --- Enum types ---

test('resolves backed string enum', function () {
    $reflection = new ReflectionClass(EnumPropertyData::class);
    $property = $reflection->getProperty('status');
    $type = $this->resolver->resolveFromProperty($property);

    expect($type['isEnum'])->toBeTrue()
        ->and($type['base'])->toBe('string')
        ->and($type['enumValues'])->toBe(['active', 'inactive', 'pending'])
        ->and($type['reference'])->toBe(StatusEnum::class);
});

test('resolves backed int enum', function () {
    $reflection = new ReflectionClass(EnumPropertyData::class);
    $property = $reflection->getProperty('priority');
    $type = $this->resolver->resolveFromProperty($property);

    expect($type['isEnum'])->toBeTrue()
        ->and($type['base'])->toBe('integer')
        ->and($type['enumValues'])->toBe([1, 2, 3, 4]);
});

test('resolves nullable unit enum', function () {
    $reflection = new ReflectionClass(EnumPropertyData::class);
    $property = $reflection->getProperty('color');
    $type = $this->resolver->resolveFromProperty($property);

    expect($type['isEnum'])->toBeTrue()
        ->and($type['nullable'])->toBeTrue()
        ->and($type['enumValues'])->toBe(['Red', 'Green', 'Blue']);
});

// --- Data class types ---

test('resolves Data class property', function () {
    $reflection = new ReflectionClass(Rfpdl\WhatsUpDoc\Tests\Fixtures\Data\NestedData::class);
    $property = $reflection->getProperty('author');
    $type = $this->resolver->resolveFromProperty($property);

    expect($type['isDataClass'])->toBeTrue()
        ->and($type['base'])->toBe('object')
        ->and($type['reference'])->toBe(SimpleData::class)
        ->and($type['name'])->toBe('SimpleData');
});

test('resolves nullable Data class property', function () {
    $reflection = new ReflectionClass(Rfpdl\WhatsUpDoc\Tests\Fixtures\Data\NestedData::class);
    $property = $reflection->getProperty('reviewer');
    $type = $this->resolver->resolveFromProperty($property);

    expect($type['isDataClass'])->toBeTrue()
        ->and($type['nullable'])->toBeTrue()
        ->and($type['reference'])->toBe(SimpleData::class);
});

// --- isDataClass ---

test('isDataClass returns true for Data subclass', function () {
    expect($this->resolver->isDataClass(SimpleData::class))->toBeTrue();
});

test('isDataClass returns false for non-Data class', function () {
    expect($this->resolver->isDataClass(\stdClass::class))->toBeFalse();
});

test('isDataClass returns false for nonexistent class', function () {
    expect($this->resolver->isDataClass('Nonexistent\\Class'))->toBeFalse();
});

// --- OpenAPI type mapping ---

test('getOpenApiType maps builtin types', function () {
    expect($this->resolver->getOpenApiType('int'))->toBe('integer')
        ->and($this->resolver->getOpenApiType('float'))->toBe('number')
        ->and($this->resolver->getOpenApiType('bool'))->toBe('boolean')
        ->and($this->resolver->getOpenApiType('array'))->toBe('array')
        ->and($this->resolver->getOpenApiType('string'))->toBe('string')
        ->and($this->resolver->getOpenApiType('mixed'))->toBe('string')
        ->and($this->resolver->getOpenApiType('SomeClass'))->toBe('object');
});

// --- Short class name ---

test('getShortClassName extracts short name', function () {
    expect($this->resolver->getShortClassName('App\\Data\\UserData'))->toBe('UserData')
        ->and($this->resolver->getShortClassName('SimpleClass'))->toBe('SimpleClass');
});

// --- Example generation ---

test('generates example for integer type', function () {
    $typeInfo = [
        'name' => 'int', 'base' => 'integer', 'nullable' => false,
        'isBuiltin' => true, 'isEnum' => false, 'isDataClass' => false,
        'isArray' => false, 'enumValues' => null, 'nestedType' => null,
        'reference' => null, 'format' => null,
    ];

    expect($this->resolver->generateExampleValue($typeInfo))->toBe(123);
});

test('generates example for enum type', function () {
    $typeInfo = [
        'name' => 'StatusEnum', 'base' => 'string', 'nullable' => false,
        'isBuiltin' => false, 'isEnum' => true, 'isDataClass' => false,
        'isArray' => false, 'enumValues' => ['active', 'inactive'], 'nestedType' => null,
        'reference' => null, 'format' => null,
    ];

    expect($this->resolver->generateExampleValue($typeInfo))->toBe('active');
});

test('generates example for array type', function () {
    $typeInfo = [
        'name' => 'array', 'base' => 'array', 'nullable' => false,
        'isBuiltin' => true, 'isEnum' => false, 'isDataClass' => false,
        'isArray' => true, 'enumValues' => null, 'nestedType' => null,
        'reference' => null, 'format' => null,
    ];

    expect($this->resolver->generateExampleValue($typeInfo))->toBe([]);
});
