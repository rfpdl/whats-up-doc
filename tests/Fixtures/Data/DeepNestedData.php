<?php

declare(strict_types=1);

namespace Rfpdl\WhatsUpDoc\Tests\Fixtures\Data;

use Spatie\LaravelData\Data;

/**
 * Three-level nesting: DeepNestedData -> NestedData -> SimpleData
 */
class DeepNestedData extends Data
{
    public function __construct(
        public int $id,
        public NestedData $content,
        /** @var NestedData[] */
        public array $related = [],
    ) {}
}
