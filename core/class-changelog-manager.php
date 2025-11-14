<?php

namespace WPMUDEV\PluginTest\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Changelog_Manager {

    /**
     * Pending changes for next version.
     * Edit this array only when you add features.
     *
     * @var array
     */
    private static $changes = [
    'Added' => [
        'Google Drive OAuth state handling added',
        'New /status REST route for connection health check'
    ],

    'Fixed' => [
        'Callback error “invalid_user” caused by missing user_id passing',
        'OAuth state transient mismatch issue'
    ],

    'Changed' => [
        'Improved SUI styles and SCSS compilation handling'
    ],

    'Removed' => ['Modifying Google Drive Features'],
];

    

    /**
     * Initialize version check.
     */
    public static function init() {
        add_action( 'plugins_loaded', [ __CLASS__, 'check_version' ], 20 );
    }

    /**
     * Check if plugin version changed.
     */
    public static function check_version() {
        $current = WPMUDEV_PLUGINTEST_VERSION;
        $saved   = get_option( 'wpmudev_plugintest_version' );

        if ( $current !== $saved ) {
            self::write_changelog( $current );
            update_option( 'wpmudev_plugintest_version', $current );
        }
    }

    /**
     * Write to changelog file.
     */
    private static function write_changelog( $version ) {

        $file = WPMUDEV_PLUGINTEST_DIR . 'changelog.txt';

        // Build the header.
        $output  = "\n\n=== Version {$version} — " . gmdate( 'Y-m-d H:i:s' ) . " UTC ===\n";

        foreach ( self::$changes as $category => $items ) {
            if ( ! empty( $items ) ) {
                $output .= "{$category}:\n";
                foreach ( $items as $line ) {
                    $output .= "- {$line}\n";
                }
                $output .= "\n";
            }
        }

        // Write or append.
        file_put_contents( $file, $output, FILE_APPEND );
    }

    /**
     * Helper method for adding entries programmatically.
     */
    public static function add( $category, $message ) {
        if ( isset( self::$changes[ $category ] ) ) {
            self::$changes[ $category ][] = $message;
        }
    }
}
