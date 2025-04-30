<?php
/**
 * Test for the BookRec_State class
 *
 * @package GoodEReader_BookRec
 */

namespace GoodEReader\BookRec\Tests;

use WP_UnitTestCase;
use ReflectionClass;

/**
 * Class Test_BookRec_State
 */
class Test_BookRec_State extends WP_UnitTestCase {

    /**
     * Test generate_session_id creates a valid UUID
     */
    public function test_generate_session_id() {
        $session_id = \BookRec_State::generate_session_id();
        
        // UUID v4 pattern
        $uuid_pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';
        
        $this->assertMatchesRegularExpression($uuid_pattern, $session_id);
    }
    
    /**
     * Test get_session_state returns default state for invalid session ID
     */
    public function test_get_session_state_with_invalid_session_id() {
        // Test with invalid session ID
        $invalid_id = 'invalid-session-id';
        $state = \BookRec_State::get_session_state($invalid_id);
        
        // Use reflection to access private static method
        $reflection = new ReflectionClass('\BookRec_State');
        $method = $reflection->getMethod('get_default_state');
        $method->setAccessible(true);
        $expected_default = $method->invoke(null);
        
        // The state should match the default
        $this->assertEquals($expected_default, $state);
    }
    
    /**
     * Test get_session_state returns default state when transient doesn't exist
     */
    public function test_get_session_state_for_nonexistent_transient() {
        // Generate a valid session ID
        $session_id = \BookRec_State::generate_session_id();
        
        // Ensure the transient doesn't exist
        delete_transient(\BookRec_State::TRANSIENT_PREFIX . $session_id);
        
        // Get state
        $state = \BookRec_State::get_session_state($session_id);
        
        // Use reflection to access private static method
        $reflection = new ReflectionClass('\BookRec_State');
        $method = $reflection->getMethod('get_default_state');
        $method->setAccessible(true);
        $expected_default = $method->invoke(null);
        
        // The state should match the default
        $this->assertEquals($expected_default, $state);
    }
    
    /**
     * Test get_session_state returns saved state when transient exists
     */
    public function test_get_session_state_returns_saved_state() {
        // Generate a valid session ID
        $session_id = \BookRec_State::generate_session_id();
        
        // Create test state data
        $test_state = [
            'history' => [
                ['role' => 'assistant', 'content' => 'Test message']
            ],
            'slots' => ['genre' => 'mystery'],
            'created_at' => time(),
            'last_activity' => time(),
        ];
        
        // Save state
        set_transient(\BookRec_State::TRANSIENT_PREFIX . $session_id, $test_state, 3600);
        
        // Get state
        $retrieved_state = \BookRec_State::get_session_state($session_id);
        
        // The retrieved state should match what we saved
        $this->assertEquals($test_state, $retrieved_state);
        
        // Clean up
        delete_transient(\BookRec_State::TRANSIENT_PREFIX . $session_id);
    }
    
    /**
     * Test save_session_state saves data correctly
     */
    public function test_save_session_state_saves_data() {
        // Generate a valid session ID
        $session_id = \BookRec_State::generate_session_id();
        
        // Create test state data
        $test_state = [
            'history' => [
                ['role' => 'assistant', 'content' => 'Test save message']
            ],
            'slots' => ['genre' => 'fantasy'],
            'created_at' => time(),
            'last_activity' => time(),
        ];
        
        // Save state
        $result = \BookRec_State::save_session_state($session_id, $test_state, 3600);
        
        // Should return true on success
        $this->assertTrue($result);
        
        // Verify saved data
        $saved_data = get_transient(\BookRec_State::TRANSIENT_PREFIX . $session_id);
        $this->assertEquals($test_state, $saved_data);
        
        // Clean up
        delete_transient(\BookRec_State::TRANSIENT_PREFIX . $session_id);
    }
    
    /**
     * Test save_session_state returns false for invalid session ID
     */
    public function test_save_session_state_with_invalid_session_id() {
        // Test with invalid session ID
        $invalid_id = 'invalid-id';
        $test_state = ['key' => 'value'];
        
        $result = \BookRec_State::save_session_state($invalid_id, $test_state);
        
        // Should return false
        $this->assertFalse($result);
    }
    
    /**
     * Test is_valid_session_id validates UUID correctly
     */
    public function test_is_valid_session_id() {
        // Use reflection to access private method
        $reflection = new ReflectionClass('\BookRec_State');
        $method = $reflection->getMethod('is_valid_session_id');
        $method->setAccessible(true);
        
        // Valid UUID
        $valid_id = '550e8400-e29b-41d4-a716-446655440000';
        $this->assertTrue($method->invoke(null, $valid_id));
        
        // Invalid UUIDs
        $invalid_ids = [
            'not-a-uuid',
            '550e8400-e29b-41d4-a716', // Too short
            '550e8400-e29b-41d4-a716-4466554400000', // Too long
            '550e8400-e29b-41d4-a716-44665544000g', // Invalid character
        ];
        
        foreach ($invalid_ids as $id) {
            $this->assertFalse($method->invoke(null, $id));
        }
    }
}