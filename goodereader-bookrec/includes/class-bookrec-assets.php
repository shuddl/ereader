<?php
/**
 * Book Recommender Assets Class
 *
 * @package   Good E-Reader Book Recommender
 */

declare(strict_types=1);

if (!defined('WPINC')) {
    die;
}

/**
 * Class BookRec_Assets
 */
class BookRec_Assets {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_assets']);
        add_action('admin_enqueue_scripts', [$this, 'register_admin_assets']);
    }

    /**
     * Register scripts and styles
     */
    public function register_assets(): void {
        // Get the bundle filename - if we're in production, look for hashed filename
        $js_bundle_filename = 'bundle.js';
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            $manifest_file = BOOKREC_PLUGIN_DIR . 'assets/js/dist/manifest.json';
            if (file_exists($manifest_file)) {
                $manifest = json_decode(file_get_contents($manifest_file), true);
                if (isset($manifest['main.js'])) {
                    $js_bundle_filename = $manifest['main.js'];
                }
            } else {
                // If manifest doesn't exist, try to find hashed bundle file directly
                $files = glob(BOOKREC_PLUGIN_DIR . 'assets/js/dist/bundle.*.js');
                if (!empty($files)) {
                    $js_bundle_filename = basename($files[0]);
                }
            }
        }
        
        // Register main script
        wp_register_script(
            'bookrec-frontend',
            BOOKREC_PLUGIN_URL . 'assets/js/dist/' . $js_bundle_filename,
            [], // We're bundling React with our app
            null, // Version handled by the hash in filename
            true  // Load in footer
        );

        // Register main style (if separately built)
        wp_register_style(
            'bookrec-frontend',
            BOOKREC_PLUGIN_URL . 'assets/css/bookrec-frontend.css',
            [],
            $this->get_asset_version()
        );
    }

    /**
     * Conditionally enqueue assets if shortcode is present
     */
    public function maybe_enqueue_assets(): void {
        global $post;

        // Only enqueue on singular pages or posts that might have our shortcode
        if (!is_singular() || !is_a($post, 'WP_Post')) {
            return;
        }

        // Check if the shortcode exists in the content
        if (has_shortcode($post->post_content, 'bookrec')) {
            // Enqueue script and style
            wp_enqueue_script('bookrec-frontend');
            wp_enqueue_style('bookrec-frontend');

            // Get container-specific data from shortcode instances
            $container_data = apply_filters('bookrec_container_data', []);

            // Localize script with AJAX URL, nonce, and container-specific data
            wp_localize_script(
                'bookrec-frontend',
                'bookrec_ajax_data',
                [
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('bookrec_chat_nonce'),
                    'default_recommendations' => (int) bookrec_get_config('default_recommendations', 3),
                    // Other global settings
                    'settings' => [
                        'amazon_tag' => bookrec_get_config('amazon_tag', 'gooderead-20'),
                        'ab_test_cta' => bookrec_get_config('ab_test_cta', 'enabled') === 'enabled',
                    ],
                    'click_nonce' => wp_create_nonce('bookrec_click_nonce'),
                    'email_nonce' => wp_create_nonce('bookrec_email_nonce'),
                    'rest_url' => rest_url(),
                    // Container-specific data
                    'containers' => $container_data,
                ]
            );
        }
    }

    /**
     * Register admin scripts and styles
     *
     * @param string $hook_suffix The current admin page.
     */
    public function register_admin_assets(string $hook_suffix): void {
        // Dashboard scripts
        if ('index.php' === $hook_suffix) {
            wp_enqueue_script(
                'bookrec-dashboard-js',
                BOOKREC_PLUGIN_URL . 'admin/js/dashboard.js',
                ['jquery'],
                $this->get_asset_version(),
                true
            );
        }
    }

    /**
     * Get asset version based on file modification time or plugin version
     */
    private function get_asset_version(): string {
        // Use file modification time in development for cache busting
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $js_file = BOOKREC_PLUGIN_DIR . 'assets/js/dist/bundle.js';
            if (file_exists($js_file)) {
                return (string) filemtime($js_file);
            }
        }

        // Use plugin version in production
        return BOOKREC_VERSION;
    }
}