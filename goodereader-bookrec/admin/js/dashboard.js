/**
 * BookRec Dashboard Widget JavaScript
 */
(function($) {
    'use strict';

    /**
     * Initialize dashboard widget interactivity
     */
    function initDashboardWidget() {
        // Handle refresh button click
        $('#bookrec-refresh-stats').on('click', function() {
            refreshDashboardData();
        });
    }

    /**
     * Refresh the dashboard widget data via AJAX
     */
    function refreshDashboardData() {
        // Add loading indicator to button
        const $button = $('#bookrec-refresh-stats');
        const originalHtml = $button.html();
        $button.html('<span class="dashicons dashicons-update" style="animation: spin 1.5s linear infinite;"></span> Refreshing...');
        $button.prop('disabled', true);
        
        // Reload the widget
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wp_dashboard_dynamic_widgets',
                widget: 'bookrec_affiliate_stats',
                nonce: wpWidgets.nonce
            },
            success: function(response) {
                // Replace widget content
                if (response) {
                    $('.bookrec-dashboard-widget').closest('.postbox').html(response);
                    initDashboardWidget();
                }
            },
            error: function() {
                $button.html(originalHtml);
                $button.prop('disabled', false);
            }
        });
    }

    // Add keyframes for spin animation
    $('<style>')
        .prop('type', 'text/css')
        .html('@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }')
        .appendTo('head');

    // Initialize when document is ready
    $(document).ready(function() {
        initDashboardWidget();
    });
    
})(jQuery);