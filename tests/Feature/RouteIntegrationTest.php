<?php

declare(strict_types=1);

namespace Rfpdl\WhatsUpDoc\Tests\Feature;

use Orchestra\Testbench\TestCase;
use Rfpdl\WhatsUpDoc\WhatsUpDocServiceProvider;
use Rfpdl\WhatsUpDoc\Services\RouteScanner;
use Illuminate\Support\Facades\Route;
use Spatie\LaravelData\Data;

class TestUserData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
    ) {}
}

class RouteIntegrationTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            WhatsUpDocServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up test routes
        Route::get('api/users/{id}', function (int $id): TestUserData {
            return new TestUserData($id, 'Test User', 'test@example.com');
        });

        Route::post('api/users', function (TestUserData $userData): TestUserData {
            return $userData;
        });
    }

    public function test_route_scanner_detects_data_routes(): void
    {
        $dataClasses = collect([
            [
                'class' => TestUserData::class,
                'reflection' => new \ReflectionClass(TestUserData::class),
                'file' => __FILE__,
            ]
        ]);

        $routeScanner = app(RouteScanner::class);
        $routes = $routeScanner->scanRoutes($dataClasses);

        $this->assertGreaterThan(0, $routes->count());
        
        // Check if our test routes are detected
        $routeUris = $routes->pluck('uri')->toArray();
        $this->assertContains('api/users/{id}', $routeUris);
        $this->assertContains('api/users', $routeUris);
    }

    public function test_route_scanner_identifies_request_and_response_data(): void
    {
        $dataClasses = collect([
            [
                'class' => TestUserData::class,
                'reflection' => new \ReflectionClass(TestUserData::class),
                'file' => __FILE__,
            ]
        ]);

        $routeScanner = app(RouteScanner::class);
        $routes = $routeScanner->scanRoutes($dataClasses);

        $postRoute = $routes->firstWhere('uri', 'api/users');
        $this->assertNotNull($postRoute);
        $this->assertEquals(TestUserData::class, $postRoute['request_data']);
        $this->assertEquals(TestUserData::class, $postRoute['response_data']);

        $getRoute = $routes->firstWhere('uri', 'api/users/{id}');
        $this->assertNotNull($getRoute);
        $this->assertNull($getRoute['request_data']);
        $this->assertEquals(TestUserData::class, $getRoute['response_data']);
    }
}
