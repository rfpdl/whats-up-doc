<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

/**
 * Data for creating a new user
 */
class CreateUserData extends Data
{
    public function __construct(
        /** User's full name */
        public string $name,
        
        /** User's email address */
        public string $email,
        
        /** User's profile picture URL */
        public ?string $avatar = null,
    ) {}
}
