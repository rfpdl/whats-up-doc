<?php

declare(strict_types=1);

namespace Rfpdl\LaravelDataDoc\Services;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use Spatie\LaravelData\Data;

class RouteScanner
{
    public function __construct(
        private Router $router
    ) {}

    public function scanRoutes(Collection $dataClasses): Collection
    {
        // Check if route documentation is enabled
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

            $routeData = $this->analyzeRoute($route, $dataClassNames);
            
            if ($routeData) {
                $routes->push($routeData);
            }
        }

        return $routes->sortBy('uri');
    }

    private function shouldIncludeRoute(Route $route, array $routePrefixes): bool
    {
        $uri = $route->uri();
        
        // Skip routes without prefixes if prefixes are specified
        if (!empty($routePrefixes)) {
            $hasValidPrefix = false;
            foreach ($routePrefixes as $prefix) {
                if (str_starts_with($uri, $prefix)) {
                    $hasValidPrefix = true;
                    break;
                }
            }
            if (!$hasValidPrefix) {
                return false;
            }
        }

        // Skip closure routes
        if (!is_string($route->getActionName())) {
            return false;
        }

        return true;
    }

    private function analyzeRoute(Route $route, array $dataClassNames): ?array
    {
        $action = $route->getAction();
        
        if (!isset($action['controller'])) {
            return null;
        }

        [$controllerClass, $method] = explode('@', $action['controller']);

        try {
            $controllerReflection = new ReflectionClass($controllerClass);
            $methodReflection = $controllerReflection->getMethod($method);
            
            $routeInfo = [
                'uri' => $route->uri(),
                'methods' => $route->methods(),
                'name' => $route->getName(),
                'controller' => $controllerClass,
                'action' => $method,
                'parameters' => $this->getRouteParameters($route),
                'request_data' => null,
                'response_data' => null,
                'description' => $this->getMethodDescription($methodReflection),
            ];

            // Analyze method parameters for request Data classes
            foreach ($methodReflection->getParameters() as $parameter) {
                $parameterType = $parameter->getType();
                if ($parameterType && !$parameterType->isBuiltin()) {
                    $typeName = $parameterType->getName();
                    if (in_array($typeName, $dataClassNames)) {
                        $routeInfo['request_data'] = $typeName;
                        break;
                    }
                }
            }

            // Analyze return type for response Data classes
            $returnType = $methodReflection->getReturnType();
            if ($returnType && !$returnType->isBuiltin()) {
                $returnTypeName = $returnType->getName();
                if (in_array($returnTypeName, $dataClassNames)) {
                    $routeInfo['response_data'] = $returnTypeName;
                }
            }

            // Only include routes that use Laravel Data classes
            if ($routeInfo['request_data'] || $routeInfo['response_data']) {
                return $routeInfo;
            }

        } catch (\Throwable $e) {
            // Skip routes that can't be analyzed
            return null;
        }

        return null;
    }

    private function getRouteParameters(Route $route): array
    {
        $parameters = [];
        
        // Extract route parameters from URI pattern
        preg_match_all('/\{([^}]+)\}/', $route->uri(), $matches);
        
        foreach ($matches[1] as $parameter) {
            $isOptional = str_ends_with($parameter, '?');
            $paramName = rtrim($parameter, '?');
            
            $parameters[] = [
                'name' => $paramName,
                'required' => !$isOptional,
                'type' => $this->guessParameterType($paramName),
            ];
        }

        return $parameters;
    }

    private function guessParameterType(string $paramName): string
    {
        // Common parameter naming conventions
        if (str_ends_with($paramName, '_id') || $paramName === 'id') {
            return 'integer';
        }
        
        if (in_array($paramName, ['slug', 'token', 'uuid'])) {
            return 'string';
        }

        return 'string'; // Default to string
    }

    private function getMethodDescription(ReflectionMethod $method): string
    {
        $docComment = $method->getDocComment();
        
        if (!$docComment) {
            return '';
        }

        // Extract description from docblock
        preg_match('/\/\*\*\s*\n\s*\*\s*(.+?)(?:\n\s*\*\s*@|\n\s*\*\/)/s', $docComment, $matches);
        
        if (isset($matches[1])) {
            // Clean up the description
            $description = preg_replace('/\n\s*\*\s*/', ' ', $matches[1]);
            return trim($description);
        }

        return '';
    }
}
