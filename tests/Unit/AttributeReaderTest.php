<?php

declare(strict_types=1);

use Rfpdl\WhatsUpDoc\Support\AttributeReader;
use Rfpdl\WhatsUpDoc\Tests\Fixtures\Data\ValidationData;
use Rfpdl\WhatsUpDoc\Tests\Fixtures\Data\SimpleData;
use Rfpdl\WhatsUpDoc\Tests\Fixtures\Data\NullableData;

beforeEach(function () {
    $this->reader = new AttributeReader();
});

// --- Validation rules ---

test('extracts Required validation rule', function () {
    $reflection = new ReflectionClass(ValidationData::class);
    $property = $reflection->getProperty('name');
    $rules = $this->reader->extractValidationRules($property);

    $ruleNames = array_column($rules, 'name');
    expect($ruleNames)->toContain('Required');
});

test('extracts Email validation rule', function () {
    $reflection = new ReflectionClass(ValidationData::class);
    $property = $reflection->getProperty('email');
    $rules = $this->reader->extractValidationRules($property);

    $ruleNames = array_column($rules, 'name');
    expect($ruleNames)->toContain('Email');
});

test('extracts Min validation rule with value', function () {
    $reflection = new ReflectionClass(ValidationData::class);
    $property = $reflection->getProperty('age');
    $rules = $this->reader->extractValidationRules($property);

    $minRule = collect($rules)->firstWhere('name', 'Min');
    expect($minRule)->not->toBeNull()
        ->and($minRule['constraint'])->toContain('min:');
});

test('extracts Max validation rule with value', function () {
    $reflection = new ReflectionClass(ValidationData::class);
    $property = $reflection->getProperty('age');
    $rules = $this->reader->extractValidationRules($property);

    $maxRule = collect($rules)->firstWhere('name', 'Max');
    expect($maxRule)->not->toBeNull()
        ->and($maxRule['constraint'])->toContain('max:');
});

test('extracts multiple validation rules from single property', function () {
    $reflection = new ReflectionClass(ValidationData::class);
    $property = $reflection->getProperty('bio');
    $rules = $this->reader->extractValidationRules($property);

    expect(count($rules))->toBeGreaterThanOrEqual(2);
    $ruleNames = array_column($rules, 'name');
    expect($ruleNames)->toContain('Min')
        ->and($ruleNames)->toContain('Max');
});

test('returns empty rules for property without validation', function () {
    $reflection = new ReflectionClass(SimpleData::class);
    $property = $reflection->getProperty('name');
    $rules = $this->reader->extractValidationRules($property);

    expect($rules)->toBeEmpty();
});

// --- Hidden attribute ---

test('isHidden returns false for normal property', function () {
    $reflection = new ReflectionClass(SimpleData::class);
    $property = $reflection->getProperty('name');

    expect($this->reader->isHidden($property))->toBeFalse();
});

// --- Class attributes ---

test('readClassAttributes returns array', function () {
    $reflection = new ReflectionClass(SimpleData::class);
    $attributes = $this->reader->readClassAttributes($reflection);

    expect($attributes)->toBeArray();
});

// --- Property attributes ---

test('readPropertyAttributes returns parsed attributes', function () {
    $reflection = new ReflectionClass(ValidationData::class);
    $property = $reflection->getProperty('email');
    $attributes = $this->reader->readPropertyAttributes($property);

    expect($attributes)->not->toBeEmpty();

    $shortNames = array_column($attributes, 'shortName');
    expect($shortNames)->toContain('Required')
        ->and($shortNames)->toContain('Email');
});

// --- Name mappings ---

test('getInputNameMapping returns null when no mapping', function () {
    $reflection = new ReflectionClass(SimpleData::class);
    $property = $reflection->getProperty('name');

    expect($this->reader->getInputNameMapping($property))->toBeNull();
});

test('getOutputNameMapping returns null when no mapping', function () {
    $reflection = new ReflectionClass(SimpleData::class);
    $property = $reflection->getProperty('name');

    expect($this->reader->getOutputNameMapping($property))->toBeNull();
});

// --- Collection item type ---

test('getCollectionItemType returns null when no DataCollectionOf', function () {
    $reflection = new ReflectionClass(SimpleData::class);
    $property = $reflection->getProperty('name');

    expect($this->reader->getCollectionItemType($property))->toBeNull();
});

// --- hasAttribute ---

test('hasAttribute returns false when attribute not present', function () {
    $reflection = new ReflectionClass(SimpleData::class);

    expect($this->reader->hasAttribute($reflection, Spatie\LaravelData\Attributes\Hidden::class))->toBeFalse();
});

// --- getAttributesOfType ---

test('getAttributesOfType returns empty when no matching attributes', function () {
    $reflection = new ReflectionClass(SimpleData::class);

    expect($this->reader->getAttributesOfType($reflection, Spatie\LaravelData\Attributes\Hidden::class))->toBeEmpty();
});
