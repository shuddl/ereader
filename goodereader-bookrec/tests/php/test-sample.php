<?php
/**
 * Sample test case to verify testing setup.
 *
 * @package GoodEReader_BookRec
 */

namespace GoodEReader\BookRec\Tests;

use WP_UnitTestCase;

/**
 * Sample test case.
 */
class Test_Sample extends WP_UnitTestCase {

    /**
     * A simple passing test.
     */
    public function test_sample() {
        $this->assertTrue( true );
    }
    
    /**
     * Test that WordPress is loaded correctly.
     */
    public function test_wordpress_loaded() {
        $this->assertTrue( function_exists( 'add_action' ) );
    }
    
    /**
     * Test that the plugin is loaded correctly.
     */
    public function test_plugin_loaded() {
        $this->assertTrue( defined( 'BOOKREC_VERSION' ) );
    }
}