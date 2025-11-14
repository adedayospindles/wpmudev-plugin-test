<?php
/**
 * Unit tests for Posts Maintenance functionality.
 *
 * @package WPMUDEV\PluginTest\Tests
 */

use WPMUDEV\PluginTest\Admin\Posts_Maintenance;

defined('ABSPATH') || exit;

/**
 * Class TestPostsMaintenance
 *
 * Contains unit tests for the Posts_Maintenance class.
 */
class TestPostsMaintenance extends WP_UnitTestCase {

    /**
     * Static array to hold created post IDs for testing.
     *
     * @var array
     */
    protected static $post_ids = [];

    /**
     * Setup test posts before each test.
     */
    public function setUp(): void {
        parent::setUp();

        // Reset post IDs to ensure fresh state for each test
        self::$post_ids = [
            'post'   => [],
            'page'   => [],
            'custom' => [],
        ];

        // Ensure required post types exist
        foreach (['post', 'page', 'custom'] as $type) {
            if (!post_type_exists($type)) {
                register_post_type($type, ['public' => true]);
            }
        }

        // Create published posts for testing
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
     * Test that scheduled_scan updates post meta for all post types.
     */
    public function test_scan_posts_updates_meta() {
        $scanner = new Posts_Maintenance();
        $ref = new \ReflectionMethod($scanner, 'scheduled_scan');
        $ref->setAccessible(true);

        // Invoke scan on all post types
        $ref->invokeArgs($scanner, [['post', 'page', 'custom']]);
        wp_cache_flush();

        // Check meta for all post types
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
     * Test that scan defaults to 'post' and 'page' when no post types are provided.
     */
    public function test_scan_with_no_post_types_defaults_to_post_and_page() {
        $scanner = new Posts_Maintenance();
        $ref = new \ReflectionMethod($scanner, 'scheduled_scan');
        $ref->setAccessible(true);

        // Invoke scan with no arguments (default types)
        $ref->invoke($scanner);
        wp_cache_flush();

        // Verify meta for default post types
        foreach (array_merge(self::$post_ids['post'], self::$post_ids['page']) as $post_id) {
            wp_cache_delete($post_id, 'post_meta');
            $meta = get_post_meta($post_id, 'wpmudev_test_last_scan', true);

            $this->assertNotEmpty($meta, "Meta should not be empty for post ID {$post_id}");
        }
    }

    /**
     * Test that non-published posts are skipped during scan.
     */
    public function test_scan_skips_non_published_posts() {
        // Create a draft post
        $draft_id = $this->factory->post->create([
            'post_type'   => 'post',
            'post_status' => 'draft',
        ]);

        $scanner = new Posts_Maintenance();
        $ref = new \ReflectionMethod($scanner, 'scheduled_scan');
        $ref->setAccessible(true);

        // Run scan
        $ref->invoke($scanner);
        wp_cache_flush();

        // Ensure draft post has no meta updated
        wp_cache_delete($draft_id, 'post_meta');
        $meta = get_post_meta($draft_id, 'wpmudev_test_last_scan', true);
        $this->assertEmpty($meta, "Draft posts should not be scanned or have meta updated");
    }

    /**
     * Test that scan is repeatable and updates timestamps on subsequent runs.
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

        // Wait to ensure timestamp difference
        sleep(2);

        // Second scan
        $ref->invokeArgs($scanner, [['post', 'page', 'custom']]);
        wp_cache_flush();

        // Validate that second scan updates timestamps
        foreach (self::$post_ids['post'] as $post_id) {
            wp_cache_delete($post_id, 'post_meta');
            $second_scan = get_post_meta($post_id, 'wpmudev_test_last_scan', true);
            $this->assertGreaterThan(
                $first_scan[$post_id],
                $second_scan,
                "Second scan should update meta timestamp for post ID {$post_id}"
            );
        }
    }
}
