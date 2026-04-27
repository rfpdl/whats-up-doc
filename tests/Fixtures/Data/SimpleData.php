<?php

declare(strict_types=1);

namespace Rfpdl\WhatsUpDoc\Tests\Fixtures\Data;

use Spatie\LaravelData\Data;

/**
 * A simple data class for testing basic property scanning.
 */
class SimpleData extends Data
{
    public function __construct(
        /** The unique identifier */
        public int $id,
        /** The user's full name */
        public string $name,
        /** The user's email address */
        public string $email,
        public bool $is_active = true,
    ) {}
}
