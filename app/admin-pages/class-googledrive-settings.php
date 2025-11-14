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

    /** @var string The page slug */
    private $page_slug = 'wpmudev_plugintest_drive';

    /** @var array Google Drive credentials */
    private $creds = array();

    /** @var string Option name for storing credentials */
    private $option_name = 'wpmudev_plugin_tests_auth';

    /** @var string Assets version for cache busting */
    private $assets_version = '';

    /** @var string Unique ID for markup and JS targeting */
    private $unique_id = '';

    /** @var array Scripts & styles for this page */
    private $page_scripts = array();

    /**
     * Initializes the admin page.
     */
    public function init() {
        $this->page_title     = __( 'Google Drive Test', 'wpmudev-plugin-test' );
        $this->creds          = get_option( $this->option_name, array() );
        $this->assets_version = ! empty( $this->script_data( 'version' ) ) ? $this->script_data( 'version' ) : WPMUDEV_PLUGINTEST_VERSION;
        $this->unique_id      = "wpmudev_plugintest_drive_main_wrap-{$this->assets_version}";

        add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_filter( 'admin_body_class', array( $this, 'admin_body_classes' ) );
    }

    /**
     * Registers the Google Drive admin page.
     */
    public function register_admin_page() {
        $page = add_menu_page(
            'Google Drive Test',
            $this->page_title,
            'manage_options',
            $this->page_slug,
            array( $this, 'callback' ),
            'dashicons-cloud',
            7
        );

        // Prepare assets when this page loads
        add_action( 'load-' . $page, array( $this, 'prepare_assets' ) );
    }

    /**
     * Admin page callback method.
     */
    public function callback() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wpmudev-plugin-test' ) );
        }
        $this->view();
    }

   /**
 * Prepares scripts and styles for this admin page.
 */
public function prepare_assets() {
    $handle    = 'wpmudev_plugintest_drivepage';
    $src       = WPMUDEV_PLUGINTEST_ASSETS_URL . '/js/drivetestpage.min.js';
    $style_src = WPMUDEV_PLUGINTEST_ASSETS_URL . '/css/drivetestpage.min.css';
    $deps      = ! empty( $this->script_data( 'dependencies' ) )
        ? $this->script_data( 'dependencies' )
        : array( 'react', 'wp-element', 'wp-i18n', 'wp-is-shallow-equal', 'wp-polyfill' );

    $this->page_scripts[ $handle ] = array(
        'src'       => $src,
        'style_src' => $style_src,
        'deps'      => $deps,
        'ver'       => $this->assets_version,
        'strategy'  => true, // Load in footer
        'localize'  => array(
            'dom_element_id'       => $this->unique_id,
            'restEndpointSave'     => 'wpmudev/v1/drive/save-credentials',
            'restEndpointAuth'     => 'wpmudev/v1/drive/auth',
            'restEndpointFiles'    => 'wpmudev/v1/drive/files',
            'restEndpointUpload'   => 'wpmudev/v1/drive/upload',
            'restEndpointDownload' => 'wpmudev/v1/drive/download',
            'restEndpointDelete'   => 'wpmudev/v1/drive/delete',
            'restEndpointCreate'   => 'wpmudev/v1/drive/create-folder',
            'nonce'                => wp_create_nonce( 'wp_rest' ),
            'authStatus'           => $this->get_auth_status(),
            'redirectUri'          => home_url( '/wp-json/wpmudev/v1/drive/callback' ),
            'hasCredentials'       => ! empty( $this->creds['client_id'] ) && ! empty( $this->creds['client_secret'] ),
        ),
    );
}


    /**
     * Checks if the user is authenticated with Google Drive.
     *
     * @return bool
     */
    private function get_auth_status() {
        $access_token = get_option( 'wpmudev_drive_access_token', '' );
        $expires_at   = (int) get_option( 'wpmudev_drive_token_expires', 0 );

        return ! empty( $access_token ) && time() < $expires_at;
    }

    /**
     * Retrieves specific script data from the asset file.
     *
     * @param string $key
     * @return string|array
     */
    protected function script_data( string $key = '' ) {
        $raw = $this->raw_script_data();
        return ( $key && isset( $raw[ $key ] ) ) ? $raw[ $key ] : '';
    }

    /**
     * Loads the script asset data from the file.
     *
     * @return array
     */
    protected function raw_script_data(): array {
        static $script_data = null;
        $asset_file = WPMUDEV_PLUGINTEST_DIR . 'assets/js/drivetestpage.min.asset.php';

        if ( is_null( $script_data ) && file_exists( $asset_file ) ) {
            $script_data = include $asset_file;
        }

        return (array) $script_data;
    }

    /**
     * Enqueues the prepared scripts and styles.
     */
    public function enqueue_assets() {
        if ( ! empty( $this->page_scripts ) ) {
            foreach ( $this->page_scripts as $handle => $script ) {
                wp_register_script( $handle, $script['src'], $script['deps'], $script['ver'], $script['strategy'] );

                if ( ! empty( $script['localize'] ) ) {
                    wp_localize_script( $handle, 'wpmudevDriveTest', $script['localize'] );
                }

                wp_enqueue_script( $handle );

                if ( ! empty( $script['style_src'] ) ) {
                    wp_enqueue_style( $handle, $script['style_src'], array(), $script['ver'] );
                }
            }
        }
    }

    /**
     * Renders the wrapper element for React.
     */
    protected function view() {
        echo '<div id="' . esc_attr( $this->unique_id ) . '" class="sui-wrap"></div>';
    }

    /**
     * Adds SUI body class to admin page.
     *
     * @param string $classes
     * @return string
     */
    public function admin_body_classes( $classes = '' ) {
        if ( ! function_exists( 'get_current_screen' ) ) {
            return $classes;
        }

        $screen = get_current_screen();

        if ( empty( $screen->id ) || strpos( $screen->id, $this->page_slug ) === false ) {
            return $classes;
        }

        $classes .= ' sui-' . str_replace( '.', '-', WPMUDEV_PLUGINTEST_SUI_VERSION ) . ' ';
        return $classes;
    }
}