<?php
/**
 * Book Recommender Google Books Service Class
 *
 * @package   Good E-Reader Book Recommender
 */

declare(strict_types=1);

if (!defined('WPINC')) {
    die;
}

/**
 * Class BookRec_GoogleBooks_Service
 * 
 * Handles fetching and caching book metadata from the Google Books API.
 */
class BookRec_GoogleBooks_Service {

    /**
     * Google Books API base URL
     *
     * @var string
     */
    private string $api_url = 'https://www.googleapis.com/books/v1/volumes';

    /**
     * Google Books API key
     *
     * @var string|null
     */
    private ?string $api_key = null;

    /**
     * Cache TTL in seconds
     *
     * @var int
     */
    private int $cache_ttl = 86400; // 24 hours default
    
    /**
     * Debug mode flag to log cache hits/misses
     *
     * @var bool
     */
    private bool $debug_cache = false;

    /**
     * Transient prefix for caching
     *
     * @var string
     */
    private string $transient_prefix = 'bookrec_gbooks_';

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
            $this->api_key = bookrec_get_config('gbooks_key', '');
        }

        // Set cache TTL from config or get from options
        if (isset($config['cache_ttl'])) {
            $this->cache_ttl = (int) $config['cache_ttl'];
        } else {
            // Default to 10 minutes for API cache
            $this->cache_ttl = (int) bookrec_get_config('cache_ttl', 600);
        }
        
        // Enable debug mode if set in config
        if (isset($config['debug_cache'])) {
            $this->debug_cache = (bool) $config['debug_cache'];
        } else {
            $this->debug_cache = (bool) bookrec_get_config('debug_cache', false);
        }
    }

    /**
     * Get book details by title and author
     *
     * @param string $title  Book title
     * @param string $author Book author
     * 
     * @return array|null Book details or null on failure
     */
    public function getBookDetailsByTitleAuthor(string $title, string $author): ?array {
        // Generate cache key
        $cache_key = $this->transient_prefix . md5($title . $author);

        // Use the cache helper to handle cache lookup and API call
        return $this->getCachedOrExecute(
            $cache_key,
            function() use ($title, $author) {
                // Construct search query
                $query = '';
                if (!empty($title)) {
                    $query .= 'intitle:' . urlencode(trim($title));
                }
                if (!empty($author)) {
                    if (!empty($query)) {
                        $query .= '+';
                    }
                    $query .= 'inauthor:' . urlencode(trim($author));
                }
        
                // If neither title nor author, return null
                if (empty($query)) {
                    error_log('BookRec: Google Books - No title or author provided');
                    return null;
                }
        
                // Build request URL with query parameters
                $query_params = [
                    'q' => $query,
                    'maxResults' => 1,
                    'fields' => 'items(id,volumeInfo(title,authors,description,imageLinks(thumbnail),publishedDate,categories,industryIdentifiers,publisher,pageCount))', 
                ];
        
                // Add API key if available
                if (!empty($this->api_key)) {
                    $query_params['key'] = $this->api_key;
                }
        
                // Make API request
                $url = add_query_arg($query_params, $this->api_url);
                $response = wp_remote_get($url, [
                    'timeout' => 15,
                    'headers' => [
                        'Accept' => 'application/json',
                    ],
                ]);
        
                // Check for request errors
                if (is_wp_error($response)) {
                    error_log('BookRec: Google Books API request failed: ' . $response->get_error_message());
                    return null;
                }
        
                // Check response code
                $response_code = wp_remote_retrieve_response_code($response);
                if ($response_code !== 200) {
                    error_log('BookRec: Google Books API error. Status: ' . $response_code);
                    
                    // Special handling for common error codes
                    if ($response_code === 403) {
                        error_log('BookRec: Google Books API access denied. Check API key.');
                    } elseif ($response_code === 429) {
                        error_log('BookRec: Google Books API quota exceeded.');
                    }
                    
                    return null;
                }
        
                // Parse response
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
        
                // Check for decoding errors
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log('BookRec: Failed to decode Google Books API response. JSON error: ' . json_last_error_msg());
                    return null;
                }
        
                // Check if we have valid results
                if (empty($data['items']) || !is_array($data['items'])) {
                    // No books found, but this is an expected condition, not an error
                    return null;
                }
        
                // Process the first result
                return $this->processBookData($data['items'][0]);
            },
            'title_author',
            $title,
            $author
        );
    }

    /**
     * Get book details by ISBN
     *
     * @param string $isbn ISBN-10 or ISBN-13
     * 
     * @return array|null Book details or null on failure
     */
    public function getBookDetailsByISBN(string $isbn): ?array {
        // Clean ISBN
        $isbn = preg_replace('/[^0-9X]/', '', $isbn);
        
        if (empty($isbn)) {
            return null;
        }
        
        // Generate cache key
        $cache_key = $this->transient_prefix . 'isbn_' . $isbn;

        // Check if we have cached results
        $cached_result = get_transient($cache_key);
        if ($cached_result !== false) {
            return $cached_result;
        }

        // Build query parameters
        $query_params = [
            'q' => 'isbn:' . $isbn,
            'maxResults' => 1,
            'fields' => 'items(id,volumeInfo(title,authors,description,imageLinks(thumbnail),publishedDate,categories,industryIdentifiers,publisher,pageCount))', 
        ];

        // Add API key if available
        if (!empty($this->api_key)) {
            $query_params['key'] = $this->api_key;
        }

        // Make API request
        $url = add_query_arg($query_params, $this->api_url);
        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        // Check for request errors
        if (is_wp_error($response)) {
            error_log('BookRec: Google Books API request failed: ' . $response->get_error_message());
            return null;
        }

        // Check response code
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('BookRec: Google Books API error. Status: ' . $response_code);
            return null;
        }

        // Parse response
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Check for decoding errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('BookRec: Failed to decode Google Books API response. JSON error: ' . json_last_error_msg());
            return null;
        }

        // Check if we have valid results
        if (empty($data['items']) || !is_array($data['items'])) {
            return null;
        }

        // Process the first result
        $book = $this->processBookData($data['items'][0]);

        // Cache the result
        set_transient($cache_key, $book, $this->cache_ttl);

        return $book;
    }

    /**
     * Search for books by query term
     *
     * @param string $query        The search query
     * @param int    $max_results  Maximum number of results to return
     * 
     * @return array|null Array of book details or null on failure
     */
    public function searchBooks(string $query, int $max_results = 5): ?array {
        if (empty($query)) {
            return null;
        }

        // Limit max results (API limit is 40)
        $max_results = min(max(1, $max_results), 40);
        
        // Generate cache key
        $cache_key = $this->transient_prefix . 'search_' . md5($query . $max_results);

        // Check if we have cached results
        $cached_result = get_transient($cache_key);
        if ($cached_result !== false) {
            return $cached_result;
        }

        // Build query parameters
        $query_params = [
            'q' => $query,
            'maxResults' => $max_results,
            'fields' => 'items(id,volumeInfo(title,authors,description,imageLinks(thumbnail),publishedDate,categories,industryIdentifiers,publisher))', 
        ];

        // Add API key if available
        if (!empty($this->api_key)) {
            $query_params['key'] = $this->api_key;
        }

        // Make API request
        $url = add_query_arg($query_params, $this->api_url);
        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        // Check for request errors
        if (is_wp_error($response)) {
            error_log('BookRec: Google Books API request failed: ' . $response->get_error_message());
            return null;
        }

        // Check response code
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('BookRec: Google Books API error. Status: ' . $response_code);
            return null;
        }

        // Parse response
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Check for decoding errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('BookRec: Failed to decode Google Books API response. JSON error: ' . json_last_error_msg());
            return null;
        }

        // Check if we have valid results
        if (empty($data['items']) || !is_array($data['items'])) {
            return null;
        }

        // Process results
        $books = [];
        foreach ($data['items'] as $item) {
            $book = $this->processBookData($item);
            if ($book) {
                $books[] = $book;
            }
        }

        // Cache the result
        set_transient($cache_key, $books, $this->cache_ttl);

        return $books;
    }
    
    /**
     * Search for books by author
     *
     * @param string $author       The author to search for
     * @param int    $max_results  Maximum number of results to return
     * @param string $order_by     How to order results (relevance, newest)
     * 
     * @return array Array of book details or empty array on failure
     */
    public function searchBooksByAuthor(string $author, int $max_results = 5, string $order_by = 'relevance'): array {
        if (empty($author)) {
            return [];
        }

        // Limit max results (API limit is 40)
        $max_results = min(max(1, $max_results), 40);
        
        // Validate order_by
        $valid_order_by = ['relevance', 'newest'];
        if (!in_array($order_by, $valid_order_by)) {
            $order_by = 'relevance';
        }
        
        // Generate cache key
        $cache_key = $this->transient_prefix . 'author_' . md5($author . $max_results . $order_by);

        // Check if we have cached results
        $cached_result = get_transient($cache_key);
        if ($cached_result !== false) {
            return $cached_result;
        }

        // Build query parameters
        $query_params = [
            'q' => 'inauthor:"' . urlencode($author) . '"',
            'maxResults' => $max_results,
            'orderBy' => $order_by,
            'printType' => 'books',
            'fields' => 'items(id,volumeInfo(title,authors,description,imageLinks(thumbnail),publishedDate,categories,industryIdentifiers,publisher))', 
        ];

        // Add API key if available
        if (!empty($this->api_key)) {
            $query_params['key'] = $this->api_key;
        }

        // Make API request
        $url = add_query_arg($query_params, $this->api_url);
        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        // Check for request errors
        if (is_wp_error($response)) {
            error_log('BookRec: Google Books API author search failed: ' . $response->get_error_message());
            return [];
        }

        // Check response code
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('BookRec: Google Books API error in author search. Status: ' . $response_code);
            return [];
        }

        // Parse response
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Check for decoding errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('BookRec: Failed to decode Google Books API author search response. JSON error: ' . json_last_error_msg());
            return [];
        }

        // Check if we have valid results
        if (empty($data['items']) || !is_array($data['items'])) {
            return [];
        }

        // Process results
        $books = [];
        foreach ($data['items'] as $item) {
            $book = $this->processBookData($item);
            if ($book) {
                $books[] = $book;
            }
        }

        // Cache the result
        set_transient($cache_key, $books, $this->cache_ttl);

        return $books;
    }
    
    /**
     * Search for books by genre/subject
     *
     * @param string $genre        The genre/subject to search for
     * @param int    $max_results  Maximum number of results to return
     * @param string $order_by     How to order results (relevance, newest)
     * 
     * @return array Array of book details or empty array on failure
     */
    public function searchBooksByGenre(string $genre, int $max_results = 5, string $order_by = 'relevance'): array {
        if (empty($genre)) {
            return [];
        }

        // Limit max results (API limit is 40)
        $max_results = min(max(1, $max_results), 40);
        
        // Validate order_by
        $valid_order_by = ['relevance', 'newest'];
        if (!in_array($order_by, $valid_order_by)) {
            $order_by = 'relevance';
        }
        
        // Generate cache key
        $cache_key = $this->transient_prefix . 'genre_' . md5($genre . $max_results . $order_by);

        // Check if we have cached results
        $cached_result = get_transient($cache_key);
        if ($cached_result !== false) {
            return $cached_result;
        }

        // Build query parameters
        $query_params = [
            'q' => 'subject:"' . urlencode($genre) . '"',
            'maxResults' => $max_results,
            'orderBy' => $order_by,
            'printType' => 'books',
            'fields' => 'items(id,volumeInfo(title,authors,description,imageLinks(thumbnail),publishedDate,categories,industryIdentifiers,publisher))', 
        ];

        // Add API key if available
        if (!empty($this->api_key)) {
            $query_params['key'] = $this->api_key;
        }

        // Make API request
        $url = add_query_arg($query_params, $this->api_url);
        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        // Check for request errors
        if (is_wp_error($response)) {
            error_log('BookRec: Google Books API genre search failed: ' . $response->get_error_message());
            return [];
        }

        // Check response code
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('BookRec: Google Books API error in genre search. Status: ' . $response_code);
            return [];
        }

        // Parse response
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Check for decoding errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('BookRec: Failed to decode Google Books API genre search response. JSON error: ' . json_last_error_msg());
            return [];
        }

        // Check if we have valid results
        if (empty($data['items']) || !is_array($data['items'])) {
            return [];
        }

        // Process results
        $books = [];
        foreach ($data['items'] as $item) {
            $book = $this->processBookData($item);
            if ($book) {
                $books[] = $book;
            }
        }

        // Cache the result
        set_transient($cache_key, $books, $this->cache_ttl);

        return $books;
    }

    /**
     * Process book data from Google Books API into a standardized format
     *
     * @param array $item Google Books API item data
     * 
     * @return array|null Processed book data or null on invalid data
     */
    private function processBookData(array $item): ?array {
        if (empty($item['volumeInfo'])) {
            return null;
        }

        $volume_info = $item['volumeInfo'];

        // Extract ISBNs
        $isbn10 = null;
        $isbn13 = null;
        if (!empty($volume_info['industryIdentifiers']) && is_array($volume_info['industryIdentifiers'])) {
            foreach ($volume_info['industryIdentifiers'] as $identifier) {
                if (isset($identifier['type']) && isset($identifier['identifier'])) {
                    if ($identifier['type'] === 'ISBN_10') {
                        $isbn10 = $identifier['identifier'];
                    } elseif ($identifier['type'] === 'ISBN_13') {
                        $isbn13 = $identifier['identifier'];
                    }
                }
            }
        }

        // Create Amazon ASIN link if ISBN available
        $amazon_link = '';
        $asin = $isbn10 ?: $isbn13;
        if ($asin) {
            $tag = bookrec_get_config('amazon_tag', 'gooderead-20');
            $amazon_link = "https://www.amazon.com/dp/{$asin}?tag={$tag}";
        }

        // Build standardized book data
        return [
            'id' => $item['id'] ?? null,
            'title' => $volume_info['title'] ?? 'Unknown Title',
            'authors' => $volume_info['authors'] ?? ['Unknown Author'],
            'author_display' => isset($volume_info['authors']) ? implode(', ', $volume_info['authors']) : 'Unknown Author',
            'description' => $volume_info['description'] ?? '',
            'thumbnail' => $volume_info['imageLinks']['thumbnail'] ?? '',
            'published_date' => $volume_info['publishedDate'] ?? '',
            'publisher' => $volume_info['publisher'] ?? '',
            'categories' => $volume_info['categories'] ?? [],
            'page_count' => $volume_info['pageCount'] ?? null,
            'isbn10' => $isbn10,
            'isbn13' => $isbn13,
            'amazon_link' => $amazon_link,
        ];
    }

    /**
     * Clear all Google Books cache
     * 
     * @return int Number of transients deleted
     */
    public function clearCache(): int {
        global $wpdb;
        
        $count = 0;
        
        // Get all transients with our prefix
        $transients = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like('_transient_' . $this->transient_prefix) . '%'
            )
        );
        
        // Delete each transient
        foreach ($transients as $transient) {
            // Extract the transient name by removing the '_transient_' prefix
            $transient_name = str_replace('_transient_', '', $transient);
            if (delete_transient($transient_name)) {
                $count++;
            }
        }
        
        return $count;
    }

    /**
     * Set API key
     *
     * @param string $api_key The Google Books API key
     */
    public function setApiKey(string $api_key): void {
        $this->api_key = $api_key;
    }

    /**
     * Set cache TTL
     *
     * @param int $ttl Cache TTL in seconds
     */
    public function setCacheTTL(int $ttl): void {
        $this->cache_ttl = max(60, $ttl); // Minimum 60 seconds
    }
    
    /**
     * Log cache event for debugging
     * 
     * @param string $event Event type (hit, miss)
     * @param string $type  Cache type (title_author, isbn, search, author, genre)
     * @param string $key1  Primary key (title, isbn, query, author, genre)
     * @param string $key2  Secondary key (author) - optional
     */
    private function log_cache_event(string $event, string $type, string $key1, string $key2 = ''): void {
        if (!$this->debug_cache) {
            return;
        }
        
        $message = "BookRec Cache [{$event}] - Type: {$type}, ";
        
        switch ($type) {
            case 'title_author':
                $message .= "Title: {$key1}, Author: {$key2}";
                break;
            case 'isbn':
                $message .= "ISBN: {$key1}";
                break;
            case 'search':
                $message .= "Query: {$key1}";
                break;
            case 'author':
                $message .= "Author: {$key1}";
                break;
            case 'genre':
                $message .= "Genre: {$key1}";
                break;
            default:
                $message .= "Key1: {$key1}, Key2: {$key2}";
                break;
        }
        
        error_log($message);
    }
    
    /**
     * Get cached result or set cache after executing a callback
     * 
     * @param string   $cache_key Cache key
     * @param callable $callback  Function to call if cache miss
     * @param string   $event_type Type of cache event
     * @param string   $key1      Primary key for logging
     * @param string   $key2      Secondary key for logging
     * 
     * @return mixed|null Cached result or callback result or null
     */
    private function getCachedOrExecute(string $cache_key, callable $callback, string $event_type, string $key1, string $key2 = '') {
        // Check cache first
        $cached_result = get_transient($cache_key);
        if ($cached_result !== false) {
            $this->log_cache_event('hit', $event_type, $key1, $key2);
            return $cached_result;
        }
        
        // Log cache miss
        $this->log_cache_event('miss', $event_type, $key1, $key2);
        
        // Execute the callback to get fresh data
        $result = $callback();
        
        // Cache the result if not null
        if ($result !== null) {
            set_transient($cache_key, $result, $this->cache_ttl);
        }
        
        return $result;
    }

    /**
     * Check if API key is set
     *
     * @return bool True if API key is set
     */
    public function hasApiKey(): bool {
        return !empty($this->api_key);
    }
}