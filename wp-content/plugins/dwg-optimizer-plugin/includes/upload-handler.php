<?php
/**
 * Upload Handler for DWG Optimizer Plugin
 * Handles file upload and initiates APS processing
 */

if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . 'aps-handler.php';

add_action('admin_post_nopriv_dwg_upload', 'dwg_upload_handler');
add_action('admin_post_dwg_upload', 'dwg_upload_handler');

function dwg_upload_handler() {
    // Verify file was uploaded
    if (!isset($_FILES['dwg_file']) || $_FILES['dwg_file']['error'] !== UPLOAD_ERR_OK) {
        wp_die('No file uploaded or upload error occurred.', 'Upload Error', ['response' => 400]);
    }

    $file = $_FILES['dwg_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    // Validate file type
    if ($ext !== 'dwg') {
        wp_die('Invalid file type. Only .dwg files are allowed.', 'Invalid File', ['response' => 400]);
    }

    // Validate file size (50MB max)
    if ($file['size'] > 50 * 1024 * 1024) {
        wp_die('File too large. Maximum size is 50MB.', 'File Too Large', ['response' => 400]);
    }

    // Verify nonce
    if (isset($_POST['dwg_upload_nonce']) && !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['dwg_upload_nonce'])), 'dwg_upload_nonce')) {
        wp_die('Security check failed. Please try again.', 'Security Error', ['response' => 403]);
    }

    // Create upload directory if it doesn't exist
    $upload_dir = plugin_dir_path(__DIR__) . 'storage/input/';
    if (!file_exists($upload_dir)) {
        wp_mkdir_p($upload_dir);
    }

    // Save uploaded file
    $original_name = sanitize_file_name($file['name']);
    $new_file = $upload_dir . uniqid() . '_' . $original_name;
    
    if (!move_uploaded_file($file['tmp_name'], $new_file)) {
        wp_die('Failed to save uploaded file.', 'Upload Error', ['response' => 500]);
    }

    // Process via APS
    $aps_result = send_to_aps($new_file);

    // Clean up input file after processing
    if (file_exists($new_file)) {
        unlink($new_file);
    }

    // Handle errors
    if (isset($aps_result['error'])) {
        $error_message = esc_html($aps_result['error']);
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <title>Processing Error - DWG Optimizer</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
                .error-box { background: #fee; border: 1px solid #c00; border-radius: 8px; padding: 20px; }
                h2 { color: #c00; margin-top: 0; }
                .back-link { margin-top: 20px; }
                .back-link a { color: #0073aa; }
            </style>
        </head>
        <body>
            <div class="error-box">
                <h2>⚠️ Processing Error</h2>
                <p><strong>Error:</strong> ' . $error_message . '</p>
                <p>Please check your file and try again. If the problem persists, contact support.</p>
            </div>
            <div class="back-link">
                <a href="javascript:history.back()">← Go Back</a>
            </div>
        </body>
        </html>';
        
        echo $html;
        exit;
    }

    // Success - show results
    $original_size = isset($aps_result['original_size']) ? $aps_result['original_size'] : 0;
    $optimized_size = isset($aps_result['optimized_size']) ? $aps_result['optimized_size'] : 0;
    $reduction = isset($aps_result['reduction_percentage']) ? $aps_result['reduction_percentage'] : 0;
    $processing_time = isset($aps_result['processing_time']) ? $aps_result['processing_time'] : 0;
    $output_path = isset($aps_result['output_path']) ? $aps_result['output_path'] : '';
    $output_filename = isset($aps_result['output_filename']) ? $aps_result['output_filename'] : '';
    $workitem_id = isset($aps_result['workitem_id']) ? $aps_result['workitem_id'] : '';

    // Create download URL
    $download_url = '';
    if ($output_filename) {
        $download_url = plugins_url('storage/output/' . $output_filename, dirname(__FILE__));
    }

    // Format sizes
    $orig_kb = number_format($original_size / 1024, 2);
    $opt_kb = number_format($optimized_size / 1024, 2);
    $saved_kb = number_format(($original_size - $optimized_size) / 1024, 2);

    $html = '<!DOCTYPE html>
    <html>
    <head>
        <title>Success - DWG Optimizer</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; max-width: 700px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
            .success-box { background: #fff; border-radius: 12px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h2 { color: #2e7d32; margin-top: 0; }
            .stats { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin: 25px 0; }
            .stat-card { background: #f8f9fa; border-radius: 8px; padding: 15px; text-align: center; }
            .stat-value { font-size: 24px; font-weight: bold; color: #1976d2; }
            .stat-label { font-size: 14px; color: #666; margin-top: 5px; }
            .reduction-highlight { background: #e8f5e9; border: 2px solid #4caf50; }
            .reduction-highlight .stat-value { color: #2e7d32; }
            .download-btn { display: inline-block; background: #1976d2; color: #fff; padding: 12px 30px; border-radius: 6px; text-decoration: none; font-weight: bold; margin-top: 20px; }
            .download-btn:hover { background: #1565c0; }
            .details { margin-top: 25px; padding-top: 20px; border-top: 1px solid #eee; }
            .details p { margin: 8px 0; color: #666; font-size: 14px; }
            .back-link { margin-top: 20px; text-align: center; }
            .back-link a { color: #1976d2; }
            .icon { font-size: 48px; margin-bottom: 10px; }
        </style>
    </head>
    <body>
        <div class="success-box">
            <div class="icon">✅</div>
            <h2>File Optimized Successfully!</h2>
            <p>Your DWG file has been cleaned and optimized using Autodesk Platform Services.</p>
            
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-value">' . esc_html($orig_kb) . ' KB</div>
                    <div class="stat-label">Original Size</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">' . esc_html($opt_kb) . ' KB</div>
                    <div class="stat-label">Optimized Size</div>
                </div>
                <div class="stat-card reduction-highlight">
                    <div class="stat-value">' . esc_html($reduction) . '%</div>
                    <div class="stat-label">Size Reduction</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">' . esc_html($saved_kb) . ' KB</div>
                    <div class="stat-label">Space Saved</div>
                </div>
            </div>';

    if ($download_url) {
        $html .= '<a href="' . esc_url($download_url) . '" class="download-btn" download>⬇️ Download Optimized File</a>';
    }

    $html .= '<div class="details">
                <p><strong>Original file:</strong> ' . esc_html($original_name) . '</p>
                <p><strong>Processing time:</strong> ' . esc_html($processing_time) . ' seconds</p>
                <p><strong>WorkItem ID:</strong> ' . esc_html($workitem_id) . '</p>
            </div>
            
            <div class="back-link">
                <a href="javascript:history.back()">← Optimize Another File</a>
            </div>
        </div>
    </body>
    </html>';

    echo $html;
    exit;
}
