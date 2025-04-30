<?php
/**
 * Base test case for BookRec plugin tests with WordPress
 *
 * @package GoodEReader_BookRec
 */

namespace GoodEReader\BookRec\Tests;

use WP_UnitTestCase;

/**
 * Base test case for WordPress-dependent tests.
 */
class BookRec_TestCase extends WP_UnitTestCase {

    /**
     * Set up function to prepare each test.
     */
    public function setUp(): void {
        parent::setUp();
        
        // Common setup for all WordPress-dependent tests
    }

    /**
     * Tear down after each test.
     */
    public function tearDown(): void {
        // Common teardown for all WordPress-dependent tests
        parent::tearDown();
    }
    
    /**
     * Helper to get a private/protected property value.
     *
     * @param object $object   The object to access.
     * @param string $property The property name.
     * @return mixed The property value.
     */
    protected function get_private_property( $object, $property ) {
        $reflection = new \ReflectionClass( get_class( $object ) );
        $property = $reflection->getProperty( $property );
        $property->setAccessible( true );
        
        return $property->getValue( $object );
    }
    
    /**
     * Helper to set a private/protected property value.
     *
     * @param object $object   The object to modify.
     * @param string $property The property name.
     * @param mixed  $value    The value to set.
     */
    protected function set_private_property( $object, $property, $value ) {
        $reflection = new \ReflectionClass( get_class( $object ) );
        $property = $reflection->getProperty( $property );
        $property->setAccessible( true );
        $property->setValue( $object, $value );
    }
    
    /**
     * Helper to call a private/protected method.
     *
     * @param object $object The object to access.
     * @param string $method The method name.
     * @param array  $args   The arguments to pass.
     * @return mixed The method return value.
     */
    protected function call_private_method( $object, $method, array $args = [] ) {
        $reflection = new \ReflectionClass( get_class( $object ) );
        $method = $reflection->getMethod( $method );
        $method->setAccessible( true );
        
        return $method->invokeArgs( $object, $args );
    }
}