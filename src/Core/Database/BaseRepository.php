<?php

namespace App\Core\Database;

use App\Core\Database\Connection;
use App\Core\Cache\CacheManager;
use PDO;
use PDOStatement;

abstract class BaseRepository implements RepositoryInterface
{
    protected PDO $connection;
    protected string $table;
    protected string $primaryKey = 'id';
    protected CacheManager $cache;
    protected int $defaultCacheTtl = 300; // 5 minutes

    private static array $instances = [];

    public function __construct()
    {
        $this->connection = Connection::getInstance();
        $this->cache = CacheManager::getInstance();
    }

    /**
     * Singleton pattern for repository instances
     */
    public static function getInstance(): static
    {
        $class = static::class;
        if (!isset(self::$instances[$class])) {
            self::$instances[$class] = new static();
        }
        return self::$instances[$class];
    }

    public function findById(int $id, bool $useCache = false)
    {
        if ($useCache) {
            $cacheKey = "{$this->table}:{$this->primaryKey}:{$id}";
            $cached = $this->cache->get($cacheKey);
            
            if ($cached !== null) {
                return $cached;
            }
        }
        
        $stmt = $this->connection->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        
        if ($useCache && $result) {
            $this->cache->set($cacheKey, $result, $this->defaultCacheTtl);
        }
        
        return $result;
    }

    public function findAll(array $filters = [], int $limit = null, int $offset = null, bool $useCache = true)
    {
        // Generate cache key for this query
        $cacheKey = null;
        if ($useCache) {
            $cacheKey = "{$this->table}:all:" . md5(serialize([$filters, $limit, $offset]));
            $cached = $this->cache->get($cacheKey);
            
            if ($cached !== null) {
                return $cached;
            }
        }
        
        $query = "SELECT * FROM {$this->table}";
        $params = [];

        if (!empty($filters)) {
            $conditions = [];
            foreach ($filters as $column => $value) {
                $conditions[] = "{$column} = ?";
                $params[] = $value;
            }
            $query .= " WHERE " . implode(' AND ', $conditions);
        }

        $query .= " ORDER BY {$this->primaryKey} DESC";

        if ($limit !== null) {
            $query .= " LIMIT ?";
            $params[] = $limit;

            if ($offset !== null) {
                $query .= " OFFSET ?";
                $params[] = $offset;
            }
        }

        $stmt = $this->connection->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetchAll();
        
        if ($useCache && $result) {
            $this->cache->set($cacheKey, $result, $this->defaultCacheTtl);
        }
        
        return $result;
    }

    public function create(array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        
        $query = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        $stmt = $this->connection->prepare($query);
        $result = $stmt->execute(array_values($data));
        
        if ($result) {
            return $this->connection->lastInsertId();
        }
        
        return 0;
    }

    public function update(int $id, array $data): bool
    {
        if (empty($data)) {
            return false; // Nothing to update
        }
        
        $columns = array_keys($data);
        // Filter out any empty column names
        $validColumns = array_filter($columns, function($col) {
            return !empty($col);
        });
        
        if (empty($validColumns)) {
            return false; // No valid columns to update
        }
        
        $setClauses = [];
        foreach ($validColumns as $column) {
            $setClauses[] = "{$column} = ?";
        }
        $setClause = implode(', ', $setClauses);
        
        $query = "UPDATE {$this->table} SET {$setClause} WHERE {$this->primaryKey} = ?";
        $values = array_merge(array_values($data), [$id]);
        
        $stmt = $this->connection->prepare($query);
        return $stmt->execute($values);
    }

    public function delete(int $id): bool
    {
        $query = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?";
        $stmt = $this->connection->prepare($query);
        return $stmt->execute([$id]);
    }

    public function count(array $filters = []): int
    {
        $query = "SELECT COUNT(*) FROM {$this->table}";
        $params = [];

        if (!empty($filters)) {
            $conditions = [];
            foreach ($filters as $column => $value) {
                $conditions[] = "{$column} = ?";
                $params[] = $value;
            }
            $query .= " WHERE " . implode(' AND ', $conditions);
        }

        $stmt = $this->connection->prepare($query);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Execute raw query with caching
     */
    protected function executeRaw(string $query, array $params = [], bool $useCache = true, int $cacheTtl = null)
    {
        if ($useCache) {
            $cacheKey = "query:" . md5($query . serialize($params));
            $cached = $this->cache->get($cacheKey);
            
            if ($cached !== null) {
                return $cached;
            }
        }
        
        // Use prepared statement caching
        $stmt = Connection::getPreparedStatement($query);
        $stmt->execute($params);
        
        $result = [
            'data' => $stmt->fetchAll(),
            'rowCount' => $stmt->rowCount()
        ];
        
        if ($useCache) {
            $ttl = $cacheTtl ?? $this->defaultCacheTtl;
            $this->cache->set($cacheKey, $result, $ttl);
        }
        
        return $result;
    }

    /**
     * Execute single row query
     */
    protected function executeSingle(string $query, array $params = [], bool $useCache = true)
    {
        if ($useCache) {
            $cacheKey = "query_single:" . md5($query . serialize($params));
            $cached = $this->cache->get($cacheKey);
            
            if ($cached !== null) {
                return $cached;
            }
        }
        
        $stmt = Connection::getPreparedStatement($query);
        $stmt->execute($params);
        $result = $stmt->fetch();
        
        if ($useCache && $result) {
            $this->cache->set($cacheKey, $result, $this->defaultCacheTtl);
        }
        
        return $result;
    }

    /**
     * Execute query and return scalar value
     */
    protected function executeScalar(string $query, array $params = [], bool $useCache = true)
    {
        if ($useCache) {
            $cacheKey = "query_scalar:" . md5($query . serialize($params));
            $cached = $this->cache->get($cacheKey);
            
            if ($cached !== null) {
                return $cached;
            }
        }
        
        $stmt = Connection::getPreparedStatement($query);
        $stmt->execute($params);
        $result = $stmt->fetchColumn();
        
        if ($useCache && $result !== false) {
            $this->cache->set($cacheKey, $result, $this->defaultCacheTtl);
        }
        
        return $result;
    }



    /**
     * Begin transaction
     */
    public function beginTransaction(): bool
    {
        return $this->connection->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit(): bool
    {
        return $this->connection->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback(): bool
    {
        return $this->connection->rollback();
    }
}