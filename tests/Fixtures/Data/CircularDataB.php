<?php

declare(strict_types=1);

namespace Rfpdl\WhatsUpDoc\Tests\Fixtures\Data;

use Spatie\LaravelData\Data;

class CircularDataB extends Data
{
    public function __construct(
        public int $id,
        public ?CircularDataA $partner = null,
    ) {}
}
