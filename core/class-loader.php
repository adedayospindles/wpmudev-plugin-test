<?php
/**
 * Class to boot up plugin.
 *
 * @link    https://wpmudev.com/
 * @since   1.0.0
 *
 * @author  WPMUDEV
 * @package WPMUDEV_PluginTest
 */

namespace WPMUDEV\PluginTest;

use WPMUDEV\PluginTest\Base;

defined( 'WPINC' ) || die;

final class Loader extends Base {

    /**
     * Settings helper class instance.
     *
     * @since 1.0.0
     * @var object|null
     */
    public $settings;

    /**
     * Minimum supported PHP version.
     *
     * @since 1.0.0
     * @var string
     */
    public $php_version = '7.4';

    /**
     * Minimum WordPress version.
     *
     * @since 1.0.0
     * @var string
     */
    public $wp_version = '6.1';

    /**
     * Initialize functionality of the plugin.
     *
     * @since 1.0.0
     * @access protected
     */
    protected function __construct() {
        if ( ! $this->can_boot() ) {
            return;
        }
        $this->init();
    }

    /**
     * Check if plugin can boot.
     *
     * @return bool
     */
    private function can_boot() {
        global $wp_version;

        return (
            version_compare( PHP_VERSION, $this->php_version, '>=' ) &&
            version_compare( $wp_version, $this->wp_version, '>=' )
        );
    }

    /**
     * Register all the actions and filters.
     *
     * @since 1.0.0
     * @access private
     */
    private function init() {
        // Always load core admin pages.
        App\Admin_Pages\Google_Drive::instance()->init();
        App\Admin_Pages\Posts_Maintenance::instance()->init();

        // Conditionally load Google Drive API endpoints.
        if ( class_exists( \Google\Client::class ) ) {
            Endpoints\V1\Drive_API::instance()->init();
        } else {
            // Show admin notice if dependency is missing.
            add_action( 'admin_notices', function() {
                echo '<div class="notice notice-warning"><p>'
                    . esc_html__( 'Google API client not found. Drive features are disabled.', 'wpmudev-plugin-test' )
                    . '</p></div>';
            });
        }

        // Load CLI commands if WP-CLI is running.
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            require_once WPMUDEV_PLUGINTEST_DIR . 'app/cli/cli.php';
        }
    }
}
