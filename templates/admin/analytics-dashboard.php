<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap chatbot-analytics">
    <h1>Chatbot Analytics</h1>
    
    <div class="stats-overview">
        <div class="stat-box">
            <h3>Total Interactions</h3>
            <p><?php echo number_format($total_interactions); ?></p>
        </div>
        <div class="stat-box">
            <h3>Success Rate</h3>
            <p><?php echo number_format($success_rate * 100, 1); ?>%</p>
        </div>
    </div>

    <div class="stats-details">
        <h2>Top Searched Industries</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Industry</th>
                    <th>Searches</th>
                    <th>Success Rate</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($top_industries as $industry): ?>
                    <tr>
                        <td><?php echo esc_html($industry->industry); ?></td>
                        <td><?php echo number_format($industry->count); ?></td>
                        <td><?php echo number_format($industry->success_rate * 100, 1); ?>%</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="stats-details recent-interactions">
        <h2>Recent Interactions</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Time (UTC)</th>
                    <th>Industry</th>
                    <th>County</th>
                    <th>City</th>
                    <th>Results</th>
                    <th>Level</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_interactions as $interaction): ?>
                    <tr>
                        <td><?php echo esc_html($interaction->timestamp_utc); ?></td>
                        <td><?php echo esc_html($interaction->industry); ?></td>
                        <td><?php echo esc_html($interaction->county); ?></td>
                        <td><?php echo esc_html($interaction->city); ?></td>
                        <td><?php echo number_format($interaction->found_results); ?></td>
                        <td><?php echo esc_html($interaction->search_level); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>