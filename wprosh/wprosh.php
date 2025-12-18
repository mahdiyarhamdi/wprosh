<?php
/**
 * Plugin Name: Wprosh - مدیریت CSV محصولات ووکامرس
 * Plugin URI: https://wprosh.ir
 * Description: خروجی گرفتن و آپدیت محصولات ووکامرس با فایل CSV به همراه گزارش خطای کامل
 * Version: 1.0.0
 * Author: Wprosh Team
 * Author URI: https://wprosh.ir
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wprosh
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WPROSH_VERSION', '1.0.0');
define('WPROSH_PLUGIN_FILE', __FILE__);
define('WPROSH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPROSH_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPROSH_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Wprosh Plugin Class
 */
final class Wprosh {
    
    /**
     * Single instance of the class
     *
     * @var Wprosh
     */
    private static $instance = null;
    
    /**
     * Get single instance of Wprosh
     *
     * @return Wprosh
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
        // Check WooCommerce dependency
        add_action('plugins_loaded', array($this, 'check_woocommerce'));
        
        // Load plugin text domain
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Initialize plugin
        add_action('plugins_loaded', array($this, 'init'), 20);
        
        // Activation hook
        register_activation_hook(__FILE__, array($this, 'activate'));
        
        // Deactivation hook
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Check if WooCommerce is active
     */
    public function check_woocommerce() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return false;
        }
        return true;
    }
    
    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p style="font-family: 'YekanBakh', Tahoma, sans-serif;">
                <strong>Wprosh:</strong> این افزونه نیاز به ووکامرس دارد. لطفاً ابتدا ووکامرس را نصب و فعال کنید.
            </p>
        </div>
        <?php
    }
    
    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain('wprosh', false, dirname(WPROSH_PLUGIN_BASENAME) . '/languages');
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        if (!$this->check_woocommerce()) {
            return;
        }
        
        // Include required files
        $this->includes();
        
        // Initialize classes
        $this->init_classes();
    }
    
    /**
     * Include required files
     */
    private function includes() {
        // Core classes
        require_once WPROSH_PLUGIN_DIR . 'includes/class-wprosh-validator.php';
        require_once WPROSH_PLUGIN_DIR . 'includes/class-wprosh-exporter.php';
        require_once WPROSH_PLUGIN_DIR . 'includes/class-wprosh-importer.php';
        require_once WPROSH_PLUGIN_DIR . 'includes/class-wprosh-admin.php';
    }
    
    /**
     * Initialize classes
     */
    private function init_classes() {
        // Admin
        if (is_admin()) {
            Wprosh_Admin::instance();
        }
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die('این افزونه نیاز به PHP نسخه 7.4 یا بالاتر دارد.');
        }
        
        // Check WordPress version
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die('این افزونه نیاز به وردپرس نسخه 5.0 یا بالاتر دارد.');
        }
        
        // Create upload directory for temporary files
        $upload_dir = wp_upload_dir();
        $wprosh_dir = $upload_dir['basedir'] . '/wprosh';
        
        if (!file_exists($wprosh_dir)) {
            wp_mkdir_p($wprosh_dir);
        }
        
        // Add .htaccess for security
        $htaccess_content = "Options -Indexes\nDeny from all";
        file_put_contents($wprosh_dir . '/.htaccess', $htaccess_content);
        
        // Set activation flag
        update_option('wprosh_version', WPROSH_VERSION);
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up temporary files
        $upload_dir = wp_upload_dir();
        $wprosh_dir = $upload_dir['basedir'] . '/wprosh';
        
        if (file_exists($wprosh_dir)) {
            $files = glob($wprosh_dir . '/*');
            foreach ($files as $file) {
                if (is_file($file) && basename($file) !== '.htaccess') {
                    unlink($file);
                }
            }
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Get plugin URL
     *
     * @return string
     */
    public function plugin_url() {
        return WPROSH_PLUGIN_URL;
    }
    
    /**
     * Get plugin path
     *
     * @return string
     */
    public function plugin_path() {
        return WPROSH_PLUGIN_DIR;
    }
}

/**
 * Get Wprosh instance
 *
 * @return Wprosh
 */
function wprosh() {
    return Wprosh::instance();
}

// Initialize plugin
wprosh();

