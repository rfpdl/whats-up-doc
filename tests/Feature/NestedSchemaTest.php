<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Rfpdl\WhatsUpDoc\Services\DocumentationGenerator;
use Rfpdl\WhatsUpDoc\Tests\Fixtures\Data\ChildData;
use Rfpdl\WhatsUpDoc\Tests\Fixtures\Data\CircularDataA;
use Rfpdl\WhatsUpDoc\Tests\Fixtures\Data\CircularDataB;
use Rfpdl\WhatsUpDoc\Tests\Fixtures\Data\CollectionData;
use Rfpdl\WhatsUpDoc\Tests\Fixtures\Data\DeepNestedData;
use Rfpdl\WhatsUpDoc\Tests\Fixtures\Data\NestedData;
use Rfpdl\WhatsUpDoc\Tests\Fixtures\Data\SimpleData;

uses(Rfpdl\WhatsUpDoc\Tests\TestCase::class);

function buildDataClassEntry(string $className): array
{
    $reflection = new ReflectionClass($className);

    return [
        'class' => $className,
        'reflection' => $reflection,
    ];
}

function getDocumentation(DocumentationGenerator $generator, array $classNames): array
{
    $dataClasses = collect(array_map(fn ($c) => buildDataClassEntry($c), $classNames));
    $openApi = $generator->buildOpenApiArray($dataClasses);

    return $openApi['components']['schemas'] ?? [];
}

it('discovers nested Data class schemas from direct property', function () {
    $generator = app(DocumentationGenerator::class);
    $schemas = getDocumentation($generator, [NestedData::class]);

    expect($schemas)->toHaveKey('NestedData');
    expect($schemas)->toHaveKey('SimpleData');
});

it('generates $ref for nested Data class property', function () {
    $generator = app(DocumentationGenerator::class);
    $schemas = getDocumentation($generator, [NestedData::class]);

    $authorProp = $schemas['NestedData']['properties']['author'] ?? null;
    expect($authorProp)->not->toBeNull();
    expect($authorProp['$ref'])->toBe('#/components/schemas/SimpleData');
});

it('marks nullable nested Data class property with oneOf in 3.1', function () {
    $generator = app(DocumentationGenerator::class);
    $schemas = getDocumentation($generator, [NestedData::class]);

    $reviewerProp = $schemas['NestedData']['properties']['reviewer'] ?? null;
    expect($reviewerProp)->not->toBeNull();
    expect($reviewerProp)->toHaveKey('oneOf');
    expect($reviewerProp['oneOf'])->toHaveCount(2);
    expect($reviewerProp['oneOf'][0]['$ref'])->toBe('#/components/schemas/SimpleData');
    expect($reviewerProp['oneOf'][1]['type'])->toBe('null');
});

it('discovers nested type from @var docblock array annotation', function () {
    $generator = app(DocumentationGenerator::class);
    $schemas = getDocumentation($generator, [CollectionData::class]);

    expect($schemas)->toHaveKey('CollectionData');
    expect($schemas)->toHaveKey('ChildData');
    expect($schemas)->toHaveKey('SimpleData');

    $itemsProp = $schemas['CollectionData']['properties']['items'] ?? null;
    expect($itemsProp['type'])->toBe('array');
    expect($itemsProp['items']['$ref'])->toBe('#/components/schemas/ChildData');
});

it('discovers schemas through 3 levels of nesting', function () {
    $generator = app(DocumentationGenerator::class);
    $schemas = getDocumentation($generator, [DeepNestedData::class]);

    expect($schemas)->toHaveKey('DeepNestedData');
    expect($schemas)->toHaveKey('NestedData');
    expect($schemas)->toHaveKey('SimpleData');
});

it('handles circular references without infinite loop', function () {
    $generator = app(DocumentationGenerator::class);
    $schemas = getDocumentation($generator, [CircularDataA::class]);

    expect($schemas)->toHaveKey('CircularDataA');
    expect($schemas)->toHaveKey('CircularDataB');

    $partnerA = $schemas['CircularDataA']['properties']['partner'] ?? null;
    expect($partnerA['oneOf'][0]['$ref'])->toBe('#/components/schemas/CircularDataB');
    expect($partnerA['oneOf'][1]['type'])->toBe('null');

    $partnerB = $schemas['CircularDataB']['properties']['partner'] ?? null;
    expect($partnerB['oneOf'][0]['$ref'])->toBe('#/components/schemas/CircularDataA');
    expect($partnerB['oneOf'][1]['type'])->toBe('null');
});

it('does not duplicate schemas already in the scanned set', function () {
    $generator = app(DocumentationGenerator::class);
    $schemas = getDocumentation($generator, [NestedData::class, SimpleData::class]);

    expect($schemas)->toHaveKey('NestedData');
    expect($schemas)->toHaveKey('SimpleData');

    $simpleProps = $schemas['SimpleData']['properties'];
    expect($simpleProps)->toHaveKey('id');
    expect($simpleProps)->toHaveKey('name');
});

it('generates recursive examples for nested Data classes', function () {
    $generator = app(DocumentationGenerator::class);
    $dataClasses = collect([buildDataClassEntry(NestedData::class)]);
    $openApi = $generator->buildOpenApiArray($dataClasses);
    $schemas = $openApi['components']['schemas'];

    $nestedDoc = $schemas['NestedData'] ?? null;
    expect($nestedDoc)->not->toBeNull();
});

it('generates example with nested array items', function () {
    $generator = app(DocumentationGenerator::class);
    $dataClasses = collect([buildDataClassEntry(CollectionData::class)]);
    $openApi = $generator->buildOpenApiArray($dataClasses);

    $schemas = $openApi['components']['schemas'];
    expect($schemas)->toHaveKey('CollectionData');
    expect($schemas)->toHaveKey('ChildData');
});

it('does not follow nested schemas when follow_nested is disabled', function () {
    config(['whats-up-doc.scan.follow_nested' => false]);

    $generator = app(DocumentationGenerator::class);
    $schemas = getDocumentation($generator, [NestedData::class]);

    expect($schemas)->toHaveKey('NestedData');
    expect($schemas)->not->toHaveKey('SimpleData');
});
