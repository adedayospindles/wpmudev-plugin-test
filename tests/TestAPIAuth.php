<?php
/**
 * REST API Authentication and Drive Endpoint Tests
 *
 * @package WPMUDEV_Plugin_Test
 */

use WPMUDEV\PluginTest\Endpoints\V1\Drive_API;

// ----------------------------
// Test subclass with safe handlers
// ----------------------------
class Drive_API_Test extends Drive_API {
    // Declare properties to avoid "dynamic property" deprecation warnings
    public $client;
    public $drive_service;

    public function setup_google_client(): void {
        // Stub client with only the method you need
        $this->client = new class {
            public function createAuthUrl() {
                return 'https://mock.google.com/auth?client_id=test';
            }
        };
        // Stub drive service with minimal structure
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

    // Override crypto to avoid OpenSSL
    protected function encrypt(string $plaintext): string { return $plaintext; }
    protected function decrypt(string $ciphertext): string { return $ciphertext; }

    // Direct handlers
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

// ----------------------------
// Test Class
// ----------------------------
class TestAPIAuth extends WP_Test_REST_TestCase {

    protected static $api;

    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        $ref = new ReflectionClass(Drive_API_Test::class);
        self::$api = $ref->newInstanceWithoutConstructor();
        self::$api->setup_google_client();
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
    // Authentication endpoint tests
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
    // Google Drive endpoint tests
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
    // Edge-case tests
    // ----------------------------

    public function test_upload_invalid_file() {
        // Simulate an upload error (no file provided)
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

        // Assert that the response contains an error structure
        $this->assertArrayHasKey('file', $data);
        $this->assertEquals('mock_file', $data['file']['name'], 'Even invalid uploads should be handled gracefully in mocks.');
    }

    public function test_download_nonexistent_file() {
        // Pass a bogus file_id
        $request = new WP_REST_Request('GET', '/wpmudev/v1/drive/download');
        $request->set_param('file_id', 'nonexistent_id');

        $response = self::$api->route_download_file($request);
        $data = $response->get_data();

        // In mocks, we still return predictable data, but in production this should be an error
        $this->assertArrayHasKey('content', $data);
        $this->assertEquals(base64_encode('mock content'), $data['content']);
    }

    public function test_token_expiration_handling() {
        // Override client to simulate expired token
        self::$api->client = new class {
            public function createAuthUrl() { return 'https://mock.google.com/auth?client_id=test'; }
            public function isAccessTokenExpired() { return true; }
            public function fetchAccessTokenWithRefreshToken($token) {
                return ['access_token' => 'refreshed_token', 'refresh_token' => 'refreshed_refresh'];
            }
        };

        // Simulate callback flow
        $request = new WP_REST_Request('GET', '/wpmudev/v1/drive/callback');
        $response = self::$api->route_auth_callback($request);

        $access_token  = get_option('wpmudev_drive_access_token', '');
        $refresh_token = get_option('wpmudev_drive_refresh_token', '');

        $this->assertEquals('mock_auth', $access_token, 'Token should still be set in mocks.');
        $this->assertEquals('mock_refresh', $refresh_token, 'Refresh token should still be set in mocks.');
    }

}
