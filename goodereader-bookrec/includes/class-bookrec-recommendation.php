<?php
/**
 * Book Recommender Recommendation Helper Class
 *
 * @package   Good E-Reader Book Recommender
 */

declare(strict_types=1);

if (!defined('WPINC')) {
    die;
}

/**
 * Class BookRec_Recommendation
 * 
 * Handles formating and enhancing book recommendations with metadata
 */
class BookRec_Recommendation {
    
    /**
     * Google Books Service instance
     *
     * @var BookRec_GoogleBooks_Service
     */
    private BookRec_GoogleBooks_Service $googlebooks;
    
    /**
     * Amazon affiliate tag
     *
     * @var string
     */
    private string $amazon_tag;
    
    /**
     * Fallback book data
     *
     * @var array
     */
    private array $fallback_data = [];
    
    /**
     * Trends service instance
     *
     * @var BookRec_Trends_Service|null
     */
    private ?BookRec_Trends_Service $trends_service = null;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->googlebooks = new BookRec_GoogleBooks_Service();
        $this->amazon_tag = bookrec_get_config('amazon_tag', 'gooderead-20');
        $this->load_fallback_data();
        
        // Initialize trends service if the class exists
        if (class_exists('BookRec_Trends_Service')) {
            $this->trends_service = new BookRec_Trends_Service();
        }
    }
    
    /**
     * Load fallback book data from JSON file
     */
    private function load_fallback_data(): void {
        $fallback_file = BOOKREC_PLUGIN_DIR . 'data/top100_goodereader.json';
        
        if (file_exists($fallback_file)) {
            $json_data = file_get_contents($fallback_file);
            if ($json_data) {
                $data = json_decode($json_data, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                    $this->fallback_data = $data;
                } else {
                    error_log('BookRec: Error parsing fallback book data JSON: ' . json_last_error_msg());
                }
            } else {
                error_log('BookRec: Could not read fallback book data file');
            }
        } else {
            error_log('BookRec: Fallback book data file not found: ' . $fallback_file);
        }
    }
    
    /**
     * Extract book recommendations from LLM output
     * 
     * @param string $llm_output The raw output from OpenAI
     * 
     * @return array Array of book recommendation objects
     */
    public function extractRecommendations(string $llm_output): array {
        $recommendations = [];
        
        // Extract book titles and authors using regex
        // This pattern looks for bold text which typically indicates title/author
        // We're matching patterns like "**Title by Author**" or "**Title** by Author"
        preg_match_all('/\*\*(.*?)\*\*(?:\s+by\s+(.*?)(?:\n|\.|,|$)|)/', $llm_output, $matches, PREG_SET_ORDER);
        
        // If we don't find any formatted recommendations, try a more general approach
        if (empty($matches)) {
            preg_match_all('/(["\'])(.*?)\1\s+by\s+(.*?)(?:\n|\.|,|$)/', $llm_output, $matches, PREG_SET_ORDER);
        }
        
        // Process matches
        foreach ($matches as $match) {
            // First pattern: **Title by Author** or **Title** by Author
            if (isset($match[1])) {
                $title_author = $match[1];
                
                // Check if "by" is in the bold text, indicating "Title by Author" format
                if (strpos($title_author, ' by ') !== false) {
                    list($title, $author) = explode(' by ', $title_author, 2);
                } else {
                    // Otherwise it's just the title, and author may be in second capture group
                    $title = $title_author;
                    $author = $match[2] ?? '';
                }
            } 
            // Second pattern: "Title" by Author
            else if (isset($match[2]) && isset($match[3])) {
                $title = $match[2];
                $author = $match[3];
            } else {
                continue; // Skip if pattern doesn't match expected format
            }
            
            // Clean up extracted data
            $title = trim($title);
            $author = trim($author);
            
            // Skip if no title
            if (empty($title)) {
                continue;
            }
            
            // Create basic recommendation
            $recommendation = [
                'title' => $title,
                'author' => $author,
                'description' => '', // Will be populated from Google Books
                'cover_url' => '', // Will be populated from Google Books
                'amazon_link' => '',
                'source' => 'ai',
            ];
            
            $recommendations[] = $recommendation;
        }
        
        return $recommendations;
    }
    
    /**
     * Enhance recommendations with metadata from Google Books
     * 
     * @param array $recommendations Array of basic recommendations
     * 
     * @return array Enhanced recommendations
     */
    public function enhanceRecommendations(array $recommendations): array {
        if (empty($recommendations)) {
            return [];
        }
        
        $enhanced = [];
        
        foreach ($recommendations as $rec) {
            $title = $rec['title'] ?? '';
            $author = $rec['author'] ?? '';
            
            // Skip if no title
            if (empty($title)) {
                continue;
            }
            
            // Get book details from Google Books
            $book_data = null;
            if (!empty($author)) {
                $book_data = $this->googlebooks->getBookDetailsByTitleAuthor($title, $author);
            } else {
                // Try search with just title if no author
                $search_results = $this->googlebooks->searchBooks("intitle:\"$title\"", 1);
                $book_data = $search_results[0] ?? null;
            }
            
            // If no Google Books data, try fallback data or keep original recommendation
            if (empty($book_data)) {
                // Try to find a match in fallback data
                $fallback_book = $this->find_fallback_book($title, $author);
                
                if (!empty($fallback_book)) {
                    // Use fallback book data
                    $fallback_rec = [
                        'title' => $fallback_book['title'],
                        'author' => $fallback_book['author'],
                        'description' => $fallback_book['description'] ?? $rec['description'] ?? '',
                        'cover_url' => $fallback_book['cover_url'] ?? $rec['cover_url'] ?? '',
                        'amazon_link' => '',
                        'source' => 'fallback_json',
                    ];
                    
                    // Generate Amazon link using search query from fallback data or based on title/author
                    $search_term = $fallback_book['amazon_search_query'] ?? urlencode("{$fallback_rec['title']} {$fallback_rec['author']}");
                    $fallback_rec['amazon_link'] = "https://www.amazon.com/s?k={$search_term}&tag={$this->amazon_tag}";
                    
                    $enhanced[] = $fallback_rec;
                    continue;
                }
                
                // If no fallback match, keep original recommendation
                // Generate Amazon link for title/author if not provided
                if (empty($rec['amazon_link'])) {
                    $search_term = urlencode("$title $author");
                    $rec['amazon_link'] = "https://www.amazon.com/s?k={$search_term}&tag={$this->amazon_tag}";
                }
                
                $enhanced[] = $rec;
                continue;
            }
            
            // Enhance the recommendation with Google Books data
            $enhanced_rec = [
                'title' => $book_data['title'] ?? $title,
                'author' => $book_data['author_display'] ?? $author,
                'description' => $book_data['description'] ?? $rec['description'] ?? '',
                'cover_url' => $book_data['thumbnail'] ?? $rec['cover_url'] ?? '',
                'published_date' => $book_data['published_date'] ?? '',
                'publisher' => $book_data['publisher'] ?? '',
                'categories' => $book_data['categories'] ?? [],
                'isbn10' => $book_data['isbn10'] ?? '',
                'isbn13' => $book_data['isbn13'] ?? '',
                'amazon_link' => $book_data['amazon_link'] ?? $rec['amazon_link'] ?? '',
                'google_id' => $book_data['id'] ?? '',
                'source' => 'google_books',
            ];
            
            // If still no Amazon link, create one based on search
            if (empty($enhanced_rec['amazon_link'])) {
                $search_term = urlencode("{$enhanced_rec['title']} {$enhanced_rec['author']}");
                $enhanced_rec['amazon_link'] = "https://www.amazon.com/s?k={$search_term}&tag={$this->amazon_tag}";
            }
            
            // Format the description (truncate if needed)
            if (!empty($enhanced_rec['description']) && strlen($enhanced_rec['description']) > 300) {
                $enhanced_rec['description'] = substr($enhanced_rec['description'], 0, 297) . '...';
            }
            
            $enhanced[] = $enhanced_rec;
        }
        
        return $enhanced;
    }
    
    /**
     * Process recommendations from raw LLM output
     * 
     * @param string $llm_output Raw output from OpenAI
     * @param int    $include_trending Number of trending books to include (0 to disable)
     * 
     * @return array Processed recommendations
     */
    public function processRecommendations(string $llm_output, int $include_trending = 1): array {
        // Extract basic recommendations
        $basic_recommendations = $this->extractRecommendations($llm_output);
        
        // Enhance with metadata
        $enhanced_recommendations = $this->enhanceRecommendations($basic_recommendations);
        
        // Include trending books if requested
        if ($include_trending > 0 && $this->trends_service !== null) {
            $enhanced_recommendations = $this->include_trending_books($enhanced_recommendations, $include_trending);
        }
        
        return $enhanced_recommendations;
    }
    
    /**
     * Extract explanatory text from LLM output
     * 
     * @param string $llm_output Raw output from OpenAI
     * 
     * @return string Explanatory text
     */
    public function extractExplanatoryText(string $llm_output): string {
        // Find the first paragraph before book recommendations
        $paragraphs = explode("\n\n", $llm_output);
        
        // Get the first paragraph if it doesn't look like a book recommendation
        if (!empty($paragraphs[0]) && 
            !preg_match('/\*\*.*?\*\*/', $paragraphs[0]) && 
            !preg_match('/https?:\/\//', $paragraphs[0])) {
            return $paragraphs[0];
        }
        
        return '';
    }
    
    /**
     * Extract concluding question from LLM output
     * 
     * @param string $llm_output Raw output from OpenAI
     * 
     * @return string Concluding question
     */
    public function extractConcludingQuestion(string $llm_output): string {
        // Look for a question at the end of the output
        $paragraphs = array_filter(explode("\n\n", $llm_output));
        $last_paragraph = end($paragraphs);
        
        // Check if last paragraph contains a question mark and doesn't have a link
        if (!empty($last_paragraph) && 
            strpos($last_paragraph, '?') !== false && 
            !preg_match('/https?:\/\//', $last_paragraph)) {
            return $last_paragraph;
        }
        
        return '';
    }
    
    /**
     * Find a matching book in the fallback data
     *
     * @param string $title  Book title to find
     * @param string $author Book author to find
     * 
     * @return array|null Matching book data or null if not found
     */
    private function find_fallback_book(string $title, string $author): ?array {
        if (empty($this->fallback_data)) {
            return null;
        }
        
        // Clean and lowercase for comparison
        $title_clean = strtolower(trim($title));
        $author_clean = strtolower(trim($author));
        
        // Try exact match first (both title and author)
        foreach ($this->fallback_data as $book) {
            $fb_title = strtolower(trim($book['title'] ?? ''));
            $fb_author = strtolower(trim($book['author'] ?? ''));
            
            // If we have an exact match on both title and author
            if ($title_clean === $fb_title && (!empty($author_clean) && $author_clean === $fb_author)) {
                return $book;
            }
        }
        
        // Try just title match if author is empty or no exact match was found
        if (empty($author_clean)) {
            foreach ($this->fallback_data as $book) {
                $fb_title = strtolower(trim($book['title'] ?? ''));
                
                // If we have an exact match on title
                if ($title_clean === $fb_title) {
                    return $book;
                }
            }
        }
        
        // Try fuzzy matching (title contains or author contains)
        foreach ($this->fallback_data as $book) {
            $fb_title = strtolower(trim($book['title'] ?? ''));
            $fb_author = strtolower(trim($book['author'] ?? ''));
            
            // If title contains the search title or vice versa
            $title_match = strpos($fb_title, $title_clean) !== false || strpos($title_clean, $fb_title) !== false;
            
            // If author contains the search author or vice versa
            $author_match = empty($author_clean) || 
                            strpos($fb_author, $author_clean) !== false || 
                            strpos($author_clean, $fb_author) !== false;
            
            if ($title_match && $author_match) {
                return $book;
            }
        }
        
        // Try genre-based recommendation if we have a genre
        $genre = $this->extract_likely_genre($title, $author);
        if (!empty($genre)) {
            $genre_matches = [];
            
            foreach ($this->fallback_data as $book) {
                if (isset($book['genre']) && strtolower($book['genre']) === strtolower($genre)) {
                    $genre_matches[] = $book;
                }
            }
            
            // Return a random book from the genre if we found some
            if (!empty($genre_matches)) {
                return $genre_matches[array_rand($genre_matches)];
            }
        }
        
        return null;
    }
    
    /**
     * Extract likely genre from title/author or conversation context
     *
     * @param string $title  Book title
     * @param string $author Book author
     * 
     * @return string|null Likely genre or null if unknown
     */
    private function extract_likely_genre(string $title, string $author): ?string {
        // This is a simplified version - would be enhanced with NLP in a real implementation
        $fantasy_keywords = ['dragon', 'magic', 'wizard', 'sword', 'spell', 'kingdom', 'quest', 'elf', 'dwarf', 'fairy'];
        $scifi_keywords = ['space', 'alien', 'robot', 'future', 'galaxy', 'planet', 'star', 'cyber', 'tech'];
        $mystery_keywords = ['mystery', 'detective', 'crime', 'murder', 'case', 'suspect', 'clue', 'investigation'];
        
        $title_lower = strtolower($title);
        
        foreach ($fantasy_keywords as $keyword) {
            if (strpos($title_lower, $keyword) !== false) {
                return 'Fantasy';
            }
        }
        
        foreach ($scifi_keywords as $keyword) {
            if (strpos($title_lower, $keyword) !== false) {
                return 'Science Fiction';
            }
        }
        
        foreach ($mystery_keywords as $keyword) {
            if (strpos($title_lower, $keyword) !== false) {
                return 'Mystery';
            }
        }
        
        // Default to Fiction if no other match
        return 'Fiction';
    }
    
    /**
     * Format recommendations for display
     * 
     * @param array $recommendations Enhanced recommendations
     * 
     * @return array Recommendations ready for frontend display
     */
    public function formatForDisplay(array $recommendations): array {
        $formatted = [];
        
        foreach ($recommendations as $rec) {
            $formatted[] = [
                'title' => $rec['title'],
                'author' => $rec['author'],
                'description' => $rec['description'],
                'coverUrl' => $rec['cover_url'],
                'amazonLink' => $rec['amazon_link'],
                'publishDate' => $rec['published_date'] ?? '',
                'publisher' => $rec['publisher'] ?? '',
                'isbn' => $rec['isbn13'] ?? $rec['isbn10'] ?? '',
                'source' => $rec['source'] ?? 'unknown',
            ];
        }
        
        return $formatted;
    }
    
    /**
     * Get fallback book data
     * 
     * @return array Fallback book data
     */
    public function get_fallback_data(): array {
        return $this->fallback_data;
    }
    
    /**
     * Get trending books
     * 
     * @param int $count Number of trending books to retrieve
     * @return array Array of trending books formatted for display
     */
    public function get_trending_books(int $count = 5): array {
        // Check if trends service is available
        if ($this->trends_service === null) {
            return [];
        }
        
        // Get trending books from service
        $trending = $this->trends_service->get_trends();
        
        if (empty($trending)) {
            return [];
        }
        
        // Limit to requested count
        $trending = array_slice($trending, 0, $count);
        
        // Enhance with metadata
        $enhanced = $this->enhanceRecommendations($trending);
        
        // Format for display
        return $this->formatForDisplay($enhanced);
    }
    
    /**
     * Include trending books in recommendations
     * 
     * @param array $recommendations Current recommendations
     * @param int   $count          Number of trending books to include (0 to disable)
     * @param bool  $replace        Whether to replace existing recommendations or add to them
     * 
     * @return array Modified recommendations with trending books included
     */
    public function include_trending_books(array $recommendations, int $count = 1, bool $replace = false): array {
        // If count is 0 or trending service isn't available, return original recommendations
        if ($count <= 0 || $this->trends_service === null) {
            return $recommendations;
        }
        
        // Get trending books
        $trending = $this->get_trending_books($count);
        
        // If no trending books, return original recommendations
        if (empty($trending)) {
            return $recommendations;
        }
        
        // If replacing recommendations and we have enough trending books
        if ($replace && count($trending) >= $count) {
            return $trending;
        }
        
        // Otherwise, add trending books to recommendations
        foreach ($trending as $trend) {
            // Mark as trending
            $trend['trending'] = true;
            
            // Add to recommendations if not already included
            $exists = false;
            foreach ($recommendations as $rec) {
                if ($rec['title'] === $trend['title'] && $rec['author'] === $trend['author']) {
                    $exists = true;
                    break;
                }
            }
            
            if (!$exists) {
                $recommendations[] = $trend;
            }
        }
        
        return $recommendations;
    }
}