<?php
/**
 * Google Drive API REST Endpoints (v1)
 *
 * Handles authentication, token storage, file operations, and folder creation
 * through the Google Drive API.
 */

namespace WPMUDEV\PluginTest\Endpoints\V1;

defined('WPINC') || die;

use WPMUDEV\PluginTest\Base;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class Drive_API extends Base {

    /**
     * @var \Google_Client|null
     */
    private ?\Google_Client $client = null;

    /**
     * @var \Google_Service_Drive|null
     */
    private ?\Google_Service_Drive $drive_service = null;

    /**
     * @var string OAuth Redirect URL
     */
    private string $redirect_uri;

    /**
     * @var string Option key for saving encrypted client credentials
     */
    private string $option_name = 'wpmudev_plugin_tests_auth';


    /* -------------------------------------------------------------------------
     * INITIALIZATION
     * ---------------------------------------------------------------------- */

    /**
     * Initialize endpoint: load Google client, set redirect URI, and register routes.
     */
    public function init(): void {
        $this->redirect_uri = home_url('/wp-json/wpmudev/v1/drive/callback');

        // Load Google Client manually if not autoloaded
        if (!class_exists('\Google_Client') && defined('WPMUDEV_PLUGINTEST_DIR')) {
            $autoload = WPMUDEV_PLUGINTEST_DIR . 'vendor/autoload.php';

            if (file_exists($autoload)) {
                require_once $autoload;
            }
        }

        $this->setup_google_client();

        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Restricts access to WordPress admins only.
     */
    public function require_admin(): bool {
        return current_user_can('manage_options');
    }


    /* -------------------------------------------------------------------------
     * REST API ROUTES
     * ---------------------------------------------------------------------- */

    /**
     * Register all Drive-related REST API routes.
     */
    public function register_routes(): void {

        $routes = [
            '/save-credentials' => [
                'methods'             => 'POST',
                'callback'            => [$this, 'save_credentials'],
                'permission_callback' => [$this, 'require_admin'],
            ],

            '/auth' => [
                'methods'             => 'POST',
                'callback'            => [$this, 'start_auth'],
                'permission_callback' => [$this, 'require_admin'],
            ],

            '/callback' => [
                'methods'             => 'GET',
                'callback'            => [$this, 'handle_callback'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'code' => [
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'state' => [
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ],

            '/files' => [
                'methods'             => 'GET',
                'callback'            => [$this, 'list_files'],
                'permission_callback' => [$this, 'require_admin'],
            ],

            '/upload' => [
                'methods'             => 'POST',
                'callback'            => [$this, 'upload_file'],
                'permission_callback' => [$this, 'require_admin'],
            ],

            '/download' => [
                'methods'             => 'GET',
                'callback'            => [$this, 'download_file'],
                'permission_callback' => [$this, 'require_admin'],
            ],

            '/delete' => [
                'methods'             => 'DELETE',
                'callback'            => [$this, 'delete_file'],
                'permission_callback' => [$this, 'require_admin'],
                'args'                => [
                    'file_id' => [
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ],

            '/create-folder' => [
                'methods'             => 'POST',
                'callback'            => [$this, 'create_folder'],
                'permission_callback' => [$this, 'require_admin'],
            ],

            '/status' => [
                'methods'             => 'GET',
                'callback'            => [$this, 'status_check'],
                'permission_callback' => [$this, 'require_admin'],
            ],
        ];

        // Register all endpoints
        foreach ($routes as $route => $data) {
            register_rest_route(
                'wpmudev/v1/drive',
                $route,
                $data
            );
        }
    }


    /* -------------------------------------------------------------------------
     * ENCRYPTION UTILITIES
     * ---------------------------------------------------------------------- */

    /**
     * Encrypt values using secure AES-256-CBC encryption.
     */
    private function encrypt(string $plaintext): string {
        if ($plaintext === '') {
            return '';
        }

        $key = hash('sha256', AUTH_KEY . NONCE_KEY);
        $iv  = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));

        $cipher = openssl_encrypt(
            $plaintext,
            'AES-256-CBC',
            substr($key, 0, 32),
            OPENSSL_RAW_DATA,
            $iv
        );

        return base64_encode($iv . $cipher);
    }

    /**
     * Decrypt previously encrypted values.
     */
    private function decrypt(string $ciphertext): string {
        if ($ciphertext === '') {
            return '';
        }

        $key  = hash('sha256', AUTH_KEY . NONCE_KEY);
        $data = base64_decode($ciphertext);

        $ivlen   = openssl_cipher_iv_length('AES-256-CBC');
        $iv      = substr($data, 0, $ivlen);
        $payload = substr($data, $ivlen);

        return openssl_decrypt(
            $payload,
            'AES-256-CBC',
            substr($key, 0, 32),
            OPENSSL_RAW_DATA,
            $iv
        );
    }


    /* -------------------------------------------------------------------------
     * GOOGLE CLIENT SETUP
     * ---------------------------------------------------------------------- */

    /**
     * Configure and initialize Google\Client with scopes and stored credentials.
     */
    private function setup_google_client(): void {

        $auth_creds = get_option($this->option_name, []);

        // Stop if credentials not saved yet
        if (empty($auth_creds['client_id']) || empty($auth_creds['client_secret'])) {
            return;
        }

        $client_id     = $this->decrypt($auth_creds['client_id']);
        $client_secret = $this->decrypt($auth_creds['client_secret']);

        if ($client_id === '' || $client_secret === '') {
            return;
        }

        $this->client = new \Google_Client();

        $this->client->setClientId($client_id);
        $this->client->setClientSecret($client_secret);
        $this->client->setRedirectUri($this->redirect_uri);

        // Force Google to always return refresh tokens
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');
        $this->client->setIncludeGrantedScopes(true);

        // Limited safe scopes (upload + metadata)
        $this->client->setScopes([
            \Google_Service_Drive::DRIVE_FILE,
            \Google_Service_Drive::DRIVE_METADATA_READONLY,
        ]);

        // Load stored access token if present
        $token = get_option('wpmudev_drive_access_token', '');

        if ($token !== '') {
            $decoded = json_decode($token, true);

            if (is_array($decoded)) {
                $this->client->setAccessToken($decoded);

                // Refresh if expired
                if ($this->client->isAccessTokenExpired()) {

                    $refresh_token = $this->decrypt(get_option('wpmudev_drive_refresh_token', ''));

                    if (!empty($refresh_token)) {
                        $new_token = $this->client->fetchAccessTokenWithRefreshToken($refresh_token);

                        if (!isset($new_token['error'])) {

                            update_option('wpmudev_drive_access_token', wp_json_encode($new_token));

                            if (isset($new_token['expires_in'])) {
                                update_option(
                                    'wpmudev_drive_token_expires',
                                    time() + intval($new_token['expires_in'])
                                );
                            }

                            $this->client->setAccessToken($new_token);
                        }
                    }
                }
            }
        }

        // Create Drive service instance
        $this->drive_service = new \Google_Service_Drive($this->client);
    }


    /* -------------------------------------------------------------------------
     * CREDENTIAL STORAGE
     * ---------------------------------------------------------------------- */

    /**
     * Save OAuth client ID and secret securely (encrypted).
     */
    public function save_credentials(WP_REST_Request $request): WP_REST_Response|WP_Error {

        $params        = $request->get_json_params();
        $client_id     = sanitize_text_field($params['client_id'] ?? '');
        $client_secret = sanitize_text_field($params['client_secret'] ?? '');

        if ($client_id === '' || $client_secret === '') {
            $error = new WP_Error(
                'invalid_params',
                'client_id and client_secret are required',
                ['status' => 400]
            );

            $this->log_error($error, 'save_credentials');
            return $error;
        }

        // Store encrypted values in DB
        update_option($this->option_name, [
            'client_id'     => $this->encrypt($client_id),
            'client_secret' => $this->encrypt($client_secret),
        ]);

        // Clear old tokens
        delete_option('wpmudev_drive_access_token');
        delete_option('wpmudev_drive_refresh_token');
        delete_option('wpmudev_drive_token_expires');

        // Reinitialize client
        $this->setup_google_client();

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Credentials saved securely.',
        ], 200);
    }


    /* -------------------------------------------------------------------------
     * AUTHENTICATION (START)
     * ---------------------------------------------------------------------- */

    /**
     * Start OAuth flow: generate state token and return Google auth URL.
     */
    public function start_auth(): WP_REST_Response|WP_Error {

        if (!$this->client) {
            $error = new WP_Error(
                'missing_credentials',
                'Google OAuth credentials not configured',
                ['status' => 400]
            );
            $this->log_error($error, 'start_auth');
            return $error;
        }

        $current_user_id = get_current_user_id();

        if ($current_user_id <= 0) {
            $error = new WP_Error(
                'invalid_user',
                'You must be logged in to authenticate with Google.',
                ['status' => 403]
            );
            $this->log_error($error, 'start_auth');
            return $error;
        }

        // Generate CSRF-safe UUID
        $uuid = wp_generate_uuid4();

        // Capture the page user started from (fallback to plugin settings page)
        $return_to = esc_url_raw( wp_get_referer() ?: admin_url('admin.php?page=wpmudev-drive') );

        // Encode UUID + user ID + return URL in state
        $payload = json_encode([
            'uuid'      => $uuid,
            'user_id'   => $current_user_id,
            'return_to' => $return_to,
        ]);

        $encoded_state = base64_encode($payload);

        // Store UUID as transient for verification
        $transient_key = 'wpmudev_drive_oauth_state_' . $current_user_id;
        set_transient($transient_key, $uuid, 900); // 15 minutes

        // Attach encoded state to Google client
        $this->client->setState($encoded_state);

        // Generate Google auth URL
        $auth_url = $this->client->createAuthUrl();

        // Log useful debug info
        $this->log_error([
            'user_id'       => $current_user_id,
            'uuid'          => $uuid,
            'transient_key' => $transient_key,
            'auth_url'      => $auth_url,
            'state_encoded' => $encoded_state,
        ], 'start_auth');

        // Respond with auth URL
        return new WP_REST_Response([
            'success'  => true,
            'auth_url' => $auth_url,
        ], 200);
    }



    /**
     * Handles the OAuth callback from Google and exchanges the code for access tokens.
     *
     * @param WP_REST_Request $request
     * @return void
     */
    public function handle_callback(WP_REST_Request $request) {

        $code          = $request->get_param('code');
        $state_encoded = $request->get_param('state');

        if (empty($code) || empty($state_encoded)) {
            wp_die(__('Missing authorization code or state.', 'wpmudev-plugin-test'));
        }

        $payload   = json_decode(base64_decode($state_encoded), true);
        $uuid      = $payload['uuid'] ?? '';
        $user_id   = intval($payload['user_id'] ?? 0);
        $return_to = $payload['return_to'] ?? admin_url('admin.php?page=wpmudev-drive');

        if ($user_id <= 0) {
            wp_die(__('User ID missing or invalid in callback.', 'wpmudev-plugin-test'));
        }

        $transient_key = 'wpmudev_drive_oauth_state_' . $user_id;
        $expected_uuid = get_transient($transient_key);

        if (empty($uuid) || $uuid !== $expected_uuid) {
            wp_die(__('Invalid state parameter. Possible CSRF detected.', 'wpmudev-plugin-test'));
        }

        delete_transient($transient_key);

        $this->setup_google_client();

        if (!$this->client) {
            wp_die(__('Google client not configured.', 'wpmudev-plugin-test'));
        }

        try {
            $token = $this->client->fetchAccessTokenWithAuthCode($code);

            if (isset($token['error'])) {
                wp_die(__('Authentication error: ', 'wpmudev-plugin-test') . $token['error_description']);
            }

            if (isset($token['refresh_token'])) {
                update_option(
                    'wpmudev_drive_refresh_token',
                    $this->encrypt($token['refresh_token'])
                );
            }

            update_option('wpmudev_drive_access_token', wp_json_encode($token));

            if (isset($token['expires_in'])) {
                update_option(
                    'wpmudev_drive_token_expires',
                    time() + intval($token['expires_in'])
                );
            }

            // Redirect back to the page user started from
            wp_safe_redirect($return_to);
            exit;

        } catch (\Exception $e) {
            wp_die(__('Failed to get access token: ', 'wpmudev-plugin-test') . $e->getMessage());
        }
    }


    /* ============================================================
    * Helper Method: Initialize Google Client Using Stored Tokens
    * ============================================================ */

    /**
     * Initialize Google client with stored access/refresh tokens.
     *
     * @return bool True if initialization succeeded
     */
    private function init_client_from_storage(): bool {

        /* ----------------------------------------------
        * 1. Prepare Google Client (client_id/secret/etc)
        * ---------------------------------------------- */
        $this->setup_google_client();

        if (!$this->client) {
            return false;
        }

        /* ----------------------------------------------
        * 2. Load Stored Access Token
        * ---------------------------------------------- */
        $access_json = get_option('wpmudev_drive_access_token', '');

        // If no token yet, client is still valid but unauthenticated
        if (empty($access_json)) {
            return true;
        }

        $token = json_decode($access_json, true);
        $this->client->setAccessToken($token);

        /* ----------------------------------------------
        * 3. Refresh Token if Expired
        * ---------------------------------------------- */
        if ($this->client->isAccessTokenExpired()) {

            // Get stored refresh token (encrypted or from token array)
            $refresh_token = $token['refresh_token'] ?? get_option('wpmudev_drive_refresh_token', '');

            if ($refresh_token) {

                // Decrypt if necessary
                $refresh_token = $this->decrypt($refresh_token);

                try {
                    $newToken = $this->client->fetchAccessTokenWithRefreshToken($refresh_token);

                    if (isset($newToken['error'])) {
                        $this->log_error($newToken, 'refresh_failed');
                        return false;
                    }

                    // Ensure refresh token persists
                    if (!isset($newToken['refresh_token'])) {
                        $newToken['refresh_token'] = $refresh_token;
                    }

                    // Save refreshed token data
                    update_option('wpmudev_drive_access_token', wp_json_encode($newToken));

                    if (isset($newToken['expires_in'])) {
                        update_option(
                            'wpmudev_drive_token_expires',
                            time() + intval($newToken['expires_in'])
                        );
                    }

                    $this->client->setAccessToken($newToken);

                } catch (\Exception $e) {
                    $this->log_error(['exception' => $e->getMessage()], 'refresh_exception');
                    return false;
                }

            } else {
                // No refresh token available — cannot recover
                $this->log_error('No refresh token available', 'refresh_missing');
                return false;
            }
        }

        return true;
    }


    /**
     * --------------------------------------------------------------
     * Ensure the access token is valid and refresh if expired
     * --------------------------------------------------------------
     */
    private function ensure_valid_token(): bool {

        // Ensure client exists
        if (!$this->client) {
            return false;
        }

        // Load stored access token
        $token = json_decode(get_option('wpmudev_drive_access_token', ''), true);
        if (!is_array($token)) {
            return false;
        }

        $this->client->setAccessToken($token);

        // --------------------------------------------------------------
        // Refresh token if expired
        // --------------------------------------------------------------
        if ($this->client->isAccessTokenExpired()) {

            // Get stored refresh token
            $refresh_token = $this->decrypt(get_option('wpmudev_drive_refresh_token', ''));
            if ($refresh_token === '') {
                return false;
            }

            try {
                // Attempt refresh
                $newToken = $this->client->fetchAccessTokenWithRefreshToken($refresh_token);

                // Ensure refresh token remains present
                if (empty($newToken['refresh_token'])) {
                    $newToken['refresh_token'] = $refresh_token;
                }

                // Update stored token
                update_option('wpmudev_drive_access_token', wp_json_encode($newToken));

                // Store new expiration time
                if (isset($newToken['expires_in'])) {
                    update_option(
                        'wpmudev_drive_token_expires',
                        time() + intval($newToken['expires_in'])
                    );
                }

                $this->client->setAccessToken($newToken);
                return true;

            } catch (\Exception $e) {
                // Log refresh failure
                $error = new WP_Error(
                    'refresh_failed',
                    'Failed to refresh token',
                    ['status' => 500]
                );

                $this->log_error($error, 'ensure_valid_token');
                return false;
            }
        }

        return true;
    }



    /**
     * --------------------------------------------------------------
     * List Files in Google Drive
     * --------------------------------------------------------------
     */
    public function list_files(WP_REST_Request $request): WP_REST_Response|WP_Error {

        $this->setup_google_client();

        // Ensure token validity
        if (!$this->ensure_valid_token()) {
            $error = new WP_Error('no_access_token', 'Not authenticated', ['status' => 401]);
            $this->log_error($error, 'list_files');
            return $error;
        }

        // Pagination parameters
        $per_page   = max(1, intval($request->get_param('per_page') ?? 20));
        $page_token = sanitize_text_field($request->get_param('pageToken') ?? '');

        // Build Drive API params
        $optParams = [
            'pageSize' => $per_page,
            'fields'   => 'nextPageToken, files(id,name,mimeType,size,modifiedTime,webViewLink,owners,ownedByMe)',
        ];

        if ($page_token !== '') {
            $optParams['pageToken'] = $page_token;
        }

        try {
            // Fetch list from Drive
            $results = $this->drive_service->files->listFiles($optParams);

            $items = [];

            foreach ($results->getFiles() as $file) {

                // Extract owners
                $owners = array_map(
                    fn($o) => method_exists($o, 'getDisplayName') ? $o->getDisplayName() : '',
                    $file->getOwners() ?? []
                );

                // Append file info
                $items[] = [
                    'id'           => $file->getId(),
                    'name'         => $file->getName(),
                    'mimeType'     => $file->getMimeType(),
                    'size'         => $file->getSize(),
                    'modifiedTime' => $file->getModifiedTime(),
                    'webViewLink'  => $file->getWebViewLink(),
                    'owners'       => $owners,
                    'canDelete'    => $file->getOwnedByMe(), // frontend helper flag
                ];
            }

            // Response
            return new WP_REST_Response([
                'success'       => true,
                'files'         => $items,
                'nextPageToken' => $results->getNextPageToken(),
            ], 200);

        } catch (\Exception $e) {

            $error = new WP_Error('drive_error', 'Failed to fetch files', ['status' => 500]);
            $this->log_error($error, 'list_files');

            return $error;
        }
    }



    /**
     * --------------------------------------------------------------
     * Map PHP Upload Errors to Human-Readable Messages
     * --------------------------------------------------------------
     */
    private function map_upload_error(int $code): string {

        $errors = [
            UPLOAD_ERR_INI_SIZE   => __('The uploaded file exceeds the upload_max_filesize directive in php.ini.', 'wpmudev-plugin-test'),
            UPLOAD_ERR_FORM_SIZE  => __('The uploaded file exceeds the MAX_FILE_SIZE directive specified in the HTML form.', 'wpmudev-plugin-test'),
            UPLOAD_ERR_PARTIAL    => __('The uploaded file was only partially uploaded.', 'wpmudev-plugin-test'),
            UPLOAD_ERR_NO_FILE    => __('No file was uploaded.', 'wpmudev-plugin-test'),
            UPLOAD_ERR_NO_TMP_DIR => __('Missing a temporary folder.', 'wpmudev-plugin-test'),
            UPLOAD_ERR_CANT_WRITE => __('Failed to write file to disk.', 'wpmudev-plugin-test'),
            UPLOAD_ERR_EXTENSION  => __('A PHP extension stopped the file upload.', 'wpmudev-plugin-test'),
        ];

        return $errors[$code] ?? __('Unknown upload error.', 'wpmudev-plugin-test');
    }



    /**
     * --------------------------------------------------------------
     * Upload Files to Google Drive
     * --------------------------------------------------------------
     */
    public function upload_file(WP_REST_Request $request): WP_REST_Response|WP_Error {

        $this->setup_google_client();

        // Ensure authenticated
        if (!$this->ensure_valid_token()) {
            $error = new WP_Error(
                'no_access_token',
                __('Not authenticated', 'wpmudev-plugin-test'),
                ['status' => 401]
            );

            $this->log_error($error, 'upload_file');
            return $error;
        }

        // Retrieve uploaded files
        $files = $request->get_file_params() ?: [];

        // Normalize input
        $uploaded = [];

        if (!empty($files['file'])) {

            // Handle multiple files
            if (is_array($files['file']['name'])) {

                foreach ($files['file']['name'] as $idx => $name) {

                    $uploaded[] = [
                        'name'     => $files['file']['name'][$idx],
                        'type'     => $files['file']['type'][$idx],
                        'tmp_name' => $files['file']['tmp_name'][$idx],
                        'error'    => $files['file']['error'][$idx],
                        'size'     => $files['file']['size'][$idx],
                    ];
                }

            } else {
                // Single file
                $uploaded[] = $files['file'];
            }
        }

        // If nothing uploaded
        if (empty($uploaded)) {
            $error = new WP_Error(
                'no_file',
                __('No file provided', 'wpmudev-plugin-test'),
                ['status' => 400]
            );

            $this->log_error($error, 'upload_file');
            return $error;
        }

        // --------------------------------------------------------------
        // Upload constraints
        // --------------------------------------------------------------
        $max_size = 25 * 1024 * 1024; // 25MB limit

        $allowed = [
            'image/jpeg',
            'image/png',
            'application/pdf',
            'text/plain',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
        ];

        $results = [];

        // --------------------------------------------------------------
        // Process each uploaded file
        // --------------------------------------------------------------
        foreach ($uploaded as $file) {

            $name = $file['name'] ?? '';
            $size = $file['size'] ?? 0;

            // Handle PHP file upload error
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $results[] = [
                    'success' => false,
                    'message' => $this->map_upload_error((int) $file['error']),
                    'name'    => $name,
                ];
                continue;
            }

            // File size check
            if ($size > $max_size) {
                $results[] = [
                    'success' => false,
                    'message' => __('File exceeds 25MB limit', 'wpmudev-plugin-test'),
                    'name'    => $name,
                ];
                continue;
            }

            // Determine MIME type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = $finfo ? finfo_file($finfo, $file['tmp_name']) : ($file['type'] ?? 'application/octet-stream');
            if ($finfo) {
                finfo_close($finfo);
            }

            // Validate MIME type
            if (!in_array($mime, $allowed, true)) {
                $results[] = [
                    'success' => false,
                    'message' => __('Unsupported file type', 'wpmudev-plugin-test'),
                    'mime'    => $mime,
                    'name'    => $name,
                ];
                continue;
            }

            // Sanitize file name
            $filename = sanitize_file_name(
                preg_replace('/[^A-Za-z0-9_\-\.]/', '_', (string) $name)
            );

            try {
                // Prepare Drive file data
                $drive_file = new \Google_Service_Drive_DriveFile();
                $drive_file->setName($filename);

                // Upload
                $created = $this->drive_service->files->create(
                    $drive_file,
                    [
                        'data'       => file_get_contents($file['tmp_name']),
                        'mimeType'   => $mime,
                        'uploadType' => 'multipart',
                        'fields'     => 'id,name,mimeType,size,webViewLink',
                    ]
                );

                // Cleanup temporary file
                @unlink($file['tmp_name']);

                // Append successful response
                $results[] = [
                    'success'     => true,
                    'id'          => $created->getId(),
                    'name'        => $created->getName(),
                    'mimeType'    => $created->getMimeType(),
                    'size'        => $created->getSize(),
                    'webViewLink' => $created->getWebViewLink(),
                ];

            } catch (\Exception $e) {

                // Log error details
                $this->log_error([
                    'error' => $e->getMessage(),
                    'file'  => $name,
                    'size'  => $size,
                    'mime'  => $mime,
                ], 'upload_file');

                $results[] = [
                    'success' => false,
                    'message' => sprintf(
                        __('Failed to upload file: %s', 'wpmudev-plugin-test'),
                        $e->getMessage()
                    ),
                    'name' => $name,
                ];
            }
        }

        // Final API response
        return new WP_REST_Response([
            'success' => true,
            'files'   => $results,
        ], 200);
    }


    /**
     * --------------------------------------------------------------
     * DELETE FILE
     * --------------------------------------------------------------
     * Deletes a Google Drive file after validating authentication,
     * sanitizing input, confirming ownership, and handling errors.
     */
    public function delete_file(WP_REST_Request $request): WP_REST_Response|WP_Error {

        // Initialize Google Client
        $this->setup_google_client();

        // Ensure valid access token
        if (!$this->ensure_valid_token()) {
            $error = new WP_Error(
                'no_access_token',
                __('Not authenticated', 'wpmudev-plugin-test'),
                ['status' => 401]
            );
            $this->log_error($error, 'delete_file');
            return $error;
        }

        // Validate required file_id
        $file_id = sanitize_text_field($request->get_param('file_id'));
        if (empty($file_id)) {
            $error = new WP_Error(
                'missing_file_id',
                __('No file ID provided', 'wpmudev-plugin-test'),
                ['status' => 400]
            );
            $this->log_error($error, 'delete_file');
            return $error;
        }

        try {
            // Pre-check: ensure file is owned by this app
            $file = $this->drive_service->files->get($file_id, [
                'fields' => 'id,ownedByMe'
            ]);

            if (!$file->getOwnedByMe()) {
                return new WP_Error(
                    'not_authorized',
                    __('This app can only delete files it created or owns.', 'wpmudev-plugin-test'),
                    ['status' => 403]
                );
            }

            // Delete file
            $this->drive_service->files->delete($file_id);

            return new WP_REST_Response([
                'success' => true,
                'message' => __('File deleted successfully', 'wpmudev-plugin-test'),
                'id'      => $file_id,
            ], 200);

        } catch (\Exception $e) {
            $this->log_error($e->getMessage(), 'delete_file');

            return new WP_Error(
                'delete_failed',
                sprintf(
                    /* translators: %s: error message */
                    __('Failed to delete file: %s', 'wpmudev-plugin-test'),
                    $e->getMessage()
                ),
                ['status' => 500]
            );
        }
    }


    /**
     * --------------------------------------------------------------
     * DOWNLOAD FILE
     * --------------------------------------------------------------
     * Downloads a file's raw binary content from Google Drive,
     * returns it base64-encoded along with metadata.
     */
    public function download_file(WP_REST_Request $request): WP_REST_Response|WP_Error {

        // Setup Google Client
        $this->setup_google_client();

        // Token validation
        if (!$this->ensure_valid_token()) {
            $error = new WP_Error('no_access_token', 'Not authenticated', ['status' => 401]);
            $this->log_error($error, 'download_file');
            return $error;
        }

        // Validate file ID
        $file_id = sanitize_text_field($request->get_param('file_id'));
        if ($file_id === '') {
            $error = new WP_Error('missing_file_id', 'File ID is required', ['status' => 400]);
            $this->log_error($error, 'download_file');
            return $error;
        }

        try {
            // Fetch metadata
            $meta = $this->drive_service->files->get($file_id, [
                'fields' => 'id,name,mimeType,size'
            ]);

            // Fetch file content stream
            $resp    = $this->drive_service->files->get($file_id, ['alt' => 'media']);
            $content = $resp->getBody();

            // Handle stream or string
            $body = is_callable([$content, 'getContents'])
                ? $content->getContents()
                : $content;

            return new WP_REST_Response([
                'success'  => true,
                'content'  => base64_encode($body),
                'filename' => $meta->getName(),
                'mimeType' => $meta->getMimeType(),
                'size'     => $meta->getSize(),
            ], 200);

        } catch (\Exception $e) {
            $error = new WP_Error(
                'download_failed',
                'Failed to download file',
                ['status' => 500]
            );
            $this->log_error($error, 'download_file');
            return $error;
        }
    }


    /**
     * --------------------------------------------------------------
     * CREATE FOLDER
     * --------------------------------------------------------------
     * Creates a new folder in Google Drive, with optional parent folder.
     */
    public function create_folder(WP_REST_Request $request): WP_REST_Response|WP_Error {

        // Setup Google Client
        $this->setup_google_client();

        // Validate token
        if (!$this->ensure_valid_token()) {
            $error = new WP_Error('no_access_token', 'Not authenticated', ['status' => 401]);
            $this->log_error($error, 'create_folder');
            return $error;
        }

        // Extract params
        $params     = $request->get_json_params();
        $name       = sanitize_text_field($params['name'] ?? '');
        $parent_id  = sanitize_text_field($params['parent_id'] ?? '');

        if ($name === '') {
            $error = new WP_Error('missing_name', 'Folder name is required', ['status' => 400]);
            $this->log_error($error, 'create_folder');
            return $error;
        }

        try {
            // Prepare folder metadata
            $folder = new \Google_Service_Drive_DriveFile([
                'name'     => $name,
                'mimeType' => 'application/vnd.google-apps.folder',
            ]);

            // Set parent folder if provided
            if ($parent_id !== '') {
                $folder->setParents([$parent_id]);
            }

            // Create folder
            $result = $this->drive_service->files->create($folder, [
                'fields' => 'id,name,mimeType,webViewLink'
            ]);

            return new WP_REST_Response([
                'success' => true,
                'folder'  => [
                    'id'          => $result->getId(),
                    'name'        => $result->getName(),
                    'mimeType'    => $result->getMimeType(),
                    'webViewLink' => $result->getWebViewLink(),
                ],
            ], 200);

        } catch (\Exception $e) {
            $error = new WP_Error('create_failed', 'Failed to create folder', ['status' => 500]);
            $this->log_error($error, 'create_folder');
            return $error;
        }
    }


    /**
     * --------------------------------------------------------------
     * STATUS CHECK
     * --------------------------------------------------------------
     * Returns authentication and token status for debugging UI.
     */
    public function status_check(WP_REST_Request $request): WP_REST_Response {

        $auth_creds = get_option($this->option_name, []);
        $token      = json_decode(get_option('wpmudev_drive_access_token', ''), true);
        $expires_at = (int) get_option('wpmudev_drive_token_expires', 0);

        return new WP_REST_Response([
            'client_ready'    => $this->client !== null,
            'has_credentials' => !empty($auth_creds['client_id']) && !empty($auth_creds['client_secret']),
            'token_present'   => is_array($token),
            'token_expired'   => time() > $expires_at,
            'expires_at'      => $expires_at,
        ], 200);
    }


    /**
     * --------------------------------------------------------------
     * ERROR LOGGING
     * --------------------------------------------------------------
     * Logs WP_Error and general exceptions with sensitive token redaction.
     */
    private function log_error($error, $context = ''): void {

        // Normalize context
        if ($context instanceof \WP_Error) {
            $context = 'WP_Error context: ' . $context->get_error_message();
        } elseif (!is_string($context)) {
            $context = print_r($context, true);
        }

        /**
         * Helper: Redact sensitive data from arrays
         */
        $redact = function ($data) {
            if (!is_array($data)) {
                return $data;
            }

            foreach (['access_token', 'refresh_token', 'id_token'] as $key) {
                if (isset($data[$key])) {
                    $token        = $data[$key];
                    $data[$key]   = '[REDACTED:' . substr($token, 0, 6) . '...' . substr($token, -6) . ']';
                }
            }

            return $data;
        };

        /**
         * WP_Error logging
         */
        if (is_wp_error($error)) {
            $code    = $error->get_error_code();
            $message = $error->get_error_message();
            $data    = $redact($error->get_error_data());

            if (!defined('WP_DEBUG') || WP_DEBUG === false) {
                error_log(sprintf('[Drive_API] %s | Code: %s | Message: %s', $context, $code, $message));
            } else {
                error_log(sprintf(
                    '[Drive_API] %s | Code: %s | Message: %s | Data: %s',
                    $context,
                    $code,
                    $message,
                    print_r($data, true)
                ));
            }

            return;
        }

        /**
         * Non-WP_Error logging
         */
        $safe_error = $redact(is_array($error) ? $error : ['error' => $error]);

        if (!defined('WP_DEBUG') || WP_DEBUG === false) {
            error_log(sprintf('[Drive_API] %s | General Error', $context));
        } else {
            error_log(sprintf(
                '[Drive_API] %s | General Error: %s',
                $context,
                print_r($safe_error, true)
            ));
     
        }
     
    }
}



