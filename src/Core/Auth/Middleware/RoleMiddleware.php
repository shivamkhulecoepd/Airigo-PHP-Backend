<?php

namespace App\Core\Auth\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use App\Core\Utils\ResponseBuilder;

class RoleMiddleware implements MiddlewareInterface
{
    private array $allowedRoles;

    public function __construct(array $allowedRoles)
    {
        $this->allowedRoles = $allowedRoles;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $request->getAttribute('user');

        if (!$user) {
            return ResponseBuilder::forbidden(['message' => 'User not authenticated']);
        }

        $userType = $user['user_type'];

        if (!in_array($userType, $this->allowedRoles)) {
            return ResponseBuilder::forbidden([
                'message' => 'Insufficient permissions',
                'required_roles' => $this->allowedRoles,
                'user_role' => $userType
            ]);
        }

        return $handler->handle($request);
    }
}