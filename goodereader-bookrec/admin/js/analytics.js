/**
 * BookRec Analytics JavaScript
 */
(function($) {
    'use strict';

    // Initialize Chart.js library
    let clicksChart = null;

    // Store the current date range
    let currentRange = '30'; // Default to 30 days
    let customStartDate = null;
    let customEndDate = null;

    /**
     * Initialize the analytics page
     */
    function initAnalytics() {
        // Set up the date range selector
        $('#bookrec-date-range').on('change', function() {
            const selectedRange = $(this).val();
            
            if (selectedRange === 'custom') {
                $('#bookrec-custom-date-range').show();
                
                // Set default dates if not already set
                if (!customStartDate || !customEndDate) {
                    const today = new Date();
                    const thirtyDaysAgo = new Date();
                    thirtyDaysAgo.setDate(today.getDate() - 30);
                    
                    $('#bookrec-date-start').val(formatDate(thirtyDaysAgo));
                    $('#bookrec-date-end').val(formatDate(today));
                    
                    customStartDate = formatDate(thirtyDaysAgo);
                    customEndDate = formatDate(today);
                }
            } else {
                $('#bookrec-custom-date-range').hide();
                currentRange = selectedRange;
                loadAnalyticsData();
            }
        });
        
        // Set up custom date range controls
        $('#bookrec-apply-date-range').on('click', function() {
            customStartDate = $('#bookrec-date-start').val();
            customEndDate = $('#bookrec-date-end').val();
            
            if (customStartDate && customEndDate) {
                currentRange = 'custom';
                loadAnalyticsData();
            } else {
                alert(bookrec_analytics.i18n.select_both_dates);
            }
        });
        
        // Set up refresh button
        $('#bookrec-refresh-analytics').on('click', function() {
            loadAnalyticsData();
        });
        
        // Set up export button
        $('#bookrec-export-analytics').on('click', function() {
            exportAnalyticsData();
        });
        
        // Initial data load
        loadAnalyticsData();
    }
    
    /**
     * Load analytics data for the selected time period
     */
    function loadAnalyticsData() {
        const $refreshButton = $('#bookrec-refresh-analytics');
        const originalButtonText = $refreshButton.html();
        
        // Show loading state
        $refreshButton.html('<span class="dashicons dashicons-update spinning"></span> ' + bookrec_analytics.i18n.loading);
        $refreshButton.prop('disabled', true);
        
        // Make the AJAX request
        $.ajax({
            url: bookrec_analytics.ajax_url,
            type: 'POST',
            data: {
                action: 'bookrec_get_analytics_data',
                nonce: bookrec_analytics.nonce,
                range: currentRange,
                start_date: customStartDate,
                end_date: customEndDate
            },
            success: function(response) {
                if (response.success) {
                    updateAnalyticsUI(response.data);
                } else {
                    alert(response.data.message || bookrec_analytics.i18n.error_loading);
                }
            },
            error: function() {
                alert(bookrec_analytics.i18n.error_loading);
            },
            complete: function() {
                // Restore button state
                $refreshButton.html(originalButtonText);
                $refreshButton.prop('disabled', false);
            }
        });
    }
    
    /**
     * Update the UI with the loaded analytics data
     */
    function updateAnalyticsUI(data) {
        // Update stats cards
        updateStatsCards(data.stats);
        
        // Update charts
        updateCharts(data.charts);
        
        // Update A/B testing section
        updateABTestingSection(data.ab_testing);
        
        // Update top books table
        updateTopBooksTable(data.top_books);
        
        // Update recommendations stats
        updateRecommendationsStats(data.recommendations);
    }
    
    /**
     * Update the stats cards with current data
     */
    function updateStatsCards(stats) {
        // Helper function to update a stat card
        function updateCard(id, value, change) {
            const $card = $('#' + id);
            $card.find('.bookrec-stat-value').text(value);
            
            const $change = $card.find('.change-value');
            $change.text((change >= 0 ? '+' : '') + change + '%');
            $change.removeClass('positive negative');
            
            if (change > 0) {
                $change.addClass('positive');
            } else if (change < 0) {
                $change.addClass('negative');
            }
        }
        
        // Update each card
        updateCard('total-clicks', stats.total_clicks, stats.clicks_change);
        updateCard('total-revenue', '$' + stats.total_revenue.toFixed(2), stats.revenue_change);
        updateCard('click-rate', stats.click_rate + '%', stats.rate_change);
        updateCard('total-sessions', stats.total_sessions, stats.sessions_change);
    }
    
    /**
     * Update the analytics charts
     */
    function updateCharts(chartData) {
        const ctx = document.getElementById('bookrec-clicks-chart').getContext('2d');
        
        // Destroy previous chart if it exists
        if (clicksChart) {
            clicksChart.destroy();
        }
        
        // Create new chart
        clicksChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.labels,
                datasets: [{
                    label: bookrec_analytics.i18n.clicks,
                    data: chartData.clicks,
                    backgroundColor: 'rgba(0, 115, 170, 0.1)',
                    borderColor: '#0073aa',
                    borderWidth: 2,
                    tension: 0.2,
                    pointBackgroundColor: '#0073aa',
                    pointRadius: 3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return bookrec_analytics.i18n.clicks + ': ' + context.raw;
                            }
                        }
                    },
                    legend: {
                        display: false
                    }
                }
            }
        });
    }
    
    /**
     * Update the A/B testing section
     */
    function updateABTestingSection(abData) {
        // Update variant A
        $('#variant-a .clicks .value').text(abData.a.clicks);
        $('#variant-a .rate .value').text(abData.a.rate + '%');
        
        // Update variant B
        $('#variant-b .clicks .value').text(abData.b.clicks);
        $('#variant-b .rate .value').text(abData.b.rate + '%');
        
        // Update winner section
        if (abData.winner) {
            $('.bookrec-ab-winner .winner .value').text(
                abData.winner === 'a' ? bookrec_analytics.i18n.variant_a : bookrec_analytics.i18n.variant_b
            );
            $('.bookrec-ab-winner .difference .value').text('+' + abData.improvement + '%');
        } else {
            $('.bookrec-ab-winner .winner .value').text(bookrec_analytics.i18n.not_enough_data);
            $('.bookrec-ab-winner .difference .value').text('0%');
        }
    }
    
    /**
     * Update the top books table
     */
    function updateTopBooksTable(books) {
        const $tableBody = $('#bookrec-top-books-data');
        $tableBody.empty();
        
        if (books.length === 0) {
            $tableBody.html('<tr class="no-items"><td class="colspanchange" colspan="5">' + 
                           bookrec_analytics.i18n.no_data + '</td></tr>');
            return;
        }
        
        books.forEach(function(book) {
            const row = $('<tr></tr>');
            
            row.append('<td>' + book.title + '</td>');
            row.append('<td>' + book.author + '</td>');
            row.append('<td>' + book.clicks + '</td>');
            row.append('<td>' + book.rate + '%</td>');
            row.append('<td>$' + book.revenue.toFixed(2) + '</td>');
            
            $tableBody.append(row);
        });
    }
    
    /**
     * Update recommendations performance stats
     */
    function updateRecommendationsStats(recData) {
        $('.bookrec-rec-stats .bookrec-rec-stat:nth-child(1) .value').text(recData.total);
        $('.bookrec-rec-stats .bookrec-rec-stat:nth-child(2) .value').text(recData.rate + '%');
        $('.bookrec-rec-stats .bookrec-rec-stat:nth-child(3) .value').text('$' + recData.avg_revenue.toFixed(2));
    }
    
    /**
     * Export analytics data as CSV
     */
    function exportAnalyticsData() {
        window.location.href = bookrec_analytics.ajax_url + 
            '?action=bookrec_export_analytics&nonce=' + bookrec_analytics.nonce + 
            '&range=' + currentRange + 
            '&start_date=' + (customStartDate || '') + 
            '&end_date=' + (customEndDate || '');
    }
    
    /**
     * Format a date as YYYY-MM-DD
     */
    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
    
    // Initialize when document is ready
    $(document).ready(function() {
        initAnalytics();
    });
    
})(jQuery);