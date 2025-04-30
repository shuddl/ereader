<?php
/**
 * Brain Monkey setup for tests without WordPress core.
 *
 * @package GoodEReader_BookRec
 */

namespace GoodEReader\BookRec\Tests;

use Brain\Monkey;
use PHPUnit\Framework\TestCase;

/**
 * Abstract test case to use when WordPress core is not available.
 * This sets up Brain Monkey to mock WordPress functions.
 */
abstract class BrainMonkeySetup extends TestCase {

    /**
     * Set up function to prepare each test.
     */
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        
        // Define common WordPress functions that are used throughout the plugin
        if ( ! function_exists( 'esc_html' ) ) {
            function esc_html( $text ) {
                return $text;
            }
        }
        
        if ( ! function_exists( 'esc_html__' ) ) {
            function esc_html__( $text, $domain = 'default' ) {
                return $text;
            }
        }
        
        if ( ! function_exists( 'esc_attr' ) ) {
            function esc_attr( $text ) {
                return $text;
            }
        }
        
        if ( ! function_exists( 'esc_url' ) ) {
            function esc_url( $url ) {
                return $url;
            }
        }
        
        if ( ! function_exists( 'wp_unslash' ) ) {
            function wp_unslash( $value ) {
                return $value;
            }
        }
    }

    /**
     * Tear down after each test.
     */
    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }
}