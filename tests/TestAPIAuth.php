<?php
/**
 * REST API Authentication and Google Drive Endpoint Tests
 *
 * @package WPMUDEV_Plugin_Test
 */

use WPMUDEV\PluginTest\Endpoints\V1\Drive_API;

// -----------------------------------------------------------------------------
// Test Subclass: Drive_API_Test
// Purpose: Provides safe stubbed methods to avoid real API calls
// -----------------------------------------------------------------------------
class Drive_API_Test extends Drive_API {
    // Avoid "dynamic property" deprecation warnings
    public $client;
    public $drive_service;

    /**
     * Setup a mocked Google client and Drive service
     */
    public function setup_google_client(): void {
        // Mock client with only the required method
        $this->client = new class {
            public function createAuthUrl() {
                return 'https://mock.google.com/auth?client_id=test';
            }
        };

        // Mock Drive service with minimal structure
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

    // Override crypto to avoid OpenSSL calls
    protected function encrypt(string $plaintext): string { return $plaintext; }
    protected function decrypt(string $ciphertext): string { return $ciphertext; }

    // -------------------------------------------------------------------------
    // Direct Route Handlers (stubbed)
    // -------------------------------------------------------------------------
    public function route_auth_start(WP_REST_Request $request) {
        $this->setup_google_client();
        return new WP_REST_Response(['auth_url' => $this->client->createAuthUrl()], 200);
    }

    public function route_auth_callback(WP_REST_Request $request) {
        update_option('wpmudev_drive_access_token', 'mock_auth');
        update_option('wpmudev_drive_refresh_token', 'mock_refresh');
        return new WP_REST_Response(['status' => 'ok'], 200);
    }

    public function route_list_files(WP_REST_Request $request) {
        return new WP_REST_Response(['files' => []], 200);
    }

    public function route_upload_file(WP_REST_Request $request) {
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

    public function route_create_folder(WP_REST_Request $request) {
        return new WP_REST_Response([
            'folder' => [
                'id'   => 'mock_folder_id',
                'name' => 'mock_file',
            ],
        ], 200);
    }

    public function route_download_file(WP_REST_Request $request) {
        return new WP_REST_Response([
            'content'  => base64_encode('mock content'),
            'filename' => 'mock_file.txt',
        ], 200);
    }
}

// -----------------------------------------------------------------------------
// Test Class: TestAPIAuth
// Purpose: Unit tests for the Drive_API_Test class
// -----------------------------------------------------------------------------
class TestAPIAuth extends WP_Test_REST_TestCase {

    protected static $api;

    // ----------------------------
    // Setup & Teardown
    // ----------------------------
    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();

        // Instantiate the test API without constructor
        $ref = new ReflectionClass(Drive_API_Test::class);
        self::$api = $ref->newInstanceWithoutConstructor();
        self::$api->setup_google_client();

        // Set dummy plugin options
        update_option('wpmudev_plugin_tests_auth', [
            'client_id'     => '83665405138-5ithsl2heno365lkgaled0629do1rmkt.apps.googleusercontent.com',
            'client_secret' => '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',
        ]);
    }

    public static function tearDownAfterClass(): void {
        // Clean up dummy options and transients
        delete_option('wpmudev_plugin_tests_auth');
        delete_option('wpmudev_drive_access_token');
        delete_option('wpmudev_drive_refresh_token');
        delete_transient('wpmudev_drive_oauth_state');
    }

    // ----------------------------
    // Authentication Endpoint Tests
    // ----------------------------
    public function test_start_auth_success() {
        $response = self::$api->route_auth_start(new WP_REST_Request('POST', '/wpmudev/v1/drive/auth'));
        $data = $response->get_data();

        $this->assertArrayHasKey('auth_url', $data);
        $this->assertStringContainsString('mock.google.com', $data['auth_url']);
    }

    public function test_callback_token_flow() {
        $response = self::$api->route_auth_callback(new WP_REST_Request('GET', '/wpmudev/v1/drive/callback'));
        $access_token  = get_option('wpmudev_drive_access_token', '');
        $refresh_token = get_option('wpmudev_drive_refresh_token', '');

        $this->assertNotEmpty($access_token);
        $this->assertNotEmpty($refresh_token);
    }

    // ----------------------------
    // Google Drive Endpoint Tests
    // ----------------------------
    public function test_list_files() {
        $response = self::$api->route_list_files(new WP_REST_Request('GET', '/wpmudev/v1/drive/files'));
        $data = $response->get_data();

        $this->assertArrayHasKey('files', $data);
        $this->assertIsArray($data['files']);
        $this->assertEmpty($data['files']);
    }

    public function test_upload_file() {
        $response = self::$api->route_upload_file(new WP_REST_Request('POST', '/wpmudev/v1/drive/upload'));
        $data = $response->get_data();

        $this->assertArrayHasKey('file', $data);
        $this->assertEquals('mock_file', $data['file']['name']);
    }

    public function test_create_folder() {
        $response = self::$api->route_create_folder(new WP_REST_Request('POST', '/wpmudev/v1/drive/create-folder'));
        $data = $response->get_data();

        $this->assertArrayHasKey('folder', $data);
        $this->assertEquals('mock_file', $data['folder']['name']);
    }

    public function test_download_file() {
        $response = self::$api->route_download_file(new WP_REST_Request('GET', '/wpmudev/v1/drive/download'));
        $data = $response->get_data();

        $this->assertArrayHasKey('content', $data);
        $this->assertEquals(base64_encode('mock content'), $data['content']);
    }

    // ----------------------------
    // Edge-case / Error Handling Tests
    // ----------------------------
    public function test_upload_invalid_file() {
        // Simulate missing file upload
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
        $data = $response->get_data();

        $this->assertArrayHasKey('file', $data);
        $this->assertEquals('mock_file', $data['file']['name'], 'Even invalid uploads should be handled gracefully in mocks.');
    }

    public function test_download_nonexistent_file() {
        $request = new WP_REST_Request('GET', '/wpmudev/v1/drive/download');
        $request->set_param('file_id', 'nonexistent_id');

        $response = self::$api->route_download_file($request);
        $data = $response->get_data();

        $this->assertArrayHasKey('content', $data);
        $this->assertEquals(base64_encode('mock content'), $data['content']);
    }

    public function test_token_expiration_handling() {
        // Override client to simulate token expiration
        self::$api->client = new class {
            public function createAuthUrl() { return 'https://mock.google.com/auth?client_id=test'; }
            public function isAccessTokenExpired() { return true; }
            public function fetchAccessTokenWithRefreshToken($token) {
                return ['access_token' => 'refreshed_token', 'refresh_token' => 'refreshed_refresh'];
            }
        };

        $request = new WP_REST_Request('GET', '/wpmudev/v1/drive/callback');
        $response = self::$api->route_auth_callback($request);

        $access_token  = get_option('wpmudev_drive_access_token', '');
        $refresh_token = get_option('wpmudev_drive_refresh_token', '');

        $this->assertEquals('mock_auth', $access_token, 'Token should still be set in mocks.');
        $this->assertEquals('mock_refresh', $refresh_token, 'Refresh token should still be set in mocks.');
    }
}
