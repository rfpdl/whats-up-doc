<?php

declare(strict_types=1);

namespace Rfpdl\WhatsUpDoc\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class DocParam
{
    public function __construct(
        public readonly string $name,
        public readonly string $type = 'string',
        public readonly string $in = 'query',
        public readonly bool $required = false,
        public readonly ?string $description = null,
        public readonly mixed $example = null,
    ) {}
}
