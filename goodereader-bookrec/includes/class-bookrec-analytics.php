<?php
/**
 * Class to handle BookRec analytics functionality.
 *
 * @package GoodEReader_BookRec
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * BookRec Analytics class.
 */
class BookRec_Analytics {
    /**
     * The average commission rate for Amazon Affiliate sales.
     *
     * @var float
     */
    private $avg_commission = 0.04; // 4% commission rate
    
    /**
     * The average book price for revenue calculations.
     *
     * @var float
     */
    private $avg_book_price = 14.99; // $14.99 avg price
    
    /**
     * Initialize the class.
     */
    public function __construct() {
        // Register AJAX handlers
        add_action('wp_ajax_bookrec_get_analytics_data', [$this, 'handle_get_analytics_data']);
        add_action('wp_ajax_bookrec_export_analytics', [$this, 'handle_export_analytics']);
        
        // Register the analytics page
        add_action('admin_menu', [$this, 'register_analytics_page']);
        
        // Register assets
        add_action('admin_enqueue_scripts', [$this, 'enqueue_analytics_assets']);
    }
    
    /**
     * Register the analytics admin page.
     */
    public function register_analytics_page(): void {
        add_submenu_page(
            'bookrec-settings',                // Parent slug
            __('Book Recommendation Analytics', 'goodereader-bookrec'), // Page title
            __('Analytics', 'goodereader-bookrec'),                    // Menu title
            'manage_options',                   // Capability
            'bookrec-analytics',                // Menu slug
            [$this, 'render_analytics_page']    // Callback function
        );
    }
    
    /**
     * Render the analytics page.
     */
    public function render_analytics_page(): void {
        include_once BOOKREC_PLUGIN_DIR . 'admin/views/analytics-page.php';
    }
    
    /**
     * Enqueue analytics assets.
     *
     * @param string $hook_suffix The current admin page.
     */
    public function enqueue_analytics_assets(string $hook_suffix): void {
        if ('bookrec-settings_page_bookrec-analytics' !== $hook_suffix) {
            return;
        }
        
        // Enqueue Chart.js
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
            [],
            '3.9.1',
            true
        );
        
        // Enqueue custom CSS
        wp_enqueue_style(
            'bookrec-analytics-css',
            BOOKREC_PLUGIN_URL . 'admin/css/analytics.css',
            [],
            BOOKREC_VERSION
        );
        
        // Enqueue custom JS
        wp_enqueue_script(
            'bookrec-analytics-js',
            BOOKREC_PLUGIN_URL . 'admin/js/analytics.js',
            ['jquery', 'chartjs'],
            BOOKREC_VERSION,
            true
        );
        
        // Add translations and variables
        wp_localize_script(
            'bookrec-analytics-js',
            'bookrec_analytics',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('bookrec_analytics_nonce'),
                'i18n' => [
                    'loading' => __('Loading...', 'goodereader-bookrec'),
                    'error_loading' => __('Error loading analytics data.', 'goodereader-bookrec'),
                    'select_both_dates' => __('Please select both start and end dates.', 'goodereader-bookrec'),
                    'clicks' => __('Clicks', 'goodereader-bookrec'),
                    'variant_a' => __('Variant A', 'goodereader-bookrec'),
                    'variant_b' => __('Variant B', 'goodereader-bookrec'),
                    'not_enough_data' => __('Not enough data', 'goodereader-bookrec'),
                    'no_data' => __('No data available.', 'goodereader-bookrec'),
                ]
            ]
        );
    }
    
    /**
     * Handle AJAX request to get analytics data.
     */
    public function handle_get_analytics_data(): void {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to access this data.', 'goodereader-bookrec')]);
        }
        
        // Verify nonce
        check_ajax_referer('bookrec_analytics_nonce', 'nonce');
        
        // Get parameters
        $range = sanitize_text_field($_POST['range'] ?? '30');
        $start_date = sanitize_text_field($_POST['start_date'] ?? '');
        $end_date = sanitize_text_field($_POST['end_date'] ?? '');
        
        // Get date boundaries
        $dates = $this->get_date_boundaries($range, $start_date, $end_date);
        
        // Prepare data
        $data = [
            'stats' => $this->get_stat_cards_data($dates),
            'charts' => $this->get_charts_data($dates),
            'ab_testing' => $this->get_ab_testing_data($dates),
            'top_books' => $this->get_top_books_data($dates),
            'recommendations' => $this->get_recommendations_data($dates),
        ];
        
        wp_send_json_success($data);
    }
    
    /**
     * Handle AJAX request to export analytics data as CSV.
     */
    public function handle_export_analytics(): void {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to export this data.', 'goodereader-bookrec'));
        }
        
        // Verify nonce
        check_ajax_referer('bookrec_analytics_nonce', 'nonce');
        
        // Get parameters
        $range = sanitize_text_field($_GET['range'] ?? '30');
        $start_date = sanitize_text_field($_GET['start_date'] ?? '');
        $end_date = sanitize_text_field($_GET['end_date'] ?? '');
        
        // Get date boundaries
        $dates = $this->get_date_boundaries($range, $start_date, $end_date);
        
        // Get data for export
        $top_books = $this->get_raw_top_books_data($dates);
        $daily_clicks = $this->get_raw_daily_clicks_data($dates);
        
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="bookrec-analytics-' . current_time('Y-m-d') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Create output stream
        $output = fopen('php://output', 'w');
        
        // Export top books data
        fputcsv($output, [__('Top Books Report', 'goodereader-bookrec'), '', '', '', '']);
        fputcsv($output, [__('Period', 'goodereader-bookrec'), $dates['start_date'] . ' to ' . $dates['end_date']]);
        fputcsv($output, ['']);
        fputcsv($output, [
            __('Book Title', 'goodereader-bookrec'),
            __('Author', 'goodereader-bookrec'),
            __('Clicks', 'goodereader-bookrec'),
            __('Click Rate', 'goodereader-bookrec'),
            __('Est. Revenue', 'goodereader-bookrec')
        ]);
        
        foreach ($top_books as $book) {
            fputcsv($output, [
                $book['title'],
                $book['author'],
                $book['clicks'],
                $book['rate'] . '%',
                '$' . number_format($book['revenue'], 2)
            ]);
        }
        
        // Add a separator
        fputcsv($output, ['']);
        fputcsv($output, ['']);
        
        // Export daily clicks data
        fputcsv($output, [__('Daily Clicks Report', 'goodereader-bookrec'), '', '']);
        fputcsv($output, ['']);
        fputcsv($output, [
            __('Date', 'goodereader-bookrec'),
            __('Clicks', 'goodereader-bookrec'),
            __('Est. Revenue', 'goodereader-bookrec')
        ]);
        
        foreach ($daily_clicks as $date => $clicks) {
            $revenue = $clicks * $this->avg_book_price * $this->avg_commission;
            fputcsv($output, [
                $date,
                $clicks,
                '$' . number_format($revenue, 2)
            ]);
        }
        
        fclose($output);
        die();
    }
    
    /**
     * Get date boundaries based on the selected range.
     *
     * @param string $range      The selected date range.
     * @param string $start_date Custom start date.
     * @param string $end_date   Custom end date.
     * @return array Date boundaries.
     */
    private function get_date_boundaries(string $range, string $start_date = '', string $end_date = ''): array {
        $today = date('Y-m-d');
        $previous_period_end = '';
        
        if ($range === 'custom' && !empty($start_date) && !empty($end_date)) {
            // Calculate the previous period for comparison
            $start = new DateTime($start_date);
            $end = new DateTime($end_date);
            $diff = $start->diff($end);
            $days = $diff->days + 1; // Include both start and end dates
            
            $previous_start = clone $start;
            $previous_start->modify('-' . $days . ' days');
            $previous_end = clone $start;
            $previous_end->modify('-1 day');
            
            return [
                'start_date' => $start_date,
                'end_date' => $end_date,
                'previous_start_date' => $previous_start->format('Y-m-d'),
                'previous_end_date' => $previous_end->format('Y-m-d'),
                'days' => $days
            ];
        }
        
        // Handle predefined ranges
        $days = 30; // Default to 30 days
        
        switch ($range) {
            case '7':
                $days = 7;
                $start_date = date('Y-m-d', strtotime('-6 days'));
                break;
            case '30':
                $days = 30;
                $start_date = date('Y-m-d', strtotime('-29 days'));
                break;
            case '90':
                $days = 90;
                $start_date = date('Y-m-d', strtotime('-89 days'));
                break;
            case '365':
                $days = 365;
                $start_date = date('Y-m-d', strtotime('-364 days'));
                break;
        }
        
        // Calculate previous period
        $previous_end_date = date('Y-m-d', strtotime('-' . $days . ' days'));
        $previous_start_date = date('Y-m-d', strtotime('-' . ($days * 2 - 1) . ' days'));
        
        return [
            'start_date' => $start_date,
            'end_date' => $today,
            'previous_start_date' => $previous_start_date,
            'previous_end_date' => $previous_end_date,
            'days' => $days
        ];
    }
    
    /**
     * Get data for the stats cards.
     *
     * @param array $dates Date boundaries.
     * @return array
     */
    private function get_stat_cards_data(array $dates): array {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bookrec_clicks';
        $table_exists = $this->table_exists($table_name);
        
        $stats = [
            'total_clicks' => 0,
            'total_revenue' => 0,
            'click_rate' => 0,
            'total_sessions' => 0,
            'clicks_change' => 0,
            'revenue_change' => 0,
            'rate_change' => 0,
            'sessions_change' => 0
        ];
        
        // Use a closure for safe database queries
        $safe_query = function($query) use ($wpdb) {
            return $wpdb->get_var($query) ?: 0;
        };
        
        if ($table_exists) {
            // Current period stats
            $stats['total_clicks'] = (int) $safe_query(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table_name} 
                     WHERE DATE(click_timestamp) BETWEEN %s AND %s",
                    $dates['start_date'],
                    $dates['end_date']
                )
            );
            
            // Previous period stats for comparison
            $previous_clicks = (int) $safe_query(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table_name} 
                     WHERE DATE(click_timestamp) BETWEEN %s AND %s",
                    $dates['previous_start_date'],
                    $dates['previous_end_date']
                )
            );
            
            // Calculate percent change
            if ($previous_clicks > 0) {
                $stats['clicks_change'] = round((($stats['total_clicks'] - $previous_clicks) / $previous_clicks) * 100, 1);
            }
            
            // Get unique session count
            $stats['total_sessions'] = (int) $safe_query(
                $wpdb->prepare(
                    "SELECT COUNT(DISTINCT session_id) FROM {$table_name} 
                     WHERE DATE(click_timestamp) BETWEEN %s AND %s",
                    $dates['start_date'],
                    $dates['end_date']
                )
            );
            
            // Get previous period session count
            $previous_sessions = (int) $safe_query(
                $wpdb->prepare(
                    "SELECT COUNT(DISTINCT session_id) FROM {$table_name} 
                     WHERE DATE(click_timestamp) BETWEEN %s AND %s",
                    $dates['previous_start_date'],
                    $dates['previous_end_date']
                )
            );
            
            // Calculate percent change for sessions
            if ($previous_sessions > 0) {
                $stats['sessions_change'] = round((($stats['total_sessions'] - $previous_sessions) / $previous_sessions) * 100, 1);
            }
            
            // Calculate click rate (if we have session data)
            $total_sessions = (int) get_option('bookrec_sessions_count', 0);
            if ($total_sessions > 0) {
                $stats['click_rate'] = round(($stats['total_clicks'] / $total_sessions) * 100, 1);
                
                // Previous period click rate
                $total_previous_sessions = (int) get_option('bookrec_previous_sessions_count', 0);
                $previous_click_rate = $total_previous_sessions > 0 ? ($previous_clicks / $total_previous_sessions) * 100 : 0;
                
                if ($previous_click_rate > 0) {
                    $stats['rate_change'] = round(($stats['click_rate'] - $previous_click_rate) / $previous_click_rate * 100, 1);
                }
            }
            
            // Calculate estimated revenue
            $stats['total_revenue'] = $stats['total_clicks'] * $this->avg_book_price * $this->avg_commission;
            $previous_revenue = $previous_clicks * $this->avg_book_price * $this->avg_commission;
            
            if ($previous_revenue > 0) {
                $stats['revenue_change'] = round((($stats['total_revenue'] - $previous_revenue) / $previous_revenue) * 100, 1);
            }
        } else {
            // Fallback to options if table doesn't exist
            $stats['total_clicks'] = (int) get_option('bookrec_amazon_clicks', 0);
            $stats['total_sessions'] = (int) get_option('bookrec_sessions_count', 0);
            $stats['click_rate'] = $stats['total_sessions'] > 0 ? round(($stats['total_clicks'] / $stats['total_sessions']) * 100, 1) : 0;
            $stats['total_revenue'] = $stats['total_clicks'] * $this->avg_book_price * $this->avg_commission;
        }
        
        return $stats;
    }
    
    /**
     * Get data for the charts.
     *
     * @param array $dates Date boundaries.
     * @return array
     */
    private function get_charts_data(array $dates): array {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bookrec_clicks';
        $table_exists = $this->table_exists($table_name);
        
        $chart_data = [
            'labels' => [],
            'clicks' => []
        ];
        
        // Generate date range
        $start = new DateTime($dates['start_date']);
        $end = new DateTime($dates['end_date']);
        $interval = new DateInterval('P1D');
        $dateRange = new DatePeriod($start, $interval, $end->modify('+1 day'));
        
        // Format for the date labels in the chart
        $date_format = $dates['days'] > 30 ? 'M j' : 'M j';
        
        // For smaller ranges, use daily data
        $daily_clicks = $this->get_raw_daily_clicks_data($dates);
        
        // Build chart data
        foreach ($dateRange as $date) {
            $formatted_date = $date->format('Y-m-d');
            $display_date = $date->format($date_format);
            
            $chart_data['labels'][] = $display_date;
            $chart_data['clicks'][] = $daily_clicks[$formatted_date] ?? 0;
        }
        
        return $chart_data;
    }
    
    /**
     * Get raw daily clicks data.
     *
     * @param array $dates Date boundaries.
     * @return array
     */
    private function get_raw_daily_clicks_data(array $dates): array {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bookrec_clicks';
        $table_exists = $this->table_exists($table_name);
        
        $daily_clicks = [];
        
        if ($table_exists) {
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT DATE(click_timestamp) as click_date, COUNT(*) as click_count 
                     FROM {$table_name} 
                     WHERE DATE(click_timestamp) BETWEEN %s AND %s 
                     GROUP BY DATE(click_timestamp) 
                     ORDER BY click_date ASC",
                    $dates['start_date'],
                    $dates['end_date']
                )
            );
            
            foreach ($results as $row) {
                $daily_clicks[$row->click_date] = (int) $row->click_count;
            }
        }
        
        // Generate all dates in the range for completeness
        $start = new DateTime($dates['start_date']);
        $end = new DateTime($dates['end_date']);
        $interval = new DateInterval('P1D');
        $dateRange = new DatePeriod($start, $interval, $end->modify('+1 day'));
        
        foreach ($dateRange as $date) {
            $formatted_date = $date->format('Y-m-d');
            if (!isset($daily_clicks[$formatted_date])) {
                $daily_clicks[$formatted_date] = 0;
            }
        }
        
        ksort($daily_clicks);
        
        return $daily_clicks;
    }
    
    /**
     * Get A/B testing data.
     *
     * @param array $dates Date boundaries.
     * @return array
     */
    private function get_ab_testing_data(array $dates): array {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bookrec_clicks';
        $table_exists = $this->table_exists($table_name);
        
        $ab_data = [
            'a' => [
                'clicks' => 0,
                'rate' => 0
            ],
            'b' => [
                'clicks' => 0,
                'rate' => 0
            ],
            'winner' => null,
            'improvement' => 0
        ];
        
        if ($table_exists) {
            // Get clicks for variant A
            $ab_data['a']['clicks'] = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table_name} 
                     WHERE cta_variation = 'A' 
                     AND DATE(click_timestamp) BETWEEN %s AND %s",
                    $dates['start_date'],
                    $dates['end_date']
                )
            );
            
            // Get clicks for variant B
            $ab_data['b']['clicks'] = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table_name} 
                     WHERE cta_variation = 'B' 
                     AND DATE(click_timestamp) BETWEEN %s AND %s",
                    $dates['start_date'],
                    $dates['end_date']
                )
            );
            
            // Calculate rates (using session count as impressions approximation)
            $total_sessions = (int) get_option('bookrec_sessions_count', 0);
            
            if ($total_sessions > 0) {
                // Assume approximately half of sessions saw each variant
                $variant_sessions = $total_sessions / 2;
                
                if ($variant_sessions > 0) {
                    $ab_data['a']['rate'] = round(($ab_data['a']['clicks'] / $variant_sessions) * 100, 1);
                    $ab_data['b']['rate'] = round(($ab_data['b']['clicks'] / $variant_sessions) * 100, 1);
                    
                    // Determine winner if we have sufficient data
                    $min_threshold = 10; // Minimum number of clicks to consider a result valid
                    
                    if ($ab_data['a']['clicks'] >= $min_threshold && $ab_data['b']['clicks'] >= $min_threshold) {
                        if ($ab_data['a']['rate'] > $ab_data['b']['rate']) {
                            $ab_data['winner'] = 'a';
                            $ab_data['improvement'] = round((($ab_data['a']['rate'] - $ab_data['b']['rate']) / $ab_data['b']['rate']) * 100, 1);
                        } elseif ($ab_data['b']['rate'] > $ab_data['a']['rate']) {
                            $ab_data['winner'] = 'b';
                            $ab_data['improvement'] = round((($ab_data['b']['rate'] - $ab_data['a']['rate']) / $ab_data['a']['rate']) * 100, 1);
                        }
                    }
                }
            }
        }
        
        return $ab_data;
    }
    
    /**
     * Get top books data.
     *
     * @param array $dates Date boundaries.
     * @return array
     */
    private function get_top_books_data(array $dates): array {
        return $this->get_raw_top_books_data($dates);
    }
    
    /**
     * Get raw top books data.
     *
     * @param array $dates Date boundaries.
     * @return array
     */
    private function get_raw_top_books_data(array $dates): array {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bookrec_clicks';
        $table_exists = $this->table_exists($table_name);
        
        $top_books = [];
        
        if ($table_exists) {
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT book_title, book_author, COUNT(*) as click_count 
                     FROM {$table_name} 
                     WHERE DATE(click_timestamp) BETWEEN %s AND %s 
                     GROUP BY book_title, book_author 
                     ORDER BY click_count DESC 
                     LIMIT 10",
                    $dates['start_date'],
                    $dates['end_date']
                )
            );
            
            // Get total recommendations for rate calculation
            $total_recs = (int) get_option('bookrec_recommendations_count', 0);
            
            foreach ($results as $row) {
                $clicks = (int) $row->click_count;
                $rate = $total_recs > 0 ? round(($clicks / $total_recs) * 100, 1) : 0;
                $revenue = $clicks * $this->avg_book_price * $this->avg_commission;
                
                $top_books[] = [
                    'title' => $row->book_title,
                    'author' => $row->book_author,
                    'clicks' => $clicks,
                    'rate' => $rate,
                    'revenue' => $revenue
                ];
            }
        }
        
        return $top_books;
    }
    
    /**
     * Get recommendations data.
     *
     * @param array $dates Date boundaries.
     * @return array
     */
    private function get_recommendations_data(array $dates): array {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bookrec_clicks';
        $table_exists = $this->table_exists($table_name);
        
        $rec_data = [
            'total' => (int) get_option('bookrec_recommendations_count', 0),
            'rate' => 0,
            'avg_revenue' => 0
        ];
        
        if ($table_exists) {
            // Get clicks for the period
            $clicks = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table_name} 
                     WHERE DATE(click_timestamp) BETWEEN %s AND %s",
                    $dates['start_date'],
                    $dates['end_date']
                )
            );
            
            // Calculate rate
            if ($rec_data['total'] > 0) {
                $rec_data['rate'] = round(($clicks / $rec_data['total']) * 100, 1);
            }
            
            // Calculate average revenue per recommendation
            $total_revenue = $clicks * $this->avg_book_price * $this->avg_commission;
            $rec_data['avg_revenue'] = $rec_data['total'] > 0 ? $total_revenue / $rec_data['total'] : 0;
        }
        
        return $rec_data;
    }
    
    /**
     * Check if a database table exists.
     *
     * @param string $table_name The table name to check.
     * @return bool
     */
    private function table_exists(string $table_name): bool {
        global $wpdb;
        return $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
    }
}