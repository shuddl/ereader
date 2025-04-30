<?php
/**
 * Test for the BookRec_Recommendation class.
 *
 * @package GoodEReader_BookRec
 */

namespace GoodEReader\BookRec\Tests;

use WP_UnitTestCase;

/**
 * Test class for BookRec_Recommendation.
 */
class Test_Recommendation extends WP_UnitTestCase {

    /**
     * The recommendation class instance.
     *
     * @var \BookRec_Recommendation
     */
    private $recommendation;

    /**
     * Set up before each test.
     */
    public function setUp(): void {
        parent::setUp();
        
        // Check if the class exists
        if ( class_exists( '\BookRec_Recommendation' ) ) {
            $this->recommendation = new \BookRec_Recommendation();
        }
    }

    /**
     * Test that the recommendation class is properly loaded.
     */
    public function test_class_exists() {
        $this->assertTrue( class_exists( '\BookRec_Recommendation' ) );
        $this->assertInstanceOf( '\BookRec_Recommendation', $this->recommendation );
    }

    /**
     * Test the extractRecommendations method.
     */
    public function test_extract_recommendations() {
        // Skip the test if the recommendation class doesn't have this method
        if ( ! method_exists( $this->recommendation, 'extractRecommendations' ) ) {
            $this->markTestSkipped( 'Method extractRecommendations does not exist.' );
            return;
        }

        // Mock response output
        $llm_output = '
        Based on your interest in mystery novels, here are 3 books I think you\'ll enjoy:

        **The Silent Patient by Alex Michaelides**
        A gripping psychological thriller about a woman who shoots her husband and then never speaks again. The twists will keep you guessing until the shocking end.

        **The Thursday Murder Club by Richard Osman**
        Four retirees meet weekly to solve cold cases, but find themselves investigating a real murder in their retirement community. Clever, charming, and surprisingly funny.

        **Bluebird, Bluebird by Attica Locke**
        A powerful novel about a Texas Ranger investigating racially charged murders in a small town. Rich atmosphere and compelling characters make this a standout.
        ';

        // Use ReflectionClass to access the private method
        $reflection = new \ReflectionClass( get_class( $this->recommendation ) );
        $method = $reflection->getMethod( 'extractRecommendations' );
        $method->setAccessible( true );
        
        // Call the method
        $result = $method->invokeArgs( $this->recommendation, array( $llm_output ) );
        
        // Assert the result is as expected
        $this->assertIsArray( $result );
        $this->assertCount( 3, $result );
        
        // Check the first book
        $this->assertEquals( 'The Silent Patient', $result[0]['title'] );
        $this->assertEquals( 'Alex Michaelides', $result[0]['author'] );
        $this->assertStringContainsString( 'gripping psychological thriller', $result[0]['description'] );
        
        // Check the second book
        $this->assertEquals( 'The Thursday Murder Club', $result[1]['title'] );
        $this->assertEquals( 'Richard Osman', $result[1]['author'] );
        
        // Check the third book
        $this->assertEquals( 'Bluebird, Bluebird', $result[2]['title'] );
        $this->assertEquals( 'Attica Locke', $result[2]['author'] );
    }
}