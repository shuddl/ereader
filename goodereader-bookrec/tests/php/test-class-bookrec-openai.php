<?php
/**
 * Test for the BookRec_OpenAI_Service class
 *
 * @package GoodEReader_BookRec
 */

namespace GoodEReader\BookRec\Tests;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Class Test_BookRec_OpenAI
 */
class Test_BookRec_OpenAI extends TestCase {

    /**
     * Set up function to initialize Brain Monkey
     */
    protected function setUp(): void {
        parent::setUp();
        \Brain\Monkey\setUp();
        
        // Mock the bookrec_get_config function
        Functions\when('bookrec_get_config')->justReturn('dummy_api_key');
        
        // Mock error_log to prevent actual error logging during tests
        Functions\when('error_log')->justReturn(null);
    }

    /**
     * Tear down function to clean up after Brain Monkey
     */
    protected function tearDown(): void {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Test constructor with direct API key
     */
    public function test_constructor_with_direct_api_key() {
        $api_key = 'test_api_key';
        $openai = new \BookRec_OpenAI_Service(['api_key' => $api_key]);
        
        $this->assertTrue($openai->has_api_key());
        
        // Test the set_api_key method
        $openai->set_api_key('new_api_key');
        $this->assertTrue($openai->has_api_key());
    }

    /**
     * Test constructor with config from options
     */
    public function test_constructor_with_config_from_options() {
        $openai = new \BookRec_OpenAI_Service();
        $this->assertTrue($openai->has_api_key());
    }

    /**
     * Test get_chat_completion with empty API key
     */
    public function test_get_chat_completion_with_empty_api_key() {
        Functions\when('bookrec_get_config')->justReturn('');
        
        $openai = new \BookRec_OpenAI_Service();
        $messages = [
            ['role' => 'user', 'content' => 'Hello']
        ];
        
        $result = $openai->get_chat_completion($messages);
        $this->assertNull($result);
    }

    /**
     * Test get_chat_completion with empty messages
     */
    public function test_get_chat_completion_with_empty_messages() {
        $openai = new \BookRec_OpenAI_Service();
        $result = $openai->get_chat_completion([]);
        $this->assertNull($result);
    }

    /**
     * Test get_chat_completion with WP_Error response
     */
    public function test_get_chat_completion_with_wp_error() {
        $openai = new \BookRec_OpenAI_Service();
        $messages = [
            ['role' => 'user', 'content' => 'Hello']
        ];
        
        // Mock wp_remote_post to return a WP_Error
        Functions\when('wp_remote_post')->justReturn(new \WP_Error('test_error', 'Test error message'));
        Functions\when('is_wp_error')->justReturn(true);
        
        $result = $openai->get_chat_completion($messages);
        $this->assertNull($result);
    }

    /**
     * Test get_chat_completion with error status code
     */
    public function test_get_chat_completion_with_error_status_code() {
        $openai = new \BookRec_OpenAI_Service();
        $messages = [
            ['role' => 'user', 'content' => 'Hello']
        ];
        
        // Mock WP HTTP response functions
        Functions\when('wp_remote_post')->justReturn(['response' => ['code' => 400]]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(400);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode([
            'error' => ['message' => 'Invalid request']
        ]));
        Functions\when('wp_json_encode')->justReturn('{"model":"gpt-4-turbo-preview","messages":[]}');
        
        $result = $openai->get_chat_completion($messages);
        $this->assertNull($result);
    }

    /**
     * Test get_chat_completion with rate limit error
     */
    public function test_get_chat_completion_with_rate_limit_error() {
        $openai = new \BookRec_OpenAI_Service();
        $messages = [
            ['role' => 'user', 'content' => 'Hello']
        ];
        
        // Mock WP HTTP response functions for 429 error
        Functions\when('wp_remote_post')->justReturn(['response' => ['code' => 429]]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(429);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode([
            'error' => ['message' => 'Rate limit exceeded']
        ]));
        Functions\when('wp_json_encode')->justReturn('{"model":"gpt-4-turbo-preview","messages":[]}');
        
        $result = $openai->get_chat_completion($messages);
        $this->assertNull($result);
    }

    /**
     * Test get_chat_completion with JSON decode error
     */
    public function test_get_chat_completion_with_json_decode_error() {
        $openai = new \BookRec_OpenAI_Service();
        $messages = [
            ['role' => 'user', 'content' => 'Hello']
        ];
        
        // Mock WP HTTP response functions
        Functions\when('wp_remote_post')->justReturn(['response' => ['code' => 200]]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn('Invalid JSON');
        Functions\when('wp_json_encode')->justReturn('{"model":"gpt-4-turbo-preview","messages":[]}');
        
        // Mock json_decode and json_last_error
        Functions\when('json_decode')->justReturn(null);
        Functions\when('json_last_error')->justReturn(JSON_ERROR_SYNTAX);
        Functions\when('json_last_error_msg')->justReturn('Syntax error');
        
        $result = $openai->get_chat_completion($messages);
        $this->assertNull($result);
    }

    /**
     * Test get_chat_completion with missing content in response
     */
    public function test_get_chat_completion_with_missing_content() {
        $openai = new \BookRec_OpenAI_Service();
        $messages = [
            ['role' => 'user', 'content' => 'Hello']
        ];
        
        // Mock WP HTTP response functions
        Functions\when('wp_remote_post')->justReturn(['response' => ['code' => 200]]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode([
            'choices' => [
                ['message' => []] // Missing 'content' key
            ]
        ]));
        Functions\when('wp_json_encode')->justReturn('{"model":"gpt-4-turbo-preview","messages":[]}');
        
        // Mock json_decode and json_last_error
        Functions\when('json_decode')->justReturn([
            'choices' => [
                ['message' => []] // Missing 'content' key
            ]
        ]);
        Functions\when('json_last_error')->justReturn(JSON_ERROR_NONE);
        
        $result = $openai->get_chat_completion($messages);
        $this->assertNull($result);
    }

    /**
     * Test get_chat_completion with successful response
     */
    public function test_get_chat_completion_success() {
        $openai = new \BookRec_OpenAI_Service();
        $messages = [
            ['role' => 'user', 'content' => 'Hello']
        ];
        
        // Expected response content
        $expected_content = 'Hello, how can I help you with book recommendations today?';
        
        // Mock WP HTTP response functions
        Functions\when('wp_remote_post')->justReturn(['response' => ['code' => 200]]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode([
            'choices' => [
                ['message' => ['content' => $expected_content]]
            ]
        ]));
        Functions\when('wp_json_encode')->justReturn('{"model":"gpt-4-turbo-preview","messages":[]}');
        
        // Mock json_decode and json_last_error
        Functions\when('json_decode')->justReturn([
            'choices' => [
                ['message' => ['content' => $expected_content]]
            ]
        ]);
        Functions\when('json_last_error')->justReturn(JSON_ERROR_NONE);
        
        $result = $openai->get_chat_completion($messages);
        $this->assertEquals($expected_content, $result);
    }

    /**
     * Test format_system_prompt with no slots
     */
    public function test_format_system_prompt_no_slots() {
        $openai = new \BookRec_OpenAI_Service();
        $prompt = 'You are a book recommendation assistant.';
        
        $result = $openai->format_system_prompt($prompt, []);
        $this->assertEquals($prompt, $result);
    }

    /**
     * Test format_system_prompt with empty prompt
     */
    public function test_format_system_prompt_empty_prompt() {
        $openai = new \BookRec_OpenAI_Service();
        $result = $openai->format_system_prompt('', ['genre' => 'sci-fi']);
        $this->assertEquals('', $result);
    }

    /**
     * Test format_system_prompt with slots
     */
    public function test_format_system_prompt_with_slots() {
        $openai = new \BookRec_OpenAI_Service();
        $prompt = 'You are a {genre} book recommendation assistant. The user likes {author} and prefers books with {page_count} pages.';
        $slots = [
            'genre' => 'sci-fi',
            'author' => 'Isaac Asimov',
            'page_count' => 300,
        ];
        
        $expected = 'You are a sci-fi book recommendation assistant. The user likes Isaac Asimov and prefers books with 300 pages.';
        $result = $openai->format_system_prompt($prompt, $slots);
        
        $this->assertEquals($expected, $result);
    }

    /**
     * Test create_messages_array with system prompt
     */
    public function test_create_messages_array_with_system_prompt() {
        $openai = new \BookRec_OpenAI_Service();
        $system_prompt = 'You are a helpful book assistant.';
        $chat_history = [
            ['role' => 'user', 'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'Hi there!'],
        ];
        
        $expected = [
            ['role' => 'system', 'content' => 'You are a helpful book assistant.'],
            ['role' => 'user', 'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'Hi there!'],
        ];
        
        $result = $openai->create_messages_array($system_prompt, $chat_history);
        $this->assertEquals($expected, $result);
    }

    /**
     * Test create_messages_array without system prompt
     */
    public function test_create_messages_array_without_system_prompt() {
        $openai = new \BookRec_OpenAI_Service();
        $chat_history = [
            ['role' => 'user', 'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'Hi there!'],
        ];
        
        $result = $openai->create_messages_array('', $chat_history);
        $this->assertEquals($chat_history, $result);
    }

    /**
     * Test create_messages_array ignores system messages in history
     */
    public function test_create_messages_array_ignores_system_in_history() {
        $openai = new \BookRec_OpenAI_Service();
        $system_prompt = 'You are a helpful book assistant.';
        $chat_history = [
            ['role' => 'system', 'content' => 'This should be ignored'],
            ['role' => 'user', 'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'Hi there!'],
        ];
        
        $expected = [
            ['role' => 'system', 'content' => 'You are a helpful book assistant.'],
            ['role' => 'user', 'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'Hi there!'],
        ];
        
        $result = $openai->create_messages_array($system_prompt, $chat_history);
        $this->assertEquals($expected, $result);
    }

    /**
     * Test create_messages_array filters invalid messages
     */
    public function test_create_messages_array_filters_invalid_messages() {
        $openai = new \BookRec_OpenAI_Service();
        $system_prompt = 'You are a helpful book assistant.';
        $chat_history = [
            ['role' => 'user', 'content' => 'Hello'],
            ['role' => 'invalid_role', 'content' => 'This should be filtered'],
            ['role' => 'assistant'], // Missing content
            ['content' => 'Missing role'],
            ['role' => 'user', 'content' => 123], // Non-string content
            ['role' => 'assistant', 'content' => 'Valid message'],
        ];
        
        $expected = [
            ['role' => 'system', 'content' => 'You are a helpful book assistant.'],
            ['role' => 'user', 'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'Valid message'],
        ];
        
        $result = $openai->create_messages_array($system_prompt, $chat_history);
        $this->assertEquals($expected, $result);
    }
}