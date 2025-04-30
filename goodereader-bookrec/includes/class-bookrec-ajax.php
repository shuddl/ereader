<?php
/**
 * Book Recommender AJAX Handler Class
 *
 * @package   Good E-Reader Book Recommender
 */

declare(strict_types=1);

if (!defined('WPINC')) {
    die;
}

/**
 * Class BookRec_Ajax
 */
class BookRec_Ajax {

    /**
     * Constructor
     */
    public function __construct() {
        // Hook AJAX handlers for both logged-in and logged-out users
        add_action('wp_ajax_bookrec_chat', [$this, 'handle_chat_message']);
        add_action('wp_ajax_nopriv_bookrec_chat', [$this, 'handle_chat_message']);
        
        // Hook AJAX handler for session initialization
        add_action('wp_ajax_bookrec_init_session', [$this, 'init_session']);
        add_action('wp_ajax_nopriv_bookrec_init_session', [$this, 'init_session']);
        
        // Hook AJAX handler for click tracking
        add_action('wp_ajax_bookrec_track_click', [$this, 'handle_track_click']);
        add_action('wp_ajax_nopriv_bookrec_track_click', [$this, 'handle_track_click']);
        
        // Hook AJAX handler for email PDF
        add_action('wp_ajax_bookrec_email_recs', [$this, 'handle_email_recs']);
        add_action('wp_ajax_nopriv_bookrec_email_recs', [$this, 'handle_email_recs']);
    }

    /**
     * OpenAI service instance
     *
     * @var BookRec_OpenAI_Service|null
     */
    private ?BookRec_OpenAI_Service $openai_service = null;
    
    /**
     * Recommendation service instance
     *
     * @var BookRec_Recommendation|null
     */
    private ?BookRec_Recommendation $recommendation_service = null;
    
    /**
     * Email service instance
     *
     * @var BookRec_Email_Service|null
     */
    private ?BookRec_Email_Service $email_service = null;
    
    /**
     * Required slots for book recommendations
     * 
     * @var array
     */
    private $required_slots = [
        'genre' => 'What genre or type of books do you enjoy?',
        'tone' => 'Do you prefer lighthearted reads or something more serious?',
        'recent_favorites' => 'Could you mention a book or author you\'ve enjoyed recently?',
    ];
    
    /**
     * Optional slots for better recommendations
     * 
     * @var array
     */
    private $optional_slots = [
        'time_period' => 'Do you have a preference for when the story takes place (modern, historical, future)?',
        'length' => 'Do you prefer shorter reads or longer, more immersive books?',
        'mood' => 'What kind of feeling are you looking for from your next book?',
        'avoid' => 'Are there any topics or themes you want to avoid?',
    ];
    
    /**
     * Handle chat messages via AJAX
     */
    public function handle_chat_message(): void {
        // Verify nonce
        check_ajax_referer('bookrec_chat_nonce', 'nonce');

        // Get and sanitize input data
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        $user_message = sanitize_textarea_field($_POST['user_message'] ?? '');
        $container_id = sanitize_text_field($_POST['container_id'] ?? '');
        $explicit_author_search = isset($_POST['author_search']) && $_POST['author_search'] === 'true';
        $explicit_author_name = sanitize_text_field($_POST['author_name'] ?? '');

        // Validate required data
        if (empty($session_id) || empty($user_message)) {
            wp_send_json_error([
                'message' => 'Missing required data',
                'code' => 'missing_data'
            ]);
        }

        // Get current session state
        $state = BookRec_State::get_session_state($session_id);

        // Initialize state fields if they don't exist
        if (!isset($state['filled_slots'])) {
            $state['filled_slots'] = [];
            
            // If genre is already in slots (from initialization), copy it to filled_slots
            if (!empty($state['slots']['genre'])) {
                $state['filled_slots']['genre'] = $state['slots']['genre'];
            }
        }
        
        if (!isset($state['next_slot_to_ask'])) {
            $state['next_slot_to_ask'] = null;
        }
        
        if (!isset($state['recommendation_mode'])) {
            $state['recommendation_mode'] = false;
        }

        // Add user message to history
        $state['history'][] = [
            'role' => 'user',
            'content' => $user_message,
            'timestamp' => time(),
        ];
        
        // Update last activity time
        $state['last_activity'] = time();
        
        // Initialize OpenAI service
        if ($this->openai_service === null) {
            $this->openai_service = new BookRec_OpenAI_Service();
        }
        
        // Try to extract slot value if we have a specific slot to ask
        if (!empty($state['next_slot_to_ask'])) {
            $extracted_value = $this->extract_slot_value($state['next_slot_to_ask'], $user_message, $state);
            if (!empty($extracted_value)) {
                $state['filled_slots'][$state['next_slot_to_ask']] = $extracted_value;
                error_log('BookRec: Extracted ' . $state['next_slot_to_ask'] . ': ' . $extracted_value);
            }
        }
        
        // Check if this is an author search request
        $author_name = '';
        if ($explicit_author_search && !empty($explicit_author_name)) {
            // Use explicit author name from request
            $author_name = $explicit_author_name;
        } else {
            // Try to detect author from message
            $author_name = $this->detect_author_search($user_message);
        }
        
        if (!empty($author_name)) {
            // Set author search mode
            $state['author_search'] = true;
            $state['author_name'] = $author_name;
            $state['recommendation_mode'] = true; // We'll skip to recommendations
        }
        
        // Determine the next action based on filled slots
        $next_action = $this->determine_next_action($state);
        
        // Handle the next action
        if ($next_action['action'] === 'ask_question') {
            // Update the state with the next slot to ask
            $state['next_slot_to_ask'] = $next_action['next_slot'];
            
            // Generate the question to ask
            $bot_response = $this->generate_slot_question($next_action['next_slot'], $state);
        } elseif ($next_action['action'] === 'get_recommendations') {
            // Set recommendation mode if not already set
            if (!$state['recommendation_mode']) {
                $state['recommendation_mode'] = true;
                $bot_response = "Great! Based on what you've shared, I can recommend some books you might enjoy. Give me a moment to think...";
            } else {
                // Generate book recommendations
                $bot_response = $this->generate_book_recommendations($state);
                
                // Mark that recommendations have been provided
                $state['recommendations_provided'] = true;
            }
        } else {
            // Continue conversation without specific slot filling (fallback)
            $bot_response = $this->get_conversational_response($state);
        }
        
        // Fallback if response generation fails
        if ($bot_response === null) {
            $bot_response = 'I apologize, but I encountered an issue processing your request. Please try again later.';
        }
        
        // Process response - extract and enhance recommendations if in recommendation mode
        $response_data = [];
        
        if (isset($state['recommendation_mode']) && $state['recommendation_mode'] && !empty($bot_response)) {
            
            // Check if we have author search recommendations
            if (isset($state['author_search']) && $state['author_search'] && isset($state['final_recommendations'])) {
                $explanatory_text = $state['author_explanatory_text'] ?? "Here are some books by this author:";
                $concluding_question = $state['author_concluding_question'] ?? "Would you like more information about any of these books?";
                $formatted_recommendations = $state['final_recommendations'];
                
                // Replace verbose bot response with a simpler prompt
                $bot_response_text = "Here are books by " . ucwords($state['author_name']) . ":";
                
                // Add to response data
                $response_data = [
                    'recommendations' => $formatted_recommendations,
                    'explanatory_text' => $explanatory_text,
                    'concluding_question' => $concluding_question,
                    'has_recommendations' => !empty($formatted_recommendations),
                ];
                
                // Use the simplified response for history
                $bot_response = $bot_response_text;
            }
            // Check for regular recommendations
            elseif (isset($state['recommendations_provided']) && $state['recommendations_provided']) {
                // Initialize recommendation service if needed
                if ($this->recommendation_service === null) {
                    $this->recommendation_service = new BookRec_Recommendation();
                }
                
                // Extract explanatory text and closing question
                $explanatory_text = $this->recommendation_service->extractExplanatoryText($bot_response);
                $concluding_question = $this->recommendation_service->extractConcludingQuestion($bot_response);
                
                // Process recommendations (including trending books if enabled)
                $include_trending = (int) bookrec_get_config('include_trending_books', 1);
                $extracted_recommendations = $this->recommendation_service->processRecommendations($bot_response, $include_trending);
                
                // Format recommendations for display
                $formatted_recommendations = $this->recommendation_service->formatForDisplay($extracted_recommendations);
                
                // Store final recommendations in state
                $state['final_recommendations'] = $formatted_recommendations;
                
                // Set recommendations shown flag
                $state['recommendations_shown'] = true;
                
                // Replace verbose bot response with a simpler prompt
                $bot_response_text = "Here are a few recommendations based on your preferences:";
                
                // Add to response data
                $response_data = [
                    'recommendations' => $formatted_recommendations,
                    'explanatory_text' => $explanatory_text,
                    'concluding_question' => $concluding_question,
                    'has_recommendations' => !empty($formatted_recommendations),
                ];
                
                // Use the simplified response for history
                $bot_response = $bot_response_text;
            }
            
            // Remove raw recommendations data to save space in session
            if (isset($state['raw_recommendations'])) {
                unset($state['raw_recommendations']);
            }
            
            // Increment recommendation counter for stats
            if (class_exists('BookRec_Dashboard')) {
                BookRec_Dashboard::increment_recommendation_counter();
            }
            
            // Use the simplified response for history
            $bot_response = $bot_response_text;
        }
        
        // Add bot response to history
        $state['history'][] = [
            'role' => 'assistant',
            'content' => $bot_response,
            'timestamp' => time(),
        ];

        // Save updated state with appropriate TTL
        // Use a longer TTL for sessions with recommendations (they're less likely to change)
        $base_ttl = (int) bookrec_get_config('session_ttl', 1800);
        $ttl = isset($state['recommendations_shown']) && $state['recommendations_shown'] ? $base_ttl * 2 : $base_ttl;
        
        // Faster timeout for sessions that haven't had activity in a while
        $last_activity = $state['last_activity'] ?? time();
        $inactivity_period = time() - $last_activity;
        if ($inactivity_period > 600) { // 10 minutes of inactivity
            $ttl = min($ttl, 600); // Only keep for 10 more minutes 
        }
        
        BookRec_State::save_session_state($session_id, $state, $ttl);

        // Send success response with any additional recommendation data
        wp_send_json_success(array_merge([
            'message' => $bot_response,
            'session_id' => $session_id,
            'history' => $state['history'],
            'context' => [
                'filled_slots' => $state['filled_slots'] ?? [],
                'next_slot' => $state['next_slot_to_ask'] ?? null,
                'recommendation_mode' => $state['recommendation_mode'] ?? false,
            ],
            'cta_variation' => $state['cta_variation'] ?? null, // Include the A/B test variation
        ], $response_data));
    }
    
    /**
     * Extract the value for a specific slot from the user message
     *
     * @param string $slot_name The name of the slot to extract
     * @param string $user_message The user's message
     * @param array  $state The current session state
     * 
     * @return string|null Extracted value or null if extraction failed
     */
    private function extract_slot_value(string $slot_name, string $user_message, array $state): ?string {
        // Check for API key
        if (!$this->openai_service->has_api_key()) {
            error_log('BookRec: OpenAI API key not configured for slot extraction');
            return $this->simple_extract_slot_value($slot_name, $user_message);
        }
        
        // Create extraction prompt
        $system_prompt = $this->create_extraction_prompt($slot_name);
        
        // Get most recent conversation context (last few messages)
        $recent_history = array_slice($state['history'], -3);
        
        // Add the current question being answered for context
        if (!empty($state['current_question'])) {
            $recent_history[] = [
                'role' => 'assistant',
                'content' => $state['current_question']
            ];
        }
        
        // Add user's current message
        $recent_history[] = [
            'role' => 'user',
            'content' => $user_message
        ];
        
        // Create messages array for the extraction
        $messages = $this->openai_service->create_messages_array($system_prompt, $recent_history);
        
        // Get the extracted value from OpenAI
        $extraction_result = $this->openai_service->get_chat_completion(
            $messages,
            'gpt-4-turbo-preview',
            0.2, // Lower temperature for more predictable extraction
            150  // Smaller token limit for extraction
        );
        
        // Clean and validate the extraction
        if (!empty($extraction_result)) {
            // Try to parse if it's JSON
            if (strpos($extraction_result, '{') === 0) {
                try {
                    $parsed = json_decode($extraction_result, true);
                    if (json_last_error() === JSON_ERROR_NONE && isset($parsed['value'])) {
                        return $parsed['value'];
                    }
                } catch (\Exception $e) {
                    error_log('BookRec: Error parsing extraction JSON: ' . $e->getMessage());
                }
            }
            
            // If not JSON, use the raw value (trimmed and limited)
            $cleaned = trim($extraction_result);
            
            // Remove any "null" or "none" or "not provided" responses
            if (preg_match('/^(null|none|not provided|unknown|n\/a)$/i', $cleaned)) {
                return null;
            }
            
            // Limit to reasonable length
            if (strlen($cleaned) > 100) {
                $cleaned = substr($cleaned, 0, 100);
            }
            
            return $cleaned;
        }
        
        // Fallback to simple extraction if OpenAI extraction fails
        return $this->simple_extract_slot_value($slot_name, $user_message);
    }
    
    /**
     * Simple extraction for when OpenAI is not available
     *
     * @param string $slot_name The name of the slot to extract
     * @param string $user_message The user's message
     * 
     * @return string|null Extracted value or null
     */
    private function simple_extract_slot_value(string $slot_name, string $user_message): ?string {
        // Very basic extraction - just return the message if not empty
        return !empty($user_message) ? $user_message : null;
    }
    
    /**
     * Create a system prompt for extracting slot values
     *
     * @param string $slot_name The name of the slot to extract
     * 
     * @return string System prompt for extraction
     */
    private function create_extraction_prompt(string $slot_name): string {
        $prompt = "You are an AI designed to extract specific information from user messages. ";
        $prompt .= "Extract the '{$slot_name}' value from the user's message. ";
        
        switch ($slot_name) {
            case 'genre':
                $prompt .= "The genre should be a book category like 'Science Fiction', 'Fantasy', 'Mystery', 'Romance', etc. ";
                $prompt .= "If multiple genres are mentioned, choose the most specific or emphasized one. ";
                $prompt .= "If no clear genre is mentioned, return null.";
                break;
                
            case 'tone':
                $prompt .= "The tone should indicate whether they prefer 'light', 'serious', 'dark', 'uplifting', 'gritty', etc. ";
                $prompt .= "If no clear tone preference is mentioned, return null.";
                break;
                
            case 'recent_favorites':
                $prompt .= "Extract any book titles, authors, or series they've mentioned enjoying. ";
                $prompt .= "Format as 'Title by Author' if both are mentioned, or just the title or author name if only one is provided. ";
                $prompt .= "If multiple are mentioned, include up to 2 separated by commas. ";
                $prompt .= "If no specific books or authors are mentioned, return null.";
                break;
                
            case 'time_period':
                $prompt .= "The time period should indicate whether they prefer 'historical', 'contemporary', 'futuristic', etc. ";
                $prompt .= "If a specific era is mentioned (like 'Victorian', '1980s', etc.), include that. ";
                $prompt .= "If no time period preference is mentioned, return null.";
                break;
                
            case 'length':
                $prompt .= "Extract whether they prefer 'short', 'medium', or 'long' books. ";
                $prompt .= "If page numbers are mentioned, short is <300 pages, medium is 300-500 pages, long is >500 pages. ";
                $prompt .= "If no length preference is mentioned, return null.";
                break;
                
            case 'mood':
                $prompt .= "The mood should indicate what feeling they want from the book: 'hopeful', 'thrilling', 'mysterious', 'romantic', 'thoughtful', etc. ";
                $prompt .= "If no mood preference is mentioned, return null.";
                break;
                
            case 'avoid':
                $prompt .= "Extract any topics, themes, or content they specifically want to avoid. ";
                $prompt .= "Common examples: 'violence', 'romance', 'politics', 'sad endings', etc. ";
                $prompt .= "If nothing to avoid is mentioned, return null.";
                break;
                
            default:
                $prompt .= "Extract any relevant information about their book preferences. ";
                $prompt .= "If no relevant information is provided, return null.";
        }
        
        $prompt .= "\n\nRespond with just the extracted value as plain text. If you cannot extract a value, respond with 'null'.";
        
        return $prompt;
    }
    
    /**
     * Determine the next action based on filled slots
     *
     * @param array $state The current session state
     * 
     * @return array Action data with 'action', 'next_slot', etc.
     */
    private function determine_next_action(array $state): array {
        $filled_slots = $state['filled_slots'] ?? [];
        
        // If already in recommendation mode, continue with recommendations
        if (isset($state['recommendation_mode']) && $state['recommendation_mode']) {
            return [
                'action' => 'get_recommendations'
            ];
        }
        
        // If recommendations have already been provided, continue conversation
        if (isset($state['recommendations_provided']) && $state['recommendations_provided']) {
            return [
                'action' => 'continue_conversation'
            ];
        }
        
        // Check if the last user message is explicitly asking for trending books
        if (!empty($state['history'])) {
            $last_message = end($state['history']);
            if ($last_message['role'] === 'user') {
                $user_message = strtolower($last_message['content']);
                
                // Keywords that strongly indicate a direct request for trending books
                $trend_request_patterns = [
                    'what( is|\'s| are) trending',
                    'trending books',
                    'popular (on )?booktok',
                    'books trending (on )?tiktok',
                    'bestsell(er|ing) (books|list)',
                    'what\'s popular',
                    'most popular books'
                ];
                
                foreach ($trend_request_patterns as $pattern) {
                    if (preg_match('/' . $pattern . '/i', $user_message)) {
                        // If they're explicitly asking for trending books, move directly to recommendation mode
                        return [
                            'action' => 'get_recommendations'
                        ];
                    }
                }
            }
        }
        
        // Get the count of user messages to determine conversation stage
        $user_message_count = 0;
        foreach ($state['history'] as $message) {
            if (isset($message['role']) && $message['role'] === 'user') {
                $user_message_count++;
            }
        }
        
        // Check if we've had enough back-and-forth to make recommendations
        $min_required_filled = 1; // At least genre
        $ideal_filled = 3;        // Ideally genre, tone, and recent_favorites
        
        // Get count of filled required slots
        $filled_required_count = 0;
        foreach (array_keys($this->required_slots) as $slot) {
            if (!empty($filled_slots[$slot])) {
                $filled_required_count++;
            }
        }
        
        // If we have enough messages or enough slots filled, give recommendations
        if (
            ($user_message_count >= 4 && $filled_required_count >= $min_required_filled) || 
            $filled_required_count >= $ideal_filled
        ) {
            return [
                'action' => 'get_recommendations'
            ];
        }
        
        // Otherwise, find the next slot to ask about
        
        // First, check required slots
        foreach (array_keys($this->required_slots) as $slot) {
            if (empty($filled_slots[$slot])) {
                return [
                    'action' => 'ask_question',
                    'next_slot' => $slot
                ];
            }
        }
        
        // If all required slots are filled but we want more info, check optional slots
        // Only ask for one optional slot at most
        $asked_optional = false;
        foreach (array_keys($this->optional_slots) as $slot) {
            if (empty($filled_slots[$slot]) && !$asked_optional) {
                // Only ask for one optional slot to avoid too many questions
                $asked_optional = true;
                return [
                    'action' => 'ask_question',
                    'next_slot' => $slot
                ];
            }
        }
        
        // If we've reached here, we have all required slots and maybe some optional ones
        // Time for recommendations
        return [
            'action' => 'get_recommendations'
        ];
    }
    
    /**
     * Generate a question to ask the user about a specific slot
     *
     * @param string $slot_name The name of the slot to ask about
     * @param array  $state The current session state
     * 
     * @return string|null The question to ask
     */
    private function generate_slot_question(string $slot_name, array &$state): ?string {
        // Simple mode when OpenAI is not available
        if (!$this->openai_service->has_api_key()) {
            $question = $this->get_default_slot_question($slot_name);
            $state['current_question'] = $question;
            return $question;
        }
        
        // Get the appropriate question template based on slot type
        $question_template = "You are an engaging book recommendation assistant. ";
        $question_template .= "Ask the user a natural, conversational question to determine their " . ucfirst($slot_name) . " preference for books. ";
        $question_template .= "Make your question friendly and contextual to the conversation. ";
        $question_template .= "Keep your response under 200 characters and conversational in tone.";
        
        // Add context about what we already know
        if (!empty($state['filled_slots'])) {
            $question_template .= "\n\nWhat we already know about their preferences: ";
            foreach ($state['filled_slots'] as $filled_slot => $value) {
                $question_template .= "\n- " . ucfirst($filled_slot) . ": " . $value;
            }
        }
        
        // Add specific guidance for this slot type
        switch ($slot_name) {
            case 'genre':
                $question_template .= "\n\nAsk about their preferred book genres or categories. Avoid listing examples unless necessary.";
                break;
                
            case 'tone':
                $question_template .= "\n\nAsk whether they prefer light/happy stories or more serious/dark ones. Keep it conversational.";
                break;
                
            case 'recent_favorites':
                $question_template .= "\n\nAsk about books or authors they've enjoyed recently. Frame it as helping you understand their taste better.";
                break;
                
            case 'time_period':
                $question_template .= "\n\nAsk if they prefer stories set in the past, present, or future. Keep it simple and conversational.";
                break;
                
            case 'length':
                $question_template .= "\n\nAsk if they prefer quick reads or longer, more detailed books. Keep it casual.";
                break;
                
            case 'mood':
                $question_template .= "\n\nAsk about the feeling or emotional experience they're looking for in their next book.";
                break;
                
            case 'avoid':
                $question_template .= "\n\nAsk if there are any topics or themes they'd like to avoid. Keep it gentle and optional-sounding.";
                break;
        }
        
        // Get recent conversation context
        $recent_history = array_slice($state['history'], -4);
        
        // Create messages array for the question generation
        $messages = $this->openai_service->create_messages_array($question_template, $recent_history);
        
        // Get the question from OpenAI
        $generated_question = $this->openai_service->get_chat_completion(
            $messages,
            'gpt-4-turbo-preview',
            0.7, 
            200
        );
        
        // Store the current question for context
        if (!empty($generated_question)) {
            $state['current_question'] = $generated_question;
            return $generated_question;
        }
        
        // Fallback to default question if generation fails
        $default_question = $this->get_default_slot_question($slot_name);
        $state['current_question'] = $default_question;
        return $default_question;
    }
    
    /**
     * Get a default question for a slot if dynamic generation fails
     *
     * @param string $slot_name The slot to get a question for
     * 
     * @return string Default question for the slot
     */
    private function get_default_slot_question(string $slot_name): string {
        if (isset($this->required_slots[$slot_name])) {
            return $this->required_slots[$slot_name];
        }
        
        if (isset($this->optional_slots[$slot_name])) {
            return $this->optional_slots[$slot_name];
        }
        
        return "Can you tell me more about what you're looking for in a book?";
    }
    
    /**
     * Detect if user is asking for books by a specific author
     *
     * @param string $user_message The user's message
     * 
     * @return string|null Author name or null if not an author search
     */
    private function detect_author_search(string $user_message): ?string {
        $user_message = strtolower($user_message);
        
        // Define patterns for author search detection
        $patterns = [
            '/books? by ([\w\s]+)/',
            '/anything by ([\w\s]+)/',
            '/books? from ([\w\s]+)/',
            '/works? by ([\w\s]+)/',
            '/recommend [\w\s]+ by ([\w\s]+)/',
            '/what ([\w\s]+) wrote/',
            '/written by ([\w\s]+)/',
            '/author ([\w\s]+)/',
            '/([\w\s]+)\'s books/',
        ];
        
        // Try each pattern
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $user_message, $matches)) {
                // Clean up the author name
                $author = trim($matches[1]);
                
                // Remove common words that might be captured
                $author = preg_replace('/\b(the|author|writer|books?|novels?|works?)\b/i', '', $author);
                
                // Trim again after removing words
                $author = trim($author);
                
                // Return if we have a meaningful author name (more than 2 characters)
                if (strlen($author) > 2) {
                    return $author;
                }
            }
        }
        
        // Also check if they've directly said they want an author in a filled slot
        if (strpos($user_message, 'author') !== false && 
            (strpos($user_message, 'looking for') !== false || 
             strpos($user_message, 'find') !== false ||
             strpos($user_message, 'searching for') !== false)) {
            
            // Extract potential author name - this is simplistic and could be improved
            $words = preg_split('/\W+/', $user_message);
            $author_index = array_search('author', $words);
            
            if ($author_index !== false && isset($words[$author_index + 1])) {
                // Take the next few words as the author
                $author = '';
                for ($i = $author_index + 1; $i < count($words) && $i < $author_index + 4; $i++) {
                    $author .= $words[$i] . ' ';
                }
                
                $author = trim($author);
                if (strlen($author) > 2) {
                    return $author;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Generate a conversational response when not in slot-filling mode
     *
     * @param array $state The current session state
     * 
     * @return string|null Conversational response
     */
    private function get_conversational_response(array $state): ?string {
        // Check if OpenAI is available
        if (!$this->openai_service->has_api_key()) {
            return "I'd like to recommend some books for you. Can you tell me what genres you enjoy reading?";
        }
        
        // Get system prompt from settings and format it with slots
        $system_prompt = bookrec_get_config('system_prompt', '');
        $system_prompt = $this->openai_service->format_system_prompt($system_prompt, $state['filled_slots'] ?? []);
        
        // Get the last user message to check for trend-related queries
        $last_user_message = '';
        if (!empty($state['history'])) {
            $history_reversed = array_reverse($state['history']);
            foreach ($history_reversed as $message) {
                if (isset($message['role']) && $message['role'] === 'user') {
                    $last_user_message = strtolower($message['content']);
                    break;
                }
            }
        }
        
        // Check if user is asking about trending books
        $trending_keywords = ['trending', 'trend', 'popular', 'bestseller', 'bestselling', 'booktok', 'tiktok', 'viral'];
        $is_trending_query = false;
        
        foreach ($trending_keywords as $keyword) {
            if (strpos($last_user_message, $keyword) !== false) {
                $is_trending_query = true;
                break;
            }
        }
        
        // Add trending books information if available and user is asking about trends
        if ($is_trending_query) {
            $trends = get_transient('bookrec_trends_data');
            
            if (!empty($trends) && is_array($trends)) {
                // Limit to 5 trending books
                $trending_books = array_slice($trends, 0, 5);
                
                // Create trending books context
                $trending_context = "\n\nThe user is asking about trending or popular books. Here are current trending titles:";
                foreach ($trending_books as $book) {
                    $trending_context .= "\n- " . $book['title'] . " by " . $book['author'];
                }
                
                // Add instruction
                $trending_context .= "\n\nMention these trending books in your response, focusing on any that match the user's genre preferences. Format your response conversationally and explain why these books are popular.";
                
                // Add to system prompt
                $system_prompt .= $trending_context;
            }
        }
        
        // Create messages array for OpenAI API
        $messages = $this->openai_service->create_messages_array($system_prompt, $state['history']);
        
        // Get response from OpenAI
        return $this->openai_service->get_chat_completion(
            $messages,
            'gpt-4-turbo-preview',
            0.7,
            500
        );
    }
    
    /**
     * Generate book recommendations based on filled slots
     *
     * @param array $state The current session state
     * 
     * @return string|null Book recommendations
     */
    private function generate_book_recommendations(array $state): ?string {
        // Check if we're doing an author search
        if (isset($state['author_search']) && $state['author_search'] && !empty($state['author_name'])) {
            return $this->generate_author_recommendations($state);
        }
        
        // Check if OpenAI is available
        if (!$this->openai_service->has_api_key()) {
            return "Based on your preferences, I recommend checking out some popular titles in " . 
                   ($state['filled_slots']['genre'] ?? "your preferred genre") . 
                   ". Would you like specific title suggestions?";
        }
        
        // Get recommendation prompt from settings
        $recommendation_prompt = bookrec_get_config('recommendation_prompt', '');
        
        // Replace count placeholder with default count
        $count = (int) bookrec_get_config('default_recommendations', 3);
        $recommendation_prompt = str_replace('{count}', (string) $count, $recommendation_prompt);
        
        // Replace genre placeholder if available
        $genre = $state['filled_slots']['genre'] ?? 'books';
        $recommendation_prompt = str_replace('{genre}', $genre, $recommendation_prompt);
        
        // Build the full system prompt for recommendations
        $system_prompt = $this->build_recommendation_system_prompt($state, $recommendation_prompt);
        
        // Filter the history to keep it relevant and within token limits
        $filtered_history = $this->filter_history_for_recommendations($state['history']);
        
        // Create messages array for OpenAI API
        $messages = $this->openai_service->create_messages_array($system_prompt, $filtered_history);
        
        // Get recommendations with higher max tokens to allow for multiple detailed book suggestions
        $recommendations = $this->openai_service->get_chat_completion(
            $messages,
            'gpt-4-turbo-preview',
            0.7,
            1200
        );
        
        // Store raw recommendations in state (will be removed after processing)
        $state['raw_recommendations'] = $recommendations;
        
        return $recommendations;
    }
    
    /**
     * Generate recommendations for a specific author
     *
     * @param array $state The current session state
     * 
     * @return string Book recommendations by the author
     */
    private function generate_author_recommendations(array &$state): string {
        $author = $state['author_name'];
        $count = (int) bookrec_get_config('default_recommendations', 3);
        
        // Initialize Google Books service if needed
        $googlebooks = new BookRec_GoogleBooks_Service();
        
        // Get books by this author
        $books = $googlebooks->searchBooksByAuthor($author, $count + 2); // Get a couple extra in case some don't have good metadata
        
        // If no books found through API
        if (empty($books)) {
            // Search fallback data for this author
            $fallback_books = $this->search_fallback_data_by_author($author);
            
            if (empty($fallback_books)) {
                // Use OpenAI to generate some generic recommendations
                return $this->generate_fallback_author_recommendations($author);
            }
            
            $books = $fallback_books;
        }
        
        // Initialize recommendation service if needed
        if ($this->recommendation_service === null) {
            $this->recommendation_service = new BookRec_Recommendation();
        }
        
        // Format for display
        $formatted_books = [];
        $added = 0;
        
        foreach ($books as $book) {
            if ($added >= $count) break;
            
            $formatted_books[] = [
                'title' => $book['title'] ?? 'Unknown Title',
                'author' => $book['author'] ?? $author,
                'description' => isset($book['description']) && !empty($book['description']) 
                    ? mb_substr($book['description'], 0, 300) . '...' 
                    : 'A compelling work by this author.',
                'coverUrl' => $book['cover_url'] ?? $book['thumbnail'] ?? '',
                'amazonLink' => $book['amazon_link'] ?? $this->generate_amazon_link($book['title'], $book['author'] ?? $author),
                'publishDate' => $book['published_date'] ?? $book['publishedDate'] ?? '',
                'publisher' => $book['publisher'] ?? '',
                'isbn' => $book['isbn13'] ?? $book['isbn10'] ?? '',
                'source' => $book['source'] ?? 'google_books',
            ];
            
            $added++;
        }
        
        // Set the formatted recommendations in state
        $state['final_recommendations'] = $formatted_books;
        $state['recommendations_shown'] = true;
        
        // Create simple text response (frontend will display structured recommendations)
        $response = "Here are some books by " . ucwords($author) . ":";
        
        // Create explanatory text and concluding question
        $state['author_explanatory_text'] = "I found these books by " . ucwords($author) . " that you might enjoy:";
        $state['author_concluding_question'] = "Would you like more information about any of these books, or would you prefer recommendations from a different author?";
        
        return $response;
    }
    
    /**
     * Search fallback data for books by a specific author
     *
     * @param string $author Author name to search for
     * 
     * @return array Array of book data
     */
    private function search_fallback_data_by_author(string $author): array {
        // Initialize recommendation service to access fallback data
        if ($this->recommendation_service === null) {
            $this->recommendation_service = new BookRec_Recommendation();
        }
        
        // Get fallback data
        $fallback_data = $this->recommendation_service->get_fallback_data();
        if (empty($fallback_data)) {
            return [];
        }
        
        // Search for author matches (case-insensitive)
        $author_lower = strtolower($author);
        $matches = [];
        
        foreach ($fallback_data as $book) {
            if (isset($book['author']) && strpos(strtolower($book['author']), $author_lower) !== false) {
                $matches[] = [
                    'title' => $book['title'],
                    'author' => $book['author'],
                    'description' => $book['description'] ?? '',
                    'cover_url' => $book['cover_url'] ?? '',
                    'amazon_link' => $this->generate_amazon_link($book['title'], $book['author']),
                    'source' => 'fallback_json',
                ];
            }
        }
        
        return $matches;
    }
    
    /**
     * Generate fallback recommendations when author isn't found
     *
     * @param string $author Author name
     * 
     * @return string Generated recommendations
     */
    private function generate_fallback_author_recommendations(string $author): string {
        if (!$this->openai_service->has_api_key()) {
            return "I couldn't find specific books by " . ucwords($author) . 
                   ". Would you like recommendations for a different author or perhaps by genre instead?";
        }
        
        // Create a prompt asking for recommendations for this author
        $system_prompt = "You are a helpful book recommendation assistant. The user has asked for books by " . 
                         $author . ", but our database doesn't have information on this author. " .
                         "Please provide 3 book recommendations by this author, including title and a brief description " .
                         "for each. If you're not familiar with this author, suggest similar authors the user might enjoy instead. " .
                         "Format your response conversationally and don't mention that our database is missing information.";
        
        // Get recommendations from OpenAI
        $messages = [
            [
                'role' => 'system',
                'content' => $system_prompt
            ]
        ];
        
        $recommendations = $this->openai_service->get_chat_completion(
            $messages,
            'gpt-4-turbo-preview',
            0.7,
            800
        );
        
        if (empty($recommendations)) {
            return "I couldn't find specific books by " . ucwords($author) . 
                   ". Would you like recommendations for a different author or perhaps by genre instead?";
        }
        
        return $recommendations;
    }
    
    /**
     * Generate Amazon affiliate link
     *
     * @param string $title  Book title
     * @param string $author Book author
     * 
     * @return string Amazon affiliate link
     */
    private function generate_amazon_link(string $title, string $author): string {
        $search_term = urlencode("$title $author");
        $tag = bookrec_get_config('amazon_tag', 'gooderead-20');
        return "https://www.amazon.com/s?k={$search_term}&tag={$tag}";
    }
    
    /**
     * Build the system prompt for book recommendations
     *
     * @param array  $state The current session state
     * @param string $recommendation_prompt The base recommendation prompt
     * 
     * @return string Complete system prompt for recommendations
     */
    private function build_recommendation_system_prompt(array $state, string $recommendation_prompt): string {
        $filled_slots = $state['filled_slots'] ?? [];
        
        $system_prompt = "You are GoodBookAI, a knowledgeable and persuasive book recommendation assistant. ";
        $system_prompt .= "Your goal is to provide personalized, compelling book recommendations that the user will love. ";
        
        // Add context about their preferences
        $system_prompt .= "\n\nUser preferences:";
        if (!empty($filled_slots)) {
            foreach ($filled_slots as $slot => $value) {
                if (!empty($value)) {
                    $system_prompt .= "\n- " . ucfirst($slot) . ": " . $value;
                }
            }
        } else {
            $system_prompt .= "\n- Limited information available";
        }
        
        // Include trending books information if available
        $trends = get_transient('bookrec_trends_data');
        $include_trends_count = (int) bookrec_get_config('include_trending_books', 1);
        
        if ($include_trends_count > 0 && !empty($trends) && is_array($trends)) {
            // Limit to 3 trending books to avoid overloading the prompt
            $trend_limit = min(3, count($trends));
            $trending_books = array_slice($trends, 0, $trend_limit);
            
            $system_prompt .= "\n\nCurrently trending books popular with readers:";
            foreach ($trending_books as $index => $book) {
                $system_prompt .= "\n- " . $book['title'] . " by " . $book['author'];
            }
            
            // Add instruction about how to incorporate trending books
            $system_prompt .= "\n\nIf any of these trending books align well with the user's preferences, consider including ONE as part of your recommendations. Only include a trending book if it genuinely matches their interests. Don't explicitly mention that a book is trending unless directly asked.";
        }
        
        // Add persuasive instruction
        $system_prompt .= "\n\nFormat your recommendations to be highly persuasive and machine-readable:";
        $system_prompt .= "\n1. Begin with a brief summary of what you've learned about their preferences";
        $system_prompt .= "\n2. For each book recommendation ALWAYS use the exact format:";
        $system_prompt .= "\n   - Start with the title and author in bold using the format: **Book Title by Author Name**";
        $system_prompt .= "\n   - Then add 2-3 sentence compelling description that connects to their stated preferences";
        $system_prompt .= "\n   - Use persuasive elements like social proof ('beloved by readers who enjoy...'), scarcity ('hidden gem'), or benefits ('will transport you to...')";
        $system_prompt .= "\n   - Do not include links - our system will automatically add them";
        $system_prompt .= "\n3. IMPORTANT: Always format the book title and author exactly as **Title by Author** so our system can parse it correctly";
        $system_prompt .= "\n4. End with a question asking which recommendation appeals to them most, or if they'd like different suggestions";
        
        // Add the recommendation prompt from settings
        $system_prompt .= "\n\n" . $recommendation_prompt;
        
        // Add quality guidelines
        $system_prompt .= "\n\nQuality guidelines:";
        $system_prompt .= "\n- Recommend real, available books with accurate titles and authors";
        $system_prompt .= "\n- Recommendations should be diverse within their preferences";
        $system_prompt .= "\n- Tailor your tone to match their conversation style";
        $system_prompt .= "\n- Format your response to be readable and engaging";
        
        return $system_prompt;
    }
    
    /**
     * Filter and prepare history for recommendation generation
     *
     * @param array $history Full conversation history
     * 
     * @return array Filtered history for recommendations
     */
    private function filter_history_for_recommendations(array $history): array {
        // If history is very short, use all of it
        if (count($history) <= 6) {
            return $history;
        }
        
        // For longer histories, keep the first message (welcome) and the most recent messages
        $filtered = [$history[0]];
        $recent = array_slice($history, -5);
        
        return array_merge($filtered, $recent);
    }

    /**
     * Initialize a new chat session
     */
    public function init_session(): void {
        // Verify nonce
        check_ajax_referer('bookrec_chat_nonce', 'nonce');

        // Get container ID and genre
        $container_id = sanitize_text_field($_POST['container_id'] ?? '');
        $genre = sanitize_text_field($_POST['genre'] ?? '');

        // Generate new session ID
        $session_id = BookRec_State::generate_session_id();
        
        // Get welcome message
        $welcome_message = $this->get_welcome_message($genre);

        // Check if A/B testing is enabled
        $ab_test_enabled = bookrec_get_config('ab_test_cta', 'disabled') === 'enabled';
        
        // Assign A/B test variation if enabled
        $cta_variation = null;
        if ($ab_test_enabled) {
            $cta_variation = (rand(0, 1) == 0) ? 'A' : 'B';
        }
        
        // Create initial state with slot structure
        $state = [
            'history' => [
                [
                    'role' => 'assistant',
                    'content' => $welcome_message,
                    'timestamp' => time(),
                ]
            ],
            'slots' => [
                'genre' => $genre,
            ],
            'filled_slots' => [
                // Pre-fill genre if provided via shortcode
                'genre' => !empty($genre) ? $genre : null,
            ],
            'next_slot_to_ask' => empty($genre) ? 'genre' : 'tone', // Next question depends on if genre is known
            'recommendation_mode' => false,
            'created_at' => time(),
            'last_activity' => time(),
            'container_id' => $container_id,
            'cta_variation' => $cta_variation, // Store the A/B test variation
        ];

        // Save state
        $ttl = (int) bookrec_get_config('cache_ttl', 1800);
        BookRec_State::save_session_state($session_id, $state, $ttl);
        
        // Increment session counter for stats
        if (class_exists('BookRec_Dashboard')) {
            BookRec_Dashboard::increment_session_counter();
        }

        // Send success response
        wp_send_json_success([
            'session_id' => $session_id,
            'history' => $state['history'],
            'welcome_message' => $welcome_message,
            'context' => [
                'filled_slots' => $state['filled_slots'],
                'next_slot' => $state['next_slot_to_ask'],
            ],
            'cta_variation' => $state['cta_variation'], // Include CTA variation in response
        ]);
    }
    
    /**
     * Handle click tracking AJAX request
     * Records click data to the database and updates metrics
     * 
     * @return void
     */
    public function handle_track_click(): void {
        global $wpdb;
        
        // Verify nonce
        check_ajax_referer('bookrec_click_nonce', 'nonce');
        
        // Get and sanitize data
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        $book_title = sanitize_text_field($_POST['title'] ?? '');
        $book_author = sanitize_text_field($_POST['author'] ?? '');
        $amazon_url = esc_url_raw($_POST['url'] ?? '');
        $cta_variation = sanitize_text_field($_POST['cta_variation'] ?? '');
        
        // Validate required data
        if (empty($session_id) || empty($book_title) || empty($amazon_url)) {
            wp_send_json_error([
                'message' => 'Missing required tracking data',
                'code' => 'missing_data'
            ]);
            return;
        }
        
        // Insert click data into database
        $table_name = $wpdb->prefix . 'bookrec_clicks';
        
        $result = $wpdb->insert(
            $table_name,
            [
                'session_id' => $session_id,
                'book_title' => $book_title,
                'book_author' => $book_author,
                'amazon_url' => $amazon_url,
                'cta_variation' => !empty($cta_variation) ? $cta_variation : null,
                // click_timestamp will be set to CURRENT_TIMESTAMP by default
            ],
            [
                '%s', // session_id
                '%s', // book_title
                '%s', // book_author
                '%s', // amazon_url
                '%s', // cta_variation
            ]
        );
        
        // Check for database errors
        if ($result === false) {
            wp_send_json_error([
                'message' => 'Failed to record click: ' . $wpdb->last_error,
                'code' => 'db_error'
            ]);
            return;
        }
        
        // Increment the click counter in options for dashboard stats
        $current_clicks = (int) get_option('bookrec_amazon_clicks', 0);
        update_option('bookrec_amazon_clicks', $current_clicks + 1);
        
        // Track book-specific clicks
        $book_clicks = get_option('bookrec_book_clicks', []);
        $book_key = md5($book_title . $book_author); // Create a unique key
        
        if (!isset($book_clicks[$book_key])) {
            $book_clicks[$book_key] = [
                'title' => $book_title,
                'author' => $book_author,
                'count' => 0
            ];
        }
        
        $book_clicks[$book_key]['count']++;
        update_option('bookrec_book_clicks', $book_clicks);
        
        // Send success response
        wp_send_json_success(['status' => 'tracked']);
    }
    
    /**
     * Handle sending recommendations as a PDF via email
     * 
     * @return void
     */
    public function handle_email_recs(): void {
        // Verify nonce
        check_ajax_referer('bookrec_email_nonce', 'nonce');
        
        // Get and sanitize email address
        $recipient_email = sanitize_email($_POST['email'] ?? '');
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        
        // Validate email
        if (empty($recipient_email) || !is_email($recipient_email)) {
            wp_send_json_error([
                'message' => __('Please provide a valid email address', 'goodereader-bookrec'),
                'code' => 'invalid_email',
            ]);
            return;
        }
        
        // Validate session ID
        if (empty($session_id)) {
            wp_send_json_error([
                'message' => __('Missing session information', 'goodereader-bookrec'),
                'code' => 'missing_session',
            ]);
            return;
        }
        
        // Get the session state
        $state = BookRec_State::get_session_state($session_id);
        
        // Check if we have recommendations
        if (empty($state['final_recommendations']) || !is_array($state['final_recommendations'])) {
            wp_send_json_error([
                'message' => __('No recommendations found to email', 'goodereader-bookrec'),
                'code' => 'no_recommendations',
            ]);
            return;
        }
        
        // Get original query from conversation if available
        $user_query = '';
        if (!empty($state['history']) && is_array($state['history'])) {
            // Get the first user message as the query
            foreach ($state['history'] as $message) {
                if (isset($message['role']) && $message['role'] === 'user') {
                    $user_query = $message['content'] ?? '';
                    break;
                }
            }
        }
        
        // Initialize email service if not already
        if ($this->email_service === null) {
            $this->email_service = new BookRec_Email_Service();
        }
        
        // Send recommendations as PDF
        $email_sent = $this->email_service->sendRecommendationsAsPDF(
            $recipient_email, 
            $state['final_recommendations'],
            $user_query
        );
        
        // Return response based on success/failure
        if ($email_sent) {
            // Log successful email in the session state
            $state['email_sent'] = [
                'timestamp' => time(),
                'recipient' => $recipient_email,
            ];
            
            // Save updated state
            BookRec_State::save_session_state($session_id, $state);
            
            // Track email metric for dashboard
            $current_emails = (int) get_option('bookrec_pdf_emails_sent', 0);
            update_option('bookrec_pdf_emails_sent', $current_emails + 1);
            
            wp_send_json_success([
                'message' => __('Book recommendations have been sent to your email', 'goodereader-bookrec'),
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Failed to send recommendations. Please try again later.', 'goodereader-bookrec'),
                'code' => 'email_failed',
            ]);
        }
    }
    
    /**
     * Get personalized welcome message based on genre
     *
     * @param string $genre Optional genre preference
     * @return string Welcome message
     */
    private function get_welcome_message(string $genre = ''): string {
        // Initialize OpenAI service if not already done
        if ($this->openai_service === null) {
            $this->openai_service = new BookRec_OpenAI_Service();
        }
        
        // Check if OpenAI is configured
        if (!$this->openai_service->has_api_key()) {
            // Fallback to default welcome message
            if (!empty($genre)) {
                return "Hello! I'm your book recommendation assistant. I see you're interested in {$genre} books. What specific types of stories do you enjoy?";
            } else {
                return "Hello! I'm your book recommendation assistant. What kinds of books do you enjoy reading?";
            }
        }
        
        // Get system prompt for welcome
        $system_prompt = "You are GoodBookAI, a friendly book recommendation assistant. Create a brief, welcoming first message to start a conversation with a user about book recommendations.";
        
        if (!empty($genre)) {
            $system_prompt .= " The user has indicated an interest in {$genre} books, so mention this in your greeting.";
        }
        
        $system_prompt .= " Keep your response under 150 characters and make it conversational and inviting. Do not introduce yourself or explain what you are - just greet them.";
        
        // Create messages array for OpenAI API
        $messages = [
            [
                'role' => 'system',
                'content' => $system_prompt
            ]
        ];
        
        // Get response from OpenAI
        $welcome = $this->openai_service->get_chat_completion(
            $messages,
            'gpt-4-turbo-preview',
            0.7,
            150
        );
        
        // Return welcome message or fallback
        if (!empty($welcome)) {
            return $welcome;
        }
        
        // Fallback welcome message
        if (!empty($genre)) {
            return "Hello! I see you're interested in {$genre} books. What specific types of stories do you enjoy?";
        } else {
            return "Hello! I'm your book recommendation assistant. What kinds of books do you enjoy reading?";
        }
    }
}