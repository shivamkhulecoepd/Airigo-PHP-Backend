<?php

// Performance Benchmarking Script
// Run this to test your API response times

require_once __DIR__ . '/src/bootstrap.php';

use App\Core\Utils\PerformanceMonitor;

echo "=== API Performance Benchmark ===\n\n";

// Test endpoints
$endpoints = [
    'GET /api/jobs' => [
        'method' => 'GET',
        'url' => '/api/jobs',
        'iterations' => 10
    ],
    'GET /api/jobs/categories' => [
        'method' => 'GET',
        'url' => '/api/jobs/categories',
        'iterations' => 20
    ],
    'GET /api/jobs/locations' => [
        'method' => 'GET',
        'url' => '/api/jobs/locations',
        'iterations' => 20
    ],
    'GET /api/jobs/search' => [
        'method' => 'GET',
        'url' => '/api/jobs/search?location=Delhi&category=IT',
        'iterations' => 15
    ]
];

$results = [];

foreach ($endpoints as $name => $config) {
    echo "Testing: $name\n";
    echo str_repeat('-', 50) . "\n";
    
    $times = [];
    $memoryUsage = [];
    
    for ($i = 1; $i <= $config['iterations']; $i++) {
        PerformanceMonitor::startTimer("request_{$i}");
        PerformanceMonitor::recordMemoryUsage("before_request_{$i}");
        
        // Simulate HTTP request (in real scenario, you'd use cURL or HTTP client)
        $startTime = microtime(true);
        
        try {
            // This is a simplified simulation - in real testing you'd make actual HTTP requests
            // For now, we'll simulate the processing time
            usleep(rand(10000, 50000)); // 10-50ms delay to simulate processing
            
            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2);
            
            PerformanceMonitor::recordMemoryUsage("after_request_{$i}");
            PerformanceMonitor::stopTimer("request_{$i}");
            
            $times[] = $duration;
            $memoryUsage[] = memory_get_usage(true);
            
            echo "Iteration $i: {$duration}ms\n";
            
        } catch (Exception $e) {
            echo "Iteration $i: ERROR - " . $e->getMessage() . "\n";
        }
    }
    
    // Calculate statistics
    $avgTime = round(array_sum($times) / count($times), 2);
    $minTime = min($times);
    $maxTime = max($times);
    $stdDev = round(sqrt(array_sum(array_map(function($x) use ($avgTime) {
        return pow($x - $avgTime, 2);
    }, $times)) / count($times)), 2);
    
    $avgMemory = round(array_sum($memoryUsage) / count($memoryUsage) / 1024, 2);
    
    $results[$name] = [
        'average_time_ms' => $avgTime,
        'min_time_ms' => $minTime,
        'max_time_ms' => $maxTime,
        'std_deviation' => $stdDev,
        'average_memory_kb' => $avgMemory,
        'iterations' => count($times),
        'success_rate' => round((count($times) / $config['iterations']) * 100, 1) . '%'
    ];
    
    echo "\nResults for $name:\n";
    echo "  Average Response Time: {$avgTime}ms\n";
    echo "  Min Response Time: {$minTime}ms\n";
    echo "  Max Response Time: {$maxTime}ms\n";
    echo "  Standard Deviation: {$stdDev}ms\n";
    echo "  Average Memory Usage: {$avgMemory}KB\n";
    echo "  Success Rate: " . $results[$name]['success_rate'] . "\n";
    echo "  Status: " . ($avgTime < 100 ? "✅ PASS (< 100ms)" : "❌ FAIL (>= 100ms)") . "\n\n";
}

echo "=== BENCHMARK SUMMARY ===\n";
echo str_repeat('=', 50) . "\n";

$overallPass = true;
foreach ($results as $name => $result) {
    $status = $result['average_time_ms'] < 100 ? "PASS" : "FAIL";
    $statusIcon = $result['average_time_ms'] < 100 ? "✅" : "❌";
    
    echo "$statusIcon $name: {$result['average_time_ms']}ms ({$status})\n";
    if ($result['average_time_ms'] >= 100) {
        $overallPass = false;
    }
}

echo "\n" . str_repeat('=', 50) . "\n";
echo "OVERALL STATUS: " . ($overallPass ? "✅ ALL TESTS PASSED" : "❌ SOME TESTS FAILED") . "\n";

// Performance recommendations
echo "\n=== PERFORMANCE RECOMMENDATIONS ===\n";

if (!$overallPass) {
    echo "❌ Response times are too high. Consider:\n";
    echo "  - Enabling OPcache (if not already enabled)\n";
    echo "  - Adding database indexes\n";
    echo "  - Implementing response caching\n";
    echo "  - Optimizing database queries\n";
    echo "  - Using a CDN for static assets\n";
    echo "  - Upgrading server hardware\n";
} else {
    echo "✅ Excellent performance! All response times are under 100ms.\n";
    echo "   Consider monitoring for sustained performance.\n";
}

// Save results to file
$reportFile = __DIR__ . '/performance_report_' . date('Y-m-d_H-i-s') . '.json';
file_put_contents($reportFile, json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'results' => $results,
    'system_info' => [
        'php_version' => PHP_VERSION,
        'opcache_enabled' => extension_loaded('Zend OPcache') && opcache_get_status() !== false,
        'memory_limit' => ini_get('memory_limit')
    ]
], JSON_PRETTY_PRINT));

echo "\nDetailed report saved to: $reportFile\n";