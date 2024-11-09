<?php
namespace TursoDBAdapter;

if (!defined('ABSPATH')) {
    exit;
}

class Client {
    private $settings;
    private $base_url;
    private $auth_token;
    private $current_baton = null;

    public function __construct($settings = null) {
        if ($settings instanceof Settings) {
            $this->base_url = $settings->get_setting('database_url') ?? '';
            $this->auth_token = $settings->get_setting('auth_token') ?? '';
            $this->settings = $settings;
        } else {
            $options = get_option('turso_db_settings', []);
            $this->base_url = $options['database_url'] ?? '';
            $this->auth_token = $options['auth_token'] ?? '';
            $this->settings = $options;
        }
        
        $this->base_url = str_replace('libsql://', 'https://', $this->base_url);
    }

    private function make_request($requests) {
        $request_body = ['requests' => $requests];
        
        if ($this->current_baton) {
            $request_body['baton'] = $this->current_baton;
        }

        $response = wp_remote_post(
            trailingslashit($this->base_url) . 'v2/pipeline',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->auth_token,
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode($request_body),
                'timeout' => 30,
            ]
        );

        if (is_wp_error($response)) {
            throw new \Exception('HTTP Error: ' . $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            throw new \Exception('API Error: ' . wp_remote_retrieve_body($response));
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        // Store baton if provided
        if (!empty($body['baton'])) {
            $this->current_baton = $body['baton'];
        }

        return $body;
    }

    public function query($sql, $params = []) {
        $request = [
            'requests' => [
                [
                    'type' => 'execute',
                    'stmt' => [
                        'sql' => $sql,
                    ]
                ],
                [
                    'type' => 'close'
                ]
            ]
        ];
    
        if (!empty($params)) {
            $formatted_params = [];
            foreach ($params as $param) {
                if (is_null($param)) {
                    $formatted_params[] = ['type' => 'null'];
                } elseif (is_int($param)) {
                    $formatted_params[] = ['type' => 'integer', 'value' => $param];
                } elseif (is_float($param)) {
                    $formatted_params[] = ['type' => 'float', 'value' => $param];
                } else {
                    $formatted_params[] = ['type' => 'text', 'value' => (string)$param];
                }
            }
            
            $request['requests'][0]['stmt']['args'] = $formatted_params;
        }
    
        $response = wp_remote_post(
            trailingslashit($this->base_url) . 'v2/pipeline',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->auth_token,
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode($request),
                'timeout' => 30,
            ]
        );
        
        if (is_wp_error($response)) {
            throw new \Exception('HTTP Error: ' . $response->get_error_message());
        }
    
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            throw new \Exception('API Error: ' . wp_remote_retrieve_body($response));
        }
    
        $body = json_decode(wp_remote_retrieve_body($response), true);
    
        if (!empty($body['results'][0]['response']['result'])) {
            $result = $body['results'][0]['response']['result'];
            if (strpos($sql, 'wp_posts') !== false) {
                error_log('WP_POSTS QUERY: ' . $sql);
                //error_log('WP_POSTS RESULT: ' . print_r($result, true));
            }
                
            // Format the results for WordPress
            return [
                'rows' => array_map(function($row) {
                    return array_map(function($col) {
                        return $col['value'] ?? null;
                    }, $row);
                }, $result['rows'] ?? []),
                'cols' => array_column($result['cols'], 'name'),
                'affected_row_count' => $result['affected_row_count'] ?? 0,
                'last_insert_rowid' => $result['last_insert_rowid'] ?? null
            ];
        }
    
        return null;
    }

    public function begin_transaction() {
        try {
            $this->query('BEGIN TRANSACTION', [], true);
            return true;
        } catch (\Exception $e) {
            error_log('Turso DB Transaction Error: ' . $e->getMessage());
            return false;
        }
    }

    public function commit() {
        try {
            if (!$this->current_baton) {
                throw new \Exception('No active transaction');
            }

            $requests = [
                [
                    'type' => 'execute',
                    'stmt' => ['sql' => 'COMMIT']
                ],
                ['type' => 'close']
            ];

            $this->make_request($requests);
            $this->current_baton = null;
            return true;
        } catch (\Exception $e) {
            error_log('Turso DB Commit Error: ' . $e->getMessage());
            return false;
        }
    }

    public function rollback() {
        try {
            if (!$this->current_baton) {
                throw new \Exception('No active transaction');
            }

            $requests = [
                [
                    'type' => 'execute',
                    'stmt' => ['sql' => 'ROLLBACK']
                ],
                ['type' => 'close']
            ];

            $this->make_request($requests);
            $this->current_baton = null;
            return true;
        } catch (\Exception $e) {
            error_log('Turso DB Rollback Error: ' . $e->getMessage());
            return false;
        }
    }

    public function get_row($sql, $params = []) {
        $result = $this->query($sql, $params);
        return $result['rows'][0] ?? null;
    }

    public function get_var($sql, $params = []) {
        $row = $this->get_row($sql, $params);
        if ($row) {
            return reset($row);
        }
        return null;
    }

    public function init_connection() {
        try {
            if (empty($this->base_url) || empty($this->auth_token)) {
                throw new \Exception('Database URL and Auth Token are required');
            }
            
            $result = $this->query("SELECT 1");
            return !empty($result);
        } catch (\Exception $e) {
            error_log('Turso DB Connection Error: ' . $e->getMessage());
            return false;
        }
    }

    private function should_log() {
        if ($this->settings instanceof Settings) {
            return $this->settings->get_setting('enable_logging') ?? false;
        }
        return $this->settings['enable_logging'] ?? false;
    }
}
