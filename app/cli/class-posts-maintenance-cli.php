<?php
/**
 * WP-CLI command for Posts Maintenance
 *
 * Scans posts and pages, updates meta, logs scan events, and provides progress feedback.
 *
 * @package WPMUDEV\PluginTest\CLI
 */

namespace WPMUDEV\PluginTest\CLI;

defined('WP_CLI') || exit;

use WP_CLI;
use WP_CLI_Command;

class Posts_Maintenance_CLI extends WP_CLI_Command {

    /**
     * Scan posts and pages, update meta for maintenance, and log scan events.
     *
     * ## OPTIONS
     *
     * [--post_type=<type>]
     * : Comma-separated list of post types to scan. Default: 'post,page'.
     *
     * [--batch=<number>]
     * : Number of posts to process per batch. Default: 20.
     *
     * [--simulate]
     * : Optional flag to simulate the process without updating meta.
     *
     * ## EXAMPLES
     *
     *     wp wpmudev posts-maintenance
     *     wp wpmudev posts-maintenance --post_type=post,page
     *     wp wpmudev posts-maintenance --post_type=custom_post --batch=50
     *     wp wpmudev posts-maintenance --simulate
     *
     * @when after_wp_load
     */
    public function __invoke($args, $assoc_args) {

        $post_types = !empty($assoc_args['post_type'])
            ? array_map('trim', explode(',', $assoc_args['post_type']))
            : ['post', 'page'];

        $batch_size = !empty($assoc_args['batch']) ? intval($assoc_args['batch']) : 20;
        $simulate = !empty($assoc_args['simulate']);

        // Get total posts count for progress bar
        $all_posts = get_posts([
            'post_type'   => $post_types,
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields'      => 'ids',
        ]);

        $total_posts = count($all_posts);

        if ($total_posts === 0) {
            WP_CLI::warning("No posts found for post types: " . implode(', ', $post_types));
            return;
        }

        $total_processed = 0;
        $total_failed = 0;
        $page = 1;

        WP_CLI::log("Starting Posts Maintenance scan for post types: " . implode(', ', $post_types));
        WP_CLI::log("Total posts to process: {$total_posts}");

        $progress = \WP_CLI\Utils\make_progress_bar('Processing posts', $total_posts);

        do {
            $query_args = [
                'post_type'      => $post_types,
                'post_status'    => 'publish',
                'posts_per_page' => $batch_size,
                'paged'          => $page,
                'fields'         => 'ids',
            ];

            $query = new \WP_Query($query_args);

            if (!$query->have_posts()) break;

            foreach ($query->posts as $post_id) {

                if (!$simulate) {
                    $updated = update_post_meta($post_id, 'wpmudev_test_last_scan', current_time('timestamp'));
                    if ($updated === false) {
                        $total_failed++;
                        WP_CLI::warning("Failed to update post ID {$post_id}");
                        $progress->tick();
                        continue;
                    }

                    // Log scan event like admin scan
                    $this->log_scan_event($post_id, 'cli');
                }

                $total_processed++;
                $progress->tick();
            }

            $page++;

            // Clean up to free memory
            wp_reset_postdata();

        } while (count($query->posts) === $batch_size);

        $progress->finish();

        WP_CLI::success("Posts Maintenance completed.");
        WP_CLI::success("Total posts processed: {$total_processed}");
        if ($total_failed > 0) {
            WP_CLI::warning("Total posts failed to update: {$total_failed}");
        }
        if ($simulate) {
            WP_CLI::log("Simulation mode enabled. No meta was actually updated.");
        }
    }

    /**
     * Log a scan event (similar to admin scan logging).
     *
     * @param int $post_id
     * @param string $source
     */
    private function log_scan_event($post_id, $source = 'cli') {
        $log = get_option('wpmudev_scan_log', []);

        $log[] = [
            'timestamp' => current_time('mysql'),
            'post_id'   => $post_id,
            'type'      => get_post_type($post_id),
            'source'    => $source,
        ];

        // Keep only the most recent 100 logs
        if (count($log) > 100) {
            $log = array_slice($log, -100);
        }

        update_option('wpmudev_scan_log', $log);
    }
}

// Register WP-CLI command
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('wpmudev posts-maintenance', __NAMESPACE__ . '\\Posts_Maintenance_CLI');
}
