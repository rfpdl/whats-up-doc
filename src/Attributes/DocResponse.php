<?php

declare(strict_types=1);

namespace Rfpdl\WhatsUpDoc\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class DocResponse
{
    public function __construct(
        public readonly int $status = 200,
        public readonly ?string $description = null,
        public readonly ?array $schema = null,
        public readonly ?string $ref = null,
    ) {}
}
