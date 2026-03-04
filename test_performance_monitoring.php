<?php

// Performance Monitoring Demo
require_once __DIR__ . '/src/bootstrap.php';

use App\Core\Utils\PerformanceMonitor;

echo "=== Performance Monitoring Demo ===\n\n";

// Start monitoring
PerformanceMonitor::startTimer('database_query');
PerformanceMonitor::recordMemoryUsage('start');

// Simulate some work
echo "Simulating database operations...\n";
usleep(50000); // 50ms delay

PerformanceMonitor::recordMemoryUsage('after_db_ops');
PerformanceMonitor::stopTimer('database_query');

// Test counters
PerformanceMonitor::incrementCounter('api_requests', 1);
PerformanceMonitor::incrementCounter('successful_responses', 1);

// More simulated work
PerformanceMonitor::startTimer('cache_operation');
usleep(20000); // 20ms delay
PerformanceMonitor::stopTimer('cache_operation');

PerformanceMonitor::incrementCounter('cache_hits', 3);

// Display results
echo "\n=== Performance Results ===\n";
$summary = PerformanceMonitor::getSummary();

echo "Total Execution Time: " . $summary['total_time_ms'] . "ms\n";
echo "Current Memory Usage: " . $summary['total_memory_kb'] . "KB\n\n";

echo "Timers:\n";
foreach ($summary['timers'] as $name => $timer) {
    echo "  {$name}: {$timer['duration_ms']}ms (Memory: {$timer['memory_used_kb']}KB)\n";
}

echo "\nCounters:\n";
foreach ($summary['counters'] as $name => $count) {
    echo "  {$name}: {$count}\n";
}

echo "\nMemory Usage Points:\n";
foreach ($summary['memory_usage'] as $point => $usage) {
    echo "  {$point}: " . round($usage['memory_usage'] / 1024, 2) . "KB\n";
}

echo "\n✅ Performance monitoring is working correctly!\n";