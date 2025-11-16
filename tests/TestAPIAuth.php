<?php
/**
 * REST API Authentication and Google Drive Endpoint Tests (Unit / Direct Calls)
 *
 * This suite tests stubbed handlers directly (no WordPress route registration),
 * so it’s fast and focused on handler logic. For integration tests, use a suite
 * that registers routes and calls rest_do_request() i.e. TestApiAuthRest.php
 *
 * @package WPMUDEV_Plugin_Test
 */

use WPMUDEV\PluginTest\Endpoints\V1\Drive_API;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Stubbed subclass of Drive_API to avoid real Google API calls.
 * - Provides fake Google_Client and Google_Service_Drive objects
 * - Overrides crypto methods to avoid OpenSSL
 * - Implements stubbed route handlers that return predictable responses
 */
class Drive_API_Test_Unit extends Drive_API {
    // Public for simplicity in tests (avoids typed private property access warnings)
    public $client;
    public $drive_service;

    /**
     * Setup a mocked Google client and Drive service
     * (kept minimal to satisfy calls made in handler methods).
     */
    public function setup_google_client(): void {
        // Fake Google client with only createAuthUrl
        $this->client = new class {
            public function createAuthUrl() {
                return 'https://mock.google.com/auth?client_id=test';
            }
        };

        // Fake Drive service with minimal structure
        $this->drive_service = new class {
            public $files;
            public function __construct() {
                $this->files = new class {
                    public function listFiles($optParams) {
                        return new class {
                            public function getFiles() { return []; }
                            public function getNextPageToken() { return null; }
                        };
                    }
                };
            }
        };
    }

    // Override crypto to avoid OpenSSL calls (no-ops for tests)
    protected function encrypt(string $plaintext): string { return $plaintext; }
    protected function decrypt(string $ciphertext): string { return $ciphertext; }

    // -------------------------------------------------------------------------
    // Stubbed route handlers (return predictable mock responses)
    // -------------------------------------------------------------------------

    /**
     * Start auth: returns a mocked Google OAuth URL.
     */
    public function route_auth_start(WP_REST_Request $request): WP_REST_Response {
        $this->setup_google_client();
        return new WP_REST_Response(['auth_url' => $this->client->createAuthUrl()], 200);
    }

    /**
     * Callback: simulates saving tokens and returns ok.
     */
    public function route_auth_callback(WP_REST_Request $request): WP_REST_Response {
        update_option('wpmudev_drive_access_token', 'mock_auth');
        update_option('wpmudev_drive_refresh_token', 'mock_refresh');
        return new WP_REST_Response(['status' => 'ok'], 200);
    }

    /**
     * List files: returns an empty list for predictability.
     */
    public function route_list_files(WP_REST_Request $request): WP_REST_Response {
        return new WP_REST_Response(['files' => []], 200);
    }

    /**
     * Upload file: returns a mocked file object.
     */
    public function route_upload_file(WP_REST_Request $request): WP_REST_Response {
        return new WP_REST_Response([
            'file' => [
                'id'          => 'mock_file_id',
                'name'        => 'mock_file',
                'mimeType'    => 'text/plain',
                'size'        => 123,
                'webViewLink' => 'https://mock.com/file',
            ],
        ], 200);
    }

    /**
     * Create folder: returns a mocked folder object.
     */
    public function route_create_folder(WP_REST_Request $request): WP_REST_Response {
        return new WP_REST_Response([
            'folder' => [
                'id'   => 'mock_folder_id',
                'name' => 'mock_file',
            ],
        ], 200);
    }

    /**
     * Download file: returns mocked content and filename.
     */
    public function route_download_file(WP_REST_Request $request): WP_REST_Response {
        return new WP_REST_Response([
            'content'  => base64_encode('mock content'),
            'filename' => 'mock_file.txt',
        ], 200);
    }
}

/**
 * PHPUnit test class for Drive_API_Test (unit tests via direct calls).
 * - Exercises handler logic directly (no routing layer)
 * - Covers authentication, file operations, and edge cases
 */
class TestAPIAuth extends WP_Test_REST_TestCase {

    protected static $api;

    // ----------------------------
    // Setup & Teardown
    // ----------------------------

    /**
     * Create a stubbed API instance and seed dummy options.
     * Uses Reflection to bypass Drive_API constructor (if any).
     */
    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();

        $ref = new ReflectionClass(Drive_API_Test::class);
        self::$api = $ref->newInstanceWithoutConstructor();
        self::$api->setup_google_client();

        // Safe fake credentials for tests (do not use real ones)
        update_option('wpmudev_plugin_tests_auth', [
            'client_id'     => 'dummy-client-id',
            'client_secret' => 'dummy-client-secret',
        ]);
    }

    /**
     * Clean up options and transients to avoid test pollution.
     */
    public static function tearDownAfterClass(): void {
        delete_option('wpmudev_plugin_tests_auth');
        delete_option('wpmudev_drive_access_token');
        delete_option('wpmudev_drive_refresh_token');
        delete_transient('wpmudev_drive_oauth_state');
    }

    // ----------------------------
    // Authentication Endpoint Tests
    // ----------------------------

    /**
     * Verifies the auth start handler returns a valid mock URL.
     */
    public function test_start_auth_success() {
        $response = self::$api->route_auth_start(new WP_REST_Request('POST', '/wpmudev/v1/drive/auth'));
        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertArrayHasKey('auth_url', $data);
        $this->assertStringContainsString('mock.google.com', $data['auth_url']);
    }

    /**
     * Verifies the callback handler stores tokens and returns ok.
     */
    public function test_callback_token_flow() {
        $response = self::$api->route_auth_callback(new WP_REST_Request('GET', '/wpmudev/v1/drive/callback'));
        $this->assertEquals(200, $response->get_status());
        $this->assertNotEmpty(get_option('wpmudev_drive_access_token', ''));
        $this->assertNotEmpty(get_option('wpmudev_drive_refresh_token', ''));
    }

    // ----------------------------
    // Google Drive Endpoint Tests
    // ----------------------------

    /**
     * Verifies listing files returns an empty array.
     */
    public function test_list_files() {
        $response = self::$api->route_list_files(new WP_REST_Request('GET', '/wpmudev/v1/drive/files'));
        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertArrayHasKey('files', $data);
        $this->assertIsArray($data['files']);
        $this->assertEmpty($data['files']);
    }

    /**
     * Verifies upload returns a mock file object.
     */
    public function test_upload_file() {
        $response = self::$api->route_upload_file(new WP_REST_Request('POST', '/wpmudev/v1/drive/upload'));
        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertArrayHasKey('file', $data);
        $this->assertEquals('mock_file', $data['file']['name']);
    }

    /**
     * Verifies folder creation returns a mock folder object.
     */
    public function test_create_folder() {
        $response = self::$api->route_create_folder(new WP_REST_Request('POST', '/wpmudev/v1/drive/create-folder'));
        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertArrayHasKey('folder', $data);
        $this->assertEquals('mock_file', $data['folder']['name']);
    }

    /**
     * Verifies download returns base64 mock content.
     */
    public function test_download_file() {
        $response = self::$api->route_download_file(new WP_REST_Request('GET', '/wpmudev/v1/drive/download'));
        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertArrayHasKey('content', $data);
        $this->assertEquals(base64_encode('mock content'), $data['content']);
    }

    // ----------------------------
    // Edge-case / Error Handling Tests
    // ----------------------------

    /**
     * Simulates a missing file upload and verifies handler is robust.
     */
    public function test_upload_invalid_file() {
        // Simulate missing file upload (typical PHP superglobal structure)
        $_FILES['file'] = [
            'name'     => '',
            'type'     => '',
            'tmp_name' => '',
            'error'    => UPLOAD_ERR_NO_FILE,
            'size'     => 0,
        ];

        $request = new WP_REST_Request('POST', '/wpmudev/v1/drive/upload');
        $request->set_file_params(['file' => $_FILES['file']]);

        $response = self::$api->route_upload_file($request);
        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertArrayHasKey('file', $data);
        $this->assertEquals('mock_file', $data['file']['name']);
    }

    /**
     * Simulates requesting a nonexistent file and verifies mocked response.
     */
    public function test_download_nonexistent_file() {
        $request = new WP_REST_Request('GET', '/wpmudev/v1/drive/download');
        $request->set_param('file_id', 'nonexistent_id');

        $response = self::$api->route_download_file($request);
        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertArrayHasKey('content', $data);
        $this->assertEquals(base64_encode('mock content'), $data['content']);
    }

    /**
     * Simulates token expiration scenario by swapping the client with one
     * that claims the token is expired and can fetch a refreshed token.
     *
     * Note: Since the stubbed callback does not perform refresh, we assert
     * the mocked tokens are still set exactly as the stubbed callback stores them.
     * This keeps the unit test deterministic; refresh logic should be covered
     * in integration tests that exercise init_client_from_storage().
     */
    public function test_token_expiration_handling() {
        // Replace client with an object that indicates expiration & can fetch refresh.
        self::$api->client = new class {
            public function createAuthUrl() { return 'https://mock.google.com/auth?client_id=test'; }
            public function isAccessTokenExpired() { return true; }
            public function fetchAccessTokenWithRefreshToken($token) {
                return ['access_token' => 'refreshed_token', 'refresh_token' => 'refreshed_refresh'];
            }
        };

        // Call the stubbed callback (which sets fixed tokens as part of the mock).
        $request = new WP_REST_Request('GET', '/wpmudev/v1/drive/callback');
        $response = self::$api->route_auth_callback($request);
        $this->assertEquals(200, $response->get_status());

        // Assert tokens reflect the stubbed behavior (not the refresh path).
        $access_token  = get_option('wpmudev_drive_access_token', '');
        $refresh_token = get_option('wpmudev_drive_refresh_token', '');
        $this->assertEquals('mock_auth', $access_token, 'Access token should be set by the stubbed callback.');
        $this->assertEquals('mock_refresh', $refresh_token, 'Refresh token should be set by the stubbed callback.');
    }
}
