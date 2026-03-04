<?php

namespace App\Core\Http\Router;

class Route
{
    public string $method;
    public string $path;
    public $handler;
    public array $middlewares = [];

    public function __construct(string $method, string $path, $handler)
    {
        $this->method = strtoupper($method);
        $this->path = $path;
        $this->handler = $handler;
    }

    public function addMiddleware($middleware): self
    {
        $this->middlewares[] = $middleware;
        return $this;
    }
}