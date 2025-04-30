<?php
/**
 * Uninstall Good E-Reader Book Recommender
 *
 * @package   Good E-Reader Book Recommender
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete all options created by the plugin
$options = [
    'bookrec_openai_key',
    'bookrec_gbooks_key',
    'bookrec_amazon_tag',
    'bookrec_genres',
    'bookrec_default_recommendations',
    'bookrec_cache_ttl',
    'bookrec_ab_test_cta',
    'bookrec_system_prompt',
    'bookrec_recommendation_prompt',
    'bookrec_version'
];

foreach ($options as $option) {
    delete_option($option);
}

// Delete any transients (to be added if needed)