<?php
/**
 * Plugin Name:       WPMU DEV Plugin Test - Forminator Developer Position
 * Description:       A plugin focused on testing coding skills.
 * Requires at least: 6.1
 * Requires PHP:      7.4
 * Version:           1.0.0
 * Author:            Adedayo Agboola
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpmudev-plugin-test
 *
 * @package           create-block
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// -----------------------------------------------------------------------------
// Autoloading
// -----------------------------------------------------------------------------
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

// -----------------------------------------------------------------------------
// Load core classes
// -----------------------------------------------------------------------------
require_once plugin_dir_path( __FILE__ ) . 'core/class-dependency-manager.php';
require_once plugin_dir_path( __FILE__ ) . 'core/class-changelog-manager.php';

// -----------------------------------------------------------------------------
// Initialize core managers
// -----------------------------------------------------------------------------
\WPMUDEV\PluginTest\Core\Dependency_Manager::init();
\WPMUDEV\PluginTest\Core\Changelog_Manager::init();

// -----------------------------------------------------------------------------
// Plugin constants
// -----------------------------------------------------------------------------

// Plugin version
if ( ! defined( 'WPMUDEV_PLUGINTEST_VERSION' ) ) {
	define( 'WPMUDEV_PLUGINTEST_VERSION', '1.0.0' );
}

// Main plugin file
if ( ! defined( 'WPMUDEV_PLUGINTEST_PLUGIN_FILE' ) ) {
	define( 'WPMUDEV_PLUGINTEST_PLUGIN_FILE', __FILE__ );
}

// Plugin directory
if ( ! defined( 'WPMUDEV_PLUGINTEST_DIR' ) ) {
	define( 'WPMUDEV_PLUGINTEST_DIR', plugin_dir_path( __FILE__ ) );
}

// Plugin URL
if ( ! defined( 'WPMUDEV_PLUGINTEST_URL' ) ) {
	define( 'WPMUDEV_PLUGINTEST_URL', plugin_dir_url( __FILE__ ) );
}

// Assets URL
if ( ! defined( 'WPMUDEV_PLUGINTEST_ASSETS_URL' ) ) {
	define( 'WPMUDEV_PLUGINTEST_ASSETS_URL', WPMUDEV_PLUGINTEST_URL . 'assets' );
}

// Shared UI Version
if ( ! defined( 'WPMUDEV_PLUGINTEST_SUI_VERSION' ) ) {
	define( 'WPMUDEV_PLUGINTEST_SUI_VERSION', '2.12.23' );
}

// -----------------------------------------------------------------------------
// Main plugin class
// -----------------------------------------------------------------------------
class WPMUDEV_PluginTest {

	/**
	 * Holds the class instance.
	 *
	 * @var WPMUDEV_PluginTest|null
	 */
	private static $instance = null;

	// -------------------------------------------------------------------------
	// Singleton instance
	// -------------------------------------------------------------------------
	/**
	 * Return an instance of the class
	 *
	 * @return WPMUDEV_PluginTest
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	// -------------------------------------------------------------------------
	// Initialize plugin
	// -------------------------------------------------------------------------
	/**
	 * Class initializer
	 */
	public function load() {
		// Load plugin translations
		load_plugin_textdomain(
			'wpmudev-plugin-test',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);

		// Initialize loader class
		WPMUDEV\PluginTest\Loader::instance();
	}
}

// -----------------------------------------------------------------------------
// Initialize the plugin on 'init' hook
// -----------------------------------------------------------------------------
add_action(
	'init',
	function () {
		WPMUDEV_PluginTest::get_instance()->load();
	},
	9
);
