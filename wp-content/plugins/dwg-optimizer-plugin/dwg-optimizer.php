<?php
/**
 * Plugin Name: DWG Optimizer
 * Plugin URI: https://example.com/dwg-optimizer
 * Description: Optimize DWG files using Autodesk Platform Services - Audit, Purge, and Reduce file size
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) exit;

// Define plugin constants
define('DWG_OPTIMIZER_VERSION', '1.0.0');
define('DWG_OPTIMIZER_PATH', plugin_dir_path(__FILE__));
define('DWG_OPTIMIZER_URL', plugin_dir_url(__FILE__));

// Include required files
require_once DWG_OPTIMIZER_PATH . 'includes/upload-handler.php';

// Register activation hook
register_activation_hook(__FILE__, 'dwg_optimizer_activate');

function dwg_optimizer_activate() {
    // Create storage directories
    $dirs = [
        DWG_OPTIMIZER_PATH . 'storage',
        DWG_OPTIMIZER_PATH . 'storage/input',
        DWG_OPTIMIZER_PATH . 'storage/output'
    ];
    
    foreach ($dirs as $dir) {
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
        
        // Create .htaccess to protect input directory
        if (strpos($dir, 'input') !== false) {
            $htaccess = $dir . '/.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, 'Deny from all');
            }
        }
    }

    // Create index.php files to prevent directory listing
    foreach ($dirs as $dir) {
        $index = $dir . '/index.php';
        if (!file_exists($index)) {
            file_put_contents($index, '<?php // Silence is golden');
        }
    }
}

// Register shortcode for upload form
add_shortcode('dwg_optimizer', 'dwg_optimizer_shortcode');

function dwg_optimizer_shortcode($atts) {
    $atts = shortcode_atts([
        'title' => 'DWG File Optimizer',
        'description' => 'Upload your DWG file to optimize it. The file will be audited, purged of unnecessary data, and optimized for smaller size.'
    ], $atts);

    $form_action = admin_url('admin-post.php');
    $nonce = wp_create_nonce('dwg_upload_nonce');

    ob_start();
    ?>
    <style>
        .dwg-optimizer-form {
            max-width: 500px;
            margin: 20px auto;
            padding: 30px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .dwg-optimizer-form h3 {
            margin-top: 0;
            color: #1976d2;
        }
        .dwg-optimizer-form p.description {
            color: #666;
            font-size: 14px;
            margin-bottom: 20px;
        }
        .dwg-optimizer-form .file-input-wrapper {
            border: 2px dashed #ccc;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            margin-bottom: 20px;
            transition: border-color 0.3s;
        }
        .dwg-optimizer-form .file-input-wrapper:hover {
            border-color: #1976d2;
        }
        .dwg-optimizer-form .file-input-wrapper.dragover {
            border-color: #1976d2;
            background: #e3f2fd;
        }
        .dwg-optimizer-form input[type="file"] {
            display: none;
        }
        .dwg-optimizer-form .file-label {
            cursor: pointer;
            color: #1976d2;
            font-weight: 500;
        }
        .dwg-optimizer-form .file-label:hover {
            text-decoration: underline;
        }
        .dwg-optimizer-form .file-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }
        .dwg-optimizer-form .file-name {
            margin-top: 10px;
            padding: 10px;
            background: #e8f5e9;
            border-radius: 4px;
            display: none;
        }
        .dwg-optimizer-form .file-name.visible {
            display: block;
        }
        .dwg-optimizer-form button {
            width: 100%;
            padding: 12px;
            background: #1976d2;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s;
        }
        .dwg-optimizer-form button:hover {
            background: #1565c0;
        }
        .dwg-optimizer-form button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .dwg-optimizer-form .info {
            margin-top: 15px;
            font-size: 12px;
            color: #999;
            text-align: center;
        }
        .dwg-optimizer-form .loading {
            display: none;
        }
        .dwg-optimizer-form.submitting .loading {
            display: inline;
        }
        .dwg-optimizer-form.submitting button {
            background: #666;
        }
    </style>

    <div class="dwg-optimizer-form" id="dwg-optimizer-form">
        <h3><?php echo esc_html($atts['title']); ?></h3>
        <p class="description"><?php echo esc_html($atts['description']); ?></p>
        
        <form method="post" action="<?php echo esc_url($form_action); ?>" enctype="multipart/form-data" id="dwg-upload-form">
            <input type="hidden" name="action" value="dwg_upload">
            <input type="hidden" name="dwg_upload_nonce" value="<?php echo esc_attr($nonce); ?>">
            
            <div class="file-input-wrapper" id="drop-zone">
                <div class="file-icon">📁</div>
                <label for="dwg_file" class="file-label">
                    Click to select or drag & drop your DWG file
                </label>
                <input type="file" name="dwg_file" id="dwg_file" accept=".dwg" required>
                <div class="file-name" id="file-name"></div>
            </div>
            
            <button type="submit" id="submit-btn" disabled>
                <span class="loading">⏳ Processing... Please wait...</span>
                <span class="button-text">🚀 Optimize DWG File</span>
            </button>
            
            <p class="info">Maximum file size: 50MB | Supported format: .dwg</p>
        </form>
    </div>

    <script>
    (function() {
        const form = document.getElementById('dwg-upload-form');
        const fileInput = document.getElementById('dwg_file');
        const fileName = document.getElementById('file-name');
        const submitBtn = document.getElementById('submit-btn');
        const dropZone = document.getElementById('drop-zone');
        const formWrapper = document.getElementById('dwg-optimizer-form');

        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                const file = this.files[0];
                if (file.name.toLowerCase().endsWith('.dwg')) {
                    fileName.textContent = '✅ Selected: ' + file.name + ' (' + (file.size / 1024 / 1024).toFixed(2) + ' MB)';
                    fileName.classList.add('visible');
                    submitBtn.disabled = false;
                } else {
                    alert('Please select a .dwg file');
                    this.value = '';
                    fileName.classList.remove('visible');
                    submitBtn.disabled = true;
                }
            }
        });

        form.addEventListener('submit', function() {
            formWrapper.classList.add('submitting');
            submitBtn.disabled = true;
        });

        // Drag and drop
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => dropZone.classList.add('dragover'), false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => dropZone.classList.remove('dragover'), false);
        });

        dropZone.addEventListener('drop', function(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files.length > 0) {
                fileInput.files = files;
                fileInput.dispatchEvent(new Event('change'));
            }
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

// Add admin menu
add_action('admin_menu', 'dwg_optimizer_admin_menu');

function dwg_optimizer_admin_menu() {
    add_menu_page(
        'DWG Optimizer',
        'DWG Optimizer',
        'manage_options',
        'dwg-optimizer',
        'dwg_optimizer_admin_page',
        'dashicons-image-filter',
        30
    );
}

function dwg_optimizer_admin_page() {
    ?>
    <div class="wrap">
        <h1>DWG Optimizer</h1>
        
        <div class="card" style="max-width: 600px; padding: 20px;">
            <h2>How to Use</h2>
            <p>Add the following shortcode to any page or post to display the DWG upload form:</p>
            <code style="display: block; padding: 10px; background: #f5f5f5; margin: 10px 0;">[dwg_optimizer]</code>
            
            <h3>Shortcode Options</h3>
            <ul>
                <li><code>title</code> - Custom title for the form</li>
                <li><code>description</code> - Custom description text</li>
            </ul>
            
            <p>Example with custom options:</p>
            <code style="display: block; padding: 10px; background: #f5f5f5; margin: 10px 0;">[dwg_optimizer title="Optimize Your CAD Files" description="Upload your DWG file to reduce its size."]</code>
        </div>
        
        <div class="card" style="max-width: 600px; padding: 20px; margin-top: 20px;">
            <h2>Storage Locations</h2>
            <ul>
                <li><strong>Input files:</strong> <?php echo esc_html(DWG_OPTIMIZER_PATH . 'storage/input/'); ?></li>
                <li><strong>Output files:</strong> <?php echo esc_html(DWG_OPTIMIZER_PATH . 'storage/output/'); ?></li>
            </ul>
        </div>

        <div class="card" style="max-width: 600px; padding: 20px; margin-top: 20px;">
            <h2>Test Upload Form</h2>
            <?php echo do_shortcode('[dwg_optimizer]'); ?>
        </div>
    </div>
    <?php
}
