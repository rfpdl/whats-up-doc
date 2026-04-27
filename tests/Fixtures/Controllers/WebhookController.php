<?php

declare(strict_types=1);

namespace Rfpdl\WhatsUpDoc\Tests\Fixtures\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Rfpdl\WhatsUpDoc\Attributes\DocBody;
use Rfpdl\WhatsUpDoc\Attributes\DocEndpoint;
use Rfpdl\WhatsUpDoc\Attributes\DocParam;
use Rfpdl\WhatsUpDoc\Attributes\DocResponse;

class WebhookController
{
    #[DocEndpoint(summary: 'Receive Stripe webhook', group: 'Webhooks')]
    #[DocParam(name: 'signature', in: 'header', type: 'string', required: true, description: 'Stripe signature')]
    #[DocBody(schema: ['type' => 'object', 'properties' => ['event' => ['type' => 'string'], 'data' => ['type' => 'object']]])]
    #[DocResponse(status: 200, description: 'Webhook processed')]
    #[DocResponse(status: 400, description: 'Invalid payload')]
    public function handleStripe(Request $request): JsonResponse
    {
        return response()->json(['ok' => true]);
    }

    #[DocEndpoint(summary: 'Health check', tags: ['System'])]
    #[DocResponse(status: 200, description: 'Service is healthy', schema: ['type' => 'object', 'properties' => ['status' => ['type' => 'string']]])]
    public function health(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    #[DocEndpoint(hidden: true)]
    public function internal(): JsonResponse
    {
        return response()->json(['internal' => true]);
    }
}
