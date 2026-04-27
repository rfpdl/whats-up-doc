<?php

declare(strict_types=1);

namespace Rfpdl\WhatsUpDoc\Tests\Fixtures\Enums;

enum PriorityEnum: int
{
    case Low = 1;
    case Medium = 2;
    case High = 3;
    case Critical = 4;
}
