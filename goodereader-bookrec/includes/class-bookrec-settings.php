<?php
/**
 * Book Recommender Settings Class
 *
 * @package   Good E-Reader Book Recommender
 */

declare(strict_types=1);

if (!defined('WPINC')) {
    die;
}

/**
 * Class BookRec_Settings
 */
class BookRec_Settings {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook): void {
        // Only load on the plugin settings page
        if ($hook !== 'settings_page_goodereader-bookrec') {
            return;
        }

        // Enqueue tabs script
        wp_enqueue_script(
            'bookrec-admin-js',
            BOOKREC_PLUGIN_URL . 'admin/js/admin.js',
            ['jquery'],
            BOOKREC_VERSION,
            true
        );

        // Enqueue admin styles
        wp_enqueue_style(
            'bookrec-admin-css',
            BOOKREC_PLUGIN_URL . 'admin/css/admin.css',
            [],
            BOOKREC_VERSION
        );
    }

    /**
     * Add options page to admin menu
     */
    public function add_admin_menu(): void {
        add_options_page(
            esc_html__('Book Recommender Settings', 'goodereader-bookrec'),
            esc_html__('Book Recommender', 'goodereader-bookrec'),
            'manage_options',
            'goodereader-bookrec',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register settings
     */
    public function register_settings(): void {
        // Register settings group
        register_setting(
            'bookrec_settings_group',
            'bookrec_openai_key'
        );
        
        register_setting(
            'bookrec_settings_group',
            'bookrec_gbooks_key'
        );
        
        register_setting(
            'bookrec_settings_group',
            'bookrec_amazon_tag'
        );
        
        register_setting(
            'bookrec_settings_group',
            'bookrec_genres'
        );
        
        register_setting(
            'bookrec_settings_group',
            'bookrec_default_recommendations',
            [
                'type' => 'integer',
                'sanitize_callback' => [$this, 'sanitize_integer'],
            ]
        );
        
        register_setting(
            'bookrec_settings_group',
            'bookrec_cache_ttl',
            [
                'type' => 'integer',
                'sanitize_callback' => [$this, 'sanitize_integer'],
            ]
        );
        
        register_setting(
            'bookrec_settings_group',
            'bookrec_ab_test_cta'
        );
        
        register_setting(
            'bookrec_settings_group',
            'bookrec_include_trending_books',
            [
                'type' => 'integer',
                'sanitize_callback' => [$this, 'sanitize_integer'],
            ]
        );
        
        register_setting(
            'bookrec_settings_group',
            'bookrec_system_prompt'
        );
        
        register_setting(
            'bookrec_settings_group',
            'bookrec_recommendation_prompt'
        );

        // API Keys Section
        add_settings_section(
            'bookrec_api_keys_section',
            esc_html__('API Keys', 'goodereader-bookrec'),
            [$this, 'render_api_keys_section'],
            'bookrec_settings_page'
        );

        add_settings_field(
            'bookrec_openai_key',
            esc_html__('OpenAI API Key', 'goodereader-bookrec'),
            [$this, 'render_openai_key_field'],
            'bookrec_settings_page',
            'bookrec_api_keys_section'
        );

        add_settings_field(
            'bookrec_gbooks_key',
            esc_html__('Google Books API Key', 'goodereader-bookrec'),
            [$this, 'render_gbooks_key_field'],
            'bookrec_settings_page',
            'bookrec_api_keys_section'
        );

        add_settings_field(
            'bookrec_amazon_tag',
            esc_html__('Amazon Affiliate Tag', 'goodereader-bookrec'),
            [$this, 'render_amazon_tag_field'],
            'bookrec_settings_page',
            'bookrec_api_keys_section'
        );

        // Configuration Section
        add_settings_section(
            'bookrec_config_section',
            esc_html__('Configuration', 'goodereader-bookrec'),
            [$this, 'render_config_section'],
            'bookrec_settings_page'
        );

        add_settings_field(
            'bookrec_genres',
            esc_html__('Book Genres', 'goodereader-bookrec'),
            [$this, 'render_genres_field'],
            'bookrec_settings_page',
            'bookrec_config_section'
        );

        add_settings_field(
            'bookrec_default_recommendations',
            esc_html__('Default Number of Recommendations', 'goodereader-bookrec'),
            [$this, 'render_default_recommendations_field'],
            'bookrec_settings_page',
            'bookrec_config_section'
        );

        add_settings_field(
            'bookrec_cache_ttl',
            esc_html__('Cache Duration (seconds)', 'goodereader-bookrec'),
            [$this, 'render_cache_ttl_field'],
            'bookrec_settings_page',
            'bookrec_config_section'
        );

        add_settings_field(
            'bookrec_ab_test_cta',
            esc_html__('A/B Test Call-to-Actions', 'goodereader-bookrec'),
            [$this, 'render_ab_test_cta_field'],
            'bookrec_settings_page',
            'bookrec_config_section'
        );

        add_settings_field(
            'bookrec_include_trending_books',
            esc_html__('Include Trending Books', 'goodereader-bookrec'),
            [$this, 'render_include_trending_books_field'],
            'bookrec_settings_page',
            'bookrec_config_section'
        );

        // AI Prompts Section
        add_settings_section(
            'bookrec_prompts_section',
            esc_html__('AI Prompts', 'goodereader-bookrec'),
            [$this, 'render_prompts_section'],
            'bookrec_settings_page'
        );

        add_settings_field(
            'bookrec_system_prompt',
            esc_html__('System Prompt', 'goodereader-bookrec'),
            [$this, 'render_system_prompt_field'],
            'bookrec_settings_page',
            'bookrec_prompts_section'
        );

        add_settings_field(
            'bookrec_recommendation_prompt',
            esc_html__('Recommendation Template', 'goodereader-bookrec'),
            [$this, 'render_recommendation_prompt_field'],
            'bookrec_settings_page',
            'bookrec_prompts_section'
        );

        // Styling Section
        add_settings_section(
            'bookrec_styling_section',
            esc_html__('Styling', 'goodereader-bookrec'),
            [$this, 'render_styling_section'],
            'bookrec_settings_page'
        );
    }

    /**
     * Sanitize integer values
     */
    public function sanitize_integer($value): int {
        return absint($value);
    }

    /**
     * Render settings page
     */
    public function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        require_once BOOKREC_PLUGIN_DIR . 'admin/views/settings-page.php';
    }

    /**
     * Section callbacks
     */
    public function render_api_keys_section(): void {
        echo '<p>' . esc_html__('Enter your API keys below. These are required for the plugin to function properly.', 'goodereader-bookrec') . '</p>';
    }

    public function render_config_section(): void {
        echo '<p>' . esc_html__('Configure the behavior of the book recommendation chatbot.', 'goodereader-bookrec') . '</p>';
    }

    public function render_prompts_section(): void {
        echo '<p>' . esc_html__('Customize the AI prompts used by the recommendation engine.', 'goodereader-bookrec') . '</p>';
    }

    public function render_styling_section(): void {
        echo '<p>' . esc_html__('Customize the appearance of the chatbot (coming soon).', 'goodereader-bookrec') . '</p>';
    }

    /**
     * Field render callbacks
     */
    public function render_openai_key_field(): void {
        $value = get_option('bookrec_openai_key', '');
        echo '<input type="password" class="regular-text" id="bookrec_openai_key" name="bookrec_openai_key" value="' . esc_attr($value) . '" />';
        echo '<p class="description">' . esc_html__('Enter your OpenAI API key. Get one from ', 'goodereader-bookrec') . '<a href="https://platform.openai.com/api-keys" target="_blank">https://platform.openai.com/api-keys</a></p>';
    }

    public function render_gbooks_key_field(): void {
        $value = get_option('bookrec_gbooks_key', '');
        echo '<input type="password" class="regular-text" id="bookrec_gbooks_key" name="bookrec_gbooks_key" value="' . esc_attr($value) . '" />';
        echo '<p class="description">' . esc_html__('Enter your Google Books API key. Get one from ', 'goodereader-bookrec') . '<a href="https://developers.google.com/books/docs/v1/using#APIKey" target="_blank">Google Cloud Console</a></p>';
    }

    public function render_amazon_tag_field(): void {
        $value = get_option('bookrec_amazon_tag', 'gooderead-20');
        echo '<input type="text" class="regular-text" id="bookrec_amazon_tag" name="bookrec_amazon_tag" value="' . esc_attr($value) . '" />';
        echo '<p class="description">' . esc_html__('Your Amazon Associate ID for affiliate links.', 'goodereader-bookrec') . '</p>';
    }

    public function render_genres_field(): void {
        $value = get_option('bookrec_genres', '');
        echo '<textarea class="large-text code" id="bookrec_genres" name="bookrec_genres" rows="10">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . esc_html__('Enter book genres, one per line. These will be used for categorizing recommendations.', 'goodereader-bookrec') . '</p>';
    }

    public function render_default_recommendations_field(): void {
        $value = get_option('bookrec_default_recommendations', 3);
        echo '<input type="number" class="small-text" id="bookrec_default_recommendations" name="bookrec_default_recommendations" min="1" max="10" value="' . esc_attr($value) . '" />';
        echo '<p class="description">' . esc_html__('Default number of book recommendations to show per query.', 'goodereader-bookrec') . '</p>';
    }

    public function render_cache_ttl_field(): void {
        $value = get_option('bookrec_cache_ttl', 300);
        echo '<input type="number" class="small-text" id="bookrec_cache_ttl" name="bookrec_cache_ttl" min="0" value="' . esc_attr($value) . '" />';
        echo '<p class="description">' . esc_html__('Cache duration in seconds for API responses (0 to disable caching).', 'goodereader-bookrec') . '</p>';
    }

    public function render_ab_test_cta_field(): void {
        $value = get_option('bookrec_ab_test_cta', 'enabled');
        echo '<select id="bookrec_ab_test_cta" name="bookrec_ab_test_cta">';
        echo '<option value="enabled" ' . selected($value, 'enabled', false) . '>' . esc_html__('Enabled', 'goodereader-bookrec') . '</option>';
        echo '<option value="disabled" ' . selected($value, 'disabled', false) . '>' . esc_html__('Disabled', 'goodereader-bookrec') . '</option>';
        echo '</select>';
        echo '<p class="description">' . esc_html__('Enable A/B testing of different call-to-action buttons for book recommendations.', 'goodereader-bookrec') . '</p>';
    }

    public function render_system_prompt_field(): void {
        $value = get_option('bookrec_system_prompt', '');
        echo '<textarea class="large-text code" id="bookrec_system_prompt" name="bookrec_system_prompt" rows="5">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . esc_html__('System prompt for the GPT-4 Turbo model. Defines the chatbot\'s personality and behavior.', 'goodereader-bookrec') . '</p>';
    }

    public function render_recommendation_prompt_field(): void {
        $value = get_option('bookrec_recommendation_prompt', '');
        echo '<textarea class="large-text code" id="bookrec_recommendation_prompt" name="bookrec_recommendation_prompt" rows="5">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . esc_html__('Template used when generating book recommendations. Use {count} and {genre} as placeholders.', 'goodereader-bookrec') . '</p>';
    }
    
    public function render_include_trending_books_field(): void {
        $value = get_option('bookrec_include_trending_books', 1);
        echo '<input type="number" class="small-text" id="bookrec_include_trending_books" name="bookrec_include_trending_books" min="0" max="5" value="' . esc_attr($value) . '" />';
        echo '<p class="description">' . esc_html__('Number of trending books to include in recommendations (0 to disable).', 'goodereader-bookrec') . '</p>';
    }
}