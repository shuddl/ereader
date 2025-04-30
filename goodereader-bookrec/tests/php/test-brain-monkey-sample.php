<?php
/**
 * Sample test case using Brain Monkey.
 *
 * @package GoodEReader_BookRec
 */

namespace GoodEReader\BookRec\Tests;

use Brain\Monkey\Functions;

/**
 * Sample Brain Monkey test case to demonstrate WordPress function mocking.
 */
class Test_Brain_Monkey_Sample extends BrainMonkeySetup {

    /**
     * Test WordPress function mocking.
     */
    public function test_wordpress_function_mocking() {
        // Set up expectation
        Functions\expect('wp_enqueue_script')
            ->once()
            ->with('test-script', \Mockery::type('string'), \Mockery::any(), \Mockery::any(), \Mockery::any());
        
        // Call the mocked function
        wp_enqueue_script('test-script', 'path/to/script.js', [], '1.0.0', true);
        
        // Mockery assertions are verified in tearDown()
        $this->assertTrue(true);
    }
    
    /**
     * Test a plugin-specific functionality using mocks.
     */
    public function test_plugin_function() {
        // Define the behavior of WordPress functions
        Functions\when('get_option')->alias(function($option, $default = false) {
            if ($option === 'bookrec_openai_key') {
                return 'test-api-key';
            }
            return $default;
        });
        
        // Test the function
        $api_key = get_option('bookrec_openai_key', '');
        
        // Assert the result
        $this->assertEquals('test-api-key', $api_key);
    }
}