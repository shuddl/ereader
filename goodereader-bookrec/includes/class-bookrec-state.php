<?php
/**
 * Book Recommender State Management Class
 *
 * @package   Good E-Reader Book Recommender
 */

declare(strict_types=1);

if (!defined('WPINC')) {
    die;
}

/**
 * Class BookRec_State
 */
class BookRec_State {

    /**
     * Transient prefix
     */
    const TRANSIENT_PREFIX = 'bookrec_session_';

    /**
     * Get session state from transient
     *
     * @param string $session_id The session ID
     * @return array The session state data
     */
    public static function get_session_state(string $session_id): array {
        // Validate session ID format to prevent transient injection
        if (!self::is_valid_session_id($session_id)) {
            return self::get_default_state();
        }

        $transient_key = self::TRANSIENT_PREFIX . $session_id;
        $state = get_transient($transient_key);

        if (false === $state) {
            return self::get_default_state();
        }

        return (array) $state;
    }

    /**
     * Save session state to transient
     *
     * @param string $session_id The session ID
     * @param array  $state_data The state data to save
     * @param int    $ttl        Time to live in seconds
     * @return bool True if the value was set, false otherwise
     */
    public static function save_session_state(string $session_id, array $state_data, int $ttl = 1800): bool {
        // Validate session ID format
        if (!self::is_valid_session_id($session_id)) {
            return false;
        }

        $transient_key = self::TRANSIENT_PREFIX . $session_id;
        return set_transient($transient_key, $state_data, $ttl);
    }

    /**
     * Generate a new session ID
     *
     * @return string Unique session ID
     */
    public static function generate_session_id(): string {
        return wp_generate_uuid4();
    }

    /**
     * Get default state structure
     *
     * @return array Default state
     */
    private static function get_default_state(): array {
        return [
            'history' => [],
            'slots' => [],
            'created_at' => time(),
            'last_activity' => time(),
            'cta_variation' => null, // Will be populated during session initialization
        ];
    }

    /**
     * Validate session ID format
     *
     * @param string $session_id The session ID to validate
     * @return bool True if valid, false otherwise
     */
    private static function is_valid_session_id(string $session_id): bool {
        // UUID format validation (basic check)
        return 1 === preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $session_id);
    }
}