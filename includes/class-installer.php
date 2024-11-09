<?php
namespace TursoDBAdapter;

class Installer {
    private $client;
    private static $instance = null;

    public function __construct(Client $client = null) {
        if ($client) {
            $this->client = $client;
        } else {
            $options = get_option('turso_db_settings', []);
            $this->client = new Client($options);
        }
    }

    public static function init_tables(Client $client) {
        if (self::$instance === null) {
            self::$instance = new self($client);
        }
        return self::$instance->init();
    }

    /**
     * Drop all WordPress tables
     */
    public function drop_tables() {
        try {
            $tables = [
                'wp_commentmeta',
                'wp_comments',
                'wp_links',
                'wp_options',
                'wp_postmeta',
                'wp_posts',
                'wp_termmeta',
                'wp_terms',
                'wp_term_relationships',
                'wp_term_taxonomy',
                'wp_usermeta',
                'wp_users'
            ];

            foreach ($tables as $table) {
                $this->client->query("DROP TABLE IF EXISTS $table");
            }

            return true;
        } catch (\Exception $e) {
            error_log('Error dropping tables: ' . $e->getMessage());
            return false;
        }
    }

    public function init() {
        try {
            $result = $this->create_posts_tables();
            if (!$result) return false;

            $result = $this->create_users_tables();
            if (!$result) return false;

            $result = $this->create_terms_tables();
            if (!$result) return false;

            $result = $this->create_comments_tables();
            if (!$result) return false;

            $result = $this->create_options_table();
            if (!$result) return false;

            $result = $this->create_links_table();
            if (!$result) return false;

            $result = $this->insert_default_data();
            if (!$result) return false;

            return true;
        } catch (\Exception $e) {
            error_log('Error during Turso table creation: ' . $e->getMessage());
            return false;
        }
    }

    private function create_posts_tables() {
        try {
            // wp_posts
            $this->client->query("CREATE TABLE IF NOT EXISTS wp_posts (
                ID INTEGER PRIMARY KEY AUTOINCREMENT,
                post_author INTEGER NOT NULL DEFAULT 0,
                post_date TEXT NOT NULL DEFAULT '',
                post_date_gmt TEXT NOT NULL DEFAULT '',
                post_content TEXT NOT NULL,
                post_title TEXT NOT NULL,
                post_excerpt TEXT NOT NULL,
                post_status TEXT NOT NULL DEFAULT 'publish',
                comment_status TEXT NOT NULL DEFAULT 'open',
                ping_status TEXT NOT NULL DEFAULT 'open',
                post_password TEXT NOT NULL DEFAULT '',
                post_name TEXT NOT NULL DEFAULT '',
                to_ping TEXT NOT NULL,
                pinged TEXT NOT NULL,
                post_modified TEXT NOT NULL DEFAULT '',
                post_modified_gmt TEXT NOT NULL DEFAULT '',
                post_content_filtered TEXT NOT NULL,
                post_parent INTEGER NOT NULL DEFAULT 0,
                guid TEXT NOT NULL DEFAULT '',
                menu_order INTEGER NOT NULL DEFAULT 0,
                post_type TEXT NOT NULL DEFAULT 'post',
                post_mime_type TEXT NOT NULL DEFAULT '',
                comment_count INTEGER NOT NULL DEFAULT 0
            )");

            // Posts indexes
            $post_indexes = [
                "CREATE INDEX IF NOT EXISTS post_name ON wp_posts(post_name)",
                "CREATE INDEX IF NOT EXISTS type_status_date ON wp_posts(post_type,post_status,post_date,ID)",
                "CREATE INDEX IF NOT EXISTS post_parent ON wp_posts(post_parent)",
                "CREATE INDEX IF NOT EXISTS post_author ON wp_posts(post_author)"
            ];
            foreach ($post_indexes as $index) {
                $this->client->query($index);
            }

            // wp_postmeta
            $this->client->query("CREATE TABLE IF NOT EXISTS wp_postmeta (
                meta_id INTEGER PRIMARY KEY AUTOINCREMENT,
                post_id INTEGER NOT NULL DEFAULT 0,
                meta_key TEXT DEFAULT NULL,
                meta_value TEXT
            )");

            // Postmeta indexes
            $postmeta_indexes = [
                "CREATE INDEX IF NOT EXISTS post_id ON wp_postmeta(post_id)",
                "CREATE INDEX IF NOT EXISTS meta_key ON wp_postmeta(meta_key)"
            ];
            foreach ($postmeta_indexes as $index) {
                $this->client->query($index);
            }

            return true;
        } catch (\Exception $e) {
            error_log('Error creating posts tables: ' . $e->getMessage());
            return false;
        }
    }

    private function create_users_tables() {
        try {
            // wp_users
            $this->client->query("CREATE TABLE IF NOT EXISTS wp_users (
                ID INTEGER PRIMARY KEY AUTOINCREMENT,
                user_login TEXT NOT NULL DEFAULT '',
                user_pass TEXT NOT NULL DEFAULT '',
                user_nicename TEXT NOT NULL DEFAULT '',
                user_email TEXT NOT NULL DEFAULT '',
                user_url TEXT NOT NULL DEFAULT '',
                user_registered TEXT NOT NULL DEFAULT '',
                user_activation_key TEXT NOT NULL DEFAULT '',
                user_status INTEGER NOT NULL DEFAULT 0,
                display_name TEXT NOT NULL DEFAULT ''
            )");

            // Users indexes
            $users_indexes = [
                "CREATE INDEX IF NOT EXISTS user_login_key ON wp_users(user_login)",
                "CREATE INDEX IF NOT EXISTS user_nicename ON wp_users(user_nicename)",
                "CREATE INDEX IF NOT EXISTS user_email ON wp_users(user_email)"
            ];
            foreach ($users_indexes as $index) {
                $this->client->query($index);
            }

            // wp_usermeta
            $this->client->query("CREATE TABLE IF NOT EXISTS wp_usermeta (
                umeta_id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL DEFAULT 0,
                meta_key TEXT DEFAULT NULL,
                meta_value TEXT
            )");

            // Usermeta indexes
            $usermeta_indexes = [
                "CREATE INDEX IF NOT EXISTS user_id ON wp_usermeta(user_id)",
                "CREATE INDEX IF NOT EXISTS meta_key ON wp_usermeta(meta_key)"
            ];
            foreach ($usermeta_indexes as $index) {
                $this->client->query($index);
            }

            return true;
        } catch (\Exception $e) {
            error_log('Error creating users tables: ' . $e->getMessage());
            return false;
        }
    }

    private function create_terms_tables() {
        try {
            // wp_terms
            $this->client->query("CREATE TABLE IF NOT EXISTS wp_terms (
                term_id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL DEFAULT '',
                slug TEXT NOT NULL DEFAULT '',
                term_group INTEGER NOT NULL DEFAULT 0
            )");

            // Terms indexes
            $terms_indexes = [
                "CREATE INDEX IF NOT EXISTS slug ON wp_terms(slug)",
                "CREATE INDEX IF NOT EXISTS name ON wp_terms(name)"
            ];
            foreach ($terms_indexes as $index) {
                $this->client->query($index);
            }

            // wp_term_taxonomy
            $this->client->query("CREATE TABLE IF NOT EXISTS wp_term_taxonomy (
                term_taxonomy_id INTEGER PRIMARY KEY AUTOINCREMENT,
                term_id INTEGER NOT NULL DEFAULT 0,
                taxonomy TEXT NOT NULL DEFAULT '',
                description TEXT NOT NULL,
                parent INTEGER NOT NULL DEFAULT 0,
                count INTEGER NOT NULL DEFAULT 0
            )");

            // Term taxonomy indexes
            $term_taxonomy_indexes = [
                "CREATE UNIQUE INDEX IF NOT EXISTS term_id_taxonomy ON wp_term_taxonomy(term_id,taxonomy)",
                "CREATE INDEX IF NOT EXISTS taxonomy ON wp_term_taxonomy(taxonomy)"
            ];
            foreach ($term_taxonomy_indexes as $index) {
                $this->client->query($index);
            }

            // wp_term_relationships
            $this->client->query("CREATE TABLE IF NOT EXISTS wp_term_relationships (
                object_id INTEGER NOT NULL DEFAULT 0,
                term_taxonomy_id INTEGER NOT NULL DEFAULT 0,
                term_order INTEGER NOT NULL DEFAULT 0,
                PRIMARY KEY (object_id, term_taxonomy_id)
            )");

            // Term relationships indexes
            $this->client->query(
                "CREATE INDEX IF NOT EXISTS term_taxonomy_id ON wp_term_relationships(term_taxonomy_id)"
            );

            // wp_termmeta
            $this->client->query("CREATE TABLE IF NOT EXISTS wp_termmeta (
                meta_id INTEGER PRIMARY KEY AUTOINCREMENT,
                term_id INTEGER NOT NULL DEFAULT 0,
                meta_key TEXT DEFAULT NULL,
                meta_value TEXT
            )");

            // Termmeta indexes
            $termmeta_indexes = [
                "CREATE INDEX IF NOT EXISTS term_id ON wp_termmeta(term_id)",
                "CREATE INDEX IF NOT EXISTS meta_key ON wp_termmeta(meta_key)"
            ];
            foreach ($termmeta_indexes as $index) {
                $this->client->query($index);
            }

            return true;
        } catch (\Exception $e) {
            error_log('Error creating terms tables: ' . $e->getMessage());
            return false;
        }
    }

    private function create_comments_tables() {
        try {
            // wp_comments
            $this->client->query("CREATE TABLE IF NOT EXISTS wp_comments (
                comment_ID INTEGER PRIMARY KEY AUTOINCREMENT,
                comment_post_ID INTEGER NOT NULL DEFAULT 0,
                comment_author TEXT NOT NULL,
                comment_author_email TEXT NOT NULL DEFAULT '',
                comment_author_url TEXT NOT NULL DEFAULT '',
                comment_author_IP TEXT NOT NULL DEFAULT '',
                comment_date TEXT NOT NULL DEFAULT '',
                comment_date_gmt TEXT NOT NULL DEFAULT '',
                comment_content TEXT NOT NULL,
                comment_karma INTEGER NOT NULL DEFAULT 0,
                comment_approved TEXT NOT NULL DEFAULT '1',
                comment_agent TEXT NOT NULL DEFAULT '',
                comment_type TEXT NOT NULL DEFAULT 'comment',
                comment_parent INTEGER NOT NULL DEFAULT 0,
                user_id INTEGER NOT NULL DEFAULT 0
            )");

            // Comments indexes
            $comments_indexes = [
                "CREATE INDEX IF NOT EXISTS comment_post_ID ON wp_comments(comment_post_ID)",
                "CREATE INDEX IF NOT EXISTS comment_approved_date_gmt ON wp_comments(comment_approved,comment_date_gmt)",
                "CREATE INDEX IF NOT EXISTS comment_date_gmt ON wp_comments(comment_date_gmt)",
                "CREATE INDEX IF NOT EXISTS comment_parent ON wp_comments(comment_parent)",
                "CREATE INDEX IF NOT EXISTS comment_author_email ON wp_comments(comment_author_email)"
            ];
            foreach ($comments_indexes as $index) {
                $this->client->query($index);
            }

            // wp_commentmeta
            $this->client->query("CREATE TABLE IF NOT EXISTS wp_commentmeta (
                meta_id INTEGER PRIMARY KEY AUTOINCREMENT,
                comment_id INTEGER NOT NULL DEFAULT 0,
                meta_key TEXT DEFAULT NULL,
                meta_value TEXT
            )");

            // Commentmeta indexes
            $commentmeta_indexes = [
                "CREATE INDEX IF NOT EXISTS comment_id ON wp_commentmeta(comment_id)",
                "CREATE INDEX IF NOT EXISTS meta_key ON wp_commentmeta(meta_key)"
            ];
            foreach ($commentmeta_indexes as $index) {
                $this->client->query($index);
            }

            return true;
        } catch (\Exception $e) {
            error_log('Error creating comments tables: ' . $e->getMessage());
            return false;
        }
    }

    private function create_options_table() {
        try {
            $this->client->query("CREATE TABLE IF NOT EXISTS wp_options (
                option_id INTEGER PRIMARY KEY AUTOINCREMENT,
                option_name TEXT UNIQUE NOT NULL DEFAULT '',
                option_value TEXT NOT NULL,
                autoload TEXT NOT NULL DEFAULT 'yes'
            )");

            // Options indexes
            $options_indexes = [
                "CREATE UNIQUE INDEX IF NOT EXISTS option_name ON wp_options(option_name)",
                "CREATE INDEX IF NOT EXISTS autoload ON wp_options(autoload)"
            ];
            foreach ($options_indexes as $index) {
                $this->client->query($index);
            }

            return true;
        } catch (\Exception $e) {
            error_log('Error creating options table: ' . $e->getMessage());
            return false;
        }
    }

    private function create_links_table() {
        try {
            $this->client->query("CREATE TABLE IF NOT EXISTS wp_links (
                link_id INTEGER PRIMARY KEY AUTOINCREMENT,
                link_url TEXT NOT NULL DEFAULT '',
                link_name TEXT NOT NULL DEFAULT '',
                link_image TEXT NOT NULL DEFAULT '',
                link_target TEXT NOT NULL DEFAULT '',
                link_description TEXT NOT NULL DEFAULT '',
                link_visible TEXT NOT NULL DEFAULT 'Y',
                link_owner INTEGER NOT NULL DEFAULT 1,
                link_rating INTEGER NOT NULL DEFAULT 0,
                link_updated TEXT NOT NULL DEFAULT '',
                link_rel TEXT NOT NULL DEFAULT '',
                link_notes TEXT NOT NULL DEFAULT '',
                link_rss TEXT NOT NULL DEFAULT ''
            )");

            // Links indexes
            $this->client->query(
                "CREATE INDEX IF NOT EXISTS link_visible ON wp_links(link_visible)"
            );

            return true;
        } catch (\Exception $e) {
            error_log('Error creating links table: ' . $e->getMessage());
            return false;
        }
    }

    private function insert_default_data() {
        try {
            // Insert default category
            $this->client->query(
                "INSERT OR IGNORE INTO wp_terms (term_id, name, slug, term_group) VALUES (1, 'Uncategorized', 'uncategorized', 0)"
            );

            $this->client->query(
                "INSERT OR IGNORE INTO wp_term_taxonomy (term_taxonomy_id, term_id, taxonomy, description, parent, count) VALUES (1, 1, 'category', '', 0, 0)"
            );

            // Create default admin user
            $admin_password = wp_hash_password('password');
            $current_time = current_time('mysql', true);
            
            // Insert admin user
            $this->client->query(
                "INSERT OR IGNORE INTO wp_users (
                    ID, user_login, user_pass, user_nicename, user_email,
                    user_registered, user_status, display_name
                ) VALUES (
                    1, 'admin', ?, 'admin', 'dev-email@wpengine.local',
                    ?, 0, 'Administrator'
                )", 
                [$admin_password, $current_time]
            );

            // Insert admin user meta
            $admin_meta = [
                ['nickname', 'admin'],
                ['first_name', ''],
                ['last_name', ''],
                ['description', ''],
                ['rich_editing', 'true'],
                ['syntax_highlighting', 'true'],
                ['comment_shortcuts', 'false'],
                ['admin_color', 'fresh'],
                ['use_ssl', '0'],
                ['show_admin_bar_front', 'true'],
                ['locale', ''],
                ['wp_capabilities', serialize(['administrator' => true])],
                ['wp_user_level', '10']
            ];

            foreach ($admin_meta as $meta) {
                $this->client->query(
                    "INSERT OR IGNORE INTO wp_usermeta (user_id, meta_key, meta_value) VALUES (1, ?, ?)",
                    $meta
                );
            }
            // Essential options
            $site_url = get_site_url();
            $home_url = get_home_url();
            
            $essential_options = [
                ['siteurl', $site_url],
                ['home', $home_url],
                ['blogname', 'My WordPress Site'],
                ['blogdescription', 'Just another WordPress site'],
                ['users_can_register', '0'],
                ['admin_email', get_option('admin_email')],
                ['start_of_week', '1'],
                ['use_balanceTags', '0'],
                ['use_smilies', '1'],
                ['require_name_email', '1'],
                ['comments_notify', '1'],
                ['posts_per_rss', '10'],
                ['rss_use_excerpt', '0'],
                ['default_category', '1'],
                ['default_comment_status', 'open'],
                ['default_ping_status', 'open'],
                ['default_pingback_flag', '1'],
                ['posts_per_page', '10'],
                ['date_format', 'F j, Y'],
                ['time_format', 'g:i a'],
                ['permalink_structure', '/%year%/%monthnum%/%day%/%postname%/'],
                ['db_version', '53496'],
                ['blog_charset', 'UTF-8'],
                ['WPLANG', '']
            ];

            foreach ($essential_options as $option) {
                $this->client->query(
                    "INSERT OR IGNORE INTO wp_options (option_name, option_value, autoload) VALUES (?, ?, 'yes')", 
                    $option
                );
            }

            return true;
        } catch (\Exception $e) {
            error_log('Error inserting default data: ' . $e->getMessage());
            return false;
        }
    }

    public function get_existing_tables() {
        try {
            $tables = [];
            $result = $this->client->query("SELECT name FROM sqlite_master WHERE type='table' AND name LIKE 'wp_%'");
            if (!empty($result['rows'])) {
                foreach ($result['rows'] as $row) {
                    $tables[] = $row[0];
                }
            }
            return $tables;
        } catch (\Exception $e) {
            error_log('Error getting existing tables: ' . $e->getMessage());
            return [];
        }
    }
}
