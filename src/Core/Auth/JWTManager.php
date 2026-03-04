<?php

namespace App\Core\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Config\AppConfig;

class JWTManager
{
    private string $secretKey;
    private string $refreshSecretKey;
    private int $expiry;
    private int $refreshExpiry;

    public function __construct()
    {
        $this->secretKey = AppConfig::get('jwt.secret');
        $this->refreshSecretKey = AppConfig::get('jwt.refresh_secret');
        $this->expiry = AppConfig::get('jwt.expiry');
        $this->refreshExpiry = AppConfig::get('jwt.refresh_expiry');
    }

    /**
     * Generate access token
     */
    public function generateAccessToken(array $payload): string
    {
        $issuedAt = time();
        $expireAt = $issuedAt + $this->expiry;

        $token = [
            'iat' => $issuedAt,
            'exp' => $expireAt,
            'data' => $payload
        ];

        return JWT::encode($token, $this->secretKey, 'HS256');
    }

    /**
     * Generate refresh token
     */
    public function generateRefreshToken(array $payload): string
    {
        $issuedAt = time();
        $expireAt = $issuedAt + $this->refreshExpiry;

        $token = [
            'iat' => $issuedAt,
            'exp' => $expireAt,
            'data' => $payload
        ];

        return JWT::encode($token, $this->refreshSecretKey, 'HS256');
    }

    /**
     * Decode access token
     */
    public function decodeAccessToken(string $token): ?object
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secretKey, 'HS256'));
            return $decoded;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Decode refresh token
     */
    public function decodeRefreshToken(string $token): ?object
    {
        try {
            $decoded = JWT::decode($token, new Key($this->refreshSecretKey, 'HS256'));
            return $decoded;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Validate access token
     */
    public function validateAccessToken(string $token): bool
    {
        $decoded = $this->decodeAccessToken($token);
        return $decoded !== null && isset($decoded->exp) && $decoded->exp > time();
    }

    /**
     * Validate refresh token
     */
    public function validateRefreshToken(string $token): bool
    {
        $decoded = $this->decodeRefreshToken($token);
        return $decoded !== null && isset($decoded->exp) && $decoded->exp > time();
    }

    /**
     * Get token expiry time
     */
    public function getTokenExpiry(): int
    {
        return $this->expiry;
    }

    /**
     * Get refresh token expiry time
     */
    public function getRefreshTokenExpiry(): int
    {
        return $this->refreshExpiry;
    }
}