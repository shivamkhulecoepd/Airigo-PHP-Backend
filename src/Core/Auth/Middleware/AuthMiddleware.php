<?php

namespace App\Core\Auth\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use App\Core\Auth\JWTManager;
use App\Core\Auth\AuthService;
use App\Core\Utils\ResponseBuilder;
use App\Core\Cache\CacheManager;
use App\Repositories\UserRepository;

class AuthMiddleware implements MiddlewareInterface
{
    private JWTManager $jwtManager;
    private UserRepository $userRepository;
    private CacheManager $cache;
    private int $tokenCacheTtl = 900; // 15 minutes

    public function __construct()
    {
        $this->jwtManager = new JWTManager();
        $this->userRepository = new UserRepository();
        $this->cache = CacheManager::getInstance();
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $authorizationHeader = $request->getHeaderLine('Authorization');

        if (empty($authorizationHeader)) {
            return ResponseBuilder::unauthorized(['message' => 'Authorization header is missing']);
        }

        $tokenParts = explode(' ', $authorizationHeader);

        if (count($tokenParts) !== 2 || $tokenParts[0] !== 'Bearer') {
            return ResponseBuilder::unauthorized(['message' => 'Invalid authorization header format']);
        }

        $token = $tokenParts[1];
        $cacheKey = 'user_token:' . md5($token);

        // Try to get user from cache first
        $cachedUser = $this->cache->get($cacheKey);
        
        if ($cachedUser !== null) {
            // Add cached user to request attributes
            $request = $request->withAttribute('user', $cachedUser);
            $request = $request->withAttribute('user_id', $cachedUser['id']);
            $request = $request->withAttribute('user_type', $cachedUser['user_type']);
            
            return $handler->handle($request);
        }

        // Validate token
        if (!$this->jwtManager->validateAccessToken($token)) {
            return ResponseBuilder::unauthorized(['message' => 'Invalid or expired token']);
        }

        $decodedToken = $this->jwtManager->decodeAccessToken($token);

        if (!$decodedToken || !isset($decodedToken->data->id)) {
            return ResponseBuilder::unauthorized(['message' => 'Invalid token payload']);
        }

        // Get user from database
        $user = $this->userRepository->findById($decodedToken->data->id);
        
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not found']);
        }

        // Cache the user data
        $this->cache->set($cacheKey, $user, $this->tokenCacheTtl);

        // Add user to request attributes
        $request = $request->withAttribute('user', $user);
        $request = $request->withAttribute('user_id', $user['id']);
        $request = $request->withAttribute('user_type', $user['user_type']);

        return $handler->handle($request);
    }

    /**
     * Clear user token cache
     */
    public function clearUserCache(int $userId): void
    {
        // This would require a more sophisticated cache invalidation strategy
        // For now, we rely on TTL expiration
    }
}