<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

/**
 * User data transfer object
 */
class UserData extends Data
{
    public function __construct(
        /** User's unique identifier */
        public int $id,
        
        /** User's full name */
        public string $name,
        
        /** User's email address */
        public string $email,
        
        /** User's profile picture URL */
        public ?string $avatar = null,
    ) {}
}
