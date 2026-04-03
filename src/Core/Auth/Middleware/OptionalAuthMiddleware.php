<?php

namespace App\Core\Auth\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use App\Core\Auth\JWTManager;
use App\Core\Cache\CacheManager;
use App\Repositories\UserRepository;

/**
 * Optional Authentication Middleware
 * 
 * Unlike AuthMiddleware, this middleware does not return an error if the token is missing or invalid.
 * It simply attempts to authenticate the user and adds user information to the request attributes if successful.
 * If authentication fails or no token is provided, it simply passes the request to the next handler without user attributes.
 */
class OptionalAuthMiddleware implements MiddlewareInterface
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
            // Authorization header is missing, proceed without user information
            return $handler->handle($request);
        }

        $tokenParts = explode(' ', $authorizationHeader);

        if (count($tokenParts) !== 2 || $tokenParts[0] !== 'Bearer') {
            // Invalid format, proceed without user information
            return $handler->handle($request);
        }

        $token = $tokenParts[1];
        $cacheKey = 'user_token:' . md5($token);

        // Try to get user from cache first
        $cachedUser = $this->cache->get($cacheKey);
        
        if ($cachedUser !== null) {
            $request = $request->withAttribute('user', $cachedUser);
            $request = $request->withAttribute('user_id', $cachedUser['id']);
            $request = $request->withAttribute('user_type', $cachedUser['user_type']);
            
            return $handler->handle($request);
        }

        // Validate token
        if (!$this->jwtManager->validateAccessToken($token)) {
            // Invalid token, proceed without user information
            return $handler->handle($request);
        }

        $decodedToken = $this->jwtManager->decodeAccessToken($token);

        if (!$decodedToken || !isset($decodedToken->data->id)) {
            // Invalid payload, proceed without user information
            return $handler->handle($request);
        }

        // Get user from database
        $user = $this->userRepository->findById($decodedToken->data->id);
        
        if (!$user) {
            // User not found, proceed without user information
            return $handler->handle($request);
        }

        // Cache the user data
        $this->cache->set($cacheKey, $user, $this->tokenCacheTtl);

        // Add user to request attributes
        $request = $request->withAttribute('user', $user);
        $request = $request->withAttribute('user_id', $user['id']);
        $request = $request->withAttribute('user_type', $user['user_type']);

        return $handler->handle($request);
    }
}
