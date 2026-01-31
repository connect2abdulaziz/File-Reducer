<?php
require_once plugin_dir_path(__FILE__) . 'aps-handler.php';

add_action('admin_post_nopriv_dwg_upload', 'dwg_upload_handler');
add_action('admin_post_dwg_upload', 'dwg_upload_handler');

function dwg_upload_handler() {
    if (!isset($_FILES['dwg_file'])) wp_die('No file uploaded.');

    $file = $_FILES['dwg_file'];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);

    if (strtolower($ext) !== 'dwg') wp_die('Invalid file type.');
    if ($file['size'] > 50 * 1024 * 1024) wp_die('File too large.');

    $upload_dir = plugin_dir_path(__DIR__) . 'storage/input/';
    if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);

    $new_file = $upload_dir . uniqid() . '.dwg';
    move_uploaded_file($file['tmp_name'], $new_file);

    // Send DWG to APS
    $aps_result = send_to_aps($new_file);

    if (isset($aps_result['error'])) {
        $error_message = esc_html($aps_result['error']);
        wp_die(
            '<h2>Error Processing File</h2>' .
            '<p><strong>Error:</strong> ' . $error_message . '</p>' .
            '<p><em>Please check your Autodesk credentials and network connectivity. For more details, check the WordPress debug log.</em></p>',
            'File Processing Error',
            ['response' => 500]
        );
    }

    echo 'File uploaded successfully.<br>';
    echo 'APS WorkItem created: ' . json_encode($aps_result['workitem']) . '<br>';
    echo 'Optimized file will be saved at: ' . $aps_result['output_path'];

    exit;
}
