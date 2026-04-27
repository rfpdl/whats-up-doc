<?php

declare(strict_types=1);

namespace Rfpdl\WhatsUpDoc\Tests\Fixtures\Data;

use Spatie\LaravelData\Data;

/**
 * Data class with array-of-Data properties using docblock typing.
 */
class CollectionData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        /** @var ChildData[] */
        public array $items,
        /** @var SimpleData[] */
        public array $contributors = [],
    ) {}
}
