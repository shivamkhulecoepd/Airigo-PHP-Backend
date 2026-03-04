<?php

namespace App\Core\Http\Controllers;

use Psr\Http\Message\ServerRequestInterface;
use App\Core\Utils\Validator;

abstract class BaseController
{
    protected Validator $validator;

    public function __construct()
    {
        $this->validator = new Validator();
    }

    protected function getUser(ServerRequestInterface $request): ?array
    {
        return $request->getAttribute('user');
    }

    protected function getUserId(ServerRequestInterface $request): ?int
    {
        return $request->getAttribute('user_id');
    }

    protected function getUserType(ServerRequestInterface $request): ?string
    {
        return $request->getAttribute('user_type');
    }

    protected function getRequestBody(ServerRequestInterface $request): array
    {
        $body = $request->getBody()->getContents();
        return json_decode($body, true) ?: [];
    }

    protected function getQueryParam(ServerRequestInterface $request, string $key, $default = null)
    {
        $queryParams = $request->getQueryParams();
        return $queryParams[$key] ?? $default;
    }

    protected function getPaginationParams(ServerRequestInterface $request): array
    {
        $page = max(1, (int) $this->getQueryParam($request, 'page', 1));
        $limit = min(100, max(1, (int) $this->getQueryParam($request, 'limit', 10)));

        return [
            'offset' => ($page - 1) * $limit,
            'limit' => $limit,
            'page' => $page
        ];
    }
}