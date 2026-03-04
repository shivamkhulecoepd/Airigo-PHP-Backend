<?php

namespace App\Core\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use App\Core\Utils\ResponseBuilder;
use Predis\Client as RedisClient;
use App\Config\AppConfig;

class RateLimitMiddleware implements MiddlewareInterface
{
    private RedisClient $redis;
    private int $requestsLimit;
    private int $timeWindow; // in seconds

    public function __construct(int $requestsLimit = 10, int $timeWindow = 3600)
    {
        $this->requestsLimit = $requestsLimit;
        $this->timeWindow = $timeWindow;

        // Initialize Redis client
        $redisConfig = [
            'scheme' => 'tcp',
            'host' => AppConfig::get('redis.host'),
            'port' => AppConfig::get('redis.port'),
        ];

        if (AppConfig::get('redis.password')) {
            $redisConfig['password'] = AppConfig::get('redis.password');
        }

        if (AppConfig::get('redis.database')) {
            $redisConfig['database'] = AppConfig::get('redis.database');
        }

        $this->redis = new RedisClient($redisConfig);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Get client IP address
        $clientIp = $this->getClientIp($request);

        // Create a unique key for rate limiting
        $rateLimitKey = "rate_limit:{$clientIp}";

        // Get current request count
        $currentRequests = $this->redis->get($rateLimitKey);

        if ($currentRequests === null) {
            // First request in this time window, set counter and expiration
            $this->redis->setex($rateLimitKey, $this->timeWindow, 1);
        } else {
            $currentRequests = (int)$currentRequests;

            if ($currentRequests >= $this->requestsLimit) {
                // Rate limit exceeded
                return ResponseBuilder::json([
                    'success' => false,
                    'message' => 'Rate limit exceeded. Please try again later.'
                ], 429);
            }

            // Increment request count
            $this->redis->incr($rateLimitKey);
        }

        // Add rate limit headers to response
        $response = $handler->handle($request);
        
        return $response
            ->withHeader('X-RateLimit-Limit', (string)$this->requestsLimit)
            ->withHeader('X-RateLimit-Remaining', (string)($this->requestsLimit - ($currentRequests ?? 0) - 1))
            ->withHeader('X-RateLimit-Reset', (string)(time() + $this->timeWindow));
    }

    private function getClientIp(ServerRequestInterface $request): string
    {
        // Check various headers that could contain the client IP
        $headers = $request->getHeaders();

        if (isset($headers['X-Forwarded-For'][0])) {
            $ips = explode(',', $headers['X-Forwarded-For'][0]);
            return trim($ips[0]);
        }

        if (isset($headers['X-Real-IP'][0])) {
            return $headers['X-Real-IP'][0];
        }

        if (isset($headers['CF-Connecting-IP'][0])) {
            return $headers['CF-Connecting-IP'][0];
        }

        // Fallback to REMOTE_ADDR from server params
        $serverParams = $request->getServerParams();
        return $serverParams['REMOTE_ADDR'] ?? 'unknown';
    }
}