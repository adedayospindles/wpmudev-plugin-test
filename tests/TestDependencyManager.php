<?php
/**
 * Class TestDependencyManager
 *
 * @package WPMUDEV_Plugin_Test
 */

use WPMUDEV\PluginTest\Core\Dependency_Manager;

/**
 * Dependency Manager test case.
 */
class TestDependencyManager extends WP_UnitTestCase {

    /**
     * Ensure the Dependency_Manager class exists and loads correctly.
     */
    public function test_class_exists() {
        $this->assertTrue(
            class_exists(Dependency_Manager::class),
            'Dependency_Manager class should exist.'
        );
    }

    /**
     * Ensure Dependency_Manager::init() can be called without error.
     */
    public function test_init_does_not_throw_errors() {
        try {
            Dependency_Manager::init();
            $this->assertTrue(true, 'Dependency_Manager::init() executed without error.');
        } catch (Exception $e) {
            $this->fail('Dependency_Manager::init() threw an exception: ' . $e->getMessage());
        }
    }

    /**
     * Ensure the check_environment() method exists.
     */
    public function test_check_environment_method_exists() {
        $this->assertTrue(
            method_exists(Dependency_Manager::class, 'check_environment'),
            'check_environment method should exist in Dependency_Manager.'
        );
    }

    /**
     * Optionally test check_environment() behavior if it returns a value.
     */
    public function test_check_environment_returns_expected_type() {
        if (method_exists(Dependency_Manager::class, 'check_environment')) {
            $result = Dependency_Manager::check_environment();
            $this->assertIsArray($result, 'check_environment should return an array.');
        } else {
            $this->markTestSkipped('check_environment method not implemented yet.');
        }
    }
}
