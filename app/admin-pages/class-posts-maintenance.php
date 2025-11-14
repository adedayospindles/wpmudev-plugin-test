<?php
/**
 * Posts Maintenance Admin Page
 *
 * Provides a UI to scan posts, update meta, and schedule daily maintenance.
 *
 * @package WPMUDEV\PluginTest\Admin
 */

namespace WPMUDEV\PluginTest\App\Admin_Pages;

defined('ABSPATH') || exit;

class Posts_Maintenance {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init() {
        add_action('admin_menu', [$this, 'register_admin_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_wpmudev_get_posts_by_type', [$this, 'ajax_get_posts_by_type']);
        add_action('wp_ajax_wpmudev_scan_posts', [$this, 'ajax_scan_posts']);
        add_action('wpmudev_background_scan_post', [$this, 'background_scan_post'], 10, 1);
        add_action('wpmudev_daily_post_scan', [$this, 'scheduled_scan']);
        add_action('wp_ajax_wpmudev_get_scan_log', [$this, 'ajax_get_scan_log']);

        // Add Last Scan column and sorting/filtering for all public post types
        add_action('admin_init', [$this, 'setup_last_scan_column']);

        // Schedule daily scan
        if (!wp_next_scheduled('wpmudev_daily_post_scan')) {
            wp_schedule_event(time(), 'daily', 'wpmudev_daily_post_scan');
        }
    }

    public function register_admin_page() {
        add_menu_page(
            __('Posts Maintenance', 'wpmudev-plugin-test'),
            __('Posts Maintenance', 'wpmudev-plugin-test'),
            'manage_options',
            'wpmudev-posts-maintenance',
            [$this, 'render_admin_page'],
            'dashicons-admin-tools',
            80
        );
    }

    public function enqueue_assets($hook) {
        if ($hook !== 'toplevel_page_wpmudev-posts-maintenance') return;

        wp_enqueue_script('wp-api');

        $asset_file = plugin_dir_path(__FILE__) . '../../assets/js/postsmaintenancepage.min.asset.php';
        $asset_data = file_exists($asset_file) ? include $asset_file : [
            'dependencies' => ['wp-element', 'wp-i18n', 'wp-components'],
            'version'      => '1.0.0',
        ];

        wp_enqueue_script(
            'wpmudev-posts-maintenance-js',
            plugin_dir_url(__FILE__) . '../../assets/js/postsmaintenancepage.min.js',
            $asset_data['dependencies'],
            $asset_data['version'],
            true
        );

        $post_types = get_post_types(['public' => true], 'objects');
        $post_types_data = is_array($post_types) ? array_values(array_map(function($pt) {
            return [
                'name'  => $pt->name,
                'label' => $pt->labels->singular_name,
            ];
        }, $post_types)) : [];

        wp_localize_script('wpmudev-posts-maintenance-js', 'WPMUDEV_PM', [
            'ajax_url'  => admin_url('admin-ajax.php'),
            'nonce'     => wp_create_nonce('wpmudev_scan_posts_nonce'),
            'postTypes' => $post_types_data,
        ]);

        wp_enqueue_style(
            'wpmudev-posts-maintenance-css',
            plugin_dir_url(__FILE__) . '../../assets/css/postsmaintenancepage.min.css',
            [],
            $asset_data['version']
        );
    }

    public function ajax_get_posts_by_type() {
        check_ajax_referer('wpmudev_scan_posts_nonce', 'nonce');

        $type = sanitize_text_field($_POST['post_type'] ?? '');
        if (!post_type_exists($type)) {
            wp_send_json_error(['message' => 'Invalid post type']);
        }

        $posts = get_posts([
            'post_type'   => $type,
            'post_status' => 'publish',
            'numberposts' => 100,
            'fields'      => 'ids',
        ]);

        $data = array_map(function($id) {
            return [
                'id'    => $id,
                'title' => get_the_title($id),
            ];
        }, $posts);

        wp_send_json_success(['posts' => $data]);
    }

    public function render_admin_page() {
        echo '<div id="wpmudev-posts-maintenance-root" class="wrap"></div>';
    }

    public function ajax_scan_posts() {
        check_ajax_referer('wpmudev_scan_posts_nonce', 'nonce');

        if (!empty($_POST['post_ids'])) {
            $post_ids = array_map('intval', $_POST['post_ids']);
            $queued = 0;

            foreach ($post_ids as $post_id) {
                if (function_exists('as_enqueue_async_action')) {
                    as_enqueue_async_action('wpmudev_background_scan_post', ['post_id' => $post_id]);
                    $queued++;
                } else {
                    $this->update_post_scan($post_id);
                    $queued++;
                }
            }

            wp_send_json_success([
                'message' => "Scan scheduled for {$queued} posts in background."
            ]);
            return;
        }

        $post_types = !empty($_POST['post_types']) ? array_map('sanitize_text_field', $_POST['post_types']) : ['post', 'page'];
        $batch_size = 20;
        $total = 0;

        $query_args = [
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => $batch_size,
            'paged'          => 1,
            'fields'         => 'ids',
        ];

        do {
            $query = new \WP_Query($query_args);
            if (!$query->have_posts()) break;

            foreach ($query->posts as $post_id) {
                $this->update_post_scan($post_id);
                $total++;
            }

            $query_args['paged']++;
            wp_reset_postdata();

        } while (count($query->posts) === $batch_size);

        wp_send_json_success([
            'message' => "Scan completed. Total posts processed: {$total}"
        ]);
    }

    public function scheduled_scan($post_types = null) {
        if (empty($post_types)) $post_types = ['post', 'page'];

        foreach ($post_types as $type) {
            if (!post_type_exists($type)) {
                register_post_type($type, ['public' => true]);
            }
        }

        $posts = get_posts([
            'post_type'   => $post_types,
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields'      => 'ids',
        ]);

        foreach ($posts as $post_id) {
            $this->update_post_scan($post_id);
        }

        error_log('Scanned ' . count($posts) . ' posts.');
    }

    public function background_scan_post($post_id) {
        if (!get_post($post_id)) {
            error_log("Invalid post ID: $post_id");
            return;
        }
        $this->update_post_scan($post_id);
    }

    private function update_post_scan($post_id) {
        $timestamp = current_time('mysql');
        update_post_meta($post_id, 'wpmudev_test_last_scan', $timestamp);
        $this->log_scan_event($post_id, 'manual');
        error_log("Post $post_id scanned at $timestamp");
    }

    private function log_scan_event($post_id, $source = 'manual') {
        $log = get_option('wpmudev_scan_log', []);
        $log[] = [
            'timestamp' => current_time('mysql'),
            'post_id'   => $post_id,
            'type'      => get_post_type($post_id),
            'source'    => $source,
        ];
        if (count($log) > 100) $log = array_slice($log, -100);
        update_option('wpmudev_scan_log', $log);
    }

    public function ajax_get_scan_log() {
        check_ajax_referer('wpmudev_scan_posts_nonce', 'nonce');
        $log = get_option('wpmudev_scan_log', []);
        //$log = array_slice($log, -15); // Limit UI display
        wp_send_json_success(['log' => $log]);
    }

    /**
     * Setup Last Scan column with sorting/filtering.
     */
    public function setup_last_scan_column() {
        $post_types = get_post_types(['public' => true], 'names');

        foreach ($post_types as $pt) {
            // Add column
            add_filter("manage_{$pt}_posts_columns", function($columns) {
                $columns['wpmudev_last_scan'] = __('Last Scan', 'wpmudev-plugin-test');
                return $columns;
            });

            // Show column data
            add_action("manage_{$pt}_posts_custom_column", function($column, $post_id) {
                if ($column === 'wpmudev_last_scan') {
                    $last_scan = get_post_meta($post_id, 'wpmudev_test_last_scan', true);
                    echo $last_scan ? esc_html($last_scan) : __('Never', 'wpmudev-plugin-test');
                }
            }, 10, 2);

            // Make column sortable
            add_filter("manage_edit-{$pt}_sortable_columns", function($columns) {
                $columns['wpmudev_last_scan'] = 'wpmudev_last_scan';
                return $columns;
            });

            // Apply sorting
            add_action('pre_get_posts', function($query) use ($pt) {
                if (!is_admin() || !$query->is_main_query()) return;
                if ($query->get('post_type') !== $pt) return;

                $orderby = $query->get('orderby');
                if ($orderby === 'wpmudev_last_scan') {
                    $query->set('meta_key', 'wpmudev_test_last_scan');
                    $query->set('orderby', 'meta_value');
                }
            });

            // Add date filter for Last Scan
            add_action('restrict_manage_posts', function() use ($pt) {
                global $typenow;
                if ($typenow !== $pt) return;

                $value = $_GET['wpmudev_last_scan_filter'] ?? '';
                echo '<input type="date" name="wpmudev_last_scan_filter" value="'.esc_attr($value).'" placeholder="Filter Last Scan" />';
            });

            // Apply date filter
            add_filter('pre_get_posts', function($query) use ($pt) {
                global $typenow;
                if (!is_admin() || !$query->is_main_query()) return;
                if ($typenow !== $pt) return;

                if (!empty($_GET['wpmudev_last_scan_filter'])) {
                    $query->set('meta_query', [
                        [
                            'key'     => 'wpmudev_test_last_scan',
                            'value'   => $_GET['wpmudev_last_scan_filter'],
                            'compare' => '>=',
                            'type'    => 'DATE',
                        ],
                    ]);
                }
            });
        }
    }
}
