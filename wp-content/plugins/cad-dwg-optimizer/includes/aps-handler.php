<?php
if (!defined('ABSPATH')) exit;

function get_aps_token() {
    $client_id = 'TxpyMdA8AbBlyqF6hp12x7mjApchsOmWP7lMKFqVEYFRlbV6';
    $client_secret = 'GAttO2OMP6A7DI86yLzJijoETRQmGGDzUS6aZCjidXXqoiqo4tW0ejrdFXoJtcxU';

    // Prepare form data for URL encoding
    $body_data = [
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'grant_type' => 'client_credentials',
        'scope' => 'data:read data:write data:create bucket:create bucket:read code:all'
    ];

    // Use WordPress HTTP API - Autodesk expects application/x-www-form-urlencoded
    // Note: sslverify may need to be false for localhost/XAMPP development
    // Correct endpoint for OAuth 2.0 token request
    $response = wp_remote_post('https://developer.api.autodesk.com/authentication/v2/token', [
        'headers' => [
            'Content-Type' => 'application/x-www-form-urlencoded'
        ],
        'body' => http_build_query($body_data),
        'timeout' => 30,
        'sslverify' => false // Set to true in production
    ]);

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        error_log('APS Token Error: ' . $error_message);
        // Store error for retrieval
        set_transient('aps_token_error', $error_message, 300);
        return ['error' => $error_message];
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if ($response_code !== 200) {
        $error_msg = 'HTTP ' . $response_code . ': ' . $body;
        error_log('APS Token HTTP Error: ' . $error_msg);
        set_transient('aps_token_error', $error_msg, 300);
        return ['error' => $error_msg, 'code' => $response_code, 'body' => $body];
    }

    if (isset($data['access_token'])) {
        // Clear any previous errors
        delete_transient('aps_token_error');
        return $data['access_token'];
    }

    $error_msg = 'No access_token in response: ' . $body;
    error_log('APS Token Response (no access_token): ' . $error_msg);
    set_transient('aps_token_error', $error_msg, 300);
    return ['error' => $error_msg, 'body' => $body];
}

function send_to_aps($dwg_file_path) {
    $token_result = get_aps_token();

    // Check if token result is an error array
    if (is_array($token_result) && isset($token_result['error'])) {
        $error_msg = $token_result['error'];
        $error_details = '';
        
        // Add more context if available
        if (isset($token_result['code'])) {
            $error_details .= ' (HTTP ' . $token_result['code'] . ')';
        }
        if (isset($token_result['body'])) {
            // Try to parse error message from response
            $error_body = json_decode($token_result['body'], true);
            if (isset($error_body['developerMessage'])) {
                $error_details .= ' - ' . $error_body['developerMessage'];
            } elseif (isset($error_body['errorDescription'])) {
                $error_details .= ' - ' . $error_body['errorDescription'];
            }
        }
        
        return [
            'error' => 'Failed to get APS authentication token: ' . $error_msg . $error_details,
            'workitem' => null,
            'output_path' => null
        ];
    }
    
    // If not a string token, something went wrong
    if (!is_string($token_result) || empty($token_result)) {
        $stored_error = get_transient('aps_token_error');
        $error_msg = $stored_error ? $stored_error : 'Unknown authentication error';
        return [
            'error' => 'Failed to get APS authentication token. ' . $error_msg,
            'workitem' => null,
            'output_path' => null
        ];
    }
    
    $token = $token_result;

    $file_name = basename($dwg_file_path);
    $output_file = plugin_dir_path(__DIR__) . 'storage/output/' . $file_name;

    // Prepare WorkItem payload
    $payload = [
        'activityId' => 'YourAppBundle.Activity+nickname', // replace with your AppBundle & Activity
        'arguments' => [
            'inputFile' => [
                'url' => 'file://' . $dwg_file_path
            ],
            'outputFile' => [
                'url' => 'file://' . $output_file
            ]
        ]
    ];

    // Use WordPress HTTP API instead of Guzzle
    // Note: sslverify may need to be false for localhost/XAMPP development
    $response = wp_remote_post('https://developer.api.autodesk.com/da/us-east/v3/workitems', [
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode($payload),
        'timeout' => 60,
        'sslverify' => false // Set to true in production
    ]);

    if (is_wp_error($response)) {
        error_log('APS WorkItem Error: ' . $response->get_error_message());
        return [
            'error' => $response->get_error_message(),
            'workitem' => null,
            'output_path' => $output_file
        ];
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    return [
        'workitem' => $data,
        'output_path' => $output_file
    ];
}
