<?php
/**
 * Class Dependency_Manager
 *
 * Handles autoloading, dependency checks, and conflict prevention.
 *
 * @package WPMUDEV\PluginTest\Core
 */

namespace WPMUDEV\PluginTest\Core;

defined('ABSPATH') || exit;

/**
 * Dependency Manager for the WPMUDEV Plugin Test.
 *
 * This class ensures that required dependencies are available, isolated,
 * and safely loaded without affecting other plugins/themes.
 */
class Dependency_Manager {

    /**
     * Initialize and verify dependencies.
     */
    public static function init() {
        self::load_composer_autoload();
        self::check_required_classes();
    }

    /**
     * Load Composer autoloader if not already loaded.
     */
    private static function load_composer_autoload() {
        // Prevent re-loading if another plugin already included it.
        if (class_exists('Composer\\Autoload\\ClassLoader')) {
            return;
        }

        $autoload = plugin_dir_path(__DIR__) . '../../vendor/autoload.php';

        if (file_exists($autoload)) {
            require_once $autoload;
        } else {
            error_log('[WPMUDEV Plugin Test] Missing vendor/autoload.php. Run composer install.');
        }
    }

    /**
     * Internal check for required classes and PHP version.
     * Logs issues but does not return them.
     */
    private static function check_required_classes() {
        $issues = self::check_environment();

        foreach ($issues as $issue) {
            error_log("[WPMUDEV Plugin Test] {$issue}");
        }
    }

    /**
     * Public method to check environment and return status.
     *
     * @return array List of missing dependencies and compatibility issues.
     */
    public static function check_environment(): array {
        $issues = [];

        $required = [
            'Google\\Client'     => 'google/apiclient',
            'GuzzleHttp\\Client' => 'guzzlehttp/guzzle',
        ];

        foreach ($required as $class => $package) {
            if (!class_exists($class)) {
                $issues[] = "Missing dependency: {$package}";
            }
        }

        if (version_compare(PHP_VERSION, '7.4', '<')) {
            $issues[] = 'Requires PHP 7.4 or higher';
        }

        return $issues;
    }
}
