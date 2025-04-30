/**
 * Book Recommender Admin Scripts
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Handle tab switching
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
            
            // Save active tab in localStorage
            localStorage.setItem('bookrec_active_tab', $(this).attr('href'));
        });
        
        // Restore active tab from localStorage if available
        const activeTab = localStorage.getItem('bookrec_active_tab');
        if (activeTab) {
            $('.nav-tab[href="' + activeTab + '"]').trigger('click');
        }
        
        // Show/hide password fields toggle
        $('.toggle-password-visibility').on('click', function(e) {
            e.preventDefault();
            
            const passwordField = $($(this).data('target'));
            const fieldType = passwordField.attr('type');
            
            if (fieldType === 'password') {
                passwordField.attr('type', 'text');
                $(this).text('Hide');
            } else {
                passwordField.attr('type', 'password');
                $(this).text('Show');
            }
        });
    });
})(jQuery);