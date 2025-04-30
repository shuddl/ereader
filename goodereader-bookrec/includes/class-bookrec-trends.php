<?php
/**
 * Book Recommender Trends Service Class
 *
 * @package Good E-Reader Book Recommender
 */

declare(strict_types=1);

if (!defined('WPINC')) {
    die;
}

/**
 * Class BookRec_Trends_Service
 * Fetches trending book data from external sources
 */
class BookRec_Trends_Service {
    
    /**
     * Transient name for storing trend data
     */
    const TRENDS_TRANSIENT = 'bookrec_trends_data';
    
    /**
     * Time-to-live for trends data cache in seconds (12 hours)
     */
    const TRENDS_TTL = 43200; // 12 * 60 * 60
    
    /**
     * Source URL for BookTok trending books
     */
    private string $booktok_source_url = 'https://www.goodreads.com/shelf/show/booktok';
    
    /**
     * User agent string for requests
     */
    private string $user_agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';
    
    /**
     * Get trending books
     *
     * @return array Array of trending books with title and author
     */
    public function get_trends(): array {
        // Try to get cached trends first
        $cached_trends = get_transient(self::TRENDS_TRANSIENT);
        
        if (false !== $cached_trends && is_array($cached_trends)) {
            return $cached_trends;
        }
        
        // No valid cache, try to fetch fresh data
        $fresh_trends = $this->fetch_booktok_trends();
        
        if (!empty($fresh_trends)) {
            return $fresh_trends;
        }
        
        // If both cache and fetch fail, return empty array
        return [];
    }
    
    /**
     * Fetch trending books from BookTok source
     *
     * @return array Array of trending books
     */
    public function fetch_booktok_trends(): array {
        // Initialize empty trend array
        $trends = [];
        
        // Request the trending page
        $response = wp_remote_get(
            $this->booktok_source_url,
            [
                'timeout' => 15,
                'user-agent' => $this->user_agent,
            ]
        );
        
        // Check for errors
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log("BookRec Trends: Failed to fetch trends - {$error_message}");
            return $trends;
        }
        
        // Check response code
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log("BookRec Trends: Bad response code - {$response_code}");
            return $trends;
        }
        
        // Get response body
        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            error_log('BookRec Trends: Empty response body');
            return $trends;
        }
        
        // Process the HTML using DOMDocument
        libxml_use_internal_errors(true); // Suppress HTML5 parsing errors
        $dom = new DOMDocument();
        $dom->loadHTML($body);
        libxml_clear_errors();
        
        // Create XPath for easier parsing
        $xpath = new DOMXPath($dom);
        
        // Find book elements
        // This XPath query targets Goodreads book elements on the shelf page
        $book_elements = $xpath->query('//div[contains(@class, "elementList")]');
        
        if ($book_elements && $book_elements->length > 0) {
            $count = 0;
            
            foreach ($book_elements as $element) {
                // Limit to top 10 trending books
                if ($count >= 10) {
                    break;
                }
                
                // Extract title
                $title_element = $xpath->query('.//a[@class="bookTitle"]', $element)->item(0);
                $title = $title_element ? trim($title_element->textContent) : null;
                
                // Extract author
                $author_element = $xpath->query('.//a[@class="authorName"]', $element)->item(0);
                $author = $author_element ? trim($author_element->textContent) : null;
                
                // Only add if we have both title and author
                if ($title && $author) {
                    $trends[] = [
                        'title' => $title,
                        'author' => $author,
                        'source' => 'goodreads_booktok',
                        'timestamp' => time(),
                    ];
                    $count++;
                }
            }
        }
        
        // If we successfully scraped trends, cache them
        if (!empty($trends)) {
            set_transient(self::TRENDS_TRANSIENT, $trends, self::TRENDS_TTL);
            error_log('BookRec Trends: Successfully fetched and cached ' . count($trends) . ' trending books');
        } else {
            error_log('BookRec Trends: Failed to extract book data from HTML');
        }
        
        return $trends;
    }
    
    /**
     * Force refresh trend data
     *
     * @return bool Success status
     */
    public function refresh_trends(): bool {
        // Delete current cache
        delete_transient(self::TRENDS_TRANSIENT);
        
        // Fetch fresh data
        $trends = $this->fetch_booktok_trends();
        
        // Return success status
        return !empty($trends);
    }
    
    /**
     * Get trend source URL
     *
     * @return string Current trend source URL
     */
    public function get_source_url(): string {
        return $this->booktok_source_url;
    }
    
    /**
     * Set trend source URL
     *
     * @param string $url New trend source URL
     * @return void
     */
    public function set_source_url(string $url): void {
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            $this->booktok_source_url = $url;
        }
    }
    
    /**
     * Get the last fetch timestamp
     *
     * @return int|null Timestamp of last fetch or null if never fetched
     */
    public function get_last_fetch_time(): ?int {
        $trends = get_transient(self::TRENDS_TRANSIENT);
        
        if (is_array($trends) && !empty($trends) && isset($trends[0]['timestamp'])) {
            return (int) $trends[0]['timestamp'];
        }
        
        return null;
    }
    
    /**
     * Log message to WordPress error log
     *
     * @param string $message Message to log
     * @return void
     */
    private function log_message(string $message): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("BookRec Trends: {$message}");
        }
    }
}