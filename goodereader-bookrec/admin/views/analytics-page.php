<?php
/**
 * Displays the BookRec analytics page.
 *
 * @package GoodEReader_BookRec
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="wrap bookrec-analytics-page">
    <h1><?php esc_html_e('Book Recommendation Analytics', 'goodereader-bookrec'); ?></h1>
    
    <div class="bookrec-analytics-header">
        <div class="bookrec-date-range-selector">
            <label for="bookrec-date-range"><?php esc_html_e('Date Range:', 'goodereader-bookrec'); ?></label>
            <select id="bookrec-date-range" class="bookrec-date-range">
                <option value="7"><?php esc_html_e('Last 7 Days', 'goodereader-bookrec'); ?></option>
                <option value="30" selected><?php esc_html_e('Last 30 Days', 'goodereader-bookrec'); ?></option>
                <option value="90"><?php esc_html_e('Last 90 Days', 'goodereader-bookrec'); ?></option>
                <option value="365"><?php esc_html_e('Last Year', 'goodereader-bookrec'); ?></option>
                <option value="custom"><?php esc_html_e('Custom Range', 'goodereader-bookrec'); ?></option>
            </select>
            
            <div id="bookrec-custom-date-range" class="bookrec-custom-date-range" style="display: none;">
                <input type="date" id="bookrec-date-start" class="bookrec-date-input">
                <span>-</span>
                <input type="date" id="bookrec-date-end" class="bookrec-date-input">
                <button id="bookrec-apply-date-range" class="button button-secondary"><?php esc_html_e('Apply', 'goodereader-bookrec'); ?></button>
            </div>
        </div>
        
        <div class="bookrec-refresh-controls">
            <button id="bookrec-refresh-analytics" class="button button-primary">
                <span class="dashicons dashicons-update"></span> <?php esc_html_e('Refresh Data', 'goodereader-bookrec'); ?>
            </button>
            <button id="bookrec-export-analytics" class="button button-secondary">
                <span class="dashicons dashicons-download"></span> <?php esc_html_e('Export CSV', 'goodereader-bookrec'); ?>
            </button>
        </div>
    </div>
    
    <div class="bookrec-analytics-dashboard">
        <!-- Top Stats Cards -->
        <div class="bookrec-stats-cards">
            <div class="bookrec-stat-card" id="total-clicks">
                <h3><?php esc_html_e('Total Clicks', 'goodereader-bookrec'); ?></h3>
                <div class="bookrec-stat-value">0</div>
                <div class="bookrec-stat-change"><span class="change-value">0%</span> vs previous period</div>
            </div>
            
            <div class="bookrec-stat-card" id="total-revenue">
                <h3><?php esc_html_e('Est. Revenue', 'goodereader-bookrec'); ?></h3>
                <div class="bookrec-stat-value">$0.00</div>
                <div class="bookrec-stat-change"><span class="change-value">0%</span> vs previous period</div>
            </div>
            
            <div class="bookrec-stat-card" id="click-rate">
                <h3><?php esc_html_e('Click Rate', 'goodereader-bookrec'); ?></h3>
                <div class="bookrec-stat-value">0%</div>
                <div class="bookrec-stat-change"><span class="change-value">0%</span> vs previous period</div>
            </div>
            
            <div class="bookrec-stat-card" id="total-sessions">
                <h3><?php esc_html_e('Total Sessions', 'goodereader-bookrec'); ?></h3>
                <div class="bookrec-stat-value">0</div>
                <div class="bookrec-stat-change"><span class="change-value">0%</span> vs previous period</div>
            </div>
        </div>
        
        <!-- Charts Section -->
        <div class="bookrec-charts-section">
            <div class="bookrec-chart-container">
                <h3><?php esc_html_e('Click Trends', 'goodereader-bookrec'); ?></h3>
                <div id="bookrec-clicks-chart" class="bookrec-chart"></div>
            </div>
        </div>
        
        <!-- A/B Testing Results -->
        <div class="bookrec-ab-testing-section">
            <h3><?php esc_html_e('A/B Testing Results', 'goodereader-bookrec'); ?></h3>
            <div class="bookrec-ab-container">
                <div class="bookrec-ab-variant" id="variant-a">
                    <h4><?php esc_html_e('Variant A', 'goodereader-bookrec'); ?></h4>
                    <div class="bookrec-ab-stat clicks">
                        <span class="label"><?php esc_html_e('Clicks:', 'goodereader-bookrec'); ?></span>
                        <span class="value">0</span>
                    </div>
                    <div class="bookrec-ab-stat rate">
                        <span class="label"><?php esc_html_e('Click Rate:', 'goodereader-bookrec'); ?></span>
                        <span class="value">0%</span>
                    </div>
                </div>
                
                <div class="bookrec-ab-variant" id="variant-b">
                    <h4><?php esc_html_e('Variant B', 'goodereader-bookrec'); ?></h4>
                    <div class="bookrec-ab-stat clicks">
                        <span class="label"><?php esc_html_e('Clicks:', 'goodereader-bookrec'); ?></span>
                        <span class="value">0</span>
                    </div>
                    <div class="bookrec-ab-stat rate">
                        <span class="label"><?php esc_html_e('Click Rate:', 'goodereader-bookrec'); ?></span>
                        <span class="value">0%</span>
                    </div>
                </div>
                
                <div class="bookrec-ab-winner">
                    <h4><?php esc_html_e('Winner', 'goodereader-bookrec'); ?></h4>
                    <div class="bookrec-ab-stat winner">
                        <span class="value"><?php esc_html_e('Not enough data', 'goodereader-bookrec'); ?></span>
                    </div>
                    <div class="bookrec-ab-stat difference">
                        <span class="label"><?php esc_html_e('Improvement:', 'goodereader-bookrec'); ?></span>
                        <span class="value">0%</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Top Books Table -->
        <div class="bookrec-top-books-section">
            <h3><?php esc_html_e('Top Performing Books', 'goodereader-bookrec'); ?></h3>
            <table class="wp-list-table widefat fixed striped bookrec-top-books-table">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e('Book Title', 'goodereader-bookrec'); ?></th>
                        <th scope="col"><?php esc_html_e('Author', 'goodereader-bookrec'); ?></th>
                        <th scope="col"><?php esc_html_e('Clicks', 'goodereader-bookrec'); ?></th>
                        <th scope="col"><?php esc_html_e('Click Rate', 'goodereader-bookrec'); ?></th>
                        <th scope="col"><?php esc_html_e('Est. Revenue', 'goodereader-bookrec'); ?></th>
                    </tr>
                </thead>
                <tbody id="bookrec-top-books-data">
                    <tr class="no-items">
                        <td class="colspanchange" colspan="5"><?php esc_html_e('No data available.', 'goodereader-bookrec'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Recommendations Performance -->
        <div class="bookrec-recommendations-section">
            <h3><?php esc_html_e('Recommendation Performance', 'goodereader-bookrec'); ?></h3>
            <div class="bookrec-rec-stats">
                <div class="bookrec-rec-stat">
                    <h4><?php esc_html_e('Total Recommendations', 'goodereader-bookrec'); ?></h4>
                    <div class="value">0</div>
                </div>
                
                <div class="bookrec-rec-stat">
                    <h4><?php esc_html_e('Click Rate', 'goodereader-bookrec'); ?></h4>
                    <div class="value">0%</div>
                </div>
                
                <div class="bookrec-rec-stat">
                    <h4><?php esc_html_e('Avg. Revenue/Rec', 'goodereader-bookrec'); ?></h4>
                    <div class="value">$0.00</div>
                </div>
            </div>
        </div>
    </div>
</div>