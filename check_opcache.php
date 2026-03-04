<?php

// OPcache Status and Configuration Checker
// Run this script to check current OPcache status and configuration

echo "=== PHP OPcache Configuration Checker ===\n\n";

// Check if OPcache is available
if (!extension_loaded('Zend OPcache')) {
    echo "❌ OPcache extension is not loaded!\n";
    echo "Please install and enable OPcache extension.\n\n";
    
    // Show how to enable it
    echo "To enable OPcache:\n";
    echo "1. Add 'zend_extension=opcache' to your php.ini\n";
    echo "2. Restart your web server\n\n";
    exit(1);
}

// Get OPcache status
$status = opcache_get_status();
$config = opcache_get_configuration();

if (!$status) {
    echo "❌ OPcache is disabled or not working properly!\n\n";
    echo "Current configuration:\n";
    print_r($config['directives']);
    exit(1);
}

echo "✅ OPcache is enabled and running!\n\n";

// Display basic statistics
echo "=== Cache Statistics ===\n";
echo "Hits: " . number_format($status['opcache_statistics']['hits']) . "\n";
echo "Misses: " . number_format($status['opcache_statistics']['misses']) . "\n";
echo "Hit Rate: " . round($status['opcache_statistics']['opcache_hit_rate'], 2) . "%\n";
echo "Cached Scripts: " . count($status['scripts']) . "\n";
echo "Memory Usage: " . round($status['memory_usage']['used_memory'] / 1024 / 1024, 2) . " MB\n";
echo "Free Memory: " . round($status['memory_usage']['free_memory'] / 1024 / 1024, 2) . " MB\n\n";

// Display important configuration settings
echo "=== Key Configuration Settings ===\n";
$directives = $config['directives'];
$keySettings = [
    'opcache.enable' => 'Enabled',
    'opcache.enable_cli' => 'CLI Enabled',
    'opcache.memory_consumption' => 'Memory (MB)',
    'opcache.max_accelerated_files' => 'Max Files',
    'opcache.validate_timestamps' => 'Timestamp Validation',
    'opcache.revalidate_freq' => 'Revalidate Frequency',
    'opcache.save_comments' => 'Save Comments',
    'opcache.fast_shutdown' => 'Fast Shutdown',
    'opcache.enable_file_override' => 'File Override'
];

foreach ($keySettings as $key => $label) {
    $value = $directives[$key] ?? 'Not Set';
    if (is_bool($value)) {
        $value = $value ? 'Yes' : 'No';
    } elseif ($key === 'opcache.memory_consumption') {
        $value = round($value / 1024 / 1024, 2) . ' MB';
    }
    echo sprintf("%-25s: %s\n", $label, $value);
}

echo "\n=== Performance Recommendations ===\n";

// Check for performance issues
$recommendations = [];

if ($directives['opcache.memory_consumption'] < 134217728) { // 128MB
    $recommendations[] = "Increase opcache.memory_consumption to at least 128MB (256MB recommended)";
}

if ($directives['opcache.max_accelerated_files'] < 10000) {
    $recommendations[] = "Increase opcache.max_accelerated_files to at least 10000 (20000 recommended)";
}

if ($directives['opcache.validate_timestamps'] === true) {
    $recommendations[] = "Set opcache.validate_timestamps=0 in production for better performance";
}

if ($directives['opcache.revalidate_freq'] > 0) {
    $recommendations[] = "Set opcache.revalidate_freq=0 in production for better performance";
}

if (empty($recommendations)) {
    echo "✅ All settings look good for production!\n";
} else {
    foreach ($recommendations as $rec) {
        echo "⚠️  $rec\n";
    }
}

echo "\n=== Cached Scripts Summary ===\n";
if (isset($status['scripts']) && count($status['scripts']) > 0) {
    $totalSize = 0;
    foreach ($status['scripts'] as $script) {
        $totalSize += $script['memory_consumption'];
    }
    echo "Total cached scripts: " . count($status['scripts']) . "\n";
    echo "Total memory used: " . round($totalSize / 1024 / 1024, 2) . " MB\n";
} else {
    echo "No scripts cached yet.\n";
}

echo "\n=== Reset OPcache ===\n";
echo "To reset OPcache (useful after deployments):\n";
echo "opcache_reset();\n";
echo "or restart your web server\n";

// Function to reset OPcache
if (isset($argv[1]) && $argv[1] === '--reset') {
    echo "\nResetting OPcache...\n";
    if (opcache_reset()) {
        echo "✅ OPcache reset successfully!\n";
    } else {
        echo "❌ Failed to reset OPcache!\n";
    }
}