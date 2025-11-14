<?php
/**
 * PHPUnit bootstrap file for Wpmudev_Plugin_Test
 *
 * @package Wpmudev_Plugin_Test
 */

// -----------------------------------------------------------------------------
// Load PHPUnit Polyfills (for backwards compatibility with different PHPUnit versions)
// -----------------------------------------------------------------------------
require_once __DIR__ . '/../vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

// -----------------------------------------------------------------------------
// Determine WordPress tests directory
// -----------------------------------------------------------------------------
$_tests_dir = getenv('WP_TESTS_DIR');

if (!$_tests_dir) {
    $_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

// -----------------------------------------------------------------------------
// Forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap
// -----------------------------------------------------------------------------
$_phpunit_polyfills_path = getenv('WP_TESTS_PHPUNIT_POLYFILLS_PATH');
if (false !== $_phpunit_polyfills_path) {
    define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path);
}

// -----------------------------------------------------------------------------
// Verify that WordPress testing functions exist
// -----------------------------------------------------------------------------
if (!file_exists("{$_tests_dir}/includes/functions.php")) {
    echo "Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    exit(1);
}

// Include WordPress testing functions
require_once "{$_tests_dir}/includes/functions.php";

// -----------------------------------------------------------------------------
// Manually load the plugin being tested
// -----------------------------------------------------------------------------
function _manually_load_plugin() {
    require dirname(dirname(__FILE__)) . '/wpmudev-plugin-test.php';
}
tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// -----------------------------------------------------------------------------
// Start up the WordPress testing environment
// -----------------------------------------------------------------------------
require "{$_tests_dir}/includes/bootstrap.php";

// -----------------------------------------------------------------------------
// Automatically load all test files in this directory
// -----------------------------------------------------------------------------
foreach (glob(__DIR__ . '/test-*.php') as $test_file) {
    require_once $test_file;
}
