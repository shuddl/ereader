<?php
/**
 * Plugin Name: Good E-Reader Book Recommender
 * Plugin URI: https://goodereader.com/
 * Description: A feature-rich book recommendation chatbot powered by GPT-4 Turbo.
 * Version: 1.0.0
 * Author: Good E-Reader
 * Author URI: https://goodereader.com/
 * Text Domain: goodereader-bookrec
 * Domain Path: /languages
 */

declare(strict_types=1);

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Plugin version
define('BOOKREC_VERSION', '1.0.0');

// Plugin paths
define('BOOKREC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BOOKREC_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Check if PHP and WordPress versions meet requirements.
 *
 * @return void
 */
function bookrec_requirements_check(): void {
    $php_version = phpversion();
    $wp_version = get_bloginfo('version');
    $php_min_version = '8.0';
    $wp_min_version = '6.0';
    $requirements_met = true;
    $notices = [];

    if (version_compare($php_version, $php_min_version, '<')) {
        $requirements_met = false;
        $notices[] = sprintf(
            /* translators: 1: Current PHP version, 2: Required PHP version */
            esc_html__('Good E-Reader Book Recommender requires PHP %2$s or higher. You are running version %1$s.', 'goodereader-bookrec'),
            $php_version,
            $php_min_version
        );
    }

    if (version_compare($wp_version, $wp_min_version, '<')) {
        $requirements_met = false;
        $notices[] = sprintf(
            /* translators: 1: Current WordPress version, 2: Required WordPress version */
            esc_html__('Good E-Reader Book Recommender requires WordPress %2$s or higher. You are running version %1$s.', 'goodereader-bookrec'),
            $wp_version,
            $wp_min_version
        );
    }

    if (!$requirements_met) {
        // Deactivate the plugin
        deactivate_plugins(plugin_basename(__FILE__));

        // Add admin notice
        add_action('admin_notices', function() use ($notices) {
            echo '<div class="error">';
            foreach ($notices as $notice) {
                echo '<p>' . $notice . '</p>';
            }
            echo '<p>' . esc_html__('The plugin has been deactivated.', 'goodereader-bookrec') . '</p>';
            echo '</div>';
            
            // Remove "Plugin activated" notice
            if (isset($_GET['activate'])) {
                unset($_GET['activate']);
            }
        });
    }
}

/**
 * Plugin activation hook.
 */
function bookrec_activate(): void {
    global $wpdb;
    
    // Check requirements first
    bookrec_requirements_check();
    
    // Create the click tracking table if it doesn't exist
    $table_name = $wpdb->prefix . 'bookrec_clicks';
    $charset_collate = $wpdb->get_charset_collate();
    
    // Only run if the table doesn't exist
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $sql = "CREATE TABLE $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id varchar(100) NOT NULL,
            book_title text NOT NULL,
            book_author text NOT NULL,
            amazon_url text NOT NULL,
            cta_variation varchar(10) DEFAULT NULL,
            click_timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY click_timestamp (click_timestamp),
            KEY session_id (session_id)
        ) $charset_collate;";
        
        // Use dbDelta for safe table creation
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
    
    // Schedule cron events
    if (class_exists('BookRec_Cron')) {
        $cron = new BookRec_Cron();
        $cron->schedule_events();
    }
    
    // Initial fetch of trend data
    if (class_exists('BookRec_Trends_Service')) {
        $trends = new BookRec_Trends_Service();
        $trends->fetch_booktok_trends();
    }
    
    // Initialize ShareCard rewrite rules
    if (class_exists('BookRec_ShareCard')) {
        BookRec_ShareCard::activate();
    }
    
    // Default genres list
    $default_genres = "Fiction
Science Fiction
Fantasy
BookTok
Paranormal Romance
Mystery
Thriller
Historical Fiction
Young Adult
Romance
Literary Fiction
Non-Fiction
Biography
Self-Help
Business
Science
History
Technology
Philosophy
Psychology
Cooking
Travel
Children's Books
Poetry
Graphic Novels
Horror
Crime Fiction
Dystopian
Urban Fantasy
Contemporary Fiction";

    // Default system prompt
    $default_system_prompt = "You are GoodBookAI, a friendly book recommendation assistant from Good E-Reader. Your goal is to help users discover books they'll love based on their tastes, preferences, and reading history. Be conversational and friendly, but concise. Ask clarifying questions to better understand their preferences before making recommendations.";

    // Default recommendation prompt
    $default_recommendation_prompt = "Based on our conversation, here are {count} books I think you'll enjoy based on your interest in {genre}. Each recommendation includes the title, author, a brief compelling description, and a purchase link.";

    // Set default options
    add_option('bookrec_openai_key', '');
    add_option('bookrec_gbooks_key', '');
    add_option('bookrec_amazon_tag', 'gooderead-20');
    add_option('bookrec_genres', $default_genres);
    add_option('bookrec_default_recommendations', 3);
    add_option('bookrec_cache_ttl', 300);
    add_option('bookrec_ab_test_cta', 'enabled');
    add_option('bookrec_include_trending_books', 1); // Include one trending book by default
    add_option('bookrec_system_prompt', $default_system_prompt);
    add_option('bookrec_recommendation_prompt', $default_recommendation_prompt);
    add_option('bookrec_version', BOOKREC_VERSION);
}

/**
 * Plugin deactivation hook.
 */
function bookrec_deactivate(): void {
    // Clear any Google Books transients
    $googlebooks = new BookRec_GoogleBooks_Service();
    $googlebooks->clearCache();
    
    // Clear any other plugin transients
    global $wpdb;
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $wpdb->esc_like('_transient_bookrec_') . '%',
            $wpdb->esc_like('_transient_timeout_bookrec_') . '%'
        )
    );
    
    // Unschedule cron events
    if (class_exists('BookRec_Cron')) {
        $cron = new BookRec_Cron();
        $cron->unschedule_events();
    }
    
    // Clean up ShareCard rewrite rules
    if (class_exists('BookRec_ShareCard')) {
        BookRec_ShareCard::deactivate();
    }
}

/**
 * Include Composer autoloader if it exists
 */
$composer_autoload = BOOKREC_PLUGIN_DIR . 'vendor/autoload.php';
if (file_exists($composer_autoload)) {
    require_once $composer_autoload;
}

/**
 * Include required files
 */
require_once BOOKREC_PLUGIN_DIR . 'includes/config-helper.php';
require_once BOOKREC_PLUGIN_DIR . 'includes/class-bookrec-settings.php';
require_once BOOKREC_PLUGIN_DIR . 'includes/class-bookrec-shortcode.php';
require_once BOOKREC_PLUGIN_DIR . 'includes/class-bookrec-assets.php';
require_once BOOKREC_PLUGIN_DIR . 'includes/class-bookrec-state.php';
require_once BOOKREC_PLUGIN_DIR . 'includes/class-bookrec-openai.php';
require_once BOOKREC_PLUGIN_DIR . 'includes/class-bookrec-googlebooks.php';
require_once BOOKREC_PLUGIN_DIR . 'includes/class-bookrec-recommendation.php';
require_once BOOKREC_PLUGIN_DIR . 'includes/class-bookrec-ajax.php';
require_once BOOKREC_PLUGIN_DIR . 'includes/class-bookrec-dashboard.php';
require_once BOOKREC_PLUGIN_DIR . 'includes/class-bookrec-analytics.php';
require_once BOOKREC_PLUGIN_DIR . 'includes/class-bookrec-trends.php';
require_once BOOKREC_PLUGIN_DIR . 'includes/class-bookrec-cron.php';
require_once BOOKREC_PLUGIN_DIR . 'includes/class-bookrec-sharecard.php';
require_once BOOKREC_PLUGIN_DIR . 'includes/class-bookrec-email.php';

// Include admin dashboard widget
if (is_admin()) {
    require_once BOOKREC_PLUGIN_DIR . 'admin/dashboard-widget.php';
}

// Register hooks
register_activation_hook(__FILE__, 'bookrec_activate');
register_deactivation_hook(__FILE__, 'bookrec_deactivate');

// Initialize classes
$bookrec_settings = new BookRec_Settings();
$bookrec_shortcode = new BookRec_Shortcode();
$bookrec_assets = new BookRec_Assets();
$bookrec_ajax = new BookRec_Ajax();
$bookrec_dashboard = new BookRec_Dashboard();
$bookrec_analytics = new BookRec_Analytics();
$bookrec_cron = new BookRec_Cron();
$bookrec_sharecard = new BookRec_ShareCard();