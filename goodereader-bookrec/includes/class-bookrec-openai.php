<?php
/**
 * Book Recommender OpenAI Service Class
 *
 * @package   Good E-Reader Book Recommender
 */

declare(strict_types=1);

if (!defined('WPINC')) {
    die;
}

/**
 * Class BookRec_OpenAI_Service
 */
class BookRec_OpenAI_Service {

    /**
     * OpenAI API endpoint URL for chat completions
     *
     * @var string
     */
    private string $api_url = 'https://api.openai.com/v1/chat/completions';

    /**
     * OpenAI API key
     *
     * @var string|null
     */
    private ?string $api_key = null;

    /**
     * Constructor
     *
     * @param array|null $config Optional configuration array
     */
    public function __construct(?array $config = null) {
        // Set API key from config or get from options
        if (isset($config['api_key'])) {
            $this->api_key = $config['api_key'];
        } else {
            $this->api_key = bookrec_get_config('openai_key', '');
        }
    }

    /**
     * Get chat completion from OpenAI GPT-4 Turbo
     *
     * @param array  $messages    Array of message objects with 'role' and 'content' keys
     * @param string $model       OpenAI model to use
     * @param float  $temperature Temperature setting (0-2)
     * @param int    $max_tokens  Maximum tokens to generate
     * 
     * @return string|null The generated text or null on failure
     */
    public function get_chat_completion(
        array $messages, 
        string $model = 'gpt-4-turbo-preview', 
        float $temperature = 0.7, 
        int $max_tokens = 300
    ): ?string {
        // Validate API key
        if (empty($this->api_key)) {
            error_log('BookRec: OpenAI API key is not set.');
            return null;
        }

        // Validate messages
        if (empty($messages)) {
            error_log('BookRec: No messages provided for OpenAI chat completion.');
            return null;
        }

        // Prepare request body
        $request_body = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $max_tokens,
        ];

        // Prepare request arguments
        $request_args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($request_body),
            'method' => 'POST',
            'timeout' => 45, // Increased timeout for API response
            'redirection' => 5,
            'httpversion' => '1.1',
            'blocking' => true,
            'data_format' => 'body',
        ];

        // Make the request
        $response = wp_remote_post($this->api_url, $request_args);

        // Check for WP error
        if (is_wp_error($response)) {
            error_log('BookRec: OpenAI API request failed: ' . $response->get_error_message());
            return null;
        }

        // Get response code
        $response_code = wp_remote_retrieve_response_code($response);

        // Check for error response codes
        if ($response_code >= 400) {
            $body = wp_remote_retrieve_body($response);
            $error_data = json_decode($body, true);
            $error_message = 'BookRec: OpenAI API error. Status: ' . $response_code;
            
            // Add detailed error info if available
            if (isset($error_data['error']['message'])) {
                $error_message .= '. Message: ' . $error_data['error']['message'];
            }
            
            // Special handling for rate limit errors
            if ($response_code === 429) {
                $error_message = 'BookRec: OpenAI API rate limit exceeded. Please try again later.';
                error_log($error_message);
            } else {
                error_log($error_message);
            }
            
            return null;
        }

        // Decode response body
        $body = wp_remote_retrieve_body($response);
        $decoded_body = json_decode($body, true);

        // Check if decoding was successful
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('BookRec: Failed to decode OpenAI API response. JSON error: ' . json_last_error_msg());
            return null;
        }

        // Extract the assistant's message content
        if (
            isset($decoded_body['choices'][0]['message']['content']) && 
            is_string($decoded_body['choices'][0]['message']['content'])
        ) {
            return $decoded_body['choices'][0]['message']['content'];
        }

        // Handle missing or invalid content in response
        error_log('BookRec: OpenAI API response did not contain expected content. Response: ' . substr($body, 0, 200) . '...');
        return null;
    }

    /**
     * Format system prompt with slot data
     *
     * @param string $system_prompt Base system prompt
     * @param array  $slots        Slot data to incorporate
     * 
     * @return string Formatted system prompt
     */
    public function format_system_prompt(string $system_prompt, array $slots = []): string {
        // If no slots or empty prompt, return the original
        if (empty($slots) || empty($system_prompt)) {
            return $system_prompt;
        }

        // Replace slot placeholders in the format {slot_name}
        foreach ($slots as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $system_prompt = str_replace('{' . $key . '}', (string) $value, $system_prompt);
            }
        }

        return $system_prompt;
    }

    /**
     * Create properly formatted messages array for OpenAI API
     *
     * @param string $system_prompt System prompt
     * @param array  $chat_history  Chat history array of message objects
     * 
     * @return array Formatted messages array
     */
    public function create_messages_array(string $system_prompt, array $chat_history): array {
        $messages = [];

        // Add system message first
        if (!empty($system_prompt)) {
            $messages[] = [
                'role' => 'system',
                'content' => $system_prompt
            ];
        }

        // Add chat history
        foreach ($chat_history as $message) {
            // Skip system messages from history (we already added system prompt)
            if (isset($message['role']) && $message['role'] === 'system') {
                continue;
            }

            // Ensure message has required fields
            if (isset($message['role'], $message['content']) && 
                in_array($message['role'], ['user', 'assistant']) && 
                is_string($message['content'])
            ) {
                $messages[] = [
                    'role' => $message['role'],
                    'content' => $message['content']
                ];
            }
        }

        return $messages;
    }

    /**
     * Set API key
     *
     * @param string $api_key The OpenAI API key
     */
    public function set_api_key(string $api_key): void {
        $this->api_key = $api_key;
    }

    /**
     * Check if API key is set
     *
     * @return bool True if API key is set
     */
    public function has_api_key(): bool {
        return !empty($this->api_key);
    }
}