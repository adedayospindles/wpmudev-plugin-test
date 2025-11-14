<?php
/**
 * Google Drive test block.
 *
 * @package WPMUDEV\PluginTest
 */

namespace WPMUDEV\PluginTest\App\Admin_Pages;

defined( 'WPINC' ) || die;

use WPMUDEV\PluginTest\Base;

class Google_Drive extends Base {

    /** @var string The page title */
    private $page_title;

    /** @var string The page slug used for the menu */
    private $page_slug = 'wpmudev_plugintest_drive';

    /** @var array Google Drive credentials */
    private $creds = array();

    /** @var string Option name for storing credentials */
    private $option_name = 'wpmudev_plugin_tests_auth';

    /** @var string Assets version (for cache busting) */
    private $assets_version = '';

    /** @var string Unique DOM ID for React root element */
    private $unique_id = '';

    /** @var array Holds scripts/styles prepared for enqueueing */
    private $page_scripts = array();

    /**
     * Initializes the admin page.
     */
    public function init() {
        // Set title and credentials
        $this->page_title     = __( 'Google Drive Test', 'wpmudev-plugin-test' );
        $this->creds          = get_option( $this->option_name, array() );

        // Load asset version from JS asset file or fallback to plugin version
        $this->assets_version = ! empty( $this->script_data( 'version' ) )
            ? $this->script_data( 'version' )
            : WPMUDEV_PLUGINTEST_VERSION;

        // Unique ID for React mount point
        $this->unique_id = "wpmudev_plugintest_drive_main_wrap-{$this->assets_version}";

        // Hook page and assets
        add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_filter( 'admin_body_class', array( $this, 'admin_body_classes' ) );
    }

    /**
     * Registers the Google Drive admin menu page.
     */
    public function register_admin_page() {
        $page = add_menu_page(
            'Google Drive Test',                // Page title in browser
            $this->page_title,                  // Menu label
            'manage_options',                   // Capability
            $this->page_slug,                   // Slug
            array( $this, 'callback' ),         // Callback renderer
            'dashicons-cloud',                  // Icon
            7                                   // Position
        );

        // Prepare required JS/CSS when this page loads
        add_action( 'load-' . $page, array( $this, 'prepare_assets' ) );
    }

    /**
     * Admin page rendering callback.
     */
    public function callback() {
        // Permission check
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wpmudev-plugin-test' ) );
        }

        // Render React wrapper
        $this->view();
    }

    /**
     * Prepares scripts and styles for the admin page.
     */
    public function prepare_assets() {
        $handle    = 'wpmudev_plugintest_drivepage';
        $src       = WPMUDEV_PLUGINTEST_ASSETS_URL . '/js/drivetestpage.min.js';
        $style_src = WPMUDEV_PLUGINTEST_ASSETS_URL . '/css/drivetestpage.min.css';

        // Dependencies from asset file or fallback list
        $deps = ! empty( $this->script_data( 'dependencies' ) )
            ? $this->script_data( 'dependencies' )
            : array( 'react', 'wp-element', 'wp-i18n', 'wp-is-shallow-equal', 'wp-polyfill' );

        // Store all assets in internal registry
        $this->page_scripts[ $handle ] = array(
            'src'       => $src,
            'style_src' => $style_src,
            'deps'      => $deps,
            'ver'       => $this->assets_version,
            'strategy'  => true, // Load JS in footer
            'localize'  => array(
                // ID of main React mount element
                'dom_element_id'       => $this->unique_id,

                // REST endpoints for Drive actions
                'restEndpointSave'     => 'wpmudev/v1/drive/save-credentials',
                'restEndpointAuth'     => 'wpmudev/v1/drive/auth',
                'restEndpointFiles'    => 'wpmudev/v1/drive/files',
                'restEndpointUpload'   => 'wpmudev/v1/drive/upload',
                'restEndpointDownload' => 'wpmudev/v1/drive/download',
                'restEndpointDelete'   => 'wpmudev/v1/drive/delete',
                'restEndpointCreate'   => 'wpmudev/v1/drive/create-folder',

                // Security nonce
                'nonce'                => wp_create_nonce( 'wp_rest' ),

                // Whether user is authenticated with Google Drive
                'authStatus'           => $this->get_auth_status(),

                // OAuth callback redirect URI
                'redirectUri'          => home_url( '/wp-json/wpmudev/v1/drive/callback' ),

                // Whether credentials already exist
                'hasCredentials'       => ! empty( $this->creds['client_id'] ) &&
                                         ! empty( $this->creds['client_secret'] ),
            ),
        );
    }

    /**
     * Checks if the user currently has a valid Google Drive access token.
     *
     * @return bool True if authenticated and token not expired.
     */
    private function get_auth_status() {
        $access_token = get_option( 'wpmudev_drive_access_token', '' );
        $expires_at   = (int) get_option( 'wpmudev_drive_token_expires', 0 );

        return ! empty( $access_token ) && time() < $expires_at;
    }

    /**
     * Retrieves specific data from the JS asset file.
     *
     * @param string $key
     * @return mixed
     */
    protected function script_data( string $key = '' ) {
        $raw = $this->raw_script_data();
        return ( $key && isset( $raw[ $key ] ) ) ? $raw[ $key ] : '';
    }

    /**
     * Loads the JS asset metadata array.
     *
     * @return array
     */
    protected function raw_script_data(): array {
        static $script_data = null;

        // Path to asset metadata file
        $asset_file = WPMUDEV_PLUGINTEST_DIR . 'assets/js/drivetestpage.min.asset.php';

        // Load once
        if ( is_null( $script_data ) && file_exists( $asset_file ) ) {
            $script_data = include $asset_file;
        }

        return (array) $script_data;
    }

    /**
     * Enqueues all scripts/styles prepared for this page.
     */
    public function enqueue_assets() {
        if ( ! empty( $this->page_scripts ) ) {
            foreach ( $this->page_scripts as $handle => $script ) {

                // Register JS
                wp_register_script(
                    $handle,
                    $script['src'],
                    $script['deps'],
                    $script['ver'],
                    $script['strategy']
                );

                // Localize data for JS usage
                if ( ! empty( $script['localize'] ) ) {
                    wp_localize_script( $handle, 'wpmudevDriveTest', $script['localize'] );
                }

                // Enqueue JS
                wp_enqueue_script( $handle );

                // Enqueue CSS if available
                if ( ! empty( $script['style_src'] ) ) {
                    wp_enqueue_style(
                        $handle,
                        $script['style_src'],
                        array(),
                        $script['ver']
                    );
                }
            }
        }
    }

    /**
     * Renders the React root element wrapper.
     */
    protected function view() {
        echo '<div id="' . esc_attr( $this->unique_id ) . '" class="sui-wrap"></div>';
    }

    /**
     * Adds SUI CSS class to admin body only on this screen.
     *
     * @param string $classes
     * @return string
     */
    public function admin_body_classes( $classes = '' ) {
        if ( ! function_exists( 'get_current_screen' ) ) {
            return $classes;
        }

        $screen = get_current_screen();

        // Only modify body class on this plugin's page
        if ( empty( $screen->id ) || strpos( $screen->id, $this->page_slug ) === false ) {
            return $classes;
        }

        // Add SUI class with version number
        $classes .= ' sui-' . str_replace( '.', '-', WPMUDEV_PLUGINTEST_SUI_VERSION ) . ' ';

        return $classes;
    }
}
