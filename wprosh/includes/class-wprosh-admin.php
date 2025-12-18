<?php
/**
 * Wprosh Admin Class
 *
 * Handles admin dashboard, menu, and AJAX handlers
 *
 * @package Wprosh
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Wprosh_Admin Class
 */
class Wprosh_Admin {
    
    /**
     * Single instance of the class
     *
     * @var Wprosh_Admin
     */
    private static $instance = null;
    
    /**
     * Get single instance of Wprosh_Admin
     *
     * @return Wprosh_Admin
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Enqueue scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // AJAX handlers
        add_action('wp_ajax_wprosh_export', array($this, 'ajax_export'));
        add_action('wp_ajax_wprosh_import', array($this, 'ajax_import'));
        add_action('wp_ajax_wprosh_get_stats', array($this, 'ajax_get_stats'));
        add_action('wp_ajax_wprosh_download', array($this, 'ajax_download'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'Wprosh - مدیریت CSV محصولات',
            'Wprosh',
            'edit_products',
            'wprosh',
            array($this, 'render_admin_page'),
            'dashicons-database-export',
            56
        );
    }
    
    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_assets($hook) {
        if ($hook !== 'toplevel_page_wprosh') {
            return;
        }
        
        // Enqueue styles
        wp_enqueue_style(
            'wprosh-admin',
            WPROSH_PLUGIN_URL . 'assets/css/admin-style.css',
            array(),
            WPROSH_VERSION
        );
        
        // Enqueue scripts
        wp_enqueue_script(
            'wprosh-admin',
            WPROSH_PLUGIN_URL . 'assets/js/admin-script.js',
            array('jquery'),
            WPROSH_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('wprosh-admin', 'wproshData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wprosh_nonce'),
            'downloadUrl' => admin_url('admin.php?page=wprosh&action=download'),
            'strings' => array(
                'exporting' => 'در حال خروجی گرفتن...',
                'exportSuccess' => 'خروجی با موفقیت انجام شد!',
                'exportError' => 'خطا در خروجی گرفتن',
                'importing' => 'در حال آپدیت محصولات...',
                'importSuccess' => 'آپدیت با موفقیت انجام شد!',
                'importError' => 'خطا در آپدیت محصولات',
                'selectFile' => 'لطفاً یک فایل CSV انتخاب کنید',
                'invalidFile' => 'فقط فایل‌های CSV مجاز هستند',
                'uploadError' => 'خطا در آپلود فایل',
                'processing' => 'در حال پردازش...',
                'downloadReport' => 'دانلود گزارش خطاها',
                'noErrors' => 'تمام محصولات با موفقیت آپدیت شدند',
            ),
        ));
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        // Check permission
        if (!current_user_can('edit_products')) {
            wp_die('شما دسترسی به این صفحه را ندارید.');
        }
        
        // Get statistics
        $exporter = new Wprosh_Exporter();
        $stats = $exporter->get_statistics();
        
        ?>
        <div class="wprosh-wrap">
            <div class="wprosh-header">
                <h1 class="wprosh-title">
                    <span class="dashicons dashicons-database-export"></span>
                    Wprosh - مدیریت CSV محصولات
                </h1>
                <p class="wprosh-subtitle">خروجی گرفتن و آپدیت محصولات ووکامرس</p>
            </div>
            
            <div class="wprosh-stats">
                <div class="wprosh-stat-card">
                    <span class="wprosh-stat-number"><?php echo esc_html($stats['total']); ?></span>
                    <span class="wprosh-stat-label">کل محصولات</span>
                </div>
                <div class="wprosh-stat-card">
                    <span class="wprosh-stat-number"><?php echo esc_html($stats['simple']); ?></span>
                    <span class="wprosh-stat-label">ساده</span>
                </div>
                <div class="wprosh-stat-card">
                    <span class="wprosh-stat-number"><?php echo esc_html($stats['variable']); ?></span>
                    <span class="wprosh-stat-label">متغیر</span>
                </div>
                <div class="wprosh-stat-card">
                    <span class="wprosh-stat-number"><?php echo esc_html($stats['variation']); ?></span>
                    <span class="wprosh-stat-label">واریاسیون</span>
                </div>
            </div>
            
            <div class="wprosh-cards">
                <!-- Export Card -->
                <div class="wprosh-card wprosh-export-card">
                    <div class="wprosh-card-header">
                        <span class="dashicons dashicons-download"></span>
                        <h2>خروجی گرفتن از محصولات</h2>
                    </div>
                    <div class="wprosh-card-body">
                        <p class="wprosh-card-description">
                            تمام محصولات ووکامرس (شامل محصولات ساده، متغیر و واریاسیون‌ها) در یک فایل CSV دانلود می‌شود.
                        </p>
                        <ul class="wprosh-features">
                            <li><span class="dashicons dashicons-yes"></span> تمام اطلاعات محصول (بدون تصاویر)</li>
                            <li><span class="dashicons dashicons-yes"></span> دسته‌بندی‌ها و برچسب‌ها</li>
                            <li><span class="dashicons dashicons-yes"></span> ویژگی‌ها و واریاسیون‌ها</li>
                            <li><span class="dashicons dashicons-yes"></span> قیمت‌ها و موجودی</li>
                        </ul>
                    </div>
                    <div class="wprosh-card-footer">
                        <button type="button" id="wprosh-export-btn" class="wprosh-btn wprosh-btn-primary">
                            <span class="dashicons dashicons-download"></span>
                            <span class="wprosh-btn-text">خروجی گرفتن</span>
                            <span class="wprosh-spinner"></span>
                        </button>
                    </div>
                </div>
                
                <!-- Import Card -->
                <div class="wprosh-card wprosh-import-card">
                    <div class="wprosh-card-header">
                        <span class="dashicons dashicons-upload"></span>
                        <h2>آپدیت محصولات</h2>
                    </div>
                    <div class="wprosh-card-body">
                        <p class="wprosh-card-description">
                            فایل CSV ویرایش شده را آپلود کنید تا محصولات آپدیت شوند. گزارش خطاها به صورت CSV دانلود می‌شود.
                        </p>
                        <div class="wprosh-upload-area" id="wprosh-upload-area">
                            <input type="file" id="wprosh-file-input" accept=".csv" class="wprosh-file-input">
                            <label for="wprosh-file-input" class="wprosh-upload-label">
                                <span class="dashicons dashicons-cloud-upload"></span>
                                <span class="wprosh-upload-text">فایل CSV را اینجا رها کنید یا کلیک کنید</span>
                                <span class="wprosh-upload-hint">فقط فایل‌های CSV</span>
                            </label>
                            <div class="wprosh-file-info" id="wprosh-file-info" style="display: none;">
                                <span class="dashicons dashicons-media-spreadsheet"></span>
                                <span class="wprosh-file-name" id="wprosh-file-name"></span>
                                <button type="button" id="wprosh-remove-file" class="wprosh-remove-file">
                                    <span class="dashicons dashicons-no-alt"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="wprosh-card-footer">
                        <button type="button" id="wprosh-import-btn" class="wprosh-btn wprosh-btn-success" disabled>
                            <span class="dashicons dashicons-update"></span>
                            <span class="wprosh-btn-text">آپدیت محصولات</span>
                        </button>
                        <!-- Progress Bar -->
                        <div class="wprosh-progress-container" id="wprosh-progress-container" style="display: none;">
                            <div class="wprosh-progress">
                                <div class="wprosh-progress-bar" id="wprosh-progress-bar" style="width: 0%"></div>
                            </div>
                            <div class="wprosh-progress-text">
                                <span id="wprosh-progress-percent">0%</span>
                                <span id="wprosh-progress-status">در حال آپلود...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Results Section -->
            <div class="wprosh-results" id="wprosh-results" style="display: none;">
                <div class="wprosh-results-header">
                    <h3>نتیجه عملیات</h3>
                </div>
                <div class="wprosh-results-body">
                    <div class="wprosh-results-stats">
                        <div class="wprosh-result-stat wprosh-result-total">
                            <span class="wprosh-result-number" id="result-total">0</span>
                            <span class="wprosh-result-label">کل ردیف‌ها</span>
                        </div>
                        <div class="wprosh-result-stat wprosh-result-success">
                            <span class="wprosh-result-number" id="result-updated">0</span>
                            <span class="wprosh-result-label">آپدیت شده</span>
                        </div>
                        <div class="wprosh-result-stat wprosh-result-skipped">
                            <span class="wprosh-result-number" id="result-skipped">0</span>
                            <span class="wprosh-result-label">بدون تغییر</span>
                        </div>
                        <div class="wprosh-result-stat wprosh-result-failed">
                            <span class="wprosh-result-number" id="result-failed">0</span>
                            <span class="wprosh-result-label">ناموفق</span>
                        </div>
                    </div>
                    <div class="wprosh-results-message" id="wprosh-results-message"></div>
                    <div class="wprosh-results-actions" id="wprosh-results-actions" style="display: none;">
                        <a href="#" id="wprosh-download-report" class="wprosh-btn wprosh-btn-warning" target="_blank">
                            <span class="dashicons dashicons-download"></span>
                            دانلود گزارش خطاها
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Help Section -->
            <div class="wprosh-help">
                <div class="wprosh-help-header">
                    <span class="dashicons dashicons-editor-help"></span>
                    <h3>راهنما</h3>
                </div>
                <div class="wprosh-help-body">
                    <div class="wprosh-help-item">
                        <h4>فیلدهای قابل ویرایش</h4>
                        <p>نام، کد محصول (SKU)، قیمت، موجودی، دسته‌بندی، برچسب، ویژگی‌ها و سایر فیلدها قابل ویرایش هستند.</p>
                    </div>
                    <div class="wprosh-help-item">
                        <h4>فیلدهای غیرقابل ویرایش</h4>
                        <p>شناسه محصول (id)، نوع محصول (type) و شناسه والد (parent_id) قابل تغییر نیستند.</p>
                    </div>
                    <div class="wprosh-help-item">
                        <h4>فرمت دسته‌بندی و برچسب</h4>
                        <p>چند دسته‌بندی یا برچسب را با | جدا کنید. مثال: <code>لباس|مردانه|پیراهن</code></p>
                    </div>
                    <div class="wprosh-help-item">
                        <h4>فرمت ویژگی‌ها</h4>
                        <p>از فرمت JSON استفاده کنید. مثال: <code>{"رنگ":"قرمز","سایز":"XL"}</code></p>
                    </div>
                </div>
            </div>
            
            <div class="wprosh-footer">
                <p>Wprosh نسخه <?php echo esc_html(WPROSH_VERSION); ?> | ساخته شده با ❤️</p>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX: Export products - returns download URL
     */
    public function ajax_export() {
        // Verify nonce
        if (!check_ajax_referer('wprosh_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'خطای امنیتی. لطفاً صفحه را رفرش کنید.'));
        }
        
        // Check permission
        if (!current_user_can('edit_products')) {
            wp_send_json_error(array('message' => 'شما دسترسی به این عملیات را ندارید.'));
        }
        
        $exporter = new Wprosh_Exporter();
        $stats = $exporter->get_statistics();
        
        if ($stats['total'] === 0) {
            wp_send_json_error(array('message' => 'هیچ محصولی برای خروجی وجود ندارد.'));
        }
        
        // Return download URL that points to AJAX download handler
        $download_url = add_query_arg(array(
            'action' => 'wprosh_download',
            'nonce' => wp_create_nonce('wprosh_nonce'),
        ), admin_url('admin-ajax.php'));
        
        wp_send_json_success(array(
            'message' => 'در حال دانلود فایل...',
            'download_url' => $download_url,
            'file_name' => 'wprosh-products-' . date('Y-m-d-H-i-s') . '.csv',
            'stats' => $stats,
            'use_redirect' => true,
        ));
    }
    
    /**
     * AJAX: Import products
     */
    public function ajax_import() {
        // Verify nonce
        if (!check_ajax_referer('wprosh_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'خطای امنیتی. لطفاً صفحه را رفرش کنید.'));
        }
        
        // Check permission
        if (!current_user_can('edit_products')) {
            wp_send_json_error(array('message' => 'شما دسترسی به این عملیات را ندارید.'));
        }
        
        // Check file upload
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $error_message = 'خطا در آپلود فایل.';
            if (isset($_FILES['file']['error'])) {
                switch ($_FILES['file']['error']) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $error_message = 'حجم فایل بیش از حد مجاز است.';
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $error_message = 'فایل به طور کامل آپلود نشد.';
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        $error_message = 'هیچ فایلی انتخاب نشده است.';
                        break;
                }
            }
            wp_send_json_error(array('message' => $error_message));
        }
        
        // Validate file type
        $file_type = wp_check_filetype($_FILES['file']['name']);
        if ($file_type['ext'] !== 'csv') {
            wp_send_json_error(array('message' => 'فقط فایل‌های CSV مجاز هستند.'));
        }
        
        // Use the temporary uploaded file directly (more reliable)
        $file_path = $_FILES['file']['tmp_name'];
        
        // Check if file exists and is readable
        if (!file_exists($file_path) || !is_readable($file_path)) {
            wp_send_json_error(array('message' => 'فایل آپلود شده قابل خواندن نیست.'));
        }
        
        // Import products directly from temp file
        $importer = new Wprosh_Importer();
        $result = $importer->import($file_path);
        
        if (!$result['success']) {
            wp_send_json_error(array('message' => $result['message']));
        }
        
        $response = array(
            'message' => 'آپدیت با موفقیت انجام شد!',
            'results' => $result['results'],
            'errors_count' => count($result['errors']),
        );
        
        // Error report is now returned as base64 content
        if ($result['error_report'] && is_array($result['error_report'])) {
            $response['error_report_data'] = $result['error_report']['content'];
            $response['error_report_name'] = $result['error_report']['filename'];
        }
        
        wp_send_json_success($response);
    }
    
    /**
     * AJAX: Get statistics
     */
    public function ajax_get_stats() {
        // Verify nonce
        if (!check_ajax_referer('wprosh_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'خطای امنیتی.'));
        }
        
        // Check permission
        if (!current_user_can('edit_products')) {
            wp_send_json_error(array('message' => 'دسترسی ندارید.'));
        }
        
        $exporter = new Wprosh_Exporter();
        $stats = $exporter->get_statistics();
        
        wp_send_json_success(array('stats' => $stats));
    }
    
    /**
     * AJAX: Download CSV file (streams directly to browser)
     */
    public function ajax_download() {
        // Verify nonce
        if (!check_ajax_referer('wprosh_nonce', 'nonce', false)) {
            wp_die('خطای امنیتی. لطفاً صفحه را رفرش کنید.');
        }
        
        // Check permission
        if (!current_user_can('edit_products')) {
            wp_die('شما دسترسی به این عملیات را ندارید.');
        }
        
        // Stream export directly to browser
        $exporter = new Wprosh_Exporter();
        $exporter->stream_export();
        // stream_export calls exit() so nothing after this runs
    }
}

