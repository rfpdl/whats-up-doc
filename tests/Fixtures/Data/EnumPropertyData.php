<?php

declare(strict_types=1);

namespace Rfpdl\WhatsUpDoc\Tests\Fixtures\Data;

use Rfpdl\WhatsUpDoc\Tests\Fixtures\Enums\ColorEnum;
use Rfpdl\WhatsUpDoc\Tests\Fixtures\Enums\PriorityEnum;
use Rfpdl\WhatsUpDoc\Tests\Fixtures\Enums\StatusEnum;
use Spatie\LaravelData\Data;

class EnumPropertyData extends Data
{
    public function __construct(
        public int $id,
        public StatusEnum $status,
        public PriorityEnum $priority,
        public ?ColorEnum $color = null,
    ) {}
}
