<?php

declare(strict_types=1);

namespace Rfpdl\WhatsUpDoc\Tests\Fixtures\Data;

use Spatie\LaravelData\Data;

class NullableData extends Data
{
    public function __construct(
        public int $id,
        public ?string $nickname = null,
        public ?int $age = null,
        public string $role = 'user',
        public bool $verified = false,
    ) {}
}
