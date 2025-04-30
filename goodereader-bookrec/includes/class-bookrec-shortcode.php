<?php
/**
 * Book Recommender Shortcode Class
 *
 * @package   Good E-Reader Book Recommender
 */

declare(strict_types=1);

if (!defined('WPINC')) {
    die;
}

/**
 * Class BookRec_Shortcode
 */
class BookRec_Shortcode {

    /**
     * Constructor
     */
    public function __construct() {
        add_shortcode('bookrec', [$this, 'render_shortcode']);
    }

    /**
     * Container IDs for localization
     * 
     * @var array
     */
    private $container_ids = [];

    /**
     * Render the [bookrec] shortcode
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     */
    public function render_shortcode($atts): string {
        // Parse shortcode attributes
        $atts = shortcode_atts(
            [
                'genre' => '', // Optional default genre
                'author_search' => 'false', // Enable/disable author search
            ],
            $atts,
            'bookrec'
        );

        // Generate a unique container ID
        $unique_id = wp_unique_id('bookrec-chatbot-');
        
        // Store container-specific data for localization
        $this->add_container_data($unique_id, $atts);

        // Return the container div
        return sprintf(
            '<div id="%s" class="bookrec-chatbot-container" data-genre="%s" data-author-search="%s"></div>',
            esc_attr($unique_id),
            esc_attr($atts['genre']),
            esc_attr($atts['author_search'])
        );
    }
    
    /**
     * Add container-specific data for script localization
     *
     * @param string $container_id Unique container ID
     * @param array  $atts         Shortcode attributes
     */
    private function add_container_data(string $container_id, array $atts): void {
        $this->container_ids[$container_id] = [
            'attributes' => $atts,
        ];
        
        // Add the container data to the global WordPress instance
        add_filter('bookrec_container_data', function($data) {
            return array_merge($data, $this->container_ids);
        });
    }
    
    /**
     * Get all container IDs and their attributes
     *
     * @return array Container data
     */
    public function get_container_data(): array {
        return $this->container_ids;
    }
}