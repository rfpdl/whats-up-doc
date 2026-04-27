<?php

declare(strict_types=1);

namespace Rfpdl\WhatsUpDoc\Tests\Fixtures\Controllers;

use Illuminate\Routing\Controller;
use Rfpdl\WhatsUpDoc\Tests\Fixtures\Data\SimpleData;

class UserController extends Controller
{
    /**
     * List all users
     */
    public function index(): array
    {
        return [];
    }

    /**
     * Get a user by ID
     */
    public function show(int $id): SimpleData
    {
        return new SimpleData($id, 'Test', 'test@example.com');
    }

    /**
     * Create a new user
     */
    public function store(SimpleData $data): SimpleData
    {
        return $data;
    }
}
