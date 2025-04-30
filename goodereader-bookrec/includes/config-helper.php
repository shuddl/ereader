<?php
/**
 * Configuration Helper Functions
 *
 * @package   Good E-Reader Book Recommender
 */

declare(strict_types=1);

if (!defined('WPINC')) {
    die;
}

/**
 * Get plugin configuration value.
 *
 * @param string $key     The configuration key (without the 'bookrec_' prefix).
 * @param mixed  $default Default value if option doesn't exist.
 * 
 * @return mixed The option value or default if not found.
 */
function bookrec_get_config(string $key, $default = null) {
    return get_option('bookrec_' . $key, $default);
}