<?php
/**
 * Book Recommender Dashboard Widget Template
 *
 * @package   Good E-Reader Book Recommender
 */

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

// Get statistics
$stats = $this->get_recommendation_stats();
?>

<div class="bookrec-dashboard-widget">
    <div class="bookrec-stats-overview">
        <div class="bookrec-stats-grid">
            <div class="bookrec-stat-card">
                <h3><?php echo esc_html($stats['total_recommendations']); ?></h3>
                <p>Total Recommendations</p>
            </div>
            <div class="bookrec-stat-card">
                <h3><?php echo esc_html($stats['total_sessions']); ?></h3>
                <p>Total Sessions</p>
            </div>
            <div class="bookrec-stat-card">
                <h3><?php echo esc_html($stats['amazon_clicks']); ?></h3>
                <p>Amazon Link Clicks</p>
            </div>
            <div class="bookrec-stat-card">
                <h3><?php echo esc_html($stats['est_revenue']); ?></h3>
                <p>Estimated Revenue</p>
            </div>
        </div>
    </div>
    
    <div class="bookrec-top-recommendations">
        <h3>Top Recommended Books</h3>
        <?php if (empty($stats['top_books'])) : ?>
            <p class="bookrec-no-data">No recommendation data available yet.</p>
        <?php else : ?>
            <table class="bookrec-top-books-table">
                <thead>
                    <tr>
                        <th>Book</th>
                        <th>Author</th>
                        <th>Times Recommended</th>
                        <th>Click Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['top_books'] as $book) : ?>
                        <tr>
                            <td><?php echo esc_html($book['title']); ?></td>
                            <td><?php echo esc_html($book['author']); ?></td>
                            <td><?php echo esc_html($book['count']); ?></td>
                            <td><?php echo esc_html($book['click_rate']); ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <div class="bookrec-timeframe-selector">
        <button type="button" data-timeframe="7" class="active">Last 7 Days</button>
        <button type="button" data-timeframe="30">Last 30 Days</button>
        <button type="button" data-timeframe="90">Last 90 Days</button>
        <button type="button" data-timeframe="all">All Time</button>
    </div>
</div>

<style>
.bookrec-dashboard-widget {
    padding: 12px;
}

.bookrec-stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
    margin-bottom: 20px;
}

.bookrec-stat-card {
    padding: 16px;
    background: #f9f9f9;
    border-radius: 4px;
    border: 1px solid #e0e0e0;
    text-align: center;
}

.bookrec-stat-card h3 {
    margin: 0;
    font-size: 24px;
    font-weight: 600;
    color: #1d2327;
}

.bookrec-stat-card p {
    margin: 5px 0 0;
    font-size: 13px;
    color: #50575e;
}

.bookrec-top-recommendations {
    margin-top: 20px;
}

.bookrec-top-books-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

.bookrec-top-books-table th, 
.bookrec-top-books-table td {
    padding: 8px;
    text-align: left;
    border-bottom: 1px solid #e0e0e0;
}

.bookrec-top-books-table th {
    background: #f0f0f1;
}

.bookrec-timeframe-selector {
    margin-top: 20px;
    display: flex;
    gap: 8px;
}

.bookrec-timeframe-selector button {
    padding: 6px 12px;
    background: #f0f0f1;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    cursor: pointer;
}

.bookrec-timeframe-selector button.active {
    background: #2271b1;
    color: white;
    border-color: #2271b1;
}

.bookrec-no-data {
    font-style: italic;
    color: #72777c;
    padding: 10px 0;
}
</style>