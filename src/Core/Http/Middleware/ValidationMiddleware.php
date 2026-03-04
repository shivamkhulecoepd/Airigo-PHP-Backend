<?php

namespace App\Core\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use App\Core\Utils\ResponseBuilder;
use App\Core\Utils\Validator;

class ValidationMiddleware implements MiddlewareInterface
{
    private array $validationRules;
    private Validator $validator;

    public function __construct(array $validationRules)
    {
        $this->validationRules = $validationRules;
        $this->validator = new Validator();
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Get request data (from body for POST/PUT/PATCH requests)
        $requestData = [];
        
        if (in_array($request->getMethod(), ['POST', 'PUT', 'PATCH'])) {
            $body = $request->getBody()->getContents();
            $requestData = json_decode($body, true) ?: [];
        }

        // Perform validation
        $errors = $this->validator->validate($requestData, $this->validationRules);

        if (!empty($errors)) {
            return ResponseBuilder::badRequest([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $errors
            ]);
        }

        // Validation passed, continue with the request
        return $handler->handle($request);
    }
}