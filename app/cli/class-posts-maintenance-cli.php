<?php
/**
 * WP-CLI command for Posts Maintenance.
 *
 * Scans posts and pages, updates meta, logs scan events,
 * and provides progress feedback.
 *
 * @package WPMUDEV\PluginTest\CLI
 */

namespace WPMUDEV\PluginTest\CLI;

defined('WP_CLI') || exit;

use WP_CLI;
use WP_CLI_Command;

class Posts_Maintenance_CLI extends WP_CLI_Command {

    /*--------------------------------------------------------------
    # MAIN COMMAND: POSTS MAINTENANCE SCAN
    --------------------------------------------------------------*/

    /**
     * Scan posts/pages, update meta, and log events.
     *
     * ## OPTIONS
     *
     * [--post_type=<type>]
     * : Comma-separated list of post types. Default: 'post,page'.
     *
     * [--batch=<number>]
     * : Batch size per cycle. Default: 20.
     *
     * [--simulate]
     * : Run without saving updates.
     *
     * ## EXAMPLES
     *
     * wp wpmudev posts-maintenance
     * wp wpmudev posts-maintenance --post_type=post,page
     * wp wpmudev posts-maintenance --post_type=product --batch=50
     * wp wpmudev posts-maintenance --simulate
     *
     * @when after_wp_load
     */
    public function __invoke($args, $assoc_args) {

        // Determine post types
        $post_types = !empty($assoc_args['post_type'])
            ? array_map('trim', explode(',', $assoc_args['post_type']))
            : ['post', 'page'];

        // Determine batch size
        $batch_size = !empty($assoc_args['batch']) ? intval($assoc_args['batch']) : 20;

        // Simulation flag (no saving)
        $simulate = !empty($assoc_args['simulate']);

        /*--------------------------------------------------------------
        # PREP: COLLECT ALL POSTS
        --------------------------------------------------------------*/

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

        WP_CLI::log("Starting Posts Maintenance scan for post types: " . implode(', ', $post_types));
        WP_CLI::log("Total posts to process: {$total_posts}");

        // Progress bar setup
        $progress = \WP_CLI\Utils\make_progress_bar('Processing posts', $total_posts);

        $total_processed = 0;
        $total_failed    = 0;
        $page            = 1;

        /*--------------------------------------------------------------
        # PROCESSING LOOP
        --------------------------------------------------------------*/

        do {
            // Query next batch
            $query_args = [
                'post_type'      => $post_types,
                'post_status'    => 'publish',
                'posts_per_page' => $batch_size,
                'paged'          => $page,
                'fields'         => 'ids',
            ];

            $query = new \WP_Query($query_args);

            if (!$query->have_posts()) {
                break;
            }

            // Handle each post in batch
            foreach ($query->posts as $post_id) {

                // Only update if not simulating
                if (!$simulate) {

                    // Write maintenance timestamp
                    $updated = update_post_meta($post_id, 'wpmudev_test_last_scan', current_time('timestamp'));

                    if ($updated === false) {
                        $total_failed++;
                        WP_CLI::warning("Failed to update post ID {$post_id}");
                        $progress->tick();
                        continue;
                    }

                    // Log the scan event
                    $this->log_scan_event($post_id, 'cli');
                }

                $total_processed++;
                $progress->tick();
            }

            $page++;

            // Clean memory
            wp_reset_postdata();

        } while (count($query->posts) === $batch_size);

        /*--------------------------------------------------------------
        # WRAP-UP
        --------------------------------------------------------------*/

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

    /*--------------------------------------------------------------
    # HELPERS
    --------------------------------------------------------------*/

    /**
     * Log a scan event.
     *
     * @param int    $post_id Post ID.
     * @param string $source  Event source (cli/admin).
     */
    private function log_scan_event($post_id, $source = 'cli') {

        // Retrieve existing log list
        $log = get_option('wpmudev_scan_log', []);

        // Add new log entry
        $log[] = [
            'timestamp' => current_time('mysql'),
            'post_id'   => $post_id,
            'type'      => get_post_type($post_id),
            'source'    => $source,
        ];

        // Keep last 100 logs only
        if (count($log) > 100) {
            $log = array_slice($log, -100);
        }

        update_option('wpmudev_scan_log', $log);
    }
}

/*--------------------------------------------------------------
# REGISTER CLI COMMAND
--------------------------------------------------------------*/

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('wpmudev posts-maintenance', __NAMESPACE__ . '\\Posts_Maintenance_CLI');
}
