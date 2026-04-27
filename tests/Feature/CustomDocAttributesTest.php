<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Rfpdl\WhatsUpDoc\Services\DataClassScanner;
use Rfpdl\WhatsUpDoc\Services\DocumentationGenerator;
use Rfpdl\WhatsUpDoc\Tests\Fixtures\Controllers\WebhookController;

uses(Rfpdl\WhatsUpDoc\Tests\TestCase::class);

function setupWebhookRoutes(): void
{
    Route::prefix('api')->group(function () {
        Route::post('webhooks/stripe', [WebhookController::class, 'handleStripe'])->name('webhooks.stripe');
        Route::get('health', [WebhookController::class, 'health'])->name('health');
        Route::get('internal', [WebhookController::class, 'internal'])->name('internal');
    });
}

function buildOpenApi(): array
{
    setupWebhookRoutes();

    config(['whats-up-doc.scan_paths' => []]);
    config(['whats-up-doc.route_prefixes' => ['api']]);

    $scanner = app(DataClassScanner::class);
    $dataClasses = $scanner->scanClasses();
    $generator = app(DocumentationGenerator::class);

    return $generator->buildOpenApiArray($dataClasses);
}

it('includes endpoint with DocEndpoint attribute even without Data classes', function () {
    $openApi = buildOpenApi();
    $paths = $openApi['paths'];

    expect($paths)->toHaveKey('/api/webhooks/stripe');
    expect($paths)->toHaveKey('/api/health');
});

it('excludes endpoint with hidden DocEndpoint', function () {
    $openApi = buildOpenApi();
    $paths = $openApi['paths'];

    expect($paths)->not->toHaveKey('/api/internal');
});

it('uses DocEndpoint summary as operation summary', function () {
    $openApi = buildOpenApi();

    $operation = $openApi['paths']['/api/webhooks/stripe']['post'];
    expect($operation['summary'])->toBe('Receive Stripe webhook');
});

it('uses DocEndpoint group as tag', function () {
    $openApi = buildOpenApi();

    $operation = $openApi['paths']['/api/webhooks/stripe']['post'];
    expect($operation['tags'])->toContain('Webhooks');
});

it('uses DocEndpoint custom tags', function () {
    $openApi = buildOpenApi();

    $operation = $openApi['paths']['/api/health']['get'];
    expect($operation['tags'])->toContain('System');
});

it('creates parameters from DocParam attributes', function () {
    $openApi = buildOpenApi();

    $operation = $openApi['paths']['/api/webhooks/stripe']['post'];
    $params = $operation['parameters'] ?? [];

    $signatureParam = collect($params)->firstWhere('name', 'signature');
    expect($signatureParam)->not->toBeNull();
    expect($signatureParam['in'])->toBe('header');
    expect($signatureParam['required'])->toBeTrue();
    expect($signatureParam['description'])->toBe('Stripe signature');
    expect($signatureParam['schema']['type'])->toBe('string');
});

it('creates request body from DocBody attribute', function () {
    $openApi = buildOpenApi();

    $operation = $openApi['paths']['/api/webhooks/stripe']['post'];
    expect($operation)->toHaveKey('requestBody');

    $body = $operation['requestBody'];
    expect($body['required'])->toBeTrue();
    expect($body['content']['application/json']['schema']['type'])->toBe('object');
    expect($body['content']['application/json']['schema']['properties'])->toHaveKey('event');
});

it('creates responses from DocResponse attributes', function () {
    $openApi = buildOpenApi();

    $operation = $openApi['paths']['/api/webhooks/stripe']['post'];
    expect($operation['responses'])->toHaveKey('200');
    expect($operation['responses'])->toHaveKey('400');

    expect($operation['responses']['200']['description'])->toBe('Webhook processed');
    expect($operation['responses']['400']['description'])->toBe('Invalid payload');
});

it('includes response schema from DocResponse', function () {
    $openApi = buildOpenApi();

    $operation = $openApi['paths']['/api/health']['get'];
    $response = $operation['responses']['200'];

    expect($response['description'])->toBe('Service is healthy');
    expect($response['content']['application/json']['schema']['properties'])->toHaveKey('status');
});
