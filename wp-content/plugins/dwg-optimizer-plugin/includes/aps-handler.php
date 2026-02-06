<?php
/**
 * Autodesk Platform Services Handler
 * Handles DWG file processing via APS Design Automation
 */

if (!defined('ABSPATH')) exit;

class DWG_Optimizer_Autodesk_Service {
    
    private $client_id = 'TxpyMdA8AbBlyqF6hp12x7mjApchsOmWP7lMKFqVEYFRlbV6';
    private $client_secret = 'GAttO2OMP6A7DI86yLzJijoETRQmGGDzUS6aZCjidXXqoiqo4tW0ejrdFXoJtcxU';
    private $bucket_key = 'filereduceruniquebucketname123';
    private $activity_id = 'TxpyMdA8AbBlyqF6hp12x7mjApchsOmWP7lMKFqVEYFRlbV6.CleanupActivityFinal+prod';

    /**
     * Get access token from Autodesk
     */
    public function get_access_token() {
        $body_data = [
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'grant_type' => 'client_credentials',
            'scope' => 'data:read data:write data:create bucket:create bucket:read code:all'
        ];

        $response = wp_remote_post('https://developer.api.autodesk.com/authentication/v2/token', [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ],
            'body' => http_build_query($body_data),
            'timeout' => 30,
            'sslverify' => apply_filters('cad_dwg_sslverify', true)
        ]);

        if (is_wp_error($response)) {
            error_log('APS Token Error: ' . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['access_token'])) {
            return $data['access_token'];
        }

        error_log('APS Token Error: ' . $body);
        return false;
    }

    /**
     * URL-encode object name for OSS
     */
    private function encode_object_name($object_name) {
        return rawurlencode($object_name);
    }

    /**
     * Upload DWG file to OSS bucket using signed S3 upload
     */
    public function upload_to_bucket($file_path, $object_name) {
        $token = $this->get_access_token();
        if (!$token) {
            return ['error' => 'Failed to get access token'];
        }

        $file_contents = file_get_contents($file_path);
        if ($file_contents === false) {
            return ['error' => 'Failed to read file'];
        }

        $object_encoded = $this->encode_object_name($object_name);
        $bucket_encoded = rawurlencode($this->bucket_key);
        $sign_url = "https://developer.api.autodesk.com/oss/v2/buckets/{$bucket_encoded}/objects/{$object_encoded}/signeds3upload";

        // Step 1: GET signed upload URL and uploadKey
        $sign_response = wp_remote_get($sign_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token
            ],
            'timeout' => 30,
            'sslverify' => apply_filters('cad_dwg_sslverify', true)
        ]);

        if (is_wp_error($sign_response)) {
            error_log('Signeds3upload GET Error: ' . $sign_response->get_error_message());
            return ['error' => 'Failed to get upload URL: ' . $sign_response->get_error_message()];
        }

        $sign_code = wp_remote_retrieve_response_code($sign_response);
        $sign_body = wp_remote_retrieve_body($sign_response);
        $sign_data = json_decode($sign_body, true);

        if ($sign_code !== 200 && $sign_code !== 201) {
            error_log('Signeds3upload GET failed: HTTP ' . $sign_code . ' | Response: ' . $sign_body);
            return ['error' => 'Failed to get upload URL: HTTP ' . $sign_code];
        }

        if (empty($sign_data['urls'][0]) || empty($sign_data['uploadKey'])) {
            error_log('Signeds3upload response missing data: ' . $sign_body);
            return ['error' => 'Upload URL or key not found in API response'];
        }

        $signed_s3_url = $sign_data['urls'][0];
        $upload_key = $sign_data['uploadKey'];

        // Step 2: PUT file to signed S3 URL
        $upload_response = wp_remote_request($signed_s3_url, [
            'method' => 'PUT',
            'headers' => [
                'Content-Type' => 'application/octet-stream'
            ],
            'body' => $file_contents,
            'timeout' => 120,
            'sslverify' => apply_filters('cad_dwg_sslverify', true)
        ]);

        if (is_wp_error($upload_response)) {
            error_log('S3 PUT Error: ' . $upload_response->get_error_message());
            return ['error' => 'Upload to storage failed: ' . $upload_response->get_error_message()];
        }

        $put_code = wp_remote_retrieve_response_code($upload_response);
        if ($put_code !== 200 && $put_code !== 201) {
            error_log('S3 PUT failed: HTTP ' . $put_code);
            return ['error' => 'Upload to storage failed: HTTP ' . $put_code];
        }

        // Step 3: POST to finalize upload
        $finalize_response = wp_remote_post($sign_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode(['uploadKey' => $upload_key]),
            'timeout' => 30,
            'sslverify' => apply_filters('cad_dwg_sslverify', true)
        ]);

        if (is_wp_error($finalize_response)) {
            error_log('Signeds3upload finalize Error: ' . $finalize_response->get_error_message());
            return ['error' => 'Failed to finalize upload: ' . $finalize_response->get_error_message()];
        }

        $fin_code = wp_remote_retrieve_response_code($finalize_response);
        if ($fin_code !== 200 && $fin_code !== 201) {
            error_log('Signeds3upload finalize failed: HTTP ' . $fin_code);
            return ['error' => 'Finalize upload failed: HTTP ' . $fin_code];
        }

        error_log('Successfully uploaded to OSS: ' . $object_name);
        return ['objectId' => $object_name];
    }

    /**
     * Get signed download URL for a file
     */
    public function get_signed_download_url($object_name) {
        $token = $this->get_access_token();
        if (!$token) {
            return ['error' => 'Failed to get access token'];
        }

        $object_encoded = $this->encode_object_name($object_name);
        $bucket_encoded = rawurlencode($this->bucket_key);
        $url = "https://developer.api.autodesk.com/oss/v2/buckets/{$bucket_encoded}/objects/{$object_encoded}/signeds3download";

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token
            ],
            'timeout' => 30,
            'sslverify' => apply_filters('cad_dwg_sslverify', true)
        ]);

        if (is_wp_error($response)) {
            error_log('Signed Download URL Error: ' . $response->get_error_message());
            return ['error' => $response->get_error_message()];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['url'])) {
            return $data['url'];
        }

        error_log('Signed Download URL Error: ' . $body);
        return ['error' => 'Failed to get signed download URL'];
    }

    /**
     * Get signed upload URL for output file
     */
    public function get_signed_upload_url($object_name) {
        $token = $this->get_access_token();
        if (!$token) {
            return ['error' => 'Failed to get access token'];
        }

        $object_encoded = $this->encode_object_name($object_name);
        $bucket_encoded = rawurlencode($this->bucket_key);
        $url = "https://developer.api.autodesk.com/oss/v2/buckets/{$bucket_encoded}/objects/{$object_encoded}/signeds3upload";

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token
            ],
            'timeout' => 30,
            'sslverify' => apply_filters('cad_dwg_sslverify', true)
        ]);

        if (is_wp_error($response)) {
            error_log('Signed Upload URL Error: ' . $response->get_error_message());
            return ['error' => $response->get_error_message()];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['urls'][0]) && isset($data['uploadKey'])) {
            return [
                'url' => $data['urls'][0],
                'uploadKey' => $data['uploadKey']
            ];
        }

        error_log('Signed Upload URL Error: ' . $body);
        return ['error' => 'Failed to get signed upload URL'];
    }

    /**
     * Finalize output file upload
     */
    public function finalize_output_upload($object_name, $upload_key) {
        $token = $this->get_access_token();
        if (!$token) {
            return ['error' => 'Failed to get access token'];
        }

        $object_encoded = $this->encode_object_name($object_name);
        $bucket_encoded = rawurlencode($this->bucket_key);
        $url = "https://developer.api.autodesk.com/oss/v2/buckets/{$bucket_encoded}/objects/{$object_encoded}/signeds3upload";

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode(['uploadKey' => $upload_key]),
            'timeout' => 30,
            'sslverify' => apply_filters('cad_dwg_sslverify', true)
        ]);

        if (is_wp_error($response)) {
            error_log('Finalize output Error: ' . $response->get_error_message());
            return ['error' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code >= 200 && $code < 300) {
            return ['success' => true];
        }

        return ['error' => 'Finalize failed: HTTP ' . $code];
    }

    /**
     * Create WorkItem to process DWG
     */
    public function create_workitem($input_signed_url, $output_signed_url) {
        $token = $this->get_access_token();
        if (!$token) {
            return ['error' => 'Failed to get access token'];
        }

        $payload = [
            'activityId' => $this->activity_id,
            'arguments' => [
                'inputFile' => [
                    'url' => $input_signed_url,
                    'verb' => 'get'
                ],
                'outputFile' => [
                    'verb' => 'put',
                    'url' => $output_signed_url
                ]
            ]
        ];

        $response = wp_remote_post('https://developer.api.autodesk.com/da/us-east/v3/workitems', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($payload),
            'timeout' => 60,
            'sslverify' => apply_filters('cad_dwg_sslverify', true)
        ]);

        if (is_wp_error($response)) {
            error_log('WorkItem Error: ' . $response->get_error_message());
            return ['error' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code !== 200 && $code !== 201) {
            $msg = isset($data['developerMessage']) ? $data['developerMessage'] : $body;
            error_log('WorkItem creation failed: HTTP ' . $code . ' - ' . $msg);
            return ['error' => 'WorkItem failed: ' . $msg];
        }

        return $data;
    }

    /**
     * Check WorkItem status
     */
    public function check_workitem_status($workitem_id) {
        $token = $this->get_access_token();
        if (!$token) {
            return ['error' => 'Failed to get access token'];
        }

        $url = "https://developer.api.autodesk.com/da/us-east/v3/workitems/{$workitem_id}";

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token
            ],
            'timeout' => 30,
            'sslverify' => apply_filters('cad_dwg_sslverify', true)
        ]);

        if (is_wp_error($response)) {
            error_log('Status Check Error: ' . $response->get_error_message());
            return ['error' => $response->get_error_message()];
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }

    /**
     * Download file from bucket using signed URL
     */
    public function download_from_bucket($object_name, $local_path) {
        $download_url = $this->get_signed_download_url($object_name);
        
        if (is_array($download_url) && isset($download_url['error'])) {
            return $download_url;
        }

        $response = wp_remote_get($download_url, [
            'timeout' => 120,
            'sslverify' => apply_filters('cad_dwg_sslverify', true)
        ]);

        if (is_wp_error($response)) {
            error_log('Download Error: ' . $response->get_error_message());
            return ['error' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            error_log('Download failed: HTTP ' . $code);
            return ['error' => 'Download failed: HTTP ' . $code];
        }

        $file_contents = wp_remote_retrieve_body($response);

        $dir = dirname($local_path);
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }

        $result = file_put_contents($local_path, $file_contents);
        
        if ($result === false) {
            return ['error' => 'Failed to save file'];
        }

        return ['success' => true, 'size' => $result];
    }

    /**
     * Process DWG file - Complete workflow
     */
    public function process_dwg_file($input_file_path) {
        $start_time = time();
        $file_name = basename($input_file_path);
        $timestamp = time();
        $input_object_name = 'input-' . $timestamp . '-' . uniqid() . '.dwg';
        $output_object_name = 'output-' . $timestamp . '-' . uniqid() . '.dwg';
        
        // Step 1: Upload to bucket
        error_log('[' . gmdate('d-M-Y H:i:s') . ' UTC] Step 1: Uploading to bucket...');
        $upload_result = $this->upload_to_bucket($input_file_path, $input_object_name);
        if (isset($upload_result['error'])) {
            return ['error' => 'Upload failed: ' . $upload_result['error']];
        }
        
        $original_size = filesize($input_file_path);
        error_log('[' . gmdate('d-M-Y H:i:s') . ' UTC] Successfully uploaded to OSS: ' . $input_object_name);
        error_log('[' . gmdate('d-M-Y H:i:s') . ' UTC] Uploaded: ' . $input_object_name . ' (' . $original_size . ' bytes)');

        // Step 2: Get signed download URL for input
        error_log('[' . gmdate('d-M-Y H:i:s') . ' UTC] Step 2: Generating signed URL...');
        $input_signed_url = $this->get_signed_download_url($input_object_name);
        if (is_array($input_signed_url) && isset($input_signed_url['error'])) {
            return ['error' => 'Signed URL failed: ' . $input_signed_url['error']];
        }
        error_log('[' . gmdate('d-M-Y H:i:s') . ' UTC] Signed URL generated');

        // Step 3: Get signed upload URL for output
        error_log('[' . gmdate('d-M-Y H:i:s') . ' UTC] Step 3: Getting output signed URL...');
        $output_upload_data = $this->get_signed_upload_url($output_object_name);
        if (is_array($output_upload_data) && isset($output_upload_data['error'])) {
            return ['error' => 'Output signed URL failed: ' . $output_upload_data['error']];
        }
        $output_signed_url = $output_upload_data['url'];
        $output_upload_key = $output_upload_data['uploadKey'];
        error_log('[' . gmdate('d-M-Y H:i:s') . ' UTC] Output signed URL generated');

        // Step 4: Create WorkItem
        error_log('[' . gmdate('d-M-Y H:i:s') . ' UTC] Step 4: Creating WorkItem...');
        $workitem = $this->create_workitem($input_signed_url, $output_signed_url);
        if (isset($workitem['error'])) {
            return ['error' => 'WorkItem creation failed: ' . $workitem['error']];
        }
        
        $workitem_id = isset($workitem['id']) ? $workitem['id'] : null;
        if (!$workitem_id) {
            error_log('WorkItem response missing id: ' . json_encode($workitem));
            return ['error' => 'WorkItem created but no ID returned'];
        }
        error_log('[' . gmdate('d-M-Y H:i:s') . ' UTC] WorkItem created: ' . $workitem_id);

        // Step 5: Poll for completion
        error_log('[' . gmdate('d-M-Y H:i:s') . ' UTC] Step 5: Polling for completion...');
        $max_attempts = 30;
        $attempt = 0;
        $status = 'pending';
        $status_result = null;

        while ($attempt < $max_attempts && !in_array($status, ['success', 'failedDownload', 'failedInstructions', 'failedUpload', 'cancelled'])) {
            sleep(5);
            $attempt++;
            
            $status_result = $this->check_workitem_status($workitem_id);
            if (isset($status_result['error'])) {
                return ['error' => 'Status check failed: ' . $status_result['error']];
            }
            
            $status = $status_result['status'];
            error_log('[' . gmdate('d-M-Y H:i:s') . ' UTC] Attempt ' . $attempt . ': Status = ' . $status);
        }

        if ($status !== 'success') {
            $report_url = isset($status_result['reportUrl']) ? $status_result['reportUrl'] : 'No report URL';
            error_log('[' . gmdate('d-M-Y H:i:s') . ' UTC] Processing failed with status: ' . $status);
            error_log('[' . gmdate('d-M-Y H:i:s') . ' UTC] Report URL: ' . $report_url);
            return [
                'error' => 'Processing failed with status: ' . $status,
                'status' => $status,
                'reportUrl' => $report_url
            ];
        }

        error_log('[' . gmdate('d-M-Y H:i:s') . ' UTC] Processing successful!');

        // Step 6: Finalize output upload
        error_log('[' . gmdate('d-M-Y H:i:s') . ' UTC] Step 6: Finalizing output upload...');
        $finalize_result = $this->finalize_output_upload($output_object_name, $output_upload_key);
        if (isset($finalize_result['error'])) {
            error_log('[' . gmdate('d-M-Y H:i:s') . ' UTC] Warning: Finalize may have failed: ' . $finalize_result['error']);
        }

        // Step 7: Download cleaned file
        error_log('[' . gmdate('d-M-Y H:i:s') . ' UTC] Step 7: Downloading result...');
        $output_dir = plugin_dir_path(__DIR__) . 'storage/output/';
        $output_filename = pathinfo($file_name, PATHINFO_FILENAME) . '_optimized_' . $timestamp . '.dwg';
        $output_path = $output_dir . $output_filename;
        
        $download_result = $this->download_from_bucket($output_object_name, $output_path);
        if (isset($download_result['error'])) {
            return ['error' => 'Download failed: ' . $download_result['error']];
        }

        $optimized_size = filesize($output_path);
        $reduction = $original_size > 0 ? (($original_size - $optimized_size) / $original_size) * 100 : 0;
        $processing_time = time() - $start_time;

        error_log('[' . gmdate('d-M-Y H:i:s') . ' UTC] Success! Original: ' . $original_size . ', Optimized: ' . $optimized_size . ', Reduction: ' . round($reduction, 2) . '%');

        return [
            'success' => true,
            'original_size' => $original_size,
            'optimized_size' => $optimized_size,
            'reduction_percentage' => round($reduction, 2),
            'output_path' => $output_path,
            'output_filename' => $output_filename,
            'processing_time' => $processing_time,
            'workitem_id' => $workitem_id
        ];
    }
}

/**
 * Wrapper function for upload-handler
 */
function send_to_aps($dwg_file_path) {
    if (!file_exists($dwg_file_path)) {
        return ['error' => 'File not found: ' . $dwg_file_path];
    }

    $service = new DWG_Optimizer_Autodesk_Service();
    $result = $service->process_dwg_file($dwg_file_path);

    if (isset($result['error'])) {
        return [
            'error' => $result['error'],
            'status' => isset($result['status']) ? $result['status'] : null,
            'reportUrl' => isset($result['reportUrl']) ? $result['reportUrl'] : null
        ];
    }

    return $result;
}
