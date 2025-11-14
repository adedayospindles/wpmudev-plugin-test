<?php
namespace WPMUDEV\PluginTest\Endpoints\V1;

defined('WPINC') || die;

use WPMUDEV\PluginTest\Base;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class Drive_API extends Base {

    private ?\Google_Client $client = null;
    private ?\Google_Service_Drive $drive_service = null;
    private string $redirect_uri;
    private string $option_name = 'wpmudev_plugin_tests_auth';

    public function init(): void {
        $this->redirect_uri = home_url('/wp-json/wpmudev/v1/drive/callback');

        // Load Google Client if not already loaded
        if (!class_exists('\Google_Client') && defined('WPMUDEV_PLUGINTEST_DIR')) {
            $autoload = WPMUDEV_PLUGINTEST_DIR . 'vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }
        }

        $this->setup_google_client();
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function require_admin(): bool {
        return current_user_can('manage_options');
    }


     /**
 * Register REST API routes.
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

    foreach ($routes as $route => $data) {
        register_rest_route(
            'wpmudev/v1/drive',
            $route,
            $data
        );
    }
}



    private function encrypt(string $plaintext): string {
        if ($plaintext === '') return '';
        $key = hash('sha256', AUTH_KEY . NONCE_KEY);
        $iv  = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));
        $cipher = openssl_encrypt($plaintext, 'AES-256-CBC', substr($key, 0, 32), OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $cipher);
    }

    private function decrypt(string $ciphertext): string {
        if ($ciphertext === '') return '';
        $key = hash('sha256', AUTH_KEY . NONCE_KEY);
        $data = base64_decode($ciphertext);
        $ivlen = openssl_cipher_iv_length('AES-256-CBC');
        $iv = substr($data, 0, $ivlen);
        $payload = substr($data, $ivlen);
        return openssl_decrypt($payload, 'AES-256-CBC', substr($key, 0, 32), OPENSSL_RAW_DATA, $iv);
    }

    /**
 * Configure the Google Client with proper scopes and settings.
 */
private function setup_google_client(): void {
    $auth_creds = get_option($this->option_name, []);
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

    // Ensure refresh tokens are returned
    $this->client->setAccessType('offline');
    $this->client->setPrompt('consent');
    $this->client->setIncludeGrantedScopes(true);

    //: full Drive access (upload + delete)
    /*
    $this->client->setScopes([
        \Google_Service_Drive::DRIVE
    ]);
    */

    // Limited Scope
    $this->client->setScopes([
        \Google_Service_Drive::DRIVE_FILE,
        \Google_Service_Drive::DRIVE_METADATA_READONLY,
    ]);


    // Load existing token if available
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
                            update_option('wpmudev_drive_token_expires', time() + intval($new_token['expires_in']));
                        }
                        $this->client->setAccessToken($new_token);
                    }
                }
            }
        }
    }

    $this->drive_service = new \Google_Service_Drive($this->client);
}

    public function save_credentials(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $params        = $request->get_json_params();
        $client_id     = sanitize_text_field($params['client_id'] ?? '');
        $client_secret = sanitize_text_field($params['client_secret'] ?? '');

        if ($client_id === '' || $client_secret === '') {
            $error = new WP_Error('invalid_params', 'client_id and client_secret are required', ['status' => 400]);
            $this->log_error($error, 'save_credentials');
            return $error;
        }

        update_option($this->option_name, [
            'client_id'     => $this->encrypt($client_id),
            'client_secret' => $this->encrypt($client_secret),
        ]);

        delete_option('wpmudev_drive_access_token');
        delete_option('wpmudev_drive_refresh_token');
        delete_option('wpmudev_drive_token_expires');

        $this->setup_google_client();

        return new WP_REST_Response(['success' => true, 'message' => 'Credentials saved securely.'], 200);
    }

  

    /**
     * Initiates the Google OAuth flow and stores a CSRF-safe state token.
     */
    public function start_auth(): WP_REST_Response|WP_Error {
        if (!$this->client) {
            $error = new WP_Error('missing_credentials', 'Google OAuth credentials not configured', ['status' => 400]);
            $this->log_error($error, 'start_auth');
            return $error;
        }

        $current_user_id = get_current_user_id();
        if ($current_user_id <= 0) {
            $error = new WP_Error('invalid_user', 'You must be logged in to authenticate with Google.', ['status' => 403]);
            $this->log_error($error, 'start_auth');
            return $error;
        }

        $uuid = wp_generate_uuid4();

        // Encode both UUID and user ID into the state
        $payload = json_encode([
            'uuid'    => $uuid,
            'user_id' => $current_user_id,
        ]);
        $encoded_state = base64_encode($payload);

        // Store UUID in transient keyed by user ID
        $transient_key = 'wpmudev_drive_oauth_state_' . $current_user_id;
        set_transient($transient_key, $uuid, 900);

        $this->client->setState($encoded_state);
        $auth_url = $this->client->createAuthUrl();

        $this->log_error([
            'user_id'       => $current_user_id,
            'uuid'          => $uuid,
            'transient_key' => $transient_key,
            'auth_url'      => $auth_url,
            'state_encoded' => $encoded_state,
        ], 'start_auth');

        return new WP_REST_Response([
            'success'  => true,
            'auth_url' => $auth_url,
        ], 200);
    }

    /**
     * Handles the OAuth callback from Google and exchanges the code for access tokens.
     */
    public function handle_callback(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $code          = $request->get_param('code');
        $state_encoded = $request->get_param('state');

        if (empty($code) || empty($state_encoded)) {
            $error = new WP_Error('missing_params', 'Missing authorization code or state.', ['status' => 400]);
            $this->log_error($error, 'handle_callback');
            return $error;
        }

        // Decode the state payload
        $payload = json_decode(base64_decode($state_encoded), true);
        $uuid    = $payload['uuid'] ?? '';
        $user_id = intval($payload['user_id'] ?? 0);

        if ($user_id <= 0) {
            $error = new WP_Error('invalid_user', 'User ID missing or invalid in callback.', ['status' => 400]);
            $this->log_error($error, 'handle_callback');
            return $error;
        }

        $transient_key = 'wpmudev_drive_oauth_state_' . $user_id;
        $expected_uuid = get_transient($transient_key);

        if (empty($uuid) || $uuid !== $expected_uuid) {
            $this->log_error([
                'user_id'       => $user_id,
                'error'         => 'State mismatch',
                'expected_uuid' => $expected_uuid,
                'received_uuid' => $uuid,
                'transient_key' => $transient_key,
            ], 'handle_callback');

            return new WP_Error('invalid_state', 'Invalid state parameter. Possible CSRF detected.', ['status' => 400]);
        }

        delete_transient($transient_key);

        $this->setup_google_client();
        if (!$this->client) {
            $error = new WP_Error('client_not_configured', 'Google client not configured.', ['status' => 400]);
            $this->log_error($error, 'handle_callback');
            return $error;
        }

        try {
            $token = $this->client->fetchAccessTokenWithAuthCode($code);
            $this->log_error($token, 'token_response');

            if (isset($token['error'])) {
                $error = new WP_Error('auth_error', $token['error_description'] ?? $token['error'], ['status' => 400]);
                $this->log_error($error, 'handle_callback');
                return $error;
            }

            if (isset($token['refresh_token'])) {
                update_option('wpmudev_drive_refresh_token', $this->encrypt($token['refresh_token']));
            }

            update_option('wpmudev_drive_access_token', wp_json_encode($token));
            if (isset($token['expires_in'])) {
                update_option('wpmudev_drive_token_expires', time() + intval($token['expires_in']));
            }

            return new WP_REST_Response([
                'success' => true,
                'message' => 'Tokens stored successfully',
                'user_id' => $user_id,
            ], 200);

        } catch (\Exception $e) {
            $error = new WP_Error('auth_exception', 'Failed to get access token: ' . $e->getMessage(), ['status' => 500]);
            $this->log_error($error, 'handle_callback');
            return $error;
        }
    }


    /* Helper: initialize Google client with stored tokens */

    private function init_client_from_storage(): bool {
        $this->setup_google_client(); // your method to create/configure $this->client with client_id/secret/redirect

        if (!$this->client) {
            return false;
        }

        $access_json = get_option('wpmudev_drive_access_token', '');
        if (empty($access_json)) {
            return true; // client exists but no token yet
        }

        $token = json_decode($access_json, true);
        $this->client->setAccessToken($token);

        // If expired, refresh using refresh_token
        if ($this->client->isAccessTokenExpired()) {
            $refresh_token = $token['refresh_token'] ?? get_option('wpmudev_drive_refresh_token', '');
            if ($refresh_token) {
                // If you stored refresh token encrypted
                $refresh_token = $this->decrypt($refresh_token);
                try {
                    $newToken = $this->client->fetchAccessTokenWithRefreshToken($refresh_token);
                    if (isset($newToken['error'])) {
                        // failed to refresh
                        $this->log_error($newToken, 'refresh_failed');
                        return false;
                    }

                    // ensure refresh_token persists if not returned
                    if (!isset($newToken['refresh_token'])) {
                        $newToken['refresh_token'] = $refresh_token;
                    }

                    update_option('wpmudev_drive_access_token', wp_json_encode($newToken));
                    if (isset($newToken['expires_in'])) {
                        update_option('wpmudev_drive_token_expires', time() + intval($newToken['expires_in']));
                    }
                    $this->client->setAccessToken($newToken);
                } catch (\Exception $e) {
                    $this->log_error(['exception' => $e->getMessage()], 'refresh_exception');
                    return false;
                }
            } else {
                $this->log_error('No refresh token available', 'refresh_missing');
                return false;
            }
        }

        return true;
    }


    /**
     * Ensure the access token is valid, refresh if expired
     */
    private function ensure_valid_token(): bool {
        if (!$this->client) return false;

        $token = json_decode(get_option('wpmudev_drive_access_token', ''), true);
        if (!is_array($token)) return false;

        $this->client->setAccessToken($token);

        if ($this->client->isAccessTokenExpired()) {
            $refresh_token = $this->decrypt(get_option('wpmudev_drive_refresh_token', ''));
            if ($refresh_token === '') return false;

            try {
                $newToken = $this->client->fetchAccessTokenWithRefreshToken($refresh_token);
                if (empty($newToken['refresh_token'])) {
                    $newToken['refresh_token'] = $refresh_token;
                }

                update_option('wpmudev_drive_access_token', wp_json_encode($newToken));
                if (isset($newToken['expires_in'])) {
                    update_option('wpmudev_drive_token_expires', time() + intval($newToken['expires_in']));
                }

                $this->client->setAccessToken($newToken);
                return true;

            } catch (\Exception $e) {
                $error = new WP_Error('refresh_failed', 'Failed to refresh token', ['status' => 500]);
                $this->log_error($error, 'ensure_valid_token');
                return false;
            }
        }

        return true;
    }

    /** List files **/

    public function list_files(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $this->setup_google_client();
        if (!$this->ensure_valid_token()) {
            $error = new WP_Error('no_access_token', 'Not authenticated', ['status' => 401]);
            $this->log_error($error, 'list_files');
            return $error;
        }

        $per_page   = max(1, intval($request->get_param('per_page') ?? 20));
        $page_token = sanitize_text_field($request->get_param('pageToken') ?? '');

        $optParams = [
            'pageSize' => $per_page,
            'fields'   => 'nextPageToken, files(id,name,mimeType,size,modifiedTime,webViewLink,owners,ownedByMe)',
        ];
        if ($page_token !== '') {
            $optParams['pageToken'] = $page_token;
        }

        try {
            $results = $this->drive_service->files->listFiles($optParams);
            $items   = [];

            foreach ($results->getFiles() as $file) {
                $owners = array_map(
                    fn($o) => method_exists($o, 'getDisplayName') ? $o->getDisplayName() : '',
                    $file->getOwners() ?? []
                );

                $items[] = [
                    'id'           => $file->getId(),
                    'name'         => $file->getName(),
                    'mimeType'     => $file->getMimeType(),
                    'size'         => $file->getSize(),
                    'modifiedTime' => $file->getModifiedTime(),
                    'webViewLink'  => $file->getWebViewLink(),
                    'owners'       => $owners,
                    'canDelete'    => $file->getOwnedByMe(), // flag for frontend
                ];
            }

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


    /* Max Upload error */
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

/** Upload */
public function upload_file(WP_REST_Request $request): WP_REST_Response|WP_Error {
    $this->setup_google_client();
    if (!$this->ensure_valid_token()) {
        $error = new WP_Error(
            'no_access_token',
            __('Not authenticated', 'wpmudev-plugin-test'),
            ['status' => 401]
        );
        $this->log_error($error, 'upload_file');
        return $error;
    }

    // Grab files from request
    $files = $request->get_file_params() ?: [];

    // IMPORTANT: populate $uploaded from $_FILES['file']
    $uploaded = [];
    if (!empty($files['file'])) {
        // If multiple files are allowed, normalize to array
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
            $uploaded[] = $files['file'];
        }
    }

    if (empty($uploaded)) {
        $error = new WP_Error(
            'no_file',
            __('No file provided', 'wpmudev-plugin-test'),
            ['status' => 400]
        );
        $this->log_error($error, 'upload_file');
        return $error;
    }

    $max_size = 25 * 1024 * 1024;
    $allowed = [
        'image/jpeg','image/png','application/pdf','text/plain',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip'
    ];

    $results = [];

    foreach ($uploaded as $file) {
        $name = $file['name'] ?? '';
        $size = $file['size'] ?? 0;

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $results[] = [
                'success' => false,
                'message' => $this->map_upload_error((int)$file['error']),
                'name'    => $name,
            ];
            continue;
        }

        if ($size > $max_size) {
            $results[] = [
                'success' => false,
                'message' => __('File exceeds 25MB limit', 'wpmudev-plugin-test'),
                'name'    => $name,
            ];
            continue;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = $finfo ? finfo_file($finfo, $file['tmp_name']) : ($file['type'] ?? 'application/octet-stream');
        if ($finfo) finfo_close($finfo);

        if (!in_array($mime, $allowed, true)) {
            $results[] = [
                'success' => false,
                'message' => __('Unsupported file type', 'wpmudev-plugin-test'),
                'mime'    => $mime,
                'name'    => $name,
            ];
            continue;
        }

        $filename = sanitize_file_name(
            preg_replace('/[^A-Za-z0-9_\-\.]/', '_', (string)$name)
        );

        try {
            $drive_file = new \Google_Service_Drive_DriveFile();
            $drive_file->setName($filename);

            $created = $this->drive_service->files->create(
                $drive_file,
                [
                    'data'       => file_get_contents($file['tmp_name']),
                    'mimeType'   => $mime,
                    'uploadType' => 'multipart',
                    'fields'     => 'id,name,mimeType,size,webViewLink',
                ]
            );

            @unlink($file['tmp_name']);

            $results[] = [
                'success'    => true,
                'id'         => $created->getId(),
                'name'       => $created->getName(),
                'mimeType'   => $created->getMimeType(),
                'size'       => $created->getSize(),
                'webViewLink'=> $created->getWebViewLink(),
            ];
        } catch (\Exception $e) {
            $this->log_error([
                'error' => $e->getMessage(),
                'file'  => $name,
                'size'  => $size,
                'mime'  => $mime,
            ], 'upload_file');

            $results[] = [
                'success' => false,
                'message' => sprintf(__('Failed to upload file: %s', 'wpmudev-plugin-test'), $e->getMessage()),
                'name'    => $name,
            ];
        }
    }

    return new WP_REST_Response([
        'success' => true,
        'files'   => $results,
    ], 200);
}



/** 
 * Delete  
 **/

public function delete_file(WP_REST_Request $request): WP_REST_Response|WP_Error {
    $this->setup_google_client();
    if (!$this->ensure_valid_token()) {
        $error = new WP_Error('no_access_token', __('Not authenticated', 'wpmudev-plugin-test'), ['status' => 401]);
        $this->log_error($error, 'delete_file');
        return $error;
    }

    $file_id = sanitize_text_field($request->get_param('file_id'));
    if (empty($file_id)) {
        $error = new WP_Error('missing_file_id', __('No file ID provided', 'wpmudev-plugin-test'), ['status' => 400]);
        $this->log_error($error, 'delete_file');
        return $error;
    }

    try {
        // Pre‑check ownership/authorization
        $file = $this->drive_service->files->get($file_id, ['fields' => 'id,ownedByMe']);
        if (!$file->getOwnedByMe()) {
            return new WP_Error(
                'not_authorized',
                __('This app can only delete files it created or owns.', 'wpmudev-plugin-test'),
                ['status' => 403]
            );
        }

        $this->drive_service->files->delete($file_id);

        return new WP_REST_Response([
            'success' => true,
            'message' => __('File deleted successfully', 'wpmudev-plugin-test'),
            'id'      => $file_id,
        ], 200);

    } catch (\Exception $e) {
        $this->log_error($e->getMessage(), 'delete_file');

        /* translators: %s: file name */
        return new WP_Error(
            'delete_failed',
            sprintf(__('Failed to delete file: %s', 'wpmudev-plugin-test'), $e->getMessage()),
            ['status' => 500]
        );
    }
}


    public function download_file(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $this->setup_google_client();
        if (!$this->ensure_valid_token()) {
            $error = new WP_Error('no_access_token', 'Not authenticated', ['status' => 401]);
            $this->log_error($error, 'download_file');
            return $error;
        }

        $file_id = sanitize_text_field($request->get_param('file_id'));
        if ($file_id === '') {
            $error = new WP_Error('missing_file_id', 'File ID is required', ['status' => 400]);
            $this->log_error($error, 'download_file');
            return $error;
        }

        try {
            $meta = $this->drive_service->files->get($file_id, ['fields' => 'id,name,mimeType,size']);
            
            // Properly fetch file content
            $resp = $this->drive_service->files->get($file_id, ['alt' => 'media']);
            $content = $resp->getBody(); // Google client streams the content
            if (is_callable([$content, 'getContents'])) {
                $body = $content->getContents();
            } else {
                $body = $content; // fallback
            }

            return new WP_REST_Response([
                'success'  => true,
                'content'  => base64_encode($body),
                'filename' => $meta->getName(),
                'mimeType' => $meta->getMimeType(),
                'size'     => $meta->getSize(),
            ], 200);

        } catch (\Exception $e) {
            $error = new WP_Error('download_failed', 'Failed to download file', ['status' => 500]);
            $this->log_error( $error, 'download_file');
            return $error;
        }
    }

    public function create_folder(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $this->setup_google_client();
        if (!$this->ensure_valid_token()) {
            $error = new WP_Error('no_access_token', 'Not authenticated', ['status' => 401]);
            $this->log_error($error, 'create_folder');
            return $error;
        }

        $params = $request->get_json_params();
        $name   = sanitize_text_field($params['name'] ?? '');
        $parent_id = sanitize_text_field($params['parent_id'] ?? '');

        if ($name === '') {
            $error = new WP_Error('missing_name', 'Folder name is required', ['status' => 400]);
            $this->log_error($error, 'create_folder');
            return $error;
        }

        try {
            $folder = new \Google_Service_Drive_DriveFile([
                'name' => $name,
                'mimeType' => 'application/vnd.google-apps.folder',
            ]);

            if ($parent_id !== '') {
                $folder->setParents([$parent_id]);
            }

            $result = $this->drive_service->files->create($folder, ['fields' => 'id,name,mimeType,webViewLink']);

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
     * Logs any WP_Error or general error for debugging.
     *
     * - Redacts sensitive tokens (access_token, refresh_token, id_token).
     * - In production (WP_DEBUG = false), logs only status/context.
     * - In development (WP_DEBUG = true), logs full arrays with redactions.
     *
     * @param mixed  $error   The error object or message.
     * @param string $context Optional context (e.g. 'save_credentials', 'auth', etc.).
     */
    private function log_error($error, $context = ''): void {
        // Normalize context
        if ($context instanceof \WP_Error) {
            $context = 'WP_Error context: ' . $context->get_error_message();
        } elseif (!is_string($context)) {
            $context = print_r($context, true);
        }

        // Helper: redact sensitive keys
        $redact = function ($data) {
            if (!is_array($data)) {
                return $data;
            }
            foreach (['access_token', 'refresh_token', 'id_token'] as $key) {
                if (isset($data[$key])) {
                    // Show only first/last 6 chars for debugging
                    $token = $data[$key];
                    $data[$key] = '[REDACTED:' . substr($token, 0, 6) . '...' . substr($token, -6) . ']';
                }
            }
            return $data;
        };

        // Handle WP_Error objects
        if (is_wp_error($error)) {
            $code    = $error->get_error_code();
            $message = $error->get_error_message();
            $data    = $redact($error->get_error_data());

            if (!defined('WP_DEBUG') || WP_DEBUG === false) {
                // Production: log only context + status
                error_log(sprintf('[Drive_API] %s | Code: %s | Message: %s', $context, $code, $message));
            } else {
                // Debug: log full (redacted) array
                error_log(sprintf('[Drive_API] %s | Code: %s | Message: %s | Data: %s',
                    $context,
                    $code,
                    $message,
                    print_r($data, true)
                ));
            }
            return;
        }

        // Handle non-WP_Error values
        $safe_error = $redact(is_array($error) ? $error : ['error' => $error]);

        if (!defined('WP_DEBUG') || WP_DEBUG === false) {
            // Production: log only context
            error_log(sprintf('[Drive_API] %s | General Error', $context));
        } else {
            // Debug: log full (redacted) array
            error_log(sprintf('[Drive_API] %s | General Error: %s', $context, print_r($safe_error, true)));
        }
    }


}
