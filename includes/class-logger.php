<?php
namespace TursoDBAdapter;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logger class for debugging Turso DB Adapter
 */
class Logger {
    /**
     * @var bool Whether logging is enabled
     */
    private static $enabled = false;

    /**
     * Initialize logger
     *
     * @param bool $enabled Whether logging is enabled
     */
    public static function init($enabled = false) {
        self::$enabled = $enabled;
    }

    /**
     * Log a message if logging is enabled
     *
     * @param string $message Message to log
     * @param string $level Log level (debug, info, error)
     */
    public static function log($message, $level = 'debug') {
        if (!self::$enabled) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $prefix = '[Turso DB]';
        
        switch ($level) {
            case 'error':
                $prefix .= ' [ERROR]';
                break;
            case 'info':
                $prefix .= ' [INFO]';
                break;
            default:
                $prefix .= ' [DEBUG]';
        }

        error_log(sprintf('%s %s: %s', $prefix, $timestamp, $message));
    }

    /**
     * Log a debug message
     *
     * @param string $message Message to log
     */
    public static function debug($message) {
        self::log($message, 'debug');
    }

    /**
     * Log an info message
     *
     * @param string $message Message to log
     */
    public static function info($message) {
        self::log($message, 'info');
    }

    /**
     * Log an error message
     *
     * @param string $message Message to log
     */
    public static function error($message) {
        self::log($message, 'error');
    }

    /**
     * Log a database query if logging is enabled
     *
     * @param string $query The SQL query
     * @param array $args Query arguments
     * @param mixed $result Query result
     */
    public static function log_query($query, $args = [], $result = null) {
        if (!self::$enabled) {
            return;
        }

        $message = "Query: $query";
        if (!empty($args)) {
            $message .= "\nArgs: " . print_r($args, true);
        }
        if ($result !== null) {
            $message .= "\nResult: " . print_r($result, true);
        }

        self::debug($message);
    }
}
