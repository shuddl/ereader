<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package GoodEReader_BookRec
 */

// Composer autoloader must be loaded before WP_PHPUNIT__DIR is defined
$composer_autoloader = dirname( __DIR__ ) . '/vendor/autoload.php';
if ( file_exists( $composer_autoloader ) ) {
    require_once $composer_autoloader;
}

// Define test environment constants
define( 'BOOKREC_TEST_DIR', __DIR__ );
define( 'BOOKREC_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'BOOKREC_PLUGIN_FILE', dirname( __DIR__ ) . '/goodereader-bookrec.php' );

// Determine if we're running on a local environment or in CI
if ( ! defined( 'WP_TESTS_DIR' ) ) {
    // Support direct specification through environment variable
    $wp_tests_dir = getenv( 'WP_TESTS_DIR' );
    
    // Otherwise, try the default location
    if ( ! $wp_tests_dir ) {
        $wp_tests_dir = '/tmp/wordpress-tests-lib';
    }
    
    // Define it if not already defined
    if ( ! defined( 'WP_TESTS_DIR' ) ) {
        define( 'WP_TESTS_DIR', $wp_tests_dir );
    }
}

// Test whether the WordPress test framework is available or we should use the mock library
if ( file_exists( WP_TESTS_DIR . '/includes/bootstrap.php' ) ) {
    // Load the WordPress Test Suite
    require_once WP_TESTS_DIR . '/includes/bootstrap.php';
    
    // Load the plugin manually
    tests_add_filter( 'muplugins_loaded', function() {
        // Load the plugin we're testing
        require BOOKREC_PLUGIN_FILE;
    });
} else {
    // WordPress tests not available, use Brain Monkey instead
    require_once BOOKREC_TEST_DIR . '/class-brain-monkey-setup.php';
}

/**
 * A helper function to load specific test case classes.
 *
 * @param string $test_case The test case class name to load.
 */
function bookrec_load_test_case( $test_case ) {
    $file_path = BOOKREC_TEST_DIR . '/testcases/class-' . strtolower( str_replace( '_', '-', $test_case ) ) . '.php';
    if ( file_exists( $file_path ) ) {
        require_once $file_path;
    }
}