<?php
/**
 * BookRec ShareCard
 * 
 * Handles share card functionality for book recommendations
 * 
 * @package GoodEReader\BookRec
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class BookRec_ShareCard
 * 
 * Handles functionality related to generating shareable cards for book recommendations
 */
class BookRec_ShareCard {
    /**
     * Initialize the class
     */
    public function __construct() {
        // Register the template for book sharing
        add_action('init', [$this, 'register_share_endpoint']);
        
        // Handle page template loading
        add_filter('template_include', [$this, 'load_share_template']);
        
        // Add rest api endpoint for generating share links
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }
    
    /**
     * Register the share endpoint in WordPress
     */
    public function register_share_endpoint(): void {
        add_rewrite_rule(
            '^book-share/([^/]+)/?$',
            'index.php?book_share=true&share_id=$matches[1]',
            'top'
        );
        
        add_rewrite_tag('%book_share%', '([^&]+)');
        add_rewrite_tag('%share_id%', '([^&]+)');
    }
    
    /**
     * Load the share template when appropriate
     * 
     * @param string $template The current template path
     * @return string The template path to use
     */
    public function load_share_template(string $template): string {
        // Check if this is a book share page
        if (get_query_var('book_share') === 'true') {
            $share_template = BOOKREC_PLUGIN_DIR . 'templates/template-book-share.php';
            
            if (file_exists($share_template)) {
                return $share_template;
            }
        }
        
        return $template;
    }
    
    /**
     * Register REST API routes for share functionality
     */
    public function register_rest_routes(): void {
        register_rest_route('bookrec/v1', '/share', [
            'methods' => 'POST',
            'callback' => [$this, 'generate_share_link'],
            'permission_callback' => '__return_true',
        ]);
    }
    
    /**
     * Generate a shareable link for a book recommendation
     * 
     * @param WP_REST_Request $request The request object
     * @return WP_REST_Response The response
     */
    public function generate_share_link($request): \WP_REST_Response {
        // Get parameters from request
        $params = $request->get_params();
        
        // Required parameters
        if (empty($params['title'])) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Book title is required', 'goodereader-bookrec'),
            ], 400);
        }
        
        // Build query string with book details
        $query_args = [
            'title' => urlencode($params['title']),
        ];
        
        // Add optional parameters if they exist
        if (!empty($params['author'])) {
            $query_args['author'] = urlencode($params['author']);
        }
        
        if (!empty($params['cover'])) {
            $query_args['cover'] = urlencode($params['cover']);
        }
        
        if (!empty($params['description'])) {
            $query_args['desc'] = urlencode(substr($params['description'], 0, 500)); // Limit description length
        }
        
        if (!empty($params['isbn'])) {
            $query_args['isbn'] = urlencode($params['isbn']);
        }
        
        // Generate the share URL
        $share_url = home_url('book-share/') . '?' . http_build_query($query_args);
        
        // Return share URL in response
        return new \WP_REST_Response([
            'success' => true,
            'share_url' => $share_url,
        ]);
    }
    
    /**
     * Flush rewrite rules on activation
     * This static method should be called during plugin activation
     */
    public static function activate(): void {
        // Ensure the endpoint is registered before flushing
        $sharecard = new self();
        $sharecard->register_share_endpoint();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Flush rewrite rules on deactivation
     * This static method should be called during plugin deactivation
     */
    public static function deactivate(): void {
        // Flush rewrite rules to remove our custom ones
        flush_rewrite_rules();
    }
}