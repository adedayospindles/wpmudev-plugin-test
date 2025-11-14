<?php
/**
 * Unit tests for Posts Maintenance functionality
 *
 * @package WPMUDEV\PluginTest\Tests
 */

use WPMUDEV\PluginTest\Admin\Posts_Maintenance;

defined('ABSPATH') || exit;

class TestPostsMaintenance extends WP_UnitTestCase {

    protected static $post_ids = [];

    /**
     * Setup test posts before each test
     */
    public function setUp(): void {
        parent::setUp();

        // Reset post IDs to ensure fresh state for each test
        self::$post_ids = [
            'post'   => [],
            'page'   => [],
            'custom' => [],
        ];

        // Ensure post types exist
        foreach (['post', 'page', 'custom'] as $type) {
            if (!post_type_exists($type)) {
                register_post_type($type, ['public' => true]);
            }
        }

        // Create published posts
        foreach (range(1, 5) as $_) {
            self::$post_ids['post'][] = $this->factory->post->create([
                'post_type'   => 'post',
                'post_status' => 'publish',
            ]);
        }

        foreach (range(1, 3) as $_) {
            self::$post_ids['page'][] = $this->factory->post->create([
                'post_type'   => 'page',
                'post_status' => 'publish',
            ]);
        }

        foreach (range(1, 2) as $_) {
            self::$post_ids['custom'][] = $this->factory->post->create([
                'post_type'   => 'custom',
                'post_status' => 'publish',
            ]);
        }
    }

    /**
     * Test that scheduled_scan updates post meta
     */
    public function test_scan_posts_updates_meta() {
        $scanner = new Posts_Maintenance();
        $ref = new \ReflectionMethod($scanner, 'scheduled_scan');
        $ref->setAccessible(true);

        $ref->invokeArgs($scanner, [['post', 'page', 'custom']]);
        wp_cache_flush();

        foreach (['post', 'page', 'custom'] as $type) {
            foreach (self::$post_ids[$type] as $post_id) {
                wp_cache_delete($post_id, 'post_meta');
                $meta = get_post_meta($post_id, 'wpmudev_test_last_scan', true);
                $this->assertNotEmpty($meta, "Post meta for {$post_id} should not be empty");
                $this->assertGreaterThan(0, intval($meta), "Post meta should be a valid timestamp");
            }
        }
    }

    /**
     * Test scan with default post types
     */
    public function test_scan_with_no_post_types_defaults_to_post_and_page() {
        $scanner = new Posts_Maintenance();
        $ref = new \ReflectionMethod($scanner, 'scheduled_scan');
        $ref->setAccessible(true);

        $ref->invoke($scanner);
        wp_cache_flush();

        foreach (array_merge(self::$post_ids['post'], self::$post_ids['page']) as $post_id) {
            wp_cache_delete($post_id, 'post_meta');
            $meta = get_post_meta($post_id, 'wpmudev_test_last_scan', true);
            $this->assertNotEmpty($meta, "Meta should not be empty for post ID {$post_id}");
        }
    }

    /**
     * Test that non-published posts are skipped
     */
    public function test_scan_skips_non_published_posts() {
        $draft_id = $this->factory->post->create([
            'post_type'   => 'post',
            'post_status' => 'draft',
        ]);

        $scanner = new Posts_Maintenance();
        $ref = new \ReflectionMethod($scanner, 'scheduled_scan');
        $ref->setAccessible(true);

        $ref->invoke($scanner);
        wp_cache_flush();

        wp_cache_delete($draft_id, 'post_meta');
        $meta = get_post_meta($draft_id, 'wpmudev_test_last_scan', true);
        $this->assertEmpty($meta, "Draft posts should not be scanned or have meta updated");
    }

    /**
     * Test scan is repeatable
     */
    public function test_scan_is_repeatable() {
        $scanner = new Posts_Maintenance();
        $ref = new \ReflectionMethod($scanner, 'scheduled_scan');
        $ref->setAccessible(true);

        // First scan
        $ref->invokeArgs($scanner, [['post', 'page', 'custom']]);
        wp_cache_flush();

        $first_scan = [];
        foreach (self::$post_ids['post'] as $post_id) {
            wp_cache_delete($post_id, 'post_meta');
            $first_scan[$post_id] = get_post_meta($post_id, 'wpmudev_test_last_scan', true);
        }

        sleep(2); // Ensure timestamp difference

        // Second scan
        $ref->invokeArgs($scanner, [['post', 'page', 'custom']]);
        wp_cache_flush();

        foreach (self::$post_ids['post'] as $post_id) {
            wp_cache_delete($post_id, 'post_meta');
            $second_scan = get_post_meta($post_id, 'wpmudev_test_last_scan', true);
            $this->assertGreaterThan($first_scan[$post_id], $second_scan, "Second scan should update meta timestamp for post ID {$post_id}");
        }
    }
}
