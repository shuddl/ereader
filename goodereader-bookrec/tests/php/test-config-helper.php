<?php
/**
 * Test for the config-helper.php functions
 *
 * @package GoodEReader_BookRec
 */

namespace GoodEReader\BookRec\Tests;

use WP_UnitTestCase;

/**
 * Class Test_Config_Helper
 */
class Test_Config_Helper extends WP_UnitTestCase {

    /**
     * Test bookrec_get_config returns correct default value when option doesn't exist
     */
    public function test_bookrec_get_config_returns_default_value() {
        // Non-existent option
        $random_key = 'random_' . md5(time());
        $expected_default = 'default_value';
        
        $result = bookrec_get_config($random_key, $expected_default);
        
        $this->assertEquals($expected_default, $result);
    }
    
    /**
     * Test bookrec_get_config returns correct option value when it exists
     */
    public function test_bookrec_get_config_returns_option_value() {
        // Create a test option
        $test_key = 'test_option';
        $test_value = 'test_value_' . md5(time());
        
        // Set the option
        update_option('bookrec_' . $test_key, $test_value);
        
        // Test getting the option
        $result = bookrec_get_config($test_key, 'default');
        
        $this->assertEquals($test_value, $result);
        
        // Clean up
        delete_option('bookrec_' . $test_key);
    }
    
    /**
     * Test bookrec_get_config returns null when no default is provided
     */
    public function test_bookrec_get_config_returns_null_when_no_default() {
        // Non-existent option
        $random_key = 'random_' . md5(time());
        
        $result = bookrec_get_config($random_key);
        
        $this->assertNull($result);
    }
}