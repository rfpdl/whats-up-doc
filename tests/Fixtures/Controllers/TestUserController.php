<?php

declare(strict_types=1);

namespace Rfpdl\WhatsUpDoc\Tests\Fixtures\Controllers;

use Illuminate\Routing\Controller;
use Rfpdl\WhatsUpDoc\Tests\Feature\TestUserData;

class TestUserController extends Controller
{
    /**
     * Get a user by ID
     */
    public function show(int $id): TestUserData
    {
        return new TestUserData($id, 'Test User', 'test@example.com');
    }

    /**
     * Create a new user
     */
    public function store(TestUserData $userData): TestUserData
    {
        return $userData;
    }
}
