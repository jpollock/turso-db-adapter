<?php
namespace TursoDBAdapter;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WordPress Database Adapter for Turso
 */
class DB_Adapter extends \wpdb {
    /**
     * @var Client
     */
    private $turso_client;

    /**
     * @var Settings
     */
    private $settings;

    /**
     * Constructor
     */
    public function __construct($settings) {
        $this->settings = $settings;
        $this->turso_client = new Client($settings);

        Logger::info('Initializing Turso DB Adapter');

        // Set dummy values for parent constructor
        parent::__construct('dummy', 'dummy', 'dummy', 'dummy');

        // Initialize the connection
        $this->init_connection();

        // Set required properties
        $this->is_mysql = false;
        $this->use_mysqli = false;
        $this->ready = true;
        $this->has_connected = true; 

        // Set table names
        $this->tables = [
            'posts',
            'comments',
            'links',
            'options',
            'postmeta',
            'terms',
            'term_taxonomy',
            'term_relationships',
            'termmeta',
            'commentmeta',
            'users',
            'usermeta',
        ];

        foreach ($this->tables as $table) {
            $this->$table = 'wp_' . $table;
        }

        $this->prefix = 'wp_';
        $this->base_prefix = 'wp_';

        Logger::info('Turso DB Adapter initialized successfully');
    }

    /**
     * Initialize the Turso connection
     */
    private function init_connection() {
        Logger::debug('Testing Turso connection...');
        if (!$this->turso_client->init_connection()) {
            Logger::error('Failed to connect to Turso database');
            wp_die(__('Failed to connect to Turso database. Please check your settings.', 'turso-db-adapter'));
        }
        Logger::debug('Turso connection successful');
    }

    /**
     * Execute a SQL query using Turso
     */
    public function query($query) {
        if (empty($query)) {
            return false;
        }

        // Reset previous results
        $this->flush();

        $this->last_query = $query;
        Logger::debug('Original query: ' . $query);

        // Convert MySQL-specific syntax to SQLite
        $query = $this->convert_mysql_to_sqlite($query);
        Logger::debug('Converted query: ' . $query);

        if (strpos($query, 'wp_posts') !== false) {
            error_log('WP_POSTS QUERY: ' . $query);
            //error_log('WP_POSTS RESULT: ' . print_r($result, true));
        }

        try {
            $this->result = $this->turso_client->query($query);
            //error_log(print_r($this->result, true));
            if (strpos($query, 'wp_posts') !== false) {
                error_log('WP_POSTS QUERY: ' . $query);
                error_log('WP_POSTS RESULT: ' . print_r($this->result, true));
            }
    
            if ($this->result === false || $this->result === null) {
                $this->last_error = 'Query failed';
                Logger::error('Query failed: ' . $query);
                return false;
            }

            if (preg_match('/^\s*(create|alter|truncate|drop)\s/i', $query)) {
                Logger::debug('DDL query executed successfully');
                return true;
            }

            if (preg_match('/^\s*(insert|delete|update|replace)\s/i', $query)) {
                $this->rows_affected = $this->result['affected_row_count'] ?? 0;
                $this->insert_id = $this->result['last_insert_rowid'] ?? 0;
                Logger::debug(sprintf('DML query affected %d rows', $this->rows_affected));
                return $this->rows_affected;
            }

            // For SELECT queries
            if (!empty($this->result['rows'])) {
                $this->last_result = $this->format_results($this->result['rows'], $this->result['cols']);
                $this->num_rows = count($this->last_result);
                Logger::debug(sprintf('SELECT query returned %d rows', $this->num_rows));
                return $this->num_rows;
            }

            return true;
        } catch (\Exception $e) {
            $this->last_error = $e->getMessage();
            Logger::error('Query error: ' . $e->getMessage());
            Logger::error('Failed query: ' . $query);
            return false;
        }
    }

    /**
     * Convert MySQL syntax to SQLite
     */
    private function convert_mysql_to_sqlite($query) {
        Logger::debug('Converting query: ' . $query);

        // Remove SQL_CALC_FOUND_ROWS
        $query = preg_replace('/\bSQL_CALC_FOUND_ROWS\b/i', '', $query);

        // Handle ON DUPLICATE KEY UPDATE
        if (stripos($query, 'ON DUPLICATE KEY UPDATE') !== false) {
            Logger::debug('Converting ON DUPLICATE KEY UPDATE query');
            $parts = explode('ON DUPLICATE KEY UPDATE', $query, 2);
            $insert_part = trim($parts[0]);
            
            // Extract table name and columns from INSERT
            if (preg_match('/INSERT\s+INTO\s+(\w+)\s*\((.*?)\)\s*VALUES/i', $insert_part, $matches)) {
                $query = str_replace('INSERT INTO', 'REPLACE INTO', $insert_part);
                Logger::debug('Converted to REPLACE INTO query: ' . $query);
            }
        }

        // Convert LIMIT x,y syntax to LIMIT y OFFSET x
        if (preg_match('/\bLIMIT\s+(\d+)\s*,\s*(\d+)\b/i', $query, $matches)) {
            $offset = $matches[1];
            $limit = $matches[2];
            $query = preg_replace(
                '/\bLIMIT\s+(\d+)\s*,\s*(\d+)\b/i',
                "LIMIT $limit OFFSET $offset",
                $query
            );
        }

        // Convert other MySQL-specific syntax
        $replacements = [
            // Engine and character set
            '/\bENGINE=InnoDB\b/i' => '',
            '/\bCHARACTER SET utf8\b/i' => '',
            '/\bCOLLATE utf8_general_ci\b/i' => '',
            
            // Date/time functions
            '/\bNOW\(\)/i' => "datetime('now')",
            '/\bUNIX_TIMESTAMP\(\)/i' => "strftime('%s', 'now')",
            
            // String functions
            '/\bCONCAT\(/i' => 'group_concat(',
            
            // Boolean literals
            '/\b1=1\b/' => 'TRUE',
            '/\b1=0\b/' => 'FALSE',
            
            // Auto increment
            '/\bAUTO_INCREMENT\b/i' => 'AUTOINCREMENT',
            
            // Index types
            '/\bUSING BTREE\b/i' => '',
            '/\bUSING HASH\b/i' => '',
        ];

        $query = preg_replace(array_keys($replacements), array_values($replacements), $query);

        // Handle FOUND_ROWS()
        if (stripos($query, 'SELECT FOUND_ROWS()') !== false) {
            // We'll need to handle this in the query method
            Logger::debug('Found SELECT FOUND_ROWS()');
        }

        Logger::debug('Converted query: ' . $query);
        return $query;
    }

    /**
     * Format results to match WordPress expected format
     */
    private function format_results($rows, $cols) {
        $results = [];
        foreach ($rows as $row) {
            $obj = new \stdClass();
            foreach ($cols as $index => $col) {
                $obj->$col = $row[$index];
            }
            $results[] = $obj;
        }
        return $results;
    }

    /**
     * Prepare a SQL query for safe execution
     */
    public function prepare($query, ...$args) {
        if (empty($args)) {
            return $query;
        }

        // If the args are passed as an array, use that instead
        if (count($args) === 1 && is_array($args[0])) {
            $args = $args[0];
        }

        Logger::debug('Preparing query: ' . $query);
        Logger::debug('With args: ' . print_r($args, true));

        // Convert each argument to a string
        $prepared_args = array_map(function($arg) {
            if (is_null($arg)) {
                return 'NULL';
            }
            if (is_bool($arg)) {
                return $arg ? '1' : '0';
            }
            if (is_int($arg)) {
                return (string)$arg;
            }
            if (is_float($arg)) {
                return sprintf('%F', $arg);
            }
            if (is_array($arg)) {
                return implode(',', array_map([$this, 'escape'], $arg));
            }
            return $this->escape((string)$arg);
        }, $args);

        // Replace placeholders
        $parts = explode('%', $query);
        $query = array_shift($parts);
        $arg_position = 0;

        foreach ($parts as $part) {
            $char = $part[0];
            $rest = substr($part, 1);

            if ($char === '%') {
                $query .= '%' . $rest;
                continue;
            }

            if ($arg_position >= count($prepared_args)) {
                $query .= '%' . $part;
                continue;
            }

            switch ($char) {
                case 's':
                case 'd':
                case 'f':
                    $query .= $prepared_args[$arg_position] . $rest;
                    $arg_position++;
                    break;
                default:
                    $query .= '%' . $part;
            }
        }

        Logger::debug('Prepared query: ' . $query);
        return $query;
    }

    /**
     * Real escape string, not needed for Turso as we use parameterized queries
     */
    public function _real_escape($string) {
        return $string;
    }

    /**
     * Escape string, not needed for Turso as we use parameterized queries
     */
    public function escape($string) {
        return "'" . $this->_real_escape($string) . "'";
    }

    /**
     * Override db_server_info() to prevent mysqli calls
     */
    public function db_server_info() {
        return 'SQLite via Turso';
    }

    /**
     * Override db_version() to prevent mysqli calls
     */
    public function db_version() {
        return '3.0.0'; // SQLite version or any compatible version number
    }

    /**
     * Override check_connection() to prevent mysqli calls
     */
    public function check_connection($allow_bail = true) {
        if (!$this->turso_client) {
            if ($allow_bail) {
                wp_die(
                    sprintf(
                        'Cannot connect to Turso database.'
                    ),
                    'Database Connection Error'
                );
            }
            return false;
        }
        return true;
    }

    /**
     * Override has_cap() to indicate SQLite capabilities
     */
    public function has_cap($db_cap) {
        $caps = [
            'collation' => false,
            'group_concat' => true,
            'subqueries' => true,
            'set_charset' => false,
            'utf8mb4' => true,
        ];

        if (isset($caps[$db_cap])) {
            return $caps[$db_cap];
        }
        
        return false;
    }

    /**
     * Override set_charset() since SQLite handles charsets differently
     * @param object|null $dbh Database connection (unused for SQLite)
     * @param string|null $charset Character set (unused for SQLite)
     * @param string|null $collate Collation (unused for SQLite)
     * @return bool Always returns true as SQLite uses UTF-8 by default
     */
    public function set_charset($dbh, $charset = null, $collate = null) {
        // SQLite uses UTF-8 by default, so we just return true
        return true;
    }

    /**
     * Override get_charset_collate() for SQLite compatibility
     */
    public function get_charset_collate() {
        return ''; // SQLite handles this internally
    }
    
}
