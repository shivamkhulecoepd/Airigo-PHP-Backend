<?php

namespace App\Core\Http\Router;

use Psr\Http\Message\ServerRequestInterface;
use App\Core\Utils\ResponseBuilder;
use App\Core\Cache\CacheManager;

class Router
{
    private array $routes = [];
    private array $globalMiddlewares = [];
    private array $routeCache = [];
    private CacheManager $cache;
    private bool $enableRouteCaching = true;
    
    private const ROUTE_CACHE_TTL = 300; // 5 minutes
    private const ROUTE_CACHE_SIZE = 1000;
    
    public function __construct()
    {
        $this->cache = CacheManager::getInstance();
    }

    public function get(string $path, $handler): Route
    {
        return $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, $handler): Route
    {
        return $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, $handler): Route
    {
        return $this->addRoute('PUT', $path, $handler);
    }

    public function patch(string $path, $handler): Route
    {
        return $this->addRoute('PATCH', $path, $handler);
    }

    public function delete(string $path, $handler): Route
    {
        return $this->addRoute('DELETE', $path, $handler);
    }

    public function addRoute(string $method, string $path, $handler): Route
    {
        $route = new Route($method, $path, $handler);
        $this->routes[] = $route;
        return $route;
    }

    public function addGlobalMiddleware($middleware): void
    {
        $this->globalMiddlewares[] = $middleware;
    }

    public function dispatch(ServerRequestInterface $request)
    {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();
        
        // Normalize path by removing script name if present in path
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        if (!empty($scriptName) && strpos($path, $scriptName) === 0) {
            $path = substr($path, strlen($scriptName));
            if (empty($path)) {
                $path = '/';
            }
        }
        
        // Also handle case where path includes public directory
        $publicDir = '/public';
        if (strpos($path, $publicDir . '/') === 0) {
            $path = substr($path, strlen($publicDir));
            if (empty($path)) {
                $path = '/';
            }
        }
        
        // Generate cache key for route lookup
        $cacheKey = "route_lookup:{$method}:{$path}";
        
        // Try to get cached route match
        if ($this->enableRouteCaching) {
            $cachedMatch = $this->cache->get($cacheKey);
            if ($cachedMatch !== null) {
                $route = $this->routes[$cachedMatch['route_index']] ?? null;
                if ($route) {
                    return $this->handleRoute($route, $request, $path, $cachedMatch['params']);
                }
            }
        }

        // Find matching route
        foreach ($this->routes as $index => $route) {
            if ($route->method === $method && $this->matchRoute($route->path, $path)) {
                // Cache the route match
                if ($this->enableRouteCaching) {
                    $routeParams = $this->extractParameters($route->path, $path);
                    $cacheData = [
                        'route_index' => $index,
                        'params' => $routeParams
                    ];
                    $this->cache->set($cacheKey, $cacheData, self::ROUTE_CACHE_TTL);
                    return $this->handleRoute($route, $request, $path, $routeParams);
                }
                return $this->handleRoute($route, $request, $path);
            }
        }

        return ResponseBuilder::notFound(['message' => 'Route not found']);
    }

    private function matchRoute(string $routePattern, string $requestPath): bool
    {
        // Convert route pattern to regex
        $pattern = preg_quote($routePattern, '/');
        $pattern = preg_replace('/\\\{([^\/]+)\\\}/', '([^\/]+)', $pattern);
        $pattern = '/^' . $pattern . '$/';
        
        return preg_match($pattern, $requestPath);
    }

    private function handleRoute(Route $route, ServerRequestInterface $request, string $path, array $routeParams = null)
    {
        // Extract parameters if not provided
        if ($routeParams === null) {
            $routeParams = $this->extractParameters($route->path, $path);
        }
        
        // Add route parameters to request attributes
        foreach ($routeParams as $key => $value) {
            $request = $request->withAttribute($key, $value);
        }

        // Apply global middlewares first
        $stack = array_reverse($this->globalMiddlewares);
        
        // Apply route-specific middlewares
        $middlewares = array_reverse($route->middlewares);
        $stack = array_merge($stack, $middlewares);

        // Build middleware stack
        $handler = $this->createHandler($route->handler);
        
        foreach ($stack as $middleware) {
            $handler = new class($middleware, $handler) implements \Psr\Http\Server\RequestHandlerInterface {
                private $middleware;
                private $nextHandler;

                public function __construct($middleware, $nextHandler)
                {
                    $this->middleware = $middleware;
                    $this->nextHandler = $nextHandler;
                }

                public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
                {
                    return $this->middleware->process($request, $this->nextHandler);
                }
            };
        }

        return $handler->handle($request);
    }

    private function extractParameters(string $routePattern, string $path): array
    {
        $routeParams = [];
        
        // Extract parameter names from route pattern
        preg_match_all('/\{([^\/]+)\}/', $routePattern, $paramNames);
        $paramNames = $paramNames[1] ?? [];
        
        // Create pattern to extract values
        $pattern = preg_quote($routePattern, '/');
        $pattern = preg_replace('/\\\{([^\/]+)\\\}/', '([^\/]+)', $pattern);
        $pattern = '/^' . $pattern . '$/';
        
        if (preg_match($pattern, $path, $matches)) {
            array_shift($matches); // Remove full match
            
            foreach ($paramNames as $index => $name) {
                if (isset($matches[$index])) {
                    $routeParams[$name] = $matches[$index];
                }
            }
        }
        
        return $routeParams;
    }

    private function createHandler($handler)
    {
        return new class($handler) implements \Psr\Http\Server\RequestHandlerInterface {
            private $handler;

            public function __construct($handler)
            {
                $this->handler = $handler;
            }

            public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                if (is_callable($this->handler)) {
                    return call_user_func($this->handler, $request);
                } elseif (is_array($this->handler)) {
                    [$controller, $method] = $this->handler;
                    $controllerInstance = new $controller();
                    return $controllerInstance->$method($request);
                }
                
                throw new \Exception('Invalid handler type');
            }
        };
    }
}