<?php
/**
 * Full REST API Authentication and Google Drive Endpoint Tests (Integration / Routing)
 *
 * This suite registers stubbed routes and uses rest_do_request() to exercise
 * WordPress's REST routing layer end-to-end. It verifies route registration,
 * permission callbacks, request parsing, and responses without calling real
 * Google APIs.
 *
 * @package WPMUDEV_Plugin_Test
 */

use WPMUDEV\PluginTest\Endpoints\V1\Drive_API;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Stubbed subclass of Drive_API to avoid real Google API calls.
 * Provides a fake Google_Client, fake Google_Service_Drive,
 * disables encryption, and implements stubbed route handlers.
 */
class Drive_API_Test_Routing extends Drive_API {

    public $client;          // Fake Google client instance
    public $drive_service;   // Fake Google Drive service instance

    /**
     * Setup fake Google client and Drive service
     * This avoids real Google API calls during tests.
     */
    public function setup_google_client(): void {

        // Fake Google client object returning a predictable auth URL
        $this->client = new class {
            public function createAuthUrl() {
                return 'https://mock.google.com/auth?client_id=test';
            }
        };

        // Fake Google Drive service with stubbed "files" handling
        $this->drive_service = new class {
            public $files;

            public function __construct() {

                // Stubbed files->listFiles() handler
                $this->files = new class {
                    public function listFiles($optParams) {

                        // Return empty list and no pagination token
                        return new class {
                            public function getFiles() { return []; }
                            public function getNextPageToken() { return null; }
                        };
                    }
                };
            }
        };
    }

    /**
     * Overrides crypto functions to bypass encryption during tests.
     * Plaintext in, plaintext out.
     */
    protected function encrypt(string $plaintext): string { return $plaintext; }
    protected function decrypt(string $ciphertext): string { return $ciphertext; }

    /**
     * Stubbed route handler for starting OAuth authentication.
     */
    public function route_auth_start(WP_REST_Request $request): WP_REST_Response {
        $this->setup_google_client();    // Ensure fake client is available
        return new WP_REST_Response([
            'auth_url' => $this->client->createAuthUrl(),
        ], 200);
    }

    /**
     * Stubbed OAuth callback handler.
     * Stores mock tokens in WordPress options.
     */
    public function route_auth_callback(WP_REST_Request $request): WP_REST_Response {
        update_option('wpmudev_drive_access_token', 'mock_auth');
        update_option('wpmudev_drive_refresh_token', 'mock_refresh');

        return new WP_REST_Response(['status' => 'ok'], 200);
    }

    /**
     * Stubbed Google Drive "list files" handler.
     */
    public function route_list_files(WP_REST_Request $request): WP_REST_Response {
        return new WP_REST_Response(['files' => []], 200);
    }

    /**
     * Stubbed "upload file" handler returning predictable file metadata.
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
     * Stubbed folder creation handler.
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
     * Stubbed file download handler.
     */
    public function route_download_file(WP_REST_Request $request): WP_REST_Response {
        return new WP_REST_Response([
            'content'  => base64_encode('mock content'),
            'filename' => 'mock_file.txt',
        ], 200);
    }

    /**
     * Registers all stubbed REST routes.
     * Each route is registered using a predictable namespace and path
     * so the PHPUnit test can call them via rest_do_request().
     */
    public function register_routes(): void {

        // OAuth start
        register_rest_route('wpmudev/v1/drive', '/auth', [
            'methods'             => 'POST',
            'callback'            => [$this, 'route_auth_start'],
            'permission_callback' => '__return_true',
        ]);

        // OAuth callback
        register_rest_route('wpmudev/v1/drive', '/callback', [
            'methods'             => 'GET',
            'callback'            => [$this, 'route_auth_callback'],
            'permission_callback' => '__return_true',
        ]);

        // List files
        register_rest_route('wpmudev/v1/drive', '/files', [
            'methods'             => 'GET',
            'callback'            => [$this, 'route_list_files'],
            'permission_callback' => '__return_true',
        ]);

        // Upload file
        register_rest_route('wpmudev/v1/drive', '/upload', [
            'methods'             => 'POST',
            'callback'            => [$this, 'route_upload_file'],
            'permission_callback' => '__return_true',
        ]);

        // Create folder
        register_rest_route('wpmudev/v1/drive', '/create-folder', [
            'methods'             => 'POST',
            'callback'            => [$this, 'route_create_folder'],
            'permission_callback' => '__return_true',
        ]);

        // Download file
        register_rest_route('wpmudev/v1/drive', '/download', [
            'methods'             => 'GET',
            'callback'            => [$this, 'route_download_file'],
            'permission_callback' => '__return_true',
        ]);
    }
}

/**
 * PHPUnit test class for testing Drive_API_Test_Routing.
 * This class performs full route tests using rest_do_request()
 * to confirm routing, callbacks, responses, and option storage.
 */
class TestAPIAuthRest extends WP_Test_REST_TestCase {

    protected static $api;   // Holds instance of stubbed API class

    /**
     * Runs once before the test suite.
     * Registers routes and seeds mock plugin options.
     */
    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();

        // Instantiate stubbed Drive API without calling its constructor
        $ref       = new ReflectionClass(Drive_API_Test_Routing::class);
        self::$api = $ref->newInstanceWithoutConstructor();

        // Setup stubbed Google client and register routes
        self::$api->setup_google_client();
        self::$api->register_routes();

        // Store dummy OAuth credentials for tests
        update_option('wpmudev_plugin_tests_auth', [
            'client_id'     => 'dummy-client-id',
            'client_secret' => 'dummy-client-secret',
        ]);
    }

    /**
     * Cleanup after tests finish.
     * Removes all mock options and transients.
     */
    public static function tearDownAfterClass(): void {
        delete_option('wpmudev_plugin_tests_auth');
        delete_option('wpmudev_drive_access_token');
        delete_option('wpmudev_drive_refresh_token');
        delete_transient('wpmudev_drive_oauth_state');
    }

    // ----------------------------------------------------
    // Authentication Route Tests
    // ----------------------------------------------------

    /**
     * Test: OAuth start returns a mock auth URL.
     */
    public function test_start_auth_success() {

        // Simulate POST /auth request
        $request  = new WP_REST_Request('POST', '/wpmudev/v1/drive/auth');
        $response = rest_do_request($request);

        // Validate HTTP status
        $this->assertEquals(200, $response->get_status());

        // Validate payload
        $data = $response->get_data();
        $this->assertArrayHasKey('auth_url', $data);
        $this->assertStringContainsString('mock.google.com', $data['auth_url']);
    }

    /**
     * Test: OAuth callback stores tokens correctly.
     */
    public function test_callback_token_flow() {

        // Simulate GET /callback request
        $request  = new WP_REST_Request('GET', '/wpmudev/v1/drive/callback');
        $response = rest_do_request($request);

        // Validate HTTP status
        $this->assertEquals(200, $response->get_status());

        // Ensure options were updated
        $this->assertNotEmpty(get_option('wpmudev_drive_access_token', ''));
        $this->assertNotEmpty(get_option('wpmudev_drive_refresh_token', ''));
    }

    // ----------------------------------------------------
    // Google Drive Stubbed Endpoint Tests
    // ----------------------------------------------------

    /**
     * Test: List files returns empty array successfully.
     */
    public function test_list_files() {

        $request  = new WP_REST_Request('GET', '/wpmudev/v1/drive/files');
        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertArrayHasKey('files', $data);
        $this->assertIsArray($data['files']);
        $this->assertEmpty($data['files']);
    }

    /**
     * Test: Upload file returns predictable mock metadata.
     */
    public function test_upload_file() {

        $request  = new WP_REST_Request('POST', '/wpmudev/v1/drive/upload');
        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertArrayHasKey('file', $data);
        $this->assertEquals('mock_file', $data['file']['name']);
    }

    /**
     * Test: Create folder returns expected mock response.
     */
    public function test_create_folder() {

        $request  = new WP_REST_Request('POST', '/wpmudev/v1/drive/create-folder');
        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertArrayHasKey('folder', $data);
        $this->assertEquals('mock_file', $data['folder']['name']);
    }

    /**
     * Test: Download file returns base64 encoded mock content.
     */
    public function test_download_file() {

        $request  = new WP_REST_Request('GET', '/wpmudev/v1/drive/download');
        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertArrayHasKey('content', $data);
        $this->assertEquals(base64_encode('mock content'), $data['content']);
    }
}
