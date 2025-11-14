=== WPMU DEV Plugin Test - Forminator Developer Position ===
Contributors: wpmu,adedayo
Tags: google drive, wp-cli, posts maintenance, react, oauth
Requires at least: 6.1
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==
This plugin is designed to test coding skills across PHP, WordPress, React, REST API, Google Drive integration, WP‑CLI, and unit testing.  

It introduces two admin pages (**Google Drive Test** and **Posts Maintenance**) and implements a full stack of backend and frontend functionality.

== Features ==

= Google Drive Admin Interface =
* Credentials management with secure storage
* OAuth 2.0 authentication flow
* File operations: upload, download, delete, create folder
* React‑based UI with drag‑and‑drop uploads, progress bars, and internationalization

= Posts Maintenance Admin Page =
* Scan posts and update metadata
* Background processing with daily scheduled tasks
* Customizable post type filters
* WP‑CLI integration for command‑line execution

= Backend REST API =
* Endpoints for credentials, authentication, file operations
* Secure validation and sanitization
* Proper permission checks

= Development Workflow =
* Composer for PHP dependencies
* NPM/Webpack for React build tasks
* Unit tests following WordPress standards

== Installation ==
1. Upload the plugin files to the `/wp-content/plugins/` directory, or clone the repository:
   `git clone https://github.com/adedayospindle/wpmudev-plugin-test.git`
2. Install PHP dependencies: `composer install`
3. Install JS dependencies: `npm install`
4. Build assets: `npm run build`
5. Activate the plugin through the 'Plugins' screen in WordPress.

== Requirements ==
* WordPress 6.1+
* PHP 7.4+
* Composer
* Node.js & npm

== Development Commands ==
* `npm run watch` – Compiles and watches for changes during development
* `npm run compile` – Compiles production‑ready assets
* `npm run build` – Builds production bundle inside `/build/` folder

== Google Drive Setup ==
1. Create a project in Google Cloud Console.
2. Enable the Google Drive API.
3. Configure OAuth 2.0 credentials with the following redirect URI:
   `https://your-site.com/wp-json/wpmudev/v1/drive/callback`
4. Copy the Client ID and Client Secret into the plugin's Google Drive Test admin page.
5. Required scopes:
   * https://www.googleapis.com/auth/drive.file
   * https://www.googleapis.com/auth/drive.metadata.readonly

== REST API Endpoints ==
* POST `/wp-json/wpmudev/v1/drive/save-credentials`
* POST `/wp-json/wpmudev/v1/drive/auth`
* GET `/wp-json/wpmudev/v1/drive/callback`
* GET `/wp-json/wpmudev/v1/drive/files`
* POST `/wp-json/wpmudev/v1/drive/upload`
* GET `/wp-json/wpmudev/v1/drive/download?file_id={id}`
* DELETE `/wp-json/wpmudev/v1/drive/delete?file_id={id}`
* POST `/wp-json/wpmudev/v1/drive/create-folder`
* GET `/wp-json/wpmudev/v1/drive/status`

== Posts Maintenance ==
Admin page: **Posts Maintenance**

Features:
* Scan posts and update `wpmudev_test_last_scan` meta
* Daily scheduled task
* Background processing

WP‑CLI command:
`wp wpmudev scan-posts --post_type=page,post`

== Unit Testing ==
Tests implemented for Posts Maintenance functionality.  
Run with: `phpunit`

== Coding Task Coverage ==
This plugin addresses all coding tasks outlined:
* Package Optimization – reduced build size by excluding unnecessary vendor assets
* Google Drive Admin Interface – React UI with i18n, credentials, authentication, file operations
* Backend Credentials Storage – secure REST endpoint with validation
* Google Drive Authentication – OAuth 2.0 flow with token refresh
* Files List API – paginated listing with metadata
* File Upload Implementation – multipart uploads with validation
* Posts Maintenance Admin Page – scanning, scheduling, background tasks
* WP‑CLI Integration – command for scanning posts
* Dependency Management – namespaced classes, Composer isolation
* Unit Testing – comprehensive tests for google drive api auth, post scanning, posts maintenance, wpcli, and dependency manager

== Changelog ==
= 1.0.0 =
* Initial release with Google Drive integration and Posts Maintenance features.

== Frequently Asked Questions ==
= Does this plugin require Google Drive credentials? =
Yes, you must configure OAuth credentials in Google Cloud Console.

== License ==
GPL‑2.0-or-later

== Authors ==
WPMU
Adedayo Agboola
