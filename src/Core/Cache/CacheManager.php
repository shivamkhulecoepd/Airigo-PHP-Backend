<?php

namespace App\Core\Cache;

use App\Config\AppConfig;

class CacheManager
{
    private static ?self $instance = null;
    private $redis = null;
    private bool $useRedis = false;
    private array $memoryCache = [];
    private int $memoryCacheSize = 1000;
    
    private function __construct()
    {
        $this->initializeRedis();
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function initializeRedis(): void
    {
        // Check if Redis extension is available
        if (!extension_loaded('redis')) {
            $this->useRedis = false;
            return;
        }
        
        try {
            // Use late static binding to avoid type checking
            $redisClass = 'Redis';
            $this->redis = new $redisClass();
            $host = AppConfig::get('redis.host', '127.0.0.1');
            $port = AppConfig::get('redis.port', 6379);
            $timeout = AppConfig::get('redis.timeout', 2.5);
            
            if ($this->redis->connect($host, $port, $timeout)) {
                try {
                    // Authenticate with Redis if credentials are provided
                    $password = AppConfig::get('redis.password');
                    $username = AppConfig::get('redis.username');
                    
                    if ($password) {
                        if ($username) {
                            // Use username and password for authentication (Redis 6.0+)
                            $this->redis->auth([$username, $password]);
                        } else {
                            // Use password only for authentication
                            $this->redis->auth($password);
                        }
                    }
                    
                    // Select database if specified
                    $database = AppConfig::get('redis.database');
                    if ($database !== null) {
                        $this->redis->select($database);
                    }
                    
                    // Set serializer option using constants
                    $this->redis->setOption(1, 1); // OPT_SERIALIZER = 1, SERIALIZER_JSON = 1
                    $this->useRedis = true;
                } catch (\Exception $e) {
                    $this->useRedis = false;
                    error_log('Redis authentication failed: ' . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            $this->useRedis = false;
            error_log('Redis connection failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Get cached data
     */
    public function get(string $key, $default = null)
    {
        // Try Redis first
        if ($this->useRedis) {
            try {
                $result = $this->redis->get($key);
                if ($result !== false) {
                    return $result;
                }
            } catch (\Exception $e) {
                error_log('Redis get failed: ' . $e->getMessage());
                // Fallback to memory cache if Redis fails
            }
        }
        
        // Try memory cache
        if (isset($this->memoryCache[$key])) {
            $item = $this->memoryCache[$key];
            if ($item['expires'] === 0 || $item['expires'] > time()) {
                return $item['data'];
            }
            // Remove expired item
            unset($this->memoryCache[$key]);
        }
        
        return $default;
    }
    
    /**
     * Set cache data
     */
    public function set(string $key, $data, int $ttl = 300): bool
    {
        $success = true;
        
        // Store in Redis if available
        if ($this->useRedis) {
            try {
                $success = $this->redis->setex($key, $ttl, $data);
            } catch (\Exception $e) {
                error_log('Redis set failed: ' . $e->getMessage());
                $success = false;
            }
        }
        
        // Store in memory cache
        $this->memoryCache[$key] = [
            'data' => $data,
            'expires' => $ttl > 0 ? time() + $ttl : 0
        ];
        
        // Limit memory cache size
        if (count($this->memoryCache) > $this->memoryCacheSize) {
            array_shift($this->memoryCache);
        }
        
        return $success;
    }
    
    /**
     * Delete cache entry
     */
    public function delete(string $key): bool
    {
        $success = true;
        
        if ($this->useRedis) {
            try {
                $success = $this->redis->del($key) > 0;
            } catch (\Exception $e) {
                error_log('Redis delete failed: ' . $e->getMessage());
                $success = false;
            }
        }
        
        unset($this->memoryCache[$key]);
        return $success;
    }
    
    /**
     * Clear all cache
     */
    public function clear(): bool
    {
        $success = true;
        
        if ($this->useRedis) {
            try {
                $success = $this->redis->flushDB();
            } catch (\Exception $e) {
                error_log('Redis clear failed: ' . $e->getMessage());
                $success = false;
            }
        }
        
        $this->memoryCache = [];
        return $success;
    }
    
    /**
     * Check if cache key exists
     */
    public function exists(string $key): bool
    {
        if ($this->useRedis) {
            try {
                if ($this->redis->exists($key)) {
                    return true;
                }
            } catch (\Exception $e) {
                error_log('Redis exists failed: ' . $e->getMessage());
            }
        }
        
        return isset($this->memoryCache[$key]) && 
               ($this->memoryCache[$key]['expires'] === 0 || 
                $this->memoryCache[$key]['expires'] > time());
    }
    
    /**
     * Get cache statistics
     */
    public function getStats(): array
    {
        $stats = [
            'memory_cache_count' => count($this->memoryCache),
            'memory_cache_size' => $this->memoryCacheSize,
            'using_redis' => $this->useRedis
        ];
        
        if ($this->useRedis) {
            try {
                $info = $this->redis->info();
                $stats['redis_info'] = $info;
            } catch (\Exception $e) {
                error_log('Redis info failed: ' . $e->getMessage());
            }
        }
        
        return $stats;
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    public function __wakeup()
    {
        throw new \Exception('Cannot unserialize singleton');
    }
}