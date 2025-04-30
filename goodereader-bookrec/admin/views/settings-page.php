<?php
/**
 * Admin settings page template
 *
 * @package   Good E-Reader Book Recommender
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Check user capability
if (!current_user_can('manage_options')) {
    return;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <form method="post" action="options.php">
        <?php settings_fields('bookrec_settings_group'); ?>
        
        <h2 class="nav-tab-wrapper">
            <a href="#tab-api-keys" class="nav-tab nav-tab-active"><?php esc_html_e('API Keys', 'goodereader-bookrec'); ?></a>
            <a href="#tab-configuration" class="nav-tab"><?php esc_html_e('Configuration', 'goodereader-bookrec'); ?></a>
            <a href="#tab-prompts" class="nav-tab"><?php esc_html_e('AI Prompts', 'goodereader-bookrec'); ?></a>
            <a href="#tab-styling" class="nav-tab"><?php esc_html_e('Styling', 'goodereader-bookrec'); ?></a>
        </h2>
        
        <div id="tab-api-keys" class="tab-content active">
            <table class="form-table">
                <?php do_settings_fields('bookrec_settings_page', 'bookrec_api_keys_section'); ?>
            </table>
        </div>
        
        <div id="tab-configuration" class="tab-content">
            <table class="form-table">
                <?php do_settings_fields('bookrec_settings_page', 'bookrec_config_section'); ?>
            </table>
        </div>
        
        <div id="tab-prompts" class="tab-content">
            <table class="form-table">
                <?php do_settings_fields('bookrec_settings_page', 'bookrec_prompts_section'); ?>
            </table>
        </div>
        
        <div id="tab-styling" class="tab-content">
            <table class="form-table">
                <?php do_settings_fields('bookrec_settings_page', 'bookrec_styling_section'); ?>
                <tr>
                    <th scope="row"><?php esc_html_e('Color Scheme', 'goodereader-bookrec'); ?></th>
                    <td>
                        <p><?php esc_html_e('Styling options will be available in a future update.', 'goodereader-bookrec'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        
        <?php submit_button(); ?>
    </form>
</div>

<script type="text/javascript">
    jQuery(document).ready(function($) {
        // Tabs functionality
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();
            
            // Hide all tab contents
            $('.tab-content').removeClass('active');
            
            // Remove active class from all tabs
            $('.nav-tab').removeClass('nav-tab-active');
            
            // Add active class to current tab
            $(this).addClass('nav-tab-active');
            
            // Show current tab content
            $($(this).attr('href')).addClass('active');
        });
    });
</script>

<style type="text/css">
    .tab-content {
        display: none;
        padding: 15px 0;
    }
    
    .tab-content.active {
        display: block;
    }
</style>