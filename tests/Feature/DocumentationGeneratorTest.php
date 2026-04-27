<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Rfpdl\WhatsUpDoc\Services\DocumentationGenerator;
use Rfpdl\WhatsUpDoc\Tests\Fixtures\Data\CollectionData;
use Rfpdl\WhatsUpDoc\Tests\Fixtures\Data\EnumPropertyData;
use Rfpdl\WhatsUpDoc\Tests\Fixtures\Data\NestedData;
use Rfpdl\WhatsUpDoc\Tests\Fixtures\Data\NullableData;
use Rfpdl\WhatsUpDoc\Tests\Fixtures\Data\SimpleData;
use Rfpdl\WhatsUpDoc\Tests\Fixtures\Data\ValidationData;

uses(Rfpdl\WhatsUpDoc\Tests\TestCase::class);

function makeDataClassEntry(string $className): array
{
    $reflection = new ReflectionClass($className);

    return [
        'class' => $className,
        'reflection' => $reflection,
    ];
}

function makeCollection(array $classNames): Collection
{
    return collect(array_map(fn ($c) => makeDataClassEntry($c), $classNames));
}

beforeEach(function () {
    $this->generator = app(DocumentationGenerator::class);
});

it('builds OpenAPI array with correct structure', function () {
    $openApi = $this->generator->buildOpenApiArray(makeCollection([SimpleData::class]));

    expect($openApi)->toHaveKeys(['openapi', 'info', 'paths', 'components']);
    expect($openApi['openapi'])->toBe('3.1.0');
    expect($openApi['components'])->toHaveKey('schemas');
});

it('generates schema for simple Data class', function () {
    $openApi = $this->generator->buildOpenApiArray(makeCollection([SimpleData::class]));
    $schemas = $openApi['components']['schemas'];

    expect($schemas)->toHaveKey('SimpleData');
    expect($schemas['SimpleData']['type'])->toBe('object');
    expect($schemas['SimpleData']['properties'])->toHaveKeys(['id', 'name', 'email', 'is_active']);
});

it('maps property types correctly in OpenAPI schema', function () {
    $openApi = $this->generator->buildOpenApiArray(makeCollection([SimpleData::class]));
    $props = $openApi['components']['schemas']['SimpleData']['properties'];

    expect($props['id']['type'])->toBe('integer');
    expect($props['name']['type'])->toBe('string');
    expect($props['is_active']['type'])->toBe('boolean');
});

it('marks required properties correctly', function () {
    $openApi = $this->generator->buildOpenApiArray(makeCollection([SimpleData::class]));
    $schema = $openApi['components']['schemas']['SimpleData'];

    expect($schema['required'])->toContain('id');
    expect($schema['required'])->toContain('name');
    expect($schema['required'])->toContain('email');
    expect($schema['required'])->not->toContain('is_active');
});

it('generates schema for nullable properties with 3.1 type array', function () {
    $openApi = $this->generator->buildOpenApiArray(makeCollection([NullableData::class]));
    $props = $openApi['components']['schemas']['NullableData']['properties'];

    expect($props['nickname']['type'])->toBe(['string', 'null']);
    expect($props['age']['type'])->toBe(['integer', 'null']);
});

it('generates schema for nullable properties with 3.0 nullable flag', function () {
    config(['whats-up-doc.openapi.version' => '3.0.0']);

    $generator = app(DocumentationGenerator::class);
    $openApi = $generator->buildOpenApiArray(makeCollection([NullableData::class]));
    $props = $openApi['components']['schemas']['NullableData']['properties'];

    expect($props['nickname']['nullable'])->toBeTrue();
    expect($props['age']['nullable'])->toBeTrue();
});

it('generates schema for enum properties', function () {
    $openApi = $this->generator->buildOpenApiArray(makeCollection([EnumPropertyData::class]));
    $props = $openApi['components']['schemas']['EnumPropertyData']['properties'];

    expect($props['status'])->toHaveKey('enum');
    expect($props['status']['enum'])->toContain('active');
    expect($props['status']['enum'])->toContain('inactive');

    expect($props['priority'])->toHaveKey('enum');
    expect($props['priority']['enum'])->toContain(1);
    expect($props['priority']['enum'])->toContain(2);
});

it('generates $ref for nested Data class properties', function () {
    $openApi = $this->generator->buildOpenApiArray(makeCollection([NestedData::class]));
    $props = $openApi['components']['schemas']['NestedData']['properties'];

    expect($props['author']['$ref'])->toBe('#/components/schemas/SimpleData');
});

it('generates array schema with $ref items for collection properties', function () {
    $openApi = $this->generator->buildOpenApiArray(makeCollection([CollectionData::class]));
    $props = $openApi['components']['schemas']['CollectionData']['properties'];

    expect($props['items']['type'])->toBe('array');
    expect($props['items']['items']['$ref'])->toBe('#/components/schemas/ChildData');
});

it('skips static properties', function () {
    $openApi = $this->generator->buildOpenApiArray(makeCollection([SimpleData::class]));
    $propNames = array_keys($openApi['components']['schemas']['SimpleData']['properties']);

    foreach ($propNames as $name) {
        $ref = new ReflectionProperty(SimpleData::class, $name);
        expect($ref->isStatic())->toBeFalse();
    }
});

it('writes JSON output file', function () {
    $outputPath = sys_get_temp_dir() . '/whats-up-doc-test-' . uniqid();
    mkdir($outputPath, 0777, true);

    $this->generator->generateJson(makeCollection([SimpleData::class]), $outputPath);

    $file = $outputPath . '/documentation.json';
    expect(file_exists($file))->toBeTrue();

    $content = json_decode(file_get_contents($file), true);
    expect($content)->toHaveKey(SimpleData::class);

    // Clean up
    unlink($file);
    rmdir($outputPath);
});

it('writes OpenAPI JSON output file', function () {
    $outputPath = sys_get_temp_dir() . '/whats-up-doc-test-' . uniqid();
    mkdir($outputPath, 0777, true);

    $this->generator->generateOpenApi(makeCollection([SimpleData::class]), $outputPath);

    $file = $outputPath . '/openapi.json';
    expect(file_exists($file))->toBeTrue();

    $content = json_decode(file_get_contents($file), true);
    expect($content['openapi'])->toBe('3.1.0');
    expect($content['components']['schemas'])->toHaveKey('SimpleData');

    // Clean up
    unlink($file);
    rmdir($outputPath);
});

it('applies validation rules to OpenAPI schema', function () {
    $openApi = $this->generator->buildOpenApiArray(makeCollection([ValidationData::class]));
    $props = $openApi['components']['schemas']['ValidationData']['properties'];

    expect($props['email'])->toHaveKey('format');
    expect($props['email']['format'])->toBe('email');
});
