<?php

declare(strict_types=1);

namespace Rfpdl\WhatsUpDoc\Tests\Fixtures\Data;

use Spatie\LaravelData\Data;

/**
 * Data class with a nested Data object property.
 */
class NestedData extends Data
{
    public function __construct(
        public int $id,
        public string $title,
        public SimpleData $author,
        public ?SimpleData $reviewer = null,
    ) {}
}
