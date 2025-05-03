<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__FILE__) . '/../chatbot-pinecone.php';
require_once dirname(__FILE__) . '/../chatbot-log.php';

class PineconeDataStandardizer {
    private $backup_dir;
    private $logger;
    
    public function __construct() {
        $this->backup_dir = WP_CONTENT_DIR . '/pinecone-backups';
        $this->logger = new ChatbotLogger();
        
        // Create backup directory if it doesn't exist
        if (!file_exists($this->backup_dir)) {
            wp_mkdir_p($this->backup_dir);
        }
    }
    
    // Create backup of current Pinecone data
    private function backup_current_data($records) {
        $timestamp = date('Y-m-d_H-i-s');
        $backup_file = $this->backup_dir . "/pinecone_backup_{$timestamp}.json";
        
        $this->logger->log("📦 Creating backup at: {$backup_file}");
        
        if (file_put_contents($backup_file, json_encode($records, JSON_PRETTY_PRINT))) {
            $this->logger->log("✅ Backup created successfully");
            return true;
        }
        
        $this->logger->log("❌ Failed to create backup");
        return false;
    }
    
    // Standardize counties
    private function standardize_county($county) {
        $valid_counties = [
            'clay',
            'duval',
            'st. johns',
            'nassau',
            'flagler'
        ];
        
        if (is_array($county)) {
            return array_map('strtolower', array_intersect($county, $valid_counties));
        }
        
        $county = strtolower(trim($county));
        return in_array($county, $valid_counties) ? $county : null;
    }
    
    // Standardize city names and map to counties
    private function standardize_city($city) {
        $city_county_map = [
            'orange park' => 'clay',
            'middleburg' => 'clay',
            'fleming island' => 'clay',
            'green cove springs' => 'clay',
            'jacksonville' => 'duval',
            'st. augustine' => 'st. johns',
            'fernandina beach' => 'nassau',
            'palm coast' => 'flagler'
        ];
        
        $city = strtolower(trim($city));
        return [
            'city' => $city,
            'county' => isset($city_county_map[$city]) ? $city_county_map[$city] : null
        ];
    }
    
    // Standardize industry data
    private function standardize_industry($industry, $synonyms = []) {
        if (is_array($industry)) {
            $industry = array_map('strtolower', array_filter($industry));
        } else {
            $industry = strtolower(trim($industry));
        }
        
        if (!empty($synonyms)) {
            if (is_string($synonyms)) {
                $synonyms = explode(',', $synonyms);
            }
            $synonyms = array_map('trim', array_map('strtolower', array_filter($synonyms)));
        }
        
        return [
            'industry' => $industry,
            'industry_synonyms' => $synonyms
        ];
    }
    
    // Main standardization function
    public function standardize_records() {
        $this->logger->log("🔄 Starting data standardization process");
        
        try {
            // Fetch all records
            $records = fetch_all_pinecone_records();
            $this->logger->log("📊 Found " . count($records) . " records to process");
            
            // Create backup
            if (!$this->backup_current_data($records)) {
                throw new Exception("Failed to create backup");
            }
            
            $updated_count = 0;
            $failed_count = 0;
            
            foreach ($records as $record) {
                try {
                    $metadata = $record['metadata'];
                    $updated = false;
                    
                    // Standardize county
                    if (isset($metadata['county'])) {
                        $metadata['county'] = $this->standardize_county($metadata['county']);
                        $updated = true;
                    }
                    
                    // Standardize city and map to county
                    if (isset($metadata['city'])) {
                        $city_data = $this->standardize_city($metadata['city']);
                        $metadata['city'] = $city_data['city'];
                        if ($city_data['county'] && empty($metadata['county'])) {
                            $metadata['county'] = $city_data['county'];
                            $updated = true;
                        }
                    }
                    
                    // Standardize industry data
                    if (isset($metadata['industry']) || isset($metadata['industry_synonyms'])) {
                        $industry_data = $this->standardize_industry(
                            $metadata['industry'] ?? '',
                            $metadata['industry_synonyms'] ?? []
                        );
                        $metadata['industry'] = $industry_data['industry'];
                        $metadata['industry_synonyms'] = $industry_data['industry_synonyms'];
                        $updated = true;
                    }
                    
                    if ($updated) {
                        if (update_pinecone_record($record['id'], $metadata)) {
                            $this->logger->log("✅ Updated record: {$record['id']}");
                            $updated_count++;
                        } else {
                            throw new Exception("Failed to update record");
                        }
                    }
                } catch (Exception $e) {
                    $this->logger->log("❌ Error processing record {$record['id']}: " . $e->getMessage());
                    $failed_count++;
                }
            }
            
            $this->logger->log("🎉 Standardization complete");
            $this->logger->log("📊 Summary:");
            $this->logger->log("   - Total records: " . count($records));
            $this->logger->log("   - Updated: {$updated_count}");
            $this->logger->log("   - Failed: {$failed_count}");
            
            return [
                'success' => true,
                'total' => count($records),
                'updated' => $updated_count,
                'failed' => $failed_count
            ];
            
        } catch (Exception $e) {
            $this->logger->log("❌ Fatal error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

// Admin page integration
add_action('admin_menu', function() {
    add_management_page(
        'Standardize Pinecone Data',
        'Standardize Data',
        'manage_options',
        'standardize-pinecone-data',
        'render_standardize_page'
    );
});

function render_standardize_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $standardizer = new PineconeDataStandardizer();
    
    echo '<div class="wrap">';
    echo '<h1>Standardize Pinecone Data</h1>';
    
    if (isset($_POST['standardize_data']) && check_admin_referer('standardize_pinecone_data')) {
        $result = $standardizer->standardize_records();
        
        if ($result['success']) {
            echo '<div class="notice notice-success"><p>';
            echo "Standardization completed successfully:<br>";
            echo "- Total records: {$result['total']}<br>";
            echo "- Updated: {$result['updated']}<br>";
            echo "- Failed: {$result['failed']}<br>";
            echo "Check the chatbot logs for detailed information.";
            echo '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>';
            echo "Standardization failed: {$result['error']}<br>";
            echo "Check the chatbot logs for detailed information.";
            echo '</p></div>';
        }
    }
    
    echo '<form method="post">';
    wp_nonce_field('standardize_pinecone_data');
    echo '<div class="card" style="max-width: 600px; margin-top: 20px; padding: 20px;">';
    echo '<h2>Data Standardization Process</h2>';
    echo '<p>This tool will standardize your Pinecone records to ensure consistent formatting for:</p>';
    echo '<ul style="list-style-type: disc; margin-left: 20px;">';
    echo '<li>Counties (lowercase, validated against known counties)</li>';
    echo '<li>Cities (mapped to correct counties)</li>';
    echo '<li>Industries (lowercase, consistent array format)</li>';
    echo '<li>Industry synonyms (lowercase, array format)</li>';
    echo '</ul>';
    echo '<p><strong>Important:</strong></p>';
    echo '<ul style="list-style-type: disc; margin-left: 20px;">';
    echo '<li>A backup will be created before any changes are made</li>';
    echo '<li>The process is logged in the chatbot logs</li>';
    echo '<li>This process cannot be undone (except by restoring from backup)</li>';
    echo '</ul>';
    echo '</div>';
    submit_button('Start Standardization', 'primary', 'standardize_data', true, ['style' => 'margin-top: 20px;']);
    echo '</form>';
    echo '</div>';
}