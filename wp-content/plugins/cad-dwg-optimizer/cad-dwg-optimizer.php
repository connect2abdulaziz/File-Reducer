<?php
/**
 * Plugin Name: CAD DWG Optimizer
 * Description: Upload DWG files, process via Autodesk Platform Services, and return optimized DWG.
 * Version: 1.0
 * Author: Your Name
 */

if (!defined('ABSPATH')) {
    exit;
}

// Automatically create storage folders
function cad_create_storage_folders() {
    $plugin_path = plugin_dir_path(__FILE__);
    $folders = ['storage/input', 'storage/output', 'storage/temp'];

    foreach ($folders as $folder) {
        $path = $plugin_path . $folder;
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }
    }
}
add_action('init', 'cad_create_storage_folders');

// Shortcode to display DWG upload form
add_shortcode('dwg_upload_form', function () {
    $nonce = wp_create_nonce('dwg_upload_nonce');
    return '
    <div class="cad-dwg-upload-container">
        <style>
            .cad-dwg-upload-container {
                max-width: 600px;
                margin: 30px auto;
                padding: 0;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            }
            .cad-dwg-upload-card {
                background: #ffffff;
                border-radius: 12px;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1), 0 2px 4px rgba(0, 0, 0, 0.06);
                padding: 40px;
                border: 1px solid #e5e7eb;
            }
            .cad-dwg-upload-title {
                font-size: 24px;
                font-weight: 600;
                color: #1f2937;
                margin: 0 0 8px 0;
                text-align: center;
            }
            .cad-dwg-upload-subtitle {
                font-size: 14px;
                color: #6b7280;
                margin: 0 0 30px 0;
                text-align: center;
            }
            .cad-dwg-file-wrapper {
                position: relative;
                margin-bottom: 24px;
            }
            .cad-dwg-file-input {
                position: absolute;
                opacity: 0;
                width: 0;
                height: 0;
            }
            .cad-dwg-file-label {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                padding: 40px 20px;
                border: 2px dashed #d1d5db;
                border-radius: 8px;
                background: #f9fafb;
                cursor: pointer;
                transition: all 0.3s ease;
                text-align: center;
            }
            .cad-dwg-file-label:hover {
                border-color: #3b82f6;
                background: #eff6ff;
            }
            .cad-dwg-file-label.has-file {
                border-color: #10b981;
                background: #ecfdf5;
            }
            .cad-dwg-file-icon {
                font-size: 48px;
                margin-bottom: 12px;
                color: #9ca3af;
            }
            .cad-dwg-file-label:hover .cad-dwg-file-icon,
            .cad-dwg-file-label.has-file .cad-dwg-file-icon {
                color: #3b82f6;
            }
            .cad-dwg-file-text {
                font-size: 16px;
                font-weight: 500;
                color: #374151;
                margin-bottom: 4px;
            }
            .cad-dwg-file-hint {
                font-size: 13px;
                color: #6b7280;
            }
            .cad-dwg-file-name {
                margin-top: 12px;
                padding: 12px;
                background: #ffffff;
                border-radius: 6px;
                border: 1px solid #e5e7eb;
                font-size: 14px;
                color: #1f2937;
                display: none;
            }
            .cad-dwg-file-name.show {
                display: block;
            }
            .cad-dwg-submit-btn {
                width: 100%;
                padding: 14px 24px;
                background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
                color: #ffffff;
                border: none;
                border-radius: 8px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                box-shadow: 0 4px 6px rgba(59, 130, 246, 0.3);
            }
            .cad-dwg-submit-btn:hover {
                background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
                box-shadow: 0 6px 12px rgba(59, 130, 246, 0.4);
                transform: translateY(-1px);
            }
            .cad-dwg-submit-btn:active {
                transform: translateY(0);
            }
            .cad-dwg-submit-btn:disabled {
                background: #9ca3af;
                cursor: not-allowed;
                box-shadow: none;
                transform: none;
            }
            .cad-dwg-loading {
                display: none;
                text-align: center;
                margin-top: 16px;
                color: #3b82f6;
                font-size: 14px;
            }
            .cad-dwg-loading.show {
                display: block;
            }
            .cad-dwg-loading-spinner {
                display: inline-block;
                width: 16px;
                height: 16px;
                border: 2px solid #e5e7eb;
                border-top-color: #3b82f6;
                border-radius: 50%;
                animation: spin 0.8s linear infinite;
                margin-right: 8px;
                vertical-align: middle;
            }
            @keyframes spin {
                to { transform: rotate(360deg); }
            }
            @media (max-width: 640px) {
                .cad-dwg-upload-card {
                    padding: 24px;
                }
                .cad-dwg-upload-title {
                    font-size: 20px;
                }
            }
        </style>
        <div class="cad-dwg-upload-card">
            <h2 class="cad-dwg-upload-title">Upload DWG File</h2>
            <p class="cad-dwg-upload-subtitle">Select your CAD file to optimize and reduce file size</p>
            <form id="cad-dwg-upload-form" action="' . admin_url('admin-post.php') . '" method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="dwg_upload">
                <input type="hidden" name="dwg_upload_nonce" value="' . esc_attr($nonce) . '">
                <div class="cad-dwg-file-wrapper">
                    <input type="file" 
                           name="dwg_file" 
                           id="cad-dwg-file-input" 
                           class="cad-dwg-file-input" 
                           accept=".dwg" 
                           required>
                    <label for="cad-dwg-file-input" class="cad-dwg-file-label" id="cad-dwg-file-label">
                        <span class="cad-dwg-file-icon">📁</span>
                        <span class="cad-dwg-file-text">Click to browse or drag and drop</span>
                        <span class="cad-dwg-file-hint">DWG files only (Max size: 50MB)</span>
                    </label>
                    <div class="cad-dwg-file-name" id="cad-dwg-file-name"></div>
                </div>
                <button type="submit" class="cad-dwg-submit-btn" id="cad-dwg-submit-btn">
                    Upload & Optimize DWG
                </button>
                <div class="cad-dwg-loading" id="cad-dwg-loading">
                    <span class="cad-dwg-loading-spinner"></span>
                    Processing your file...
                </div>
            </form>
        </div>
        <script>
            (function() {
                const fileInput = document.getElementById("cad-dwg-file-input");
                const fileLabel = document.getElementById("cad-dwg-file-label");
                const fileName = document.getElementById("cad-dwg-file-name");
                const submitBtn = document.getElementById("cad-dwg-submit-btn");
                const loading = document.getElementById("cad-dwg-loading");
                const form = document.getElementById("cad-dwg-upload-form");
                
                if (fileInput && fileLabel) {
                    fileInput.addEventListener("change", function(e) {
                        const file = e.target.files[0];
                        if (file) {
                            fileLabel.classList.add("has-file");
                            fileName.textContent = "Selected: " + file.name + " (" + (file.size / 1024 / 1024).toFixed(2) + " MB)";
                            fileName.classList.add("show");
                        } else {
                            fileLabel.classList.remove("has-file");
                            fileName.classList.remove("show");
                        }
                    });
                    
                    // Drag and drop support
                    fileLabel.addEventListener("dragover", function(e) {
                        e.preventDefault();
                        fileLabel.style.borderColor = "#3b82f6";
                        fileLabel.style.background = "#eff6ff";
                    });
                    
                    fileLabel.addEventListener("dragleave", function(e) {
                        e.preventDefault();
                        if (!fileInput.files[0]) {
                            fileLabel.style.borderColor = "#d1d5db";
                            fileLabel.style.background = "#f9fafb";
                        }
                    });
                    
                    fileLabel.addEventListener("drop", function(e) {
                        e.preventDefault();
                        const files = e.dataTransfer.files;
                        if (files.length > 0 && files[0].name.toLowerCase().endsWith(".dwg")) {
                            fileInput.files = files;
                            fileInput.dispatchEvent(new Event("change"));
                        }
                    });
                }
                
                if (form) {
                    form.addEventListener("submit", function() {
                        submitBtn.disabled = true;
                        submitBtn.textContent = "Uploading...";
                        loading.classList.add("show");
                    });
                }
            })();
        </script>
    </div>
    ';
});

// Load plugin scripts
require_once plugin_dir_path(__FILE__) . 'includes/upload-handler.php';
require_once plugin_dir_path(__FILE__) . 'includes/aps-handler.php';