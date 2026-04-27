<?php

declare(strict_types=1);

namespace Rfpdl\WhatsUpDoc\Tests\Fixtures\Data;

use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class ValidationData extends Data
{
    public function __construct(
        #[Required]
        public string $name,
        #[Required, Email]
        public string $email,
        #[Min(1), Max(150)]
        public int $age,
        #[Min(3), Max(255)]
        public string $bio = '',
    ) {}
}
