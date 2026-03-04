<?php

namespace App\Core\Utils;

use GuzzleHttp\Psr7\Response;

class ResponseBuilder
{
    public static function ok($data = null, array $headers = []): Response
    {
        return new Response(
            200,
            array_merge(['Content-Type' => 'application/json'], $headers),
            $data !== null ? json_encode($data) : ''
        );
    }

    public static function created($data = null, array $headers = []): Response
    {
        return new Response(
            201,
            array_merge(['Content-Type' => 'application/json'], $headers),
            $data !== null ? json_encode($data) : ''
        );
    }

    public static function accepted($data = null, array $headers = []): Response
    {
        return new Response(
            202,
            array_merge(['Content-Type' => 'application/json'], $headers),
            $data !== null ? json_encode($data) : ''
        );
    }

    public static function badRequest($data = null, array $headers = []): Response
    {
        return new Response(
            400,
            array_merge(['Content-Type' => 'application/json'], $headers),
            $data !== null ? json_encode($data) : ''
        );
    }

    public static function unauthorized($data = null, array $headers = []): Response
    {
        return new Response(
            401,
            array_merge(['Content-Type' => 'application/json'], $headers),
            $data !== null ? json_encode($data) : ''
        );
    }

    public static function forbidden($data = null, array $headers = []): Response
    {
        return new Response(
            403,
            array_merge(['Content-Type' => 'application/json'], $headers),
            $data !== null ? json_encode($data) : ''
        );
    }

    public static function notFound($data = null, array $headers = []): Response
    {
        return new Response(
            404,
            array_merge(['Content-Type' => 'application/json'], $headers),
            $data !== null ? json_encode($data) : ''
        );
    }

    public static function conflict($data = null, array $headers = []): Response
    {
        return new Response(
            409,
            array_merge(['Content-Type' => 'application/json'], $headers),
            $data !== null ? json_encode($data) : ''
        );
    }

    public static function unprocessableEntity($data = null, array $headers = []): Response
    {
        return new Response(
            422,
            array_merge(['Content-Type' => 'application/json'], $headers),
            $data !== null ? json_encode($data) : ''
        );
    }

    public static function serverError($data = null, array $headers = []): Response
    {
        return new Response(
            500,
            array_merge(['Content-Type' => 'application/json'], $headers),
            $data !== null ? json_encode($data) : ''
        );
    }

    public static function json($data, int $statusCode = 200, array $headers = []): Response
    {
        return new Response(
            $statusCode,
            array_merge(['Content-Type' => 'application/json'], $headers),
            json_encode($data)
        );
    }
}