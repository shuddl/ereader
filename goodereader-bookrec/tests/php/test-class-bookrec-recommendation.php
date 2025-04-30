<?php
/**
 * Test for the BookRec_Recommendation class
 *
 * @package GoodEReader_BookRec
 */

namespace GoodEReader\BookRec\Tests;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Class Test_BookRec_Recommendation
 */
class Test_BookRec_Recommendation extends TestCase {

    /**
     * Set up function to initialize Brain Monkey
     */
    protected function setUp(): void {
        parent::setUp();
        \Brain\Monkey\setUp();
        
        // Mock bookrec_get_config to return test values
        Functions\when('bookrec_get_config')->justReturn('test-tag');
        
        // Mock error_log to prevent actual logging
        Functions\when('error_log')->justReturn(null);
        
        // Mock basic file functions
        Functions\when('file_exists')->justReturn(true);
        Functions\when('file_get_contents')->justReturn('[]');
        Functions\when('json_decode')->justReturn([]);
        Functions\when('json_last_error')->justReturn(JSON_ERROR_NONE);
        
        // Define a constant needed by the class
        if (!defined('BOOKREC_PLUGIN_DIR')) {
            define('BOOKREC_PLUGIN_DIR', '/path/to/plugin/');
        }
        
        // Create mock for GoogleBooks service
        $this->mockGoogleBooksService();
    }

    /**
     * Tear down function to clean up after Brain Monkey
     */
    protected function tearDown(): void {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }
    
    /**
     * Mock the BookRec_GoogleBooks_Service class
     */
    private function mockGoogleBooksService() {
        // Create a mock class for GoogleBooks service
        if (!class_exists('\BookRec_GoogleBooks_Service')) {
            eval('
                class BookRec_GoogleBooks_Service {
                    public function getBookDetailsByTitleAuthor($title, $author) {
                        return null;
                    }
                    public function searchBooks($query, $max_results = 5) {
                        return [];
                    }
                }
            ');
        }
    }

    /**
     * Test extractRecommendations with formatted books (bold format)
     */
    public function test_extractRecommendations_with_bold_format() {
        $llm_output = "Here are some great books:\n\n**The Hobbit by J.R.R. Tolkien**\n\n**Dune** by Frank Herbert\n\n**Foundation**";
        
        $recommendation = new \BookRec_Recommendation();
        $result = $recommendation->extractRecommendations($llm_output);
        
        $this->assertCount(3, $result);
        $this->assertEquals('The Hobbit', $result[0]['title']);
        $this->assertEquals('J.R.R. Tolkien', $result[0]['author']);
        $this->assertEquals('Dune', $result[1]['title']);
        $this->assertEquals('Frank Herbert', $result[1]['author']);
        $this->assertEquals('Foundation', $result[2]['title']);
        $this->assertEquals('', $result[2]['author']);
    }

    /**
     * Test extractRecommendations with quoted format
     */
    public function test_extractRecommendations_with_quoted_format() {
        $llm_output = "I recommend these books:\n\n\"The Great Gatsby\" by F. Scott Fitzgerald.\n\n'To Kill a Mockingbird' by Harper Lee,\n\n";
        
        $recommendation = new \BookRec_Recommendation();
        $result = $recommendation->extractRecommendations($llm_output);
        
        $this->assertCount(2, $result);
        $this->assertEquals('The Great Gatsby', $result[0]['title']);
        $this->assertEquals('F. Scott Fitzgerald', $result[0]['author']);
        $this->assertEquals('To Kill a Mockingbird', $result[1]['title']);
        $this->assertEquals('Harper Lee', $result[1]['author']);
    }

    /**
     * Test extractRecommendations with no recommendations
     */
    public function test_extractRecommendations_with_no_recommendations() {
        $llm_output = "I don't have any recommendations for you at this time.";
        
        $recommendation = new \BookRec_Recommendation();
        $result = $recommendation->extractRecommendations($llm_output);
        
        $this->assertCount(0, $result);
    }

    /**
     * Test enhanceRecommendations with empty recommendations
     */
    public function test_enhanceRecommendations_with_empty_input() {
        $recommendation = new \BookRec_Recommendation();
        $result = $recommendation->enhanceRecommendations([]);
        
        $this->assertEmpty($result);
    }

    /**
     * Test enhanceRecommendations with Google Books data
     */
    public function test_enhanceRecommendations_with_google_books_data() {
        // Create a test recommendation
        $testRec = [
            'title' => 'Test Book',
            'author' => 'Test Author',
            'description' => '',
            'cover_url' => '',
            'amazon_link' => '',
            'source' => 'ai',
        ];
        
        // Mock Google Books data
        $googleBooksData = [
            'title' => 'Test Book Enhanced',
            'author_display' => 'Test Author',
            'description' => 'This is a test book description.',
            'thumbnail' => 'http://example.com/cover.jpg',
            'published_date' => '2023',
            'publisher' => 'Test Publisher',
            'categories' => ['Fiction'],
            'isbn10' => '1234567890',
            'isbn13' => '9781234567890',
            'id' => 'test123',
            'amazon_link' => 'https://www.amazon.com/dp/1234567890?tag=test-tag',
        ];
        
        // Create mock GoogleBooks service with defined behavior
        $googleBooksMock = $this->getMockBuilder('\BookRec_GoogleBooks_Service')
            ->disableOriginalConstructor()
            ->getMock();
        
        $googleBooksMock->method('getBookDetailsByTitleAuthor')
            ->willReturn($googleBooksData);
        
        // Replace the original service with our mock
        $reflectionClass = new \ReflectionClass(\BookRec_Recommendation::class);
        $reflectionProperty = $reflectionClass->getProperty('googlebooks');
        $reflectionProperty->setAccessible(true);
        
        $recommendation = new \BookRec_Recommendation();
        $reflectionProperty->setValue($recommendation, $googleBooksMock);
        
        // Test the method
        $result = $recommendation->enhanceRecommendations([$testRec]);
        
        $this->assertCount(1, $result);
        $this->assertEquals('Test Book Enhanced', $result[0]['title']);
        $this->assertEquals('Test Author', $result[0]['author']);
        $this->assertEquals('This is a test book description.', $result[0]['description']);
        $this->assertEquals('http://example.com/cover.jpg', $result[0]['cover_url']);
        $this->assertEquals('https://www.amazon.com/dp/1234567890?tag=test-tag', $result[0]['amazon_link']);
        $this->assertEquals('google_books', $result[0]['source']);
    }

    /**
     * Test enhanceRecommendations with fallback data
     */
    public function test_enhanceRecommendations_with_fallback_data() {
        // Create a test recommendation
        $testRec = [
            'title' => 'Test Book',
            'author' => 'Test Author',
            'description' => '',
            'cover_url' => '',
            'amazon_link' => '',
            'source' => 'ai',
        ];
        
        // Set up fallback data
        $fallbackData = [
            [
                'title' => 'Test Book',
                'author' => 'Test Author',
                'description' => 'Fallback description',
                'cover_url' => 'http://example.com/fallback.jpg',
                'amazon_search_query' => 'test+book+author',
            ]
        ];
        
        // Mock Google Books to return null (no match)
        $googleBooksMock = $this->getMockBuilder('\BookRec_GoogleBooks_Service')
            ->disableOriginalConstructor()
            ->getMock();
        
        $googleBooksMock->method('getBookDetailsByTitleAuthor')
            ->willReturn(null);
        
        $googleBooksMock->method('searchBooks')
            ->willReturn([]);
        
        // Replace services with mocks and set fallback data
        $reflectionClass = new \ReflectionClass(\BookRec_Recommendation::class);
        
        $reflectionGoogleBooks = $reflectionClass->getProperty('googlebooks');
        $reflectionGoogleBooks->setAccessible(true);
        
        $reflectionFallback = $reflectionClass->getProperty('fallback_data');
        $reflectionFallback->setAccessible(true);
        
        $recommendation = new \BookRec_Recommendation();
        $reflectionGoogleBooks->setValue($recommendation, $googleBooksMock);
        $reflectionFallback->setValue($recommendation, $fallbackData);
        
        // Test the method
        $result = $recommendation->enhanceRecommendations([$testRec]);
        
        $this->assertCount(1, $result);
        $this->assertEquals('Test Book', $result[0]['title']);
        $this->assertEquals('Test Author', $result[0]['author']);
        $this->assertEquals('Fallback description', $result[0]['description']);
        $this->assertEquals('http://example.com/fallback.jpg', $result[0]['cover_url']);
        $this->assertStringContainsString('amazon.com/s?k=test+book+author', $result[0]['amazon_link']);
        $this->assertStringContainsString('tag=test-tag', $result[0]['amazon_link']);
        $this->assertEquals('fallback_json', $result[0]['source']);
    }

    /**
     * Test enhanceRecommendations with no data (generates Amazon search link)
     */
    public function test_enhanceRecommendations_with_no_data() {
        // Create a test recommendation
        $testRec = [
            'title' => 'Unknown Book',
            'author' => 'Unknown Author',
            'description' => '',
            'cover_url' => '',
            'amazon_link' => '',
            'source' => 'ai',
        ];
        
        // Mock Google Books to return null (no match)
        $googleBooksMock = $this->getMockBuilder('\BookRec_GoogleBooks_Service')
            ->disableOriginalConstructor()
            ->getMock();
        
        $googleBooksMock->method('getBookDetailsByTitleAuthor')
            ->willReturn(null);
        
        $googleBooksMock->method('searchBooks')
            ->willReturn([]);
        
        // Mock urlencode for Amazon link generation
        Functions\when('urlencode')->returnArg(0);
        
        // Replace Google Books service with mock
        $reflectionClass = new \ReflectionClass(\BookRec_Recommendation::class);
        $reflectionProperty = $reflectionClass->getProperty('googlebooks');
        $reflectionProperty->setAccessible(true);
        
        $recommendation = new \BookRec_Recommendation();
        $reflectionProperty->setValue($recommendation, $googleBooksMock);
        
        // Test the method
        $result = $recommendation->enhanceRecommendations([$testRec]);
        
        $this->assertCount(1, $result);
        $this->assertEquals('Unknown Book', $result[0]['title']);
        $this->assertEquals('Unknown Author', $result[0]['author']);
        $this->assertStringContainsString('amazon.com/s?k=Unknown Book Unknown Author', $result[0]['amazon_link']);
        $this->assertStringContainsString('tag=test-tag', $result[0]['amazon_link']);
        $this->assertEquals('ai', $result[0]['source']);
    }

    /**
     * Test processRecommendations with valid input
     */
    public function test_processRecommendations() {
        $llm_output = "Here are some recommendations:\n\n**Dune by Frank Herbert**";
        
        // Mock Google Books data
        $googleBooksData = [
            'title' => 'Dune',
            'author_display' => 'Frank Herbert',
            'description' => 'A science fiction masterpiece.',
            'thumbnail' => 'http://example.com/dune.jpg',
            'published_date' => '1965',
            'publisher' => 'Chilton Books',
            'categories' => ['Science Fiction'],
            'isbn10' => '0441172717',
            'isbn13' => '9780441172719',
            'id' => 'dune123',
            'amazon_link' => 'https://www.amazon.com/dp/0441172717?tag=test-tag',
        ];
        
        // Create mock GoogleBooks service
        $googleBooksMock = $this->getMockBuilder('\BookRec_GoogleBooks_Service')
            ->disableOriginalConstructor()
            ->getMock();
        
        $googleBooksMock->method('getBookDetailsByTitleAuthor')
            ->willReturn($googleBooksData);
        
        // Replace GoogleBooks service with mock
        $reflectionClass = new \ReflectionClass(\BookRec_Recommendation::class);
        $reflectionProperty = $reflectionClass->getProperty('googlebooks');
        $reflectionProperty->setAccessible(true);
        
        $recommendation = new \BookRec_Recommendation();
        $reflectionProperty->setValue($recommendation, $googleBooksMock);
        
        // Test with no trending books (include_trending = 0)
        $result = $recommendation->processRecommendations($llm_output, 0);
        
        $this->assertCount(1, $result);
        $this->assertEquals('Dune', $result[0]['title']);
        $this->assertEquals('Frank Herbert', $result[0]['author']);
        $this->assertEquals('https://www.amazon.com/dp/0441172717?tag=test-tag', $result[0]['amazon_link']);
    }

    /**
     * Test extractExplanatoryText finds introductory paragraph
     */
    public function test_extractExplanatoryText_finds_intro() {
        $llm_output = "Based on your interest in fantasy novels, here are five books you might enjoy.\n\n**The Hobbit by J.R.R. Tolkien**\n\nA classic adventure story.";
        
        $recommendation = new \BookRec_Recommendation();
        $result = $recommendation->extractExplanatoryText($llm_output);
        
        $this->assertEquals('Based on your interest in fantasy novels, here are five books you might enjoy.', $result);
    }

    /**
     * Test extractExplanatoryText returns empty when no intro paragraph
     */
    public function test_extractExplanatoryText_with_no_intro() {
        $llm_output = "**The Hobbit by J.R.R. Tolkien**\n\nA classic adventure story.";
        
        $recommendation = new \BookRec_Recommendation();
        $result = $recommendation->extractExplanatoryText($llm_output);
        
        $this->assertEquals('', $result);
    }

    /**
     * Test extractConcludingQuestion finds question at end
     */
    public function test_extractConcludingQuestion_finds_question() {
        $llm_output = "**The Hobbit by J.R.R. Tolkien**\n\nA classic adventure story.\n\nWould you like more recommendations in this genre?";
        
        $recommendation = new \BookRec_Recommendation();
        $result = $recommendation->extractConcludingQuestion($llm_output);
        
        $this->assertEquals('Would you like more recommendations in this genre?', $result);
    }

    /**
     * Test formatForDisplay creates frontend-friendly format
     */
    public function test_formatForDisplay() {
        $recommendations = [
            [
                'title' => 'Test Book',
                'author' => 'Test Author',
                'description' => 'Test description',
                'cover_url' => 'http://example.com/cover.jpg',
                'amazon_link' => 'https://www.amazon.com/dp/1234567890?tag=test-tag',
                'published_date' => '2023',
                'publisher' => 'Test Publisher',
                'isbn13' => '9781234567890',
                'source' => 'google_books',
            ]
        ];
        
        $recommendation = new \BookRec_Recommendation();
        $result = $recommendation->formatForDisplay($recommendations);
        
        $this->assertCount(1, $result);
        $this->assertEquals('Test Book', $result[0]['title']);
        $this->assertEquals('Test Author', $result[0]['author']);
        $this->assertEquals('Test description', $result[0]['description']);
        $this->assertEquals('http://example.com/cover.jpg', $result[0]['coverUrl']);
        $this->assertEquals('https://www.amazon.com/dp/1234567890?tag=test-tag', $result[0]['amazonLink']);
        $this->assertEquals('2023', $result[0]['publishDate']);
        $this->assertEquals('Test Publisher', $result[0]['publisher']);
        $this->assertEquals('9781234567890', $result[0]['isbn']);
        $this->assertEquals('google_books', $result[0]['source']);
    }

    /**
     * Test find_fallback_book with exact match
     */
    public function test_find_fallback_book_exact_match() {
        // Set up fallback data
        $fallbackData = [
            [
                'title' => 'Exact Match Book',
                'author' => 'Exact Author',
                'description' => 'Fallback description',
            ],
            [
                'title' => 'Another Book',
                'author' => 'Another Author',
            ]
        ];
        
        // Set fallback data using reflection
        $reflectionClass = new \ReflectionClass(\BookRec_Recommendation::class);
        $reflectionProperty = $reflectionClass->getProperty('fallback_data');
        $reflectionProperty->setAccessible(true);
        
        $recommendation = new \BookRec_Recommendation();
        $reflectionProperty->setValue($recommendation, $fallbackData);
        
        // Access private method using reflection
        $reflectionMethod = $reflectionClass->getMethod('find_fallback_book');
        $reflectionMethod->setAccessible(true);
        
        // Test exact match
        $result = $reflectionMethod->invoke($recommendation, 'Exact Match Book', 'Exact Author');
        
        $this->assertNotNull($result);
        $this->assertEquals('Exact Match Book', $result['title']);
        $this->assertEquals('Exact Author', $result['author']);
    }

    /**
     * Test find_fallback_book with fuzzy match
     */
    public function test_find_fallback_book_fuzzy_match() {
        // Set up fallback data
        $fallbackData = [
            [
                'title' => 'The Complete Guide to Something',
                'author' => 'Expert Author',
                'description' => 'Fallback description',
            ]
        ];
        
        // Set fallback data using reflection
        $reflectionClass = new \ReflectionClass(\BookRec_Recommendation::class);
        $reflectionProperty = $reflectionClass->getProperty('fallback_data');
        $reflectionProperty->setAccessible(true);
        
        $recommendation = new \BookRec_Recommendation();
        $reflectionProperty->setValue($recommendation, $fallbackData);
        
        // Access private method using reflection
        $reflectionMethod = $reflectionClass->getMethod('find_fallback_book');
        $reflectionMethod->setAccessible(true);
        
        // Test fuzzy match (partial title match)
        $result = $reflectionMethod->invoke($recommendation, 'Guide to Something', 'Expert Author');
        
        $this->assertNotNull($result);
        $this->assertEquals('The Complete Guide to Something', $result['title']);
    }

    /**
     * Test extract_likely_genre determines genre from keywords
     */
    public function test_extract_likely_genre() {
        $reflectionClass = new \ReflectionClass(\BookRec_Recommendation::class);
        $reflectionMethod = $reflectionClass->getMethod('extract_likely_genre');
        $reflectionMethod->setAccessible(true);
        
        $recommendation = new \BookRec_Recommendation();
        
        // Test fantasy detection
        $fantasy = $reflectionMethod->invoke($recommendation, 'The Dragon Kingdom', 'Fantasy Author');
        $this->assertEquals('Fantasy', $fantasy);
        
        // Test sci-fi detection
        $scifi = $reflectionMethod->invoke($recommendation, 'Galaxy Space Wars', 'Sci-Fi Author');
        $this->assertEquals('Science Fiction', $scifi);
        
        // Test mystery detection
        $mystery = $reflectionMethod->invoke($recommendation, 'The Murder Mystery', 'Mystery Author');
        $this->assertEquals('Mystery', $mystery);
        
        // Test default to fiction
        $fiction = $reflectionMethod->invoke($recommendation, 'Generic Title', 'Generic Author');
        $this->assertEquals('Fiction', $fiction);
    }
}