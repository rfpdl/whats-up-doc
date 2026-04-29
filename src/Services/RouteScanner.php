<?php

declare(strict_types=1);

namespace Rfpdl\WhatsUpDoc\Services;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use Rfpdl\WhatsUpDoc\Attributes\DocBody;
use Rfpdl\WhatsUpDoc\Attributes\DocEndpoint;
use Rfpdl\WhatsUpDoc\Attributes\DocParam;
use Rfpdl\WhatsUpDoc\Attributes\DocResponse;
use Rfpdl\WhatsUpDoc\Support\DocblockParser;
use Rfpdl\WhatsUpDoc\Support\ErrorCollector;
use Rfpdl\WhatsUpDoc\Support\ScanError;
use Rfpdl\WhatsUpDoc\Support\TypeResolver;

class RouteScanner
{
    private ErrorCollector $errors;

    public function __construct(
        private readonly Router $router,
        private readonly DocblockParser $docblockParser,
        private readonly TypeResolver $typeResolver,
    ) {
        $this->errors = new ErrorCollector();
    }

    /**
     * Get errors from the last scan
     */
    public function getErrors(): ErrorCollector
    {
        return $this->errors;
    }

    /**
     * Scan routes for Laravel Data class usage
     */
    public function scanRoutes(Collection $dataClasses): Collection
    {
        $this->errors->clear();

        if (!config('whats-up-doc.routes.enabled', true)) {
            return collect();
        }

        $routes = collect();
        $routePrefixes = config('whats-up-doc.route_prefixes', ['api']);
        $dataClassNames = $dataClasses->pluck('class')->toArray();

        foreach ($this->router->getRoutes() as $route) {
            if (!$this->shouldIncludeRoute($route, $routePrefixes)) {
                continue;
            }

            try {
                $routeData = $this->analyzeRoute($route, $dataClassNames);

                if ($routeData !== null) {
                    $routes->push($routeData);
                }
            } catch (\Throwable $e) {
                $this->errors->add(ScanError::routeAnalysisFailed($route->uri(), $e));
            }
        }

        // Group by prefix if configured
        if (config('whats-up-doc.routes.group_by_prefix', true)) {
            return $routes->sortBy('uri')->groupBy(function ($route) {
                $segments = explode('/', $route['uri']);
                return $segments[0] ?? 'other';
            })->flatten(1);
        }

        return $routes->sortBy('uri');
    }

    /**
     * Determine if a route should be included in documentation
     */
    private function shouldIncludeRoute(Route $route, array $routePrefixes): bool
    {
        $uri = $route->uri();

        // Check prefixes efficiently using Str::startsWith
        if (!empty($routePrefixes)) {
            $hasValidPrefix = Str::startsWith($uri, $routePrefixes);
            if (!$hasValidPrefix) {
                return false;
            }
        }

        // Skip closure routes (can't reflect them)
        $action = $route->getActionName();
        if (!is_string($action) || $action === 'Closure') {
            return false;
        }

        // Must have a controller
        if (!isset($route->getAction()['controller'])) {
            return false;
        }

        return true;
    }

    /**
     * Analyze a single route for Data class usage or custom doc attributes
     */
    private function analyzeRoute(Route $route, array $dataClassNames): ?array
    {
        $action = $route->getAction();
        $controllerAction = $action['controller'];

        if (!str_contains($controllerAction, '@')) {
            $controllerClass = $controllerAction;
            $method = '__invoke';
        } else {
            [$controllerClass, $method] = explode('@', $controllerAction);
        }

        if (!class_exists($controllerClass)) {
            return null;
        }

        $controllerReflection = new ReflectionClass($controllerClass);

        if (!$controllerReflection->hasMethod($method)) {
            return null;
        }

        $methodReflection = $controllerReflection->getMethod($method);

        $docEndpoint = $this->getDocEndpoint($methodReflection);
        if ($docEndpoint && $docEndpoint->hidden) {
            return null;
        }

        $routeInfo = [
            'uri' => $route->uri(),
            'methods' => $this->filterMethods($route->methods()),
            'name' => $route->getName(),
            'controller' => $controllerClass,
            'action' => $method,
            'parameters' => $this->extractRouteParameters($route, $methodReflection),
            'request_data' => null,
            'response_data' => null,
            'description' => $docEndpoint?->summary ?: $this->extractMethodDescription($methodReflection),
            'middleware' => config('whats-up-doc.routes.include_middleware', false)
                ? $route->middleware()
                : [],
            'custom_params' => $this->getDocParams($methodReflection),
            'custom_body' => $this->getDocBody($methodReflection),
            'custom_responses' => $this->getDocResponses($methodReflection),
            'doc_endpoint' => $docEndpoint,
        ];

        $requestData = $this->findRequestDataClass($methodReflection, $dataClassNames);
        if ($requestData) {
            $routeInfo['request_data'] = $requestData;
        }

        $responseData = $this->findResponseDataClass($methodReflection, $dataClassNames);
        if ($responseData) {
            $routeInfo['response_data'] = $responseData;
        }

        $hasDataClasses = $routeInfo['request_data'] !== null || $routeInfo['response_data'] !== null;
        $hasCustomDocs = $docEndpoint !== null;
        $hasResponses = !empty($routeInfo['custom_responses']);

        // Keep routes that either:
        // 1. Have Data classes
        // 2. Have custom DocEndpoint
        // 3. Have custom responses (DocResponse attributes)
        // 4. Or are explicitly included via config
        if (!$hasDataClasses && !$hasCustomDocs && !$hasResponses) {
            return null;
        }

        return $routeInfo;
    }

    private function getDocEndpoint(ReflectionMethod $method): ?DocEndpoint
    {
        $attrs = $method->getAttributes(DocEndpoint::class);

        return !empty($attrs) ? $attrs[0]->newInstance() : null;
    }

    private function getDocParams(ReflectionMethod $method): array
    {
        $params = [];

        foreach ($method->getAttributes(DocParam::class) as $attr) {
            $params[] = $attr->newInstance();
        }

        return $params;
    }

    private function getDocBody(ReflectionMethod $method): ?DocBody
    {
        $attrs = $method->getAttributes(DocBody::class);

        return !empty($attrs) ? $attrs[0]->newInstance() : null;
    }

    private function getDocResponses(ReflectionMethod $method): array
    {
        $responses = [];

        foreach ($method->getAttributes(DocResponse::class) as $attr) {
            $responses[] = $attr->newInstance();
        }

        return $responses;
    }

    /**
     * Filter out unwanted HTTP methods
     */
    private function filterMethods(array $methods): array
    {
        // Remove HEAD as it's automatically added and not useful for docs
        return array_values(array_filter($methods, fn($m) => $m !== 'HEAD'));
    }

    /**
     * Find the request Data class from method parameters
     */
    private function findRequestDataClass(ReflectionMethod $method, array $dataClassNames): ?string
    {
        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type === null || $type->isBuiltin()) {
                continue;
            }

            $typeName = $type->getName();

            // Check if it's one of the known Data classes
            if (in_array($typeName, $dataClassNames)) {
                return $typeName;
            }

            // Check if it extends Data (might not be in our scanned paths)
            if ($this->typeResolver->isDataClass($typeName)) {
                return $typeName;
            }
        }

        return null;
    }

    /**
     * Find the response Data class from return type
     */
    private function findResponseDataClass(ReflectionMethod $method, array $dataClassNames): ?string
    {
        $returnType = $method->getReturnType();

        if ($returnType === null || $returnType->isBuiltin()) {
            // Check docblock for @return annotation
            $docblock = $this->docblockParser->parseMethod($method);
            if ($docblock['return'] && isset($docblock['return']['type'])) {
                $returnTypeName = ltrim($docblock['return']['type'], '\\');
                if (in_array($returnTypeName, $dataClassNames) || $this->typeResolver->isDataClass($returnTypeName)) {
                    return $returnTypeName;
                }
            }
            return null;
        }

        $typeName = $returnType->getName();

        // Check if it's one of the known Data classes
        if (in_array($typeName, $dataClassNames)) {
            return $typeName;
        }

        // Check if it extends Data
        if ($this->typeResolver->isDataClass($typeName)) {
            return $typeName;
        }

        return null;
    }

    /**
     * Extract route parameters with improved type inference
     */
    private function extractRouteParameters(Route $route, ReflectionMethod $method): array
    {
        $parameters = [];

        // Extract parameters from URI pattern
        preg_match_all('/\{([^}]+)\}/', $route->uri(), $matches);

        // Build a map of method parameter types
        $methodParamTypes = [];
        foreach ($method->getParameters() as $param) {
            $type = $param->getType();
            if ($type && $type->isBuiltin()) {
                $methodParamTypes[$param->getName()] = $type->getName();
            }
        }

        foreach ($matches[1] as $parameter) {
            $isOptional = str_ends_with($parameter, '?');
            $paramName = rtrim($parameter, '?');

            // Try to get type from method parameter first
            $type = $methodParamTypes[$paramName] ?? $this->guessParameterType($paramName);

            $parameters[] = [
                'name' => $paramName,
                'required' => !$isOptional,
                'type' => $type,
                'in' => 'path',
            ];
        }

        return $parameters;
    }

    /**
     * Guess parameter type based on naming conventions
     */
    private function guessParameterType(string $paramName): string
    {
        // ID-like parameters
        if ($paramName === 'id' || str_ends_with($paramName, '_id') || str_ends_with($paramName, 'Id')) {
            return 'integer';
        }

        // UUID-like parameters
        if (str_contains($paramName, 'uuid') || str_contains($paramName, 'Uuid')) {
            return 'string';
        }

        // Slug-like parameters
        if (str_contains($paramName, 'slug') || str_contains($paramName, 'Slug')) {
            return 'string';
        }

        // Token-like parameters
        if (str_contains($paramName, 'token') || str_contains($paramName, 'Token')) {
            return 'string';
        }

        // Default to string
        return 'string';
    }

    /**
     * Extract method description from docblock
     */
    private function extractMethodDescription(ReflectionMethod $method): string
    {
        $docblock = $this->docblockParser->parseMethod($method);
        return $docblock['description'] ?? '';
    }
}
