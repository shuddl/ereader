<?php
/**
 * Test for the BookRec_GoogleBooks_Service class
 *
 * @package GoodEReader_BookRec
 */

namespace GoodEReader\BookRec\Tests;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Class Test_BookRec_GoogleBooks
 */
class Test_BookRec_GoogleBooks extends TestCase {

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

        // Mock WordPress transient functions
        Functions\when('get_transient')->justReturn(false);
        Functions\when('set_transient')->justReturn(true);
        Functions\when('delete_transient')->justReturn(true);
    }

    /**
     * Tear down function to clean up after Brain Monkey
     */
    protected function tearDown(): void {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Test constructor with direct API key and cache TTL
     */
    public function test_constructor_with_direct_config() {
        $config = [
            'api_key' => 'test_api_key',
            'cache_ttl' => 3600,
        ];
        $googlebooks = new \BookRec_GoogleBooks_Service($config);
        
        $this->assertTrue($googlebooks->hasApiKey());
    }

    /**
     * Test constructor with config from options
     */
    public function test_constructor_with_config_from_options() {
        // Mock multiple config values
        Functions\when('bookrec_get_config')->justReturn(
            Functions\returnValueMap([
                ['gbooks_key', '', 'test_gbooks_key'],
                ['cache_ttl', 86400, 7200],
            ])
        );
        
        $googlebooks = new \BookRec_GoogleBooks_Service();
        $this->assertTrue($googlebooks->hasApiKey());
    }

    /**
     * Test getBookDetailsByTitleAuthor with cached results
     */
    public function test_getBookDetailsByTitleAuthor_with_cache() {
        $cached_data = [
            'title' => 'Test Book',
            'authors' => ['Test Author'],
        ];

        // Mock get_transient to return cached data
        Functions\when('get_transient')->justReturn($cached_data);
        
        $googlebooks = new \BookRec_GoogleBooks_Service();
        $result = $googlebooks->getBookDetailsByTitleAuthor('Test Book', 'Test Author');
        
        $this->assertEquals($cached_data, $result);
    }

    /**
     * Test getBookDetailsByTitleAuthor with empty parameters
     */
    public function test_getBookDetailsByTitleAuthor_with_empty_params() {
        $googlebooks = new \BookRec_GoogleBooks_Service();
        $result = $googlebooks->getBookDetailsByTitleAuthor('', '');
        
        $this->assertNull($result);
    }

    /**
     * Test getBookDetailsByTitleAuthor with API request error
     */
    public function test_getBookDetailsByTitleAuthor_with_wp_error() {
        // Reset get_transient to return false (no cache)
        Functions\when('get_transient')->justReturn(false);
        
        // Mock WordPress functions for URL and API request
        Functions\when('add_query_arg')->returnArg(0);
        Functions\when('wp_remote_get')->justReturn(new \WP_Error('test_error', 'Test error message'));
        Functions\when('is_wp_error')->justReturn(true);
        Functions\when('urlencode')->returnArg(0);
        
        $googlebooks = new \BookRec_GoogleBooks_Service();
        $result = $googlebooks->getBookDetailsByTitleAuthor('Test Book', 'Test Author');
        
        $this->assertNull($result);
    }

    /**
     * Test getBookDetailsByTitleAuthor with non-200 response code
     */
    public function test_getBookDetailsByTitleAuthor_with_error_status() {
        // Reset get_transient to return false (no cache)
        Functions\when('get_transient')->justReturn(false);
        
        // Mock WordPress functions for URL and API request
        Functions\when('add_query_arg')->returnArg(0);
        Functions\when('wp_remote_get')->justReturn(['response' => ['code' => 400]]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(400);
        Functions\when('urlencode')->returnArg(0);
        
        $googlebooks = new \BookRec_GoogleBooks_Service();
        $result = $googlebooks->getBookDetailsByTitleAuthor('Test Book', 'Test Author');
        
        $this->assertNull($result);
    }

    /**
     * Test getBookDetailsByTitleAuthor with JSON decode error
     */
    public function test_getBookDetailsByTitleAuthor_with_json_error() {
        // Reset get_transient to return false (no cache)
        Functions\when('get_transient')->justReturn(false);
        
        // Mock WordPress functions for URL and API request
        Functions\when('add_query_arg')->returnArg(0);
        Functions\when('wp_remote_get')->justReturn(['response' => ['code' => 200]]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn('Invalid JSON');
        Functions\when('urlencode')->returnArg(0);
        
        // Mock json_decode and json_last_error
        Functions\when('json_decode')->justReturn(null);
        Functions\when('json_last_error')->justReturn(JSON_ERROR_SYNTAX);
        Functions\when('json_last_error_msg')->justReturn('Syntax error');
        
        $googlebooks = new \BookRec_GoogleBooks_Service();
        $result = $googlebooks->getBookDetailsByTitleAuthor('Test Book', 'Test Author');
        
        $this->assertNull($result);
    }

    /**
     * Test getBookDetailsByTitleAuthor with empty items in response
     */
    public function test_getBookDetailsByTitleAuthor_with_empty_items() {
        // Reset get_transient to return false (no cache)
        Functions\when('get_transient')->justReturn(false);
        
        // Mock WordPress functions for URL and API request
        Functions\when('add_query_arg')->returnArg(0);
        Functions\when('wp_remote_get')->justReturn(['response' => ['code' => 200]]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn('{"kind":"books#volumes"}');
        Functions\when('urlencode')->returnArg(0);
        
        // Mock json_decode and json_last_error
        Functions\when('json_decode')->justReturn(['kind' => 'books#volumes']);
        Functions\when('json_last_error')->justReturn(JSON_ERROR_NONE);
        
        $googlebooks = new \BookRec_GoogleBooks_Service();
        $result = $googlebooks->getBookDetailsByTitleAuthor('Test Book', 'Test Author');
        
        $this->assertNull($result);
    }

    /**
     * Test getBookDetailsByTitleAuthor with successful response
     */
    public function test_getBookDetailsByTitleAuthor_success() {
        // Reset get_transient to return false (no cache)
        Functions\when('get_transient')->justReturn(false);
        
        // Sample API response
        $api_response = [
            'items' => [
                [
                    'id' => 'test123',
                    'volumeInfo' => [
                        'title' => 'Test Book',
                        'authors' => ['Test Author'],
                        'description' => 'Test description',
                        'imageLinks' => ['thumbnail' => 'http://example.com/image.jpg'],
                        'publishedDate' => '2023',
                        'publisher' => 'Test Publisher',
                        'categories' => ['Fiction'],
                        'pageCount' => 300,
                        'industryIdentifiers' => [
                            ['type' => 'ISBN_10', 'identifier' => '1234567890'],
                            ['type' => 'ISBN_13', 'identifier' => '9781234567890'],
                        ],
                    ],
                ],
            ],
        ];
        
        // Mock WordPress functions for URL and API request
        Functions\when('add_query_arg')->returnArg(0);
        Functions\when('wp_remote_get')->justReturn(['response' => ['code' => 200]]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode($api_response));
        Functions\when('urlencode')->returnArg(0);
        
        // Mock json_decode and json_last_error
        Functions\when('json_decode')->justReturn($api_response);
        Functions\when('json_last_error')->justReturn(JSON_ERROR_NONE);
        
        // Mock Amazon tag for link generation
        Functions\when('bookrec_get_config')->justReturn('gooderead-20')->whenArgsMatch(function ($key, $default) {
            return $key === 'amazon_tag';
        });
        
        $googlebooks = new \BookRec_GoogleBooks_Service();
        $result = $googlebooks->getBookDetailsByTitleAuthor('Test Book', 'Test Author');
        
        $this->assertNotNull($result);
        $this->assertEquals('Test Book', $result['title']);
        $this->assertEquals(['Test Author'], $result['authors']);
        $this->assertEquals('Test Author', $result['author_display']);
        $this->assertEquals('1234567890', $result['isbn10']);
        $this->assertEquals('9781234567890', $result['isbn13']);
        $this->assertStringContainsString('amazon.com/dp/1234567890', $result['amazon_link']);
        $this->assertStringContainsString('gooderead-20', $result['amazon_link']);
    }

    /**
     * Test getBookDetailsByISBN with invalid ISBN
     */
    public function test_getBookDetailsByISBN_with_invalid_isbn() {
        $googlebooks = new \BookRec_GoogleBooks_Service();
        $result = $googlebooks->getBookDetailsByISBN('');
        
        $this->assertNull($result);
    }

    /**
     * Test getBookDetailsByISBN cleans ISBN correctly
     */
    public function test_getBookDetailsByISBN_cleans_isbn() {
        // Mock get_transient to return cached data based on cleaned ISBN
        Functions\when('get_transient')->returnArg(0);
        
        $googlebooks = new \BookRec_GoogleBooks_Service();
        
        // This should clean to "1234567890"
        $googlebooks->getBookDetailsByISBN('123-456-7890');
        
        // This should verify that the transient key contains the cleaned ISBN
        $this->assertContains('bookrec_gbooks_isbn_1234567890', \Brain\Monkey\Functions\invoked('get_transient'));
    }

    /**
     * Test searchBooks with empty query
     */
    public function test_searchBooks_with_empty_query() {
        $googlebooks = new \BookRec_GoogleBooks_Service();
        $result = $googlebooks->searchBooks('');
        
        $this->assertNull($result);
    }

    /**
     * Test searchBooks limits max_results correctly
     */
    public function test_searchBooks_limits_max_results() {
        // Mock add_query_arg to capture the query parameters
        Functions\when('add_query_arg')->returnCallback(function ($args) {
            return $args;
        });
        
        // Mock wp_remote_get to avoid actual API call
        Functions\when('wp_remote_get')->justReturn(['response' => ['code' => 200]]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn('{"items":[]}');
        
        // Mock json_decode to return empty items array
        Functions\when('json_decode')->justReturn(['items' => []]);
        Functions\when('json_last_error')->justReturn(JSON_ERROR_NONE);
        
        $googlebooks = new \BookRec_GoogleBooks_Service();
        
        // Test with value below minimum (should use 1)
        $googlebooks->searchBooks('test', 0);
        // Test with value above maximum (should use 40)
        $googlebooks->searchBooks('test', 50);
        
        // This should verify that maxResults was properly limited
        $this->assertContains(1, \Brain\Monkey\Functions\invoked('add_query_arg'));
        $this->assertContains(40, \Brain\Monkey\Functions\invoked('add_query_arg'));
    }

    /**
     * Test searchBooksByAuthor returns empty array for empty author
     */
    public function test_searchBooksByAuthor_with_empty_author() {
        $googlebooks = new \BookRec_GoogleBooks_Service();
        $result = $googlebooks->searchBooksByAuthor('');
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test searchBooksByAuthor validates order_by parameter
     */
    public function test_searchBooksByAuthor_validates_order_by() {
        // Mock add_query_arg to capture the query parameters
        Functions\when('add_query_arg')->returnCallback(function ($args) {
            return $args;
        });
        
        // Mock wp_remote_get to avoid actual API call
        Functions\when('wp_remote_get')->justReturn(['response' => ['code' => 200]]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn('{"items":[]}');
        
        // Mock json_decode to return empty items array
        Functions\when('json_decode')->justReturn(['items' => []]);
        Functions\when('json_last_error')->justReturn(JSON_ERROR_NONE);
        
        $googlebooks = new \BookRec_GoogleBooks_Service();
        
        // Test with invalid order_by (should default to 'relevance')
        $googlebooks->searchBooksByAuthor('Test Author', 5, 'invalid_order');
        
        // This should verify that orderBy was set to 'relevance'
        $this->assertContains('relevance', \Brain\Monkey\Functions\invoked('add_query_arg'));
    }

    /**
     * Test searchBooksByGenre returns empty array for empty genre
     */
    public function test_searchBooksByGenre_with_empty_genre() {
        $googlebooks = new \BookRec_GoogleBooks_Service();
        $result = $googlebooks->searchBooksByGenre('');
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test searchBooksByGenre with successful response
     */
    public function test_searchBooksByGenre_success() {
        // Reset get_transient to return false (no cache)
        Functions\when('get_transient')->justReturn(false);
        
        // Sample API response with multiple books
        $api_response = [
            'items' => [
                [
                    'id' => 'book1',
                    'volumeInfo' => [
                        'title' => 'Book 1',
                        'authors' => ['Author 1'],
                        'publisher' => 'Publisher 1',
                    ],
                ],
                [
                    'id' => 'book2',
                    'volumeInfo' => [
                        'title' => 'Book 2',
                        'authors' => ['Author 2'],
                        'publisher' => 'Publisher 2',
                    ],
                ],
            ],
        ];
        
        // Mock WordPress functions for URL and API request
        Functions\when('add_query_arg')->returnArg(0);
        Functions\when('wp_remote_get')->justReturn(['response' => ['code' => 200]]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode($api_response));
        Functions\when('urlencode')->returnArg(0);
        
        // Mock json_decode and json_last_error
        Functions\when('json_decode')->justReturn($api_response);
        Functions\when('json_last_error')->justReturn(JSON_ERROR_NONE);
        
        $googlebooks = new \BookRec_GoogleBooks_Service();
        $result = $googlebooks->searchBooksByGenre('Science Fiction');
        
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('Book 1', $result[0]['title']);
        $this->assertEquals('Book 2', $result[1]['title']);
    }

    /**
     * Test clearCache function
     */
    public function test_clearCache() {
        // Mock WordPress global $wpdb
        $wpdb = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['get_col', 'prepare', 'esc_like'])
            ->getMock();
        
        $wpdb->options = 'wp_options';
        $wpdb->expects($this->once())
            ->method('prepare')
            ->willReturn('SQL QUERY');
        
        $wpdb->expects($this->once())
            ->method('get_col')
            ->willReturn([
                '_transient_bookrec_gbooks_abc123',
                '_transient_bookrec_gbooks_def456',
                '_transient_bookrec_gbooks_ghi789',
            ]);
        
        $wpdb->expects($this->once())
            ->method('esc_like')
            ->willReturn('bookrec_gbooks_');
        
        // Set global $wpdb
        global $wpdb;
        $wpdb = $wpdb;
        
        // Mock delete_transient to return true
        Functions\when('delete_transient')->justReturn(true);
        
        $googlebooks = new \BookRec_GoogleBooks_Service();
        $result = $googlebooks->clearCache();
        
        $this->assertEquals(3, $result);
    }

    /**
     * Test setter methods
     */
    public function test_setter_methods() {
        $googlebooks = new \BookRec_GoogleBooks_Service();
        
        // Test setApiKey
        $googlebooks->setApiKey('new_api_key');
        $this->assertTrue($googlebooks->hasApiKey());
        
        // Test setCacheTTL with value above minimum
        $googlebooks->setCacheTTL(3600);
        
        // Test setCacheTTL with value below minimum (should use 60)
        $googlebooks->setCacheTTL(30);
    }
}