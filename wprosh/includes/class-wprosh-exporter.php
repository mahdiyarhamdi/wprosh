<?php
/**
 * Wprosh Exporter Class
 *
 * Handles CSV export of WooCommerce products
 *
 * @package Wprosh
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Wprosh_Exporter Class
 */
class Wprosh_Exporter {
    
    /**
     * CSV columns definition
     *
     * @var array
     */
    private $columns = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_columns();
    }
    
    /**
     * Initialize CSV columns
     */
    private function init_columns() {
        $this->columns = array(
            'id' => 'شناسه',
            'sku' => 'کد محصول (SKU)',
            'name' => 'نام محصول',
            'slug' => 'نامک',
            'type' => 'نوع محصول',
            'status' => 'وضعیت',
            'description' => 'توضیحات کامل',
            'short_description' => 'توضیحات کوتاه',
            'regular_price' => 'قیمت اصلی',
            'sale_price' => 'قیمت فروش ویژه',
            'sale_date_from' => 'تاریخ شروع تخفیف',
            'sale_date_to' => 'تاریخ پایان تخفیف',
            'tax_status' => 'وضعیت مالیات',
            'tax_class' => 'کلاس مالیاتی',
            'stock_status' => 'وضعیت موجودی',
            'stock_quantity' => 'تعداد موجودی',
            'manage_stock' => 'مدیریت موجودی',
            'backorders' => 'پیش‌سفارش',
            'low_stock_amount' => 'حد هشدار موجودی',
            'weight' => 'وزن',
            'length' => 'طول',
            'width' => 'عرض',
            'height' => 'ارتفاع',
            'categories' => 'دسته‌بندی‌ها',
            'tags' => 'برچسب‌ها',
            'attributes' => 'ویژگی‌ها',
            'parent_id' => 'شناسه والد',
            'menu_order' => 'ترتیب نمایش',
            'virtual' => 'مجازی',
            'downloadable' => 'دانلودی',
            'purchase_note' => 'یادداشت خرید',
            'catalog_visibility' => 'نمایش در کاتالوگ',
            'featured' => 'ویژه',
            'sold_individually' => 'فروش تکی',
            'upsell_ids' => 'محصولات پیشنهادی',
            'cross_sell_ids' => 'محصولات مرتبط',
        );
    }
    
    /**
     * Get column headers
     *
     * @return array
     */
    public function get_columns() {
        return $this->columns;
    }
    
    /**
     * Get column keys
     *
     * @return array
     */
    public function get_column_keys() {
        return array_keys($this->columns);
    }
    
    /**
     * Export products to CSV
     *
     * @return string|WP_Error File path or error
     */
    public function export() {
        // Get all products including variations
        $products = $this->get_all_products();
        
        if (empty($products)) {
            return new WP_Error('no_products', 'هیچ محصولی برای خروجی وجود ندارد.');
        }
        
        // Create CSV content
        $csv_data = $this->generate_csv($products);
        
        // Save to file
        $file_path = $this->save_csv_file($csv_data);
        
        return $file_path;
    }
    
    /**
     * Get all products and variations
     *
     * @return array
     */
    private function get_all_products() {
        $all_products = array();
        
        // Get all simple, variable, grouped, external products
        $args = array(
            'status' => array('publish', 'draft', 'pending', 'private'),
            'type' => array('simple', 'variable', 'grouped', 'external'),
            'limit' => -1,
            'orderby' => 'ID',
            'order' => 'ASC',
        );
        
        $products = wc_get_products($args);
        
        foreach ($products as $product) {
            // Add main product
            $all_products[] = $product;
            
            // Add variations for variable products
            if ($product->is_type('variable')) {
                $variations = $product->get_children();
                foreach ($variations as $variation_id) {
                    $variation = wc_get_product($variation_id);
                    if ($variation) {
                        $all_products[] = $variation;
                    }
                }
            }
        }
        
        return $all_products;
    }
    
    /**
     * Generate CSV content
     *
     * @param array $products Array of WC_Product objects
     * @return string CSV content
     */
    private function generate_csv($products) {
        $output = fopen('php://temp', 'r+');
        
        // Add BOM for UTF-8
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        
        // Write header row
        fputcsv($output, $this->get_column_keys());
        
        // Write product rows
        foreach ($products as $product) {
            $row = $this->get_product_row($product);
            fputcsv($output, $row);
        }
        
        rewind($output);
        $csv_content = stream_get_contents($output);
        fclose($output);
        
        return $csv_content;
    }
    
    /**
     * Get product data row
     *
     * @param WC_Product $product Product object
     * @return array Row data
     */
    private function get_product_row($product) {
        $row = array();
        
        foreach ($this->get_column_keys() as $column) {
            $row[$column] = $this->get_column_value($product, $column);
        }
        
        return $row;
    }
    
    /**
     * Get column value for product
     *
     * @param WC_Product $product Product object
     * @param string $column Column key
     * @return mixed Column value
     */
    private function get_column_value($product, $column) {
        switch ($column) {
            case 'id':
                return $product->get_id();
            
            case 'sku':
                return $product->get_sku();
            
            case 'name':
                return $product->get_name();
            
            case 'slug':
                return $product->get_slug();
            
            case 'type':
                return $product->get_type();
            
            case 'status':
                return $product->get_status();
            
            case 'description':
                return $product->get_description();
            
            case 'short_description':
                return $product->get_short_description();
            
            case 'regular_price':
                return $product->get_regular_price();
            
            case 'sale_price':
                return $product->get_sale_price();
            
            case 'sale_date_from':
                $date = $product->get_date_on_sale_from();
                return $date ? $date->format('Y-m-d') : '';
            
            case 'sale_date_to':
                $date = $product->get_date_on_sale_to();
                return $date ? $date->format('Y-m-d') : '';
            
            case 'tax_status':
                return $product->get_tax_status();
            
            case 'tax_class':
                return $product->get_tax_class();
            
            case 'stock_status':
                return $product->get_stock_status();
            
            case 'stock_quantity':
                return $product->get_stock_quantity();
            
            case 'manage_stock':
                return $product->get_manage_stock() ? 'yes' : 'no';
            
            case 'backorders':
                return $product->get_backorders();
            
            case 'low_stock_amount':
                return $product->get_low_stock_amount();
            
            case 'weight':
                return $product->get_weight();
            
            case 'length':
                return $product->get_length();
            
            case 'width':
                return $product->get_width();
            
            case 'height':
                return $product->get_height();
            
            case 'categories':
                return $this->get_categories_string($product);
            
            case 'tags':
                return $this->get_tags_string($product);
            
            case 'attributes':
                return $this->get_attributes_json($product);
            
            case 'parent_id':
                return $product->get_parent_id();
            
            case 'menu_order':
                return $product->get_menu_order();
            
            case 'virtual':
                return $product->is_virtual() ? 'yes' : 'no';
            
            case 'downloadable':
                return $product->is_downloadable() ? 'yes' : 'no';
            
            case 'purchase_note':
                return $product->get_purchase_note();
            
            case 'catalog_visibility':
                return $product->get_catalog_visibility();
            
            case 'featured':
                return $product->is_featured() ? 'yes' : 'no';
            
            case 'sold_individually':
                return $product->is_sold_individually() ? 'yes' : 'no';
            
            case 'upsell_ids':
                return implode('|', $product->get_upsell_ids());
            
            case 'cross_sell_ids':
                return implode('|', $product->get_cross_sell_ids());
            
            default:
                return '';
        }
    }
    
    /**
     * Get categories as pipe-separated string
     *
     * @param WC_Product $product Product object
     * @return string Categories string
     */
    private function get_categories_string($product) {
        // Variations don't have categories
        if ($product->is_type('variation')) {
            return '';
        }
        
        $terms = get_the_terms($product->get_id(), 'product_cat');
        
        if (empty($terms) || is_wp_error($terms)) {
            return '';
        }
        
        $names = array();
        foreach ($terms as $term) {
            $names[] = $term->name;
        }
        
        return implode('|', $names);
    }
    
    /**
     * Get tags as pipe-separated string
     *
     * @param WC_Product $product Product object
     * @return string Tags string
     */
    private function get_tags_string($product) {
        // Variations don't have tags
        if ($product->is_type('variation')) {
            return '';
        }
        
        $terms = get_the_terms($product->get_id(), 'product_tag');
        
        if (empty($terms) || is_wp_error($terms)) {
            return '';
        }
        
        $names = array();
        foreach ($terms as $term) {
            $names[] = $term->name;
        }
        
        return implode('|', $names);
    }
    
    /**
     * Get attributes as JSON string
     *
     * @param WC_Product $product Product object
     * @return string JSON string
     */
    private function get_attributes_json($product) {
        $attributes = $product->get_attributes();
        
        if (empty($attributes)) {
            return '';
        }
        
        $attr_data = array();
        
        foreach ($attributes as $attribute) {
            if ($product->is_type('variation')) {
                // For variations, get the attribute value
                $value = $attribute;
                $name = wc_attribute_label(str_replace('attribute_', '', $attribute));
                if (is_string($attribute)) {
                    // This is a variation attribute
                    $attr_name = str_replace('attribute_pa_', '', str_replace('attribute_', '', $attribute));
                    $attr_value = $product->get_attribute($attr_name);
                    if ($attr_value) {
                        $attr_data[$attr_name] = $attr_value;
                    }
                }
            } else {
                // For other products
                if (is_object($attribute)) {
                    $name = $attribute->get_name();
                    
                    // Check if it's a taxonomy attribute
                    if ($attribute->is_taxonomy()) {
                        $taxonomy = $attribute->get_taxonomy_object();
                        $name = $taxonomy ? $taxonomy->attribute_label : $name;
                        $terms = $attribute->get_terms();
                        $values = array();
                        if (!empty($terms)) {
                            foreach ($terms as $term) {
                                $values[] = $term->name;
                            }
                        }
                    } else {
                        // Custom attribute
                        $values = $attribute->get_options();
                    }
                    
                    $attr_data[$name] = is_array($values) ? implode('|', $values) : $values;
                }
            }
        }
        
        // For variations, get attributes differently
        if ($product->is_type('variation')) {
            $attr_data = array();
            $variation_attributes = $product->get_variation_attributes();
            foreach ($variation_attributes as $attr_key => $attr_value) {
                $attr_name = str_replace('attribute_pa_', '', str_replace('attribute_', '', $attr_key));
                $attr_label = wc_attribute_label($attr_name);
                $attr_data[$attr_label] = $attr_value;
            }
        }
        
        if (empty($attr_data)) {
            return '';
        }
        
        return json_encode($attr_data, JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * Save CSV content to file
     *
     * @param string $content CSV content
     * @return string|WP_Error File path or error
     */
    private function save_csv_file($content) {
        $upload_dir = wp_upload_dir();
        
        // Check for upload directory errors
        if (!empty($upload_dir['error'])) {
            return new WP_Error('upload_dir_error', 'خطا در دسترسی به پوشه آپلود: ' . $upload_dir['error']);
        }
        
        $wprosh_dir = $upload_dir['basedir'] . '/wprosh';
        
        // Create directory if not exists
        if (!file_exists($wprosh_dir)) {
            $created = wp_mkdir_p($wprosh_dir);
            if (!$created) {
                return new WP_Error('mkdir_error', 'خطا در ایجاد پوشه wprosh در مسیر uploads. لطفاً دسترسی پوشه uploads را بررسی کنید.');
            }
        }
        
        // Check if directory is writable
        if (!is_writable($wprosh_dir)) {
            return new WP_Error('not_writable', 'پوشه wprosh قابل نوشتن نیست. لطفاً دسترسی (permission) پوشه را به 755 یا 775 تغییر دهید.');
        }
        
        // Generate filename with timestamp
        $filename = 'wprosh-products-' . date('Y-m-d-H-i-s') . '.csv';
        $file_path = $wprosh_dir . '/' . $filename;
        
        // Write file with error suppression to catch the error message
        $result = @file_put_contents($file_path, $content);
        
        if ($result === false) {
            $error = error_get_last();
            $error_msg = isset($error['message']) ? $error['message'] : 'دلیل نامشخص';
            return new WP_Error('write_error', 'خطا در نوشتن فایل CSV: ' . $error_msg);
        }
        
        return $file_path;
    }
    
    /**
     * Get download URL for file
     *
     * @param string $file_path File path
     * @return string Download URL
     */
    public function get_download_url($file_path) {
        $upload_dir = wp_upload_dir();
        $file_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $file_path);
        return $file_url;
    }
    
    /**
     * Stream CSV directly to browser
     *
     * @return void
     */
    public function stream_export() {
        // Get all products
        $products = $this->get_all_products();
        
        if (empty($products)) {
            wp_die('هیچ محصولی برای خروجی وجود ندارد.');
        }
        
        // Set headers for download
        $filename = 'wprosh-products-' . date('Y-m-d-H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        
        // Write header row
        fputcsv($output, $this->get_column_keys());
        
        // Write product rows
        foreach ($products as $product) {
            $row = $this->get_product_row($product);
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Get export statistics
     *
     * @return array Statistics
     */
    public function get_statistics() {
        $products = $this->get_all_products();
        
        $stats = array(
            'total' => count($products),
            'simple' => 0,
            'variable' => 0,
            'variation' => 0,
            'grouped' => 0,
            'external' => 0,
        );
        
        foreach ($products as $product) {
            $type = $product->get_type();
            if (isset($stats[$type])) {
                $stats[$type]++;
            }
        }
        
        return $stats;
    }
}

