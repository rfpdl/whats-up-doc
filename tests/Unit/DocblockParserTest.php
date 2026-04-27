<?php

declare(strict_types=1);

use Rfpdl\WhatsUpDoc\Support\DocblockParser;

beforeEach(function () {
    $this->parser = new DocblockParser();
});

// --- Description extraction ---

test('extracts multiline description', function () {
    $doc = <<<'DOC'
    /**
     * This is a multiline
     * description of the class.
     *
     * @param string $name
     */
    DOC;

    expect($this->parser->extractDescription($doc))->toBe('This is a multiline description of the class.');
});

test('extracts inline description', function () {
    $doc = '/** A simple inline description */';

    expect($this->parser->extractDescription($doc))->toBe('A simple inline description');
});

test('extracts property inline description', function () {
    $doc = '/** The user email address */';

    expect($this->parser->extractPropertyDescription($doc))->toBe('The user email address');
});

test('returns empty string for empty docblock', function () {
    expect($this->parser->extractDescription('/** */'))->toBe('');
});

// --- Tag extraction ---

test('extracts single param', function () {
    $doc = <<<'DOC'
    /**
     * @param string $name The user name
     */
    DOC;

    $params = $this->parser->extractParams($doc);

    expect($params)->toHaveKey('name')
        ->and($params['name']['type'])->toBe('string')
        ->and($params['name']['description'])->toBe('The user name');
});

test('extracts multiple params', function () {
    $doc = "/**\n * @param string \$name\n * @param int \$age\n */";

    $params = $this->parser->extractParams($doc);

    expect($params)->toHaveCount(2)
        ->and($params)->toHaveKeys(['name', 'age']);
});

test('extracts return type', function () {
    $doc = <<<'DOC'
    /**
     * @return UserData The user object
     */
    DOC;

    $return = $this->parser->extractReturn($doc);

    expect($return)->not->toBeNull()
        ->and($return['type'])->toBe('UserData')
        ->and($return['description'])->toBe('The user object');
});

test('extracts return type without description', function () {
    $doc = "/**\n * @return void\n */";

    $return = $this->parser->extractReturn($doc);

    expect($return['type'])->toBe('void')
        ->and($return['description'])->toBe('');
});

test('returns null when no return tag', function () {
    $doc = '/** No return here */';

    expect($this->parser->extractReturn($doc))->toBeNull();
});

test('extracts var tag', function () {
    $doc = "/** @var PageData[] The pages */";

    $var = $this->parser->extractVar($doc);

    expect($var)->not->toBeNull()
        ->and($var['type'])->toBe('PageData[]')
        ->and($var['description'])->toContain('The pages');
});

test('extracts example tag', function () {
    $doc = <<<'DOC'
    /**
     * @example "john@example.com"
     */
    DOC;

    expect($this->parser->extractExample($doc))->toBe('"john@example.com"');
});

test('returns null when no example', function () {
    $doc = '/** No example */';
    expect($this->parser->extractExample($doc))->toBeNull();
});

test('extracts deprecated with message', function () {
    $doc = <<<'DOC'
    /**
     * @deprecated Use NewData instead
     */
    DOC;

    $deprecated = $this->parser->extractDeprecated($doc);

    expect($deprecated)->not->toBeNull()
        ->and($deprecated['deprecated'])->toBeTrue()
        ->and($deprecated['message'])->toBe('Use NewData instead');
});

test('extracts deprecated without message', function () {
    $doc = <<<'DOC'
    /**
     * @deprecated
     */
    DOC;

    $deprecated = $this->parser->extractDeprecated($doc);

    expect($deprecated)->not->toBeNull()
        ->and($deprecated['deprecated'])->toBeTrue();
});

test('extracts multiple see tags', function () {
    $doc = <<<'DOC'
    /**
     * @see UserData
     * @see https://example.com
     */
    DOC;

    $sees = $this->parser->extractSee($doc);

    expect($sees)->toHaveCount(2)
        ->and($sees[0])->toBe('UserData')
        ->and($sees[1])->toBe('https://example.com');
});

test('extracts throws tags', function () {
    $doc = <<<'DOC'
    /**
     * @throws \RuntimeException When something fails
     * @throws \InvalidArgumentException
     */
    DOC;

    $throws = $this->parser->extractThrows($doc);

    expect($throws)->toHaveCount(2)
        ->and($throws[0]['type'])->toBe('\\RuntimeException')
        ->and($throws[0]['description'])->toBe('When something fails');
});

test('extracts all tags as key-value pairs', function () {
    $doc = <<<'DOC'
    /**
     * @param string $name
     * @return void
     * @deprecated
     */
    DOC;

    $tags = $this->parser->extractAllTags($doc);

    expect($tags)->toHaveKeys(['param', 'return', 'deprecated']);
});

// --- Type string parsing ---

test('parseTypeString handles nullable shorthand', function () {
    $result = $this->parser->parseTypeString('?string');

    expect($result['nullable'])->toBeTrue()
        ->and($result['types'])->toBe(['string']);
});

test('parseTypeString handles array notation', function () {
    $result = $this->parser->parseTypeString('UserData[]');

    expect($result['isArray'])->toBeTrue()
        ->and($result['types'])->toBe(['UserData']);
});

test('parseTypeString handles union types', function () {
    $result = $this->parser->parseTypeString('string|int|null');

    expect($result['nullable'])->toBeTrue()
        ->and($result['types'])->toBe(['string', 'int']);
});

test('extractGenericType matches generic pattern', function () {
    $result = $this->parser->extractGenericType('Collection<UserData>');

    expect($result)->not->toBeNull()
        ->and($result['wrapper'])->toBe('Collection')
        ->and($result['inner'])->toBe('UserData');
});

test('extractGenericType returns null for non-generic', function () {
    expect($this->parser->extractGenericType('string'))->toBeNull();
});

// --- Composite parsing ---

test('parseClass returns correct structure', function () {
    $reflection = new ReflectionClass(Rfpdl\WhatsUpDoc\Tests\Fixtures\Data\SimpleData::class);
    $result = $this->parser->parseClass($reflection);

    expect($result)->toHaveKeys(['description', 'tags', 'properties', 'deprecated', 'see'])
        ->and($result['description'])->toContain('simple data class');
});

test('parseClass returns empty for no docblock', function () {
    $reflection = new ReflectionClass(Rfpdl\WhatsUpDoc\Tests\Fixtures\Data\ChildData::class);
    $result = $this->parser->parseClass($reflection);

    expect($result['description'])->toBe('')
        ->and($result['tags'])->toBe([])
        ->and($result['deprecated'])->toBeNull();
});

test('parseProperty returns correct structure', function () {
    $reflection = new ReflectionClass(Rfpdl\WhatsUpDoc\Tests\Fixtures\Data\SimpleData::class);
    $property = $reflection->getProperty('id');
    $result = $this->parser->parseProperty($property);

    expect($result)->toHaveKeys(['description', 'var', 'example', 'deprecated', 'tags'])
        ->and($result['description'])->toContain('unique identifier');
});

test('parseMethod returns correct structure', function () {
    $reflection = new ReflectionClass(Rfpdl\WhatsUpDoc\Tests\Fixtures\Controllers\UserController::class);
    $method = $reflection->getMethod('show');
    $result = $this->parser->parseMethod($method);

    expect($result)->toHaveKeys(['description', 'params', 'return', 'throws', 'deprecated', 'tags'])
        ->and($result['description'])->toContain('user by ID');
});
