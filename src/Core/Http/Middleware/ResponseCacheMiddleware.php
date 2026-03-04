<?php

namespace App\Core\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use App\Core\Cache\CacheManager;

class ResponseCacheMiddleware implements MiddlewareInterface
{
    private CacheManager $cache;
    private int $defaultTtl;
    private array $cacheableRoutes;
    
    public function __construct(int $defaultTtl = 300, array $cacheableRoutes = [])
    {
        $this->cache = CacheManager::getInstance();
        $this->defaultTtl = $defaultTtl;
        $this->cacheableRoutes = $cacheableRoutes;
    }
    
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();
        
        // Only cache GET requests for specific routes
        if ($method !== 'GET' || !$this->isCacheableRoute($path)) {
            return $handler->handle($request);
        }
        
        // Generate cache key
        $cacheKey = $this->generateCacheKey($request);
        
        // Try to get cached response
        $cachedResponse = $this->cache->get($cacheKey);
        
        if ($cachedResponse !== null) {
            // Return cached response
            return $this->deserializeResponse($cachedResponse);
        }
        
        // Process request and cache response
        $response = $handler->handle($request);
        
        // Only cache successful responses
        if ($response->getStatusCode() === 200) {
            $ttl = $this->getTtlForRoute($path);
            $serializedResponse = $this->serializeResponse($response);
            $this->cache->set($cacheKey, $serializedResponse, $ttl);
        }
        
        return $response;
    }
    
    private function isCacheableRoute(string $path): bool
    {
        if (empty($this->cacheableRoutes)) {
            // Default cacheable routes
            $defaultRoutes = [
                '/api/jobs',
                '/api/jobs/categories',
                '/api/jobs/locations',
                '/api/jobs/search'
            ];
            return in_array($path, $defaultRoutes);
        }
        
        return in_array($path, $this->cacheableRoutes);
    }
    
    private function getTtlForRoute(string $path): int
    {
        $routeTtls = [
            '/api/jobs' => 180, // 3 minutes
            '/api/jobs/categories' => 3600, // 1 hour
            '/api/jobs/locations' => 3600, // 1 hour
            '/api/jobs/search' => 120 // 2 minutes
        ];
        
        return $routeTtls[$path] ?? $this->defaultTtl;
    }
    
    private function generateCacheKey(ServerRequestInterface $request): string
    {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();
        $queryParams = $request->getQueryParams();
        
        // Sort query parameters for consistent cache keys
        ksort($queryParams);
        $queryString = http_build_query($queryParams);
        
        $keyString = $method . ':' . $path;
        if (!empty($queryString)) {
            $keyString .= '?' . $queryString;
        }
        
        return 'response_cache:' . md5($keyString);
    }
    
    private function serializeResponse(ResponseInterface $response): string
    {
        $data = [
            'status_code' => $response->getStatusCode(),
            'headers' => $response->getHeaders(),
            'body' => (string) $response->getBody()
        ];
        
        return json_encode($data);
    }
    
    private function deserializeResponse(string $serializedData): ResponseInterface
    {
        $data = json_decode($serializedData, true);
        
        $response = new \GuzzleHttp\Psr7\Response(
            $data['status_code'],
            $data['headers'],
            $data['body']
        );
        
        return $response;
    }
}