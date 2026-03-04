<?php

namespace App\Core\Utils;

class PerformanceMonitor
{
    private static array $timers = [];
    private static array $counters = [];
    private static array $memoryUsage = [];
    private static bool $enabled = true;
    
    /**
     * Start timing an operation
     */
    public static function startTimer(string $name): void
    {
        if (!self::$enabled) return;
        
        self::$timers[$name] = [
            'start' => microtime(true),
            'memory_start' => memory_get_usage(true)
        ];
    }
    
    /**
     * Stop timing and record results
     */
    public static function stopTimer(string $name): array
    {
        if (!self::$enabled || !isset(self::$timers[$name])) {
            return [];
        }
        
        $timer = self::$timers[$name];
        $end = microtime(true);
        $memoryEnd = memory_get_usage(true);
        
        $result = [
            'duration_ms' => round(($end - $timer['start']) * 1000, 2),
            'memory_used_kb' => round(($memoryEnd - $timer['memory_start']) / 1024, 2),
            'timestamp' => $end
        ];
        
        self::$timers[$name]['result'] = $result;
        return $result;
    }
    
    /**
     * Get all timer results
     */
    public static function getTimers(): array
    {
        $results = [];
        foreach (self::$timers as $name => $timer) {
            if (isset($timer['result'])) {
                $results[$name] = $timer['result'];
            }
        }
        return $results;
    }
    
    /**
     * Increment counter
     */
    public static function incrementCounter(string $name, int $value = 1): void
    {
        if (!self::$enabled) return;
        
        if (!isset(self::$counters[$name])) {
            self::$counters[$name] = 0;
        }
        self::$counters[$name] += $value;
    }
    
    /**
     * Get counter value
     */
    public static function getCounter(string $name): int
    {
        return self::$counters[$name] ?? 0;
    }
    
    /**
     * Get all counters
     */
    public static function getCounters(): array
    {
        return self::$counters;
    }
    
    /**
     * Record memory usage at a point
     */
    public static function recordMemoryUsage(string $point): void
    {
        if (!self::$enabled) return;
        
        self::$memoryUsage[$point] = [
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'timestamp' => microtime(true)
        ];
    }
    
    /**
     * Get memory usage records
     */
    public static function getMemoryUsage(): array
    {
        return self::$memoryUsage;
    }
    
    /**
     * Get performance summary
     */
    public static function getSummary(): array
    {
        if (!self::$enabled) {
            return ['monitoring' => 'disabled'];
        }
        
        return [
            'timers' => self::getTimers(),
            'counters' => self::getCounters(),
            'memory_usage' => self::getMemoryUsage(),
            'total_time_ms' => self::getTotalTime(),
            'total_memory_kb' => round(memory_get_usage(true) / 1024, 2)
        ];
    }
    
    /**
     * Get total execution time
     */
    public static function getTotalTime(): float
    {
        $total = 0;
        foreach (self::getTimers() as $timer) {
            $total += $timer['duration_ms'];
        }
        return round($total, 2);
    }
    
    /**
     * Enable/disable monitoring
     */
    public static function setEnabled(bool $enabled): void
    {
        self::$enabled = $enabled;
    }
    
    /**
     * Reset all metrics
     */
    public static function reset(): void
    {
        self::$timers = [];
        self::$counters = [];
        self::$memoryUsage = [];
    }
    
    /**
     * Export metrics to JSON
     */
    public static function toJson(): string
    {
        return json_encode(self::getSummary(), JSON_PRETTY_PRINT);
    }
    
    /**
     * Export metrics to array suitable for logging
     */
    public static function toLogArray(): array
    {
        $summary = self::getSummary();
        return [
            'performance_metrics' => $summary,
            'request_id' => uniqid('req_', true),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}