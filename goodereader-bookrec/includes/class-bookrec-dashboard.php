<?php
/**
 * Book Recommender Dashboard Widget Class
 *
 * @package   Good E-Reader Book Recommender
 */

declare(strict_types=1);

if (!defined('WPINC')) {
    die;
}

/**
 * Class BookRec_Dashboard
 * 
 * Handles the dashboard widget for tracking recommendations and affiliate stats
 */
class BookRec_Dashboard {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Register dashboard widget
        add_action('wp_dashboard_setup', [$this, 'register_dashboard_widget']);
        
        // Register admin scripts
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        
        // Register AJAX handler for dashboard stats
        add_action('wp_ajax_bookrec_get_dashboard_stats', [$this, 'ajax_get_dashboard_stats']);
        
        // Register AJAX handler for tracking Amazon clicks
        add_action('wp_ajax_bookrec_track_amazon_click', [$this, 'ajax_track_amazon_click']);
        add_action('wp_ajax_nopriv_bookrec_track_amazon_click', [$this, 'ajax_track_amazon_click']);
    }
    
    /**
     * Register the dashboard widget
     */
    public function register_dashboard_widget(): void {
        wp_add_dashboard_widget(
            'bookrec_dashboard_widget',
            'BookRec Affiliate Stats',
            [$this, 'render_dashboard_widget']
        );
    }
    
    /**
     * Render the dashboard widget
     */
    public function render_dashboard_widget(): void {
        require_once BOOKREC_PLUGIN_DIR . 'admin/views/dashboard-widget.php';
    }
    
    /**
     * Enqueue admin scripts
     * 
     * @param string $hook The current admin page
     */
    public function enqueue_admin_scripts(string $hook): void {
        if ($hook !== 'index.php') {
            return;
        }
        
        wp_enqueue_script(
            'bookrec-admin-dashboard',
            BOOKREC_PLUGIN_URL . 'admin/js/dashboard.js',
            ['jquery'],
            BOOKREC_VERSION,
            true
        );
        
        wp_localize_script('bookrec-admin-dashboard', 'bookrecAdmin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bookrec_admin_nonce'),
        ]);
    }
    
    /**
     * Get recommendation statistics
     * 
     * @param int $days Number of days to get stats for (0 = all time)
     * 
     * @return array Statistics data
     */
    public function get_recommendation_stats(int $days = 7): array {
        // For MVP, we'll return placeholder data
        // In a real implementation, this would query the database for actual stats
        
        $stats = [
            'total_recommendations' => 0,
            'total_sessions' => 0,
            'amazon_clicks' => 0,
            'est_revenue' => '$0.00',
            'top_books' => [],
        ];
        
        // Get recommendation count from transients
        $recommendation_count = get_option('bookrec_recommendation_count', 0);
        $stats['total_recommendations'] = $recommendation_count;
        
        // Get session count
        $session_count = get_option('bookrec_session_count', 0);
        $stats['total_sessions'] = $session_count;
        
        // Get Amazon click count
        $amazon_clicks = get_option('bookrec_amazon_clicks', 0);
        $stats['amazon_clicks'] = $amazon_clicks;
        
        // Calculate estimated revenue (very rough estimate for demo purposes)
        $avg_commission = 0.04; // 4% average commission
        $avg_purchase_value = 15.00; // Average book purchase price
        $conversion_rate = 0.03; // 3% conversion rate
        
        $est_revenue = $amazon_clicks * $conversion_rate * $avg_purchase_value * $avg_commission;
        $stats['est_revenue'] = '$' . number_format($est_revenue, 2);
        
        // Get top recommended books (placeholder data for MVP)
        $stats['top_books'] = [
            [
                'title' => 'Project Hail Mary',
                'author' => 'Andy Weir',
                'count' => 12,
                'click_rate' => 24,
            ],
            [
                'title' => 'The Midnight Library',
                'author' => 'Matt Haig',
                'count' => 10,
                'click_rate' => 18,
            ],
            [
                'title' => 'Atomic Habits',
                'author' => 'James Clear',
                'count' => 8,
                'click_rate' => 32,
            ],
        ];
        
        return $stats;
    }
    
    /**
     * AJAX handler for getting dashboard stats
     */
    public function ajax_get_dashboard_stats(): void {
        // Verify nonce
        check_ajax_referer('bookrec_admin_nonce', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
            return;
        }
        
        // Get timeframe
        $days = isset($_GET['days']) ? (int) $_GET['days'] : 7;
        
        // Get stats
        $stats = $this->get_recommendation_stats($days);
        
        wp_send_json_success($stats);
    }
    
    /**
     * AJAX handler for tracking Amazon clicks
     */
    public function ajax_track_amazon_click(): void {
        // Verify nonce
        check_ajax_referer('bookrec_chat_nonce', 'nonce');
        
        // Increment click counter
        $current_clicks = get_option('bookrec_amazon_clicks', 0);
        update_option('bookrec_amazon_clicks', $current_clicks + 1);
        
        // Track book ID if provided
        if (isset($_POST['book_id']) && !empty($_POST['book_id'])) {
            $book_id = sanitize_text_field($_POST['book_id']);
            $book_clicks = get_option('bookrec_book_clicks', []);
            
            if (!isset($book_clicks[$book_id])) {
                $book_clicks[$book_id] = 0;
            }
            
            $book_clicks[$book_id]++;
            update_option('bookrec_book_clicks', $book_clicks);
        }
        
        wp_send_json_success(['status' => 'click_tracked']);
    }
    
    /**
     * Increment recommendation counter
     * 
     * @return void
     */
    public static function increment_recommendation_counter(): void {
        $current_count = get_option('bookrec_recommendation_count', 0);
        update_option('bookrec_recommendation_count', $current_count + 1);
    }
    
    /**
     * Increment session counter
     * 
     * @return void
     */
    public static function increment_session_counter(): void {
        $current_count = get_option('bookrec_session_count', 0);
        update_option('bookrec_session_count', $current_count + 1);
    }
}