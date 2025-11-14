<?php
/**
 * Unit tests for Dependency_Manager functionality
 *
 * @package WPMUDEV_Plugin_Test
 */

use WPMUDEV\PluginTest\Core\Dependency_Manager;

/**
 * Class TestDependencyManager
 *
 * Contains unit tests for the Dependency_Manager class.
 */
class TestDependencyManager extends WP_UnitTestCase {

    // -------------------------------------------------------------------------
    // Test: Class existence
    // -------------------------------------------------------------------------
    /**
     * Ensure the Dependency_Manager class exists.
     */
    public function test_class_exists() {
        $this->assertTrue(
            class_exists(Dependency_Manager::class),
            'Dependency_Manager class should exist.'
        );
    }

    // -------------------------------------------------------------------------
    // Test: Initialization
    // -------------------------------------------------------------------------
    /**
     * Ensure Dependency_Manager::init() can be called without throwing errors.
     */
    public function test_init_does_not_throw_errors() {
        try {
            Dependency_Manager::init();
            $this->assertTrue(true, 'Dependency_Manager::init() executed without error.');
        } catch (Exception $e) {
            $this->fail('Dependency_Manager::init() threw an exception: ' . $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // Test: Method existence
    // -------------------------------------------------------------------------
    /**
     * Ensure the check_environment() method exists in Dependency_Manager.
     */
    public function test_check_environment_method_exists() {
        $this->assertTrue(
            method_exists(Dependency_Manager::class, 'check_environment'),
            'check_environment method should exist in Dependency_Manager.'
        );
    }

    // -------------------------------------------------------------------------
    // Test: Method behavior
    // -------------------------------------------------------------------------
    /**
     * Optionally test check_environment() behavior if implemented.
     */
    public function test_check_environment_returns_expected_type() {
        if (method_exists(Dependency_Manager::class, 'check_environment')) {
            $result = Dependency_Manager::check_environment();

            // Expecting an array from check_environment
            $this->assertIsArray($result, 'check_environment should return an array.');
        } else {
            // Skip test if method is not implemented
            $this->markTestSkipped('check_environment method not implemented yet.');
        }
    }
}
