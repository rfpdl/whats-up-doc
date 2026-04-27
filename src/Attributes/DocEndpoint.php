<?php

declare(strict_types=1);

namespace Rfpdl\WhatsUpDoc\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class DocEndpoint
{
    public function __construct(
        public readonly string $summary = '',
        public readonly string $description = '',
        public readonly ?string $group = null,
        public readonly ?array $tags = null,
        public readonly bool $hidden = false,
    ) {}
}
