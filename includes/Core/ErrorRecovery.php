<?php
/**
 * Error Recovery System
 *
 * @package FP\Esperienze\Core
 */

namespace FP\Esperienze\Core;

defined('ABSPATH') || exit;

/**
 * Handles errors gracefully and provides recovery mechanisms
 */
class ErrorRecovery {
    
    /**
     * Execute function with graceful error handling
     *
     * @param callable $function Function to execute
     * @param mixed $fallback Fallback value on error
     * @param string $operation_name Operation name for logging
     * @return mixed Function result or fallback
     */
    public static function execute(callable $function, $fallback = null, string $operation_name = 'unknown'): mixed {
        try {
            return $function();
        } catch (\Throwable $e) {
            // Log the error
            Log::error('Error Recovery', [
                'operation' => $operation_name,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return fallback value
            return $fallback;
        }
    }
    
    /**
     * Execute database operation with retry mechanism
     *
     * @param callable $function Database function to execute
     * @param int $max_retries Maximum retry attempts
     * @param int $delay_ms Delay between retries in milliseconds
     * @return mixed Function result or false
     */
    public static function executeWithRetry(callable $function, int $max_retries = 3, int $delay_ms = 100): mixed {
        $attempt = 0;
        
        while ($attempt < $max_retries) {
            try {
                return $function();
            } catch (\Throwable $e) {
                $attempt++;
                
                if ($attempt >= $max_retries) {
                    Log::error('Database operation failed after retries', [
                        'attempts' => $attempt,
                        'error' => $e->getMessage()
                    ]);
                    return false;
                }
                
                // Wait before retry
                if ($delay_ms > 0) {
                    usleep($delay_ms * 1000);
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check system health and return status
     *
     * @return array Health status
     */
    public static function checkHealth(): array {
        $health = [
            'status' => 'healthy',
            'issues' => [],
            'warnings' => []
        ];
        
        // Check database connection
        if (!self::checkDatabaseHealth()) {
            $health['issues'][] = 'Database connection issues detected';
            $health['status'] = 'critical';
        }
        
        // Check memory usage
        $memory_usage = memory_get_usage(true);
        $memory_limit = self::parseMemoryLimit(ini_get('memory_limit'));
        
        if ($memory_usage > ($memory_limit * 0.9)) {
            $health['warnings'][] = 'High memory usage detected';
            if ($health['status'] === 'healthy') {
                $health['status'] = 'warning';
            }
        }
        
        // Check for missing dependencies
        $deps = DependencyChecker::checkAll();
        $missing_critical = array_filter($deps, function($dep) {
            return !$dep['available'] && $dep['status'] === 'warning';
        });
        
        if (!empty($missing_critical)) {
            $health['warnings'][] = 'Optional dependencies missing';
            if ($health['status'] === 'healthy') {
                $health['status'] = 'warning';
            }
        }
        
        return $health;
    }
    
    /**
     * Check database health
     *
     * @return bool Database is healthy
     */
    private static function checkDatabaseHealth(): bool {
        global $wpdb;
        
        try {
            $result = $wpdb->get_var("SELECT 1");
            return $result === '1';
        } catch (\Throwable $e) {
            return false;
        }
    }
    
    /**
     * Parse memory limit string to bytes
     *
     * @param string $limit Memory limit string
     * @return int Memory limit in bytes
     */
    private static function parseMemoryLimit(string $limit): int {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $value = (int) $limit;
        
        switch ($last) {
            case 'g':
                $value *= 1024 * 1024 * 1024;
                break;
            case 'm':
                $value *= 1024 * 1024;
                break;
            case 'k':
                $value *= 1024;
                break;
        }
        
        return $value;
    }
}