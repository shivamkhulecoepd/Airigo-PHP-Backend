<?php

namespace App\Core\Database;

use PDO;
use PDOException;
use App\Config\AppConfig;

class Connection
{
    private static ?PDO $instance = null;
    private static array $preparedStatements = [];
    private static array $connectionPool = [];
    private static int $maxPoolSize = 10;
    private static int $currentPoolIndex = 0;

    /**
     * Get database connection instance (optimized with connection pooling)
     */
    public static function getInstance(): ?PDO
    {
        if (self::$instance === null) {
            self::connect();
        }
        
        return self::$instance;
    }

    /**
     * Get pooled connection for high-performance scenarios
     */
    public static function getPooledConnection(): PDO
    {
        $poolSize = count(self::$connectionPool);
        
        if ($poolSize < self::$maxPoolSize) {
            // Create new connection in pool
            $connection = self::createPooledConnection();
            self::$connectionPool[] = $connection;
            return $connection;
        }
        
        // Round-robin connection selection
        $connection = self::$connectionPool[self::$currentPoolIndex];
        self::$currentPoolIndex = (self::$currentPoolIndex + 1) % self::$maxPoolSize;
        
        return $connection;
    }

    /**
     * Create pooled connection with optimized settings
     */
    private static function createPooledConnection(): PDO
    {
        try {
            $host = AppConfig::get('database.host');
            $port = AppConfig::get('database.port');
            $dbname = AppConfig::get('database.database');
            $username = AppConfig::get('database.username');
            $password = AppConfig::get('database.password');
            $charset = AppConfig::get('database.charset');

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => true,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                PDO::ATTR_TIMEOUT => 5,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'"
            ];

            return new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            throw new PDOException('Connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Get cached prepared statement
     */
    public static function getPreparedStatement(string $query): \PDOStatement
    {
        if (!isset(self::$preparedStatements[$query])) {
            $connection = self::getInstance();
            self::$preparedStatements[$query] = $connection->prepare($query);
        }
        
        return self::$preparedStatements[$query];
    }

    /**
     * Clear prepared statement cache
     */
    public static function clearPreparedStatementCache(): void
    {
        self::$preparedStatements = [];
    }

    /**
     * Establish database connection
     */
    private static function connect(): void
    {
        try {
            $host = AppConfig::get('database.host');
            $port = AppConfig::get('database.port');
            $dbname = AppConfig::get('database.database');
            $username = AppConfig::get('database.username');
            $password = AppConfig::get('database.password');
            $charset = AppConfig::get('database.charset');

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => true,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                PDO::ATTR_TIMEOUT => 5,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'"
            ];

            self::$instance = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            throw new PDOException('Connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Prevent cloning of connection
     */
    private function __clone()
    {
    }

    /**
     * Prevent wakeup of connection
     */
    public function __wakeup()
    {
        throw new \Exception('Cannot unserialize singleton');
    }
}