<?php

declare(strict_types=1);

namespace Rfpdl\WhatsUpDoc\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class DocBody
{
    public function __construct(
        public readonly array $schema,
        public readonly ?string $description = null,
        public readonly bool $required = true,
        public readonly ?string $mediaType = 'application/json',
    ) {}
}
