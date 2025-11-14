<?php
/**
 * Bootstrap loader for the Posts Maintenance WP-CLI command.
 *
 * Ensures the command class is loaded only when WP-CLI is running.
 */

// --------------------------------------------------------------
// Load WP-CLI Command Class
// --------------------------------------------------------------
if (defined('WP_CLI') && WP_CLI) {

    // Load the Posts Maintenance CLI command class file
    require_once __DIR__ . '/class-posts-maintenance-cli.php';
}
