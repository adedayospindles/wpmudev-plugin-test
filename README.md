# WPMU DEV Plugin Test -- Forminator Developer Position

This is a plugin that can be used for testing coding skills for
WordPress and PHP.

A WordPress plugin designed to test coding skills across PHP, WordPress,
React, REST API, Google Drive integration, WP‑CLI, and unit testing.
This plugin introduces two admin pages (Google Drive Test and Posts
Maintenance) and implements a full stack of backend and frontend
functionality.

**Features**

## Google Drive Admin Interface

- Credentials management with secure storage
- OAuth 2.0 authentication flow
- File operations: upload, download, delete, create folder
- React‑based UI with drag‑and‑drop uploads, progress bars, and
  internationalization

## Posts Maintenance Admin Page

- Scan posts and update metadata
- Background processing with daily scheduled tasks
- Customizable post type filters
- WP‑CLI integration for command‑line execution

## Backend REST API

- Endpoints for credentials, authentication, file operations
- Secure validation and sanitization
- Proper permission checks

## Development Workflow

- Composer for PHP dependencies
- NPM/Webpack for React build tasks
- Unit tests following WordPress standards

**Requirements**

- WordPress 6.1+
- PHP 7.4+
- Composer
- Node.js & npm

**Installation**

Clone this repository into your WordPress plugins directory:

```bash
cd wp-content/plugins
git clone https://github.com/adedayospindle/wpmudev-plugin-test.git
```

Install PHP dependencies:

```bash
composer install
```

Install JS dependencies:

```bash
npm install
```

Build assets:

```bash
npm run build
```

Activate the plugin in the WordPress admin dashboard.

**Development Commands**

Command Action

---

npm run watch Compiles and watches for changes during development
npm run compile Compiles production‑ready assets
npm run build Builds production bundle inside /build/ folder

**Google Drive Setup**

1.  Create a project in Google Cloud Console.
2.  Enable the Google Drive API.
3.  Configure OAuth 2.0 credentials:

Authorized redirect URI:

    https://your-site.com/wp-json/wpmudev/v1/drive/callback

Copy the Client ID and Client Secret into the plugin's Google Drive Test
admin page.

Required scopes:

- https://www.googleapis.com/auth/drive.file\
- https://www.googleapis.com/auth/drive.metadata.readonly

**REST API Endpoints**

- POST `/wp-json/wpmudev/v1/drive/save-credentials`
- POST `/wp-json/wpmudev/v1/drive/auth`
- GET `/wp-json/wpmudev/v1/drive/callback`
- GET `/wp-json/wpmudev/v1/drive/files`
- POST `/wp-json/wpmudev/v1/drive/upload`
- GET `/wp-json/wpmudev/v1/drive/download?file_id={id}`
- DELETE `/wp-json/wpmudev/v1/drive/delete?file_id={id}`
- POST `/wp-json/wpmudev/v1/drive/create-folder`
- GET `/wp-json/wpmudev/v1/drive/status`

**Posts Maintenance** Admin page: **Posts Maintenance**

Features: - Scan posts and update `wpmudev_test_last_scan` meta\

- Daily scheduled task\
- Background processing

WP‑CLI command:

```bash
wp wpmudev scan-posts --post_type=page,post
```

**Unit Testing**

Tests implemented for Posts Maintenance functionality, Googledrive Api Auth, and Dependency Manager

Run with:

```bash
phpunit
```

**Integration Testing**

Tests implemented for Googledrive Api Auth.

**Coding Task Coverage**

This plugin addresses all coding tasks outlined:

- Package Optimization -- reduced build size by excluding unnecessary
  vendor assets.
- Google Drive Admin Interface -- React UI with i18n, credentials,
  authentication, file operations.
- Backend Credentials Storage -- secure REST endpoint with
  validation.
- Google Drive Authentication -- OAuth 2.0 flow with token refresh.
- Files List API -- paginated listing with metadata.
- File Upload Implementation -- multipart uploads with validation.
- Posts Maintenance Admin Page -- scanning, scheduling, background
  tasks.
- WP‑CLI Integration -- command for scanning posts.
- Dependency Management -- namespaced classes, Composer isolation.
- Unit Testing – comprehensive tests for google drive api auth, post scanning, posts maintenance, wpcli, and dependency manager

**License**
GPL‑2.0-or-later See LICENSE

👤 **Author**
Adedayo Agboola
