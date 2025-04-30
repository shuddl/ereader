<?php
/**
 * Book Recommender Cron Tasks Class
 *
 * @package Good E-Reader Book Recommender
 */

declare(strict_types=1);

if (!defined('WPINC')) {
    die;
}

/**
 * Class BookRec_Cron
 * Handles WordPress cron scheduling for background tasks
 */
class BookRec_Cron {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Register custom cron interval
        add_filter('cron_schedules', [$this, 'add_cron_intervals']);
        
        // Register cron hooks
        add_action('bookrec_fetch_trends_hook', [$this, 'run_trend_fetch']);
    }
    
    /**
     * Register custom cron intervals
     *
     * @param array $schedules Existing cron schedules
     * @return array Modified cron schedules
     */
    public function add_cron_intervals(array $schedules): array {
        // Add twice daily interval (every 12 hours)
        $schedules['bookrec_twicedaily'] = [
            'interval' => 43200, // 12 hours in seconds
            'display' => __('Twice Daily', 'goodereader-bookrec'),
        ];
        
        return $schedules;
    }
    
    /**
     * Schedule all cron events
     */
    public function schedule_events(): void {
        $this->schedule_trend_fetch();
    }
    
    /**
     * Schedule the trend fetching event
     */
    public function schedule_trend_fetch(): void {
        // Only schedule if not already scheduled
        if (!wp_next_scheduled('bookrec_fetch_trends_hook')) {
            wp_schedule_event(time(), 'bookrec_twicedaily', 'bookrec_fetch_trends_hook');
            error_log('BookRec Cron: Scheduled trend fetching task');
        }
    }
    
    /**
     * Unschedule all cron events
     */
    public function unschedule_events(): void {
        $timestamp = wp_next_scheduled('bookrec_fetch_trends_hook');
        
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'bookrec_fetch_trends_hook');
            error_log('BookRec Cron: Unscheduled trend fetching task');
        }
    }
    
    /**
     * Execute trend fetching task
     */
    public function run_trend_fetch(): void {
        // Include the trends service class if not already included
        if (!class_exists('BookRec_Trends_Service')) {
            require_once BOOKREC_PLUGIN_DIR . 'includes/class-bookrec-trends.php';
        }
        
        // Create service instance and fetch trends
        $trends_service = new BookRec_Trends_Service();
        $results = $trends_service->fetch_booktok_trends();
        
        // Log result
        if (!empty($results)) {
            error_log('BookRec Cron: Successfully fetched ' . count($results) . ' trending books');
        } else {
            error_log('BookRec Cron: Failed to fetch trending books');
        }
    }
}