<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Data\UserData;
use App\Data\CreateUserData;
use Illuminate\Http\Controller;

/**
 * User management endpoints
 */
class UserController extends Controller
{
    /**
     * Get a user by ID
     */
    public function show(int $id): UserData
    {
        // Implementation would fetch user from database
        return new UserData(
            id: $id,
            name: 'John Doe',
            email: 'john@example.com',
            avatar: null
        );
    }

    /**
     * Create a new user
     */
    public function store(CreateUserData $userData): UserData
    {
        // Implementation would save user to database
        return new UserData(
            id: 123,
            name: $userData->name,
            email: $userData->email,
            avatar: $userData->avatar
        );
    }

    /**
     * Update an existing user
     */
    public function update(int $id, CreateUserData $userData): UserData
    {
        // Implementation would update user in database
        return new UserData(
            id: $id,
            name: $userData->name,
            email: $userData->email,
            avatar: $userData->avatar
        );
    }

    /**
     * Delete a user
     */
    public function destroy(int $id): void
    {
        // Implementation would delete user from database
    }
}
