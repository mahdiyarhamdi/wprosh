<?php
/**
 * Wprosh Sync Class
 *
 * Handles synchronization with accounting software
 * Supports CSV and XLSX file formats
 *
 * @package Wprosh
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Wprosh_Sync Class
 */
class Wprosh_Sync {
    
    /**
     * Field mapping from accounting to WooCommerce
     *
     * @var array
     */
    private $field_mapping = array(
        'نام کالا' => 'name',
        'سریال' => 'sku',
        'قیمت فروش' => 'regular_price',
        'موجودی اولیه' => 'stock_quantity',
        'تخفیف فروش' => 'sale_discount',
    );
    
    /**
     * Results tracking
     *
     * @var array
     */
    private $results = array(
        'total' => 0,
        'created' => 0,
        'updated' => 0,
        'failed' => 0,
        'skipped' => 0,
    );
    
    /**
     * Errors collection
     *
     * @var array
     */
    private $errors = array();
    
    /**
     * Processed products for output
     *
     * @var array
     */
    private $processed_products = array();
    
    /**
     * Exporter instance for output format
     *
     * @var Wprosh_Exporter
     */
    private $exporter;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->exporter = new Wprosh_Exporter();
    }
    
    /**
     * Process accounting file
     *
     * @param string $file_path Path to uploaded file
     * @param string $file_type File type (csv or xlsx)
     * @return array Results
     */
    public function process($file_path, $file_type = 'csv') {
        // Reset results
        $this->reset_results();
        
        // Parse file based on type
        if ($file_type === 'xlsx') {
            $rows = $this->parse_xlsx($file_path);
        } else {
            $rows = $this->parse_csv($file_path);
        }
        
        if (is_wp_error($rows)) {
            return array(
                'success' => false,
                'message' => $rows->get_error_message(),
            );
        }
        
        if (empty($rows)) {
            return array(
                'success' => false,
                'message' => 'فایل خالی است یا فرمت آن نامعتبر است.',
            );
        }
        
        $this->results['total'] = count($rows);
        
        // Process each row
        foreach ($rows as $index => $row) {
            $row_number = $index + 2; // +2 for header and 0-based index
            $this->process_row($row, $row_number);
        }
        
        // Generate output CSV
        $output_csv = $this->generate_output_csv();
        
        // Generate error report
        $error_report = $this->generate_error_report();
        
        return array(
            'success' => true,
            'results' => $this->results,
            'output_csv' => $output_csv,
            'error_report' => $error_report,
            'errors' => $this->errors,
        );
    }
    
    /**
     * Reset results
     */
    private function reset_results() {
        $this->results = array(
            'total' => 0,
            'created' => 0,
            'updated' => 0,
            'failed' => 0,
            'skipped' => 0,
        );
        $this->errors = array();
        $this->processed_products = array();
    }
    
    /**
     * Parse CSV file
     *
     * @param string $file_path Path to CSV file
     * @return array|WP_Error Parsed rows or error
     */
    private function parse_csv($file_path) {
        $rows = array();
        $header = array();
        
        // Detect delimiter
        $delimiter = $this->detect_delimiter($file_path);
        
        if (($handle = fopen($file_path, 'r')) !== false) {
            // Read BOM if exists
            $bom = fread($handle, 3);
            if ($bom !== chr(0xEF) . chr(0xBB) . chr(0xBF)) {
                rewind($handle);
            }
            
            $line = 0;
            while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
                $line++;
                
                if ($line === 1) {
                    // Header row
                    $header = array_map('trim', $data);
                    continue;
                }
                
                // Skip empty rows
                if (empty(array_filter($data))) {
                    continue;
                }
                
                // Map data to header
                $row = array();
                foreach ($header as $index => $key) {
                    $row[$key] = isset($data[$index]) ? trim($data[$index]) : '';
                }
                
                $rows[] = $row;
            }
            
            fclose($handle);
        } else {
            return new WP_Error('parse_error', 'خطا در خواندن فایل CSV.');
        }
        
        return $rows;
    }
    
    /**
     * Parse XLSX file
     *
     * @param string $file_path Path to XLSX file
     * @return array|WP_Error Parsed rows or error
     */
    private function parse_xlsx($file_path) {
        // Simple XLSX parser without external library
        // XLSX is a ZIP file containing XML files
        
        $rows = array();
        
        $zip = new ZipArchive();
        if ($zip->open($file_path) !== true) {
            return new WP_Error('xlsx_error', 'خطا در باز کردن فایل XLSX.');
        }
        
        // Read shared strings
        $shared_strings = array();
        $shared_strings_xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($shared_strings_xml) {
            $xml = simplexml_load_string($shared_strings_xml);
            if ($xml) {
                foreach ($xml->si as $si) {
                    if (isset($si->t)) {
                        $shared_strings[] = (string) $si->t;
                    } elseif (isset($si->r)) {
                        $text = '';
                        foreach ($si->r as $r) {
                            $text .= (string) $r->t;
                        }
                        $shared_strings[] = $text;
                    }
                }
            }
        }
        
        // Read worksheet
        $sheet_xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if (!$sheet_xml) {
            $zip->close();
            return new WP_Error('xlsx_error', 'فایل XLSX فاقد sheet است.');
        }
        
        $xml = simplexml_load_string($sheet_xml);
        if (!$xml) {
            $zip->close();
            return new WP_Error('xlsx_error', 'خطا در خواندن محتوای XLSX.');
        }
        
        $zip->close();
        
        $header = array();
        $row_num = 0;
        
        foreach ($xml->sheetData->row as $row) {
            $row_num++;
            $row_data = array();
            $col_index = 0;
            
            foreach ($row->c as $cell) {
                $value = '';
                
                // Get cell reference to determine column
                $cell_ref = (string) $cell['r'];
                $col_letter = preg_replace('/[0-9]/', '', $cell_ref);
                $col_index = $this->column_letter_to_index($col_letter);
                
                // Check cell type
                $type = (string) $cell['t'];
                
                if ($type === 's') {
                    // Shared string
                    $string_index = (int) $cell->v;
                    $value = isset($shared_strings[$string_index]) ? $shared_strings[$string_index] : '';
                } elseif (isset($cell->v)) {
                    $value = (string) $cell->v;
                }
                
                $row_data[$col_index] = $value;
            }
            
            // Fill in missing columns
            if (!empty($row_data)) {
                $max_col = max(array_keys($row_data));
                for ($i = 0; $i <= $max_col; $i++) {
                    if (!isset($row_data[$i])) {
                        $row_data[$i] = '';
                    }
                }
                ksort($row_data);
                $row_data = array_values($row_data);
            }
            
            if ($row_num === 1) {
                // Header row
                $header = array_map('trim', $row_data);
            } else {
                // Skip empty rows
                if (empty(array_filter($row_data))) {
                    continue;
                }
                
                // Map to header
                $mapped_row = array();
                foreach ($header as $index => $key) {
                    $mapped_row[$key] = isset($row_data[$index]) ? trim($row_data[$index]) : '';
                }
                
                $rows[] = $mapped_row;
            }
        }
        
        return $rows;
    }
    
    /**
     * Convert column letter to index (A=0, B=1, etc.)
     *
     * @param string $letter Column letter
     * @return int Column index
     */
    private function column_letter_to_index($letter) {
        $letter = strtoupper($letter);
        $length = strlen($letter);
        $index = 0;
        
        for ($i = 0; $i < $length; $i++) {
            $index = $index * 26 + (ord($letter[$i]) - ord('A') + 1);
        }
        
        return $index - 1;
    }
    
    /**
     * Detect CSV delimiter
     *
     * @param string $file_path Path to CSV file
     * @return string Delimiter
     */
    private function detect_delimiter($file_path) {
        $delimiters = array(',', ';', "\t", '|');
        $results = array();
        
        $handle = fopen($file_path, 'r');
        if ($handle) {
            $line = fgets($handle);
            fclose($handle);
            
            foreach ($delimiters as $delimiter) {
                $count = count(str_getcsv($line, $delimiter));
                $results[$delimiter] = $count;
            }
        }
        
        arsort($results);
        return array_key_first($results);
    }
    
    /**
     * Process a single row
     *
     * @param array $row Row data
     * @param int $row_number Row number for error reporting
     */
    private function process_row($row, $row_number) {
        // Get SKU (serial)
        $sku = isset($row['سریال']) ? trim($row['سریال']) : '';
        
        if (empty($sku)) {
            $this->add_error($row_number, '', 'سریال', '', 'EMPTY_SKU', 'فیلد سریال خالی است', 'سریال محصول را وارد کنید');
            $this->results['skipped']++;
            return;
        }
        
        // Get other fields
        $name = isset($row['نام کالا']) ? trim($row['نام کالا']) : '';
        $regular_price_rial = isset($row['قیمت فروش']) ? $this->sanitize_price($row['قیمت فروش']) : '';
        $stock_quantity = isset($row['موجودی اولیه']) ? intval($row['موجودی اولیه']) : 0;
        $sale_discount_rial = isset($row['تخفیف فروش']) ? $this->sanitize_price($row['تخفیف فروش']) : 0;
        
        // Convert Rial to Toman (divide by 10)
        $regular_price = $regular_price_rial !== '' ? floor(floatval($regular_price_rial) / 10) : '';
        $sale_discount = $sale_discount_rial > 0 ? floor(floatval($sale_discount_rial) / 10) : 0;
        
        // Calculate sale price (in Toman)
        $sale_price = '';
        if ($regular_price !== '' && $sale_discount > 0) {
            $sale_price = max(0, floatval($regular_price) - floatval($sale_discount));
        }
        
        // Check if product exists by SKU
        $existing_product_id = wc_get_product_id_by_sku($sku);
        
        if ($existing_product_id) {
            // Update existing product
            $this->update_product($existing_product_id, $row_number, $sku, $regular_price, $stock_quantity, $sale_price);
        } else {
            // Create new product
            $this->create_product($row_number, $name, $sku, $regular_price, $stock_quantity, $sale_price);
        }
    }
    
    /**
     * Create a new product
     *
     * @param int $row_number Row number
     * @param string $name Product name
     * @param string $sku SKU
     * @param string $regular_price Regular price
     * @param int $stock_quantity Stock quantity
     * @param string $sale_price Sale price
     */
    private function create_product($row_number, $name, $sku, $regular_price, $stock_quantity, $sale_price) {
        if (empty($name)) {
            $this->add_error($row_number, '', 'نام کالا', '', 'EMPTY_NAME', 'نام کالا برای محصول جدید الزامی است', 'نام کالا را وارد کنید');
            $this->results['failed']++;
            return;
        }
        
        try {
            $product = new WC_Product_Simple();
            
            $product->set_name($name);
            $product->set_sku($sku);
            $product->set_status('publish');
            $product->set_catalog_visibility('visible');
            $product->set_manage_stock(true);
            $product->set_stock_quantity($stock_quantity);
            $product->set_stock_status($stock_quantity > 0 ? 'instock' : 'outofstock');
            
            if ($regular_price !== '') {
                $product->set_regular_price($regular_price);
            }
            
            if ($sale_price !== '' && $sale_price > 0) {
                $product->set_sale_price($sale_price);
            }
            
            // Set default category "دسته بندی نشده" with slug "uncat"
            $default_category = $this->get_or_create_default_category();
            if ($default_category) {
                $product->set_category_ids(array($default_category));
            }
            
            $product_id = $product->save();
            
            if ($product_id) {
                $this->results['created']++;
                $this->add_to_output($product_id);
            } else {
                $this->add_error($row_number, '', 'sku', $sku, 'CREATE_FAILED', 'خطا در ایجاد محصول', 'دوباره تلاش کنید');
                $this->results['failed']++;
            }
            
        } catch (WC_Data_Exception $e) {
            $this->add_error($row_number, '', 'sku', $sku, 'WC_ERROR', $e->getMessage(), 'مشکل را برطرف کنید');
            $this->results['failed']++;
        } catch (Exception $e) {
            $this->add_error($row_number, '', 'sku', $sku, 'ERROR', $e->getMessage(), 'دوباره تلاش کنید');
            $this->results['failed']++;
        }
    }
    
    /**
     * Update an existing product
     *
     * @param int $product_id Product ID
     * @param int $row_number Row number
     * @param string $sku SKU
     * @param string $regular_price Regular price
     * @param int $stock_quantity Stock quantity
     * @param string $sale_price Sale price
     */
    private function update_product($product_id, $row_number, $sku, $regular_price, $stock_quantity, $sale_price) {
        $product = wc_get_product($product_id);
        
        if (!$product) {
            $this->add_error($row_number, $product_id, 'sku', $sku, 'PRODUCT_NOT_FOUND', 'محصول یافت نشد', 'SKU را بررسی کنید');
            $this->results['failed']++;
            return;
        }
        
        try {
            // Save original images (critical protection)
            $original_image_id = $product->get_image_id();
            $original_gallery_ids = $product->get_gallery_image_ids();
            
            // Update fields (don't change name)
            if ($regular_price !== '') {
                $product->set_regular_price($regular_price);
            }
            
            if ($sale_price !== '' && $sale_price > 0) {
                $product->set_sale_price($sale_price);
            } else {
                $product->set_sale_price('');
            }
            
            $product->set_manage_stock(true);
            $product->set_stock_quantity($stock_quantity);
            $product->set_stock_status($stock_quantity > 0 ? 'instock' : 'outofstock');
            
            // Restore images before save
            if ($original_image_id) {
                $product->set_image_id($original_image_id);
            }
            if (!empty($original_gallery_ids)) {
                $product->set_gallery_image_ids($original_gallery_ids);
            }
            
            $product->save();
            
            $this->results['updated']++;
            $this->add_to_output($product_id);
            
        } catch (WC_Data_Exception $e) {
            $this->add_error($row_number, $product_id, 'sku', $sku, 'WC_ERROR', $e->getMessage(), 'مشکل را برطرف کنید');
            $this->results['failed']++;
        } catch (Exception $e) {
            $this->add_error($row_number, $product_id, 'sku', $sku, 'ERROR', $e->getMessage(), 'دوباره تلاش کنید');
            $this->results['failed']++;
        }
    }
    
    /**
     * Get or create the default category "دسته بندی نشده"
     *
     * @return int|false Category ID or false on failure
     */
    private function get_or_create_default_category() {
        $category_slug = 'uncat';
        $category_name = 'دسته بندی نشده';
        
        // Try to get existing category by slug
        $term = get_term_by('slug', $category_slug, 'product_cat');
        
        if ($term && !is_wp_error($term)) {
            return $term->term_id;
        }
        
        // Create the category if it doesn't exist
        $result = wp_insert_term(
            $category_name,
            'product_cat',
            array(
                'slug' => $category_slug,
                'description' => 'محصولات دسته‌بندی نشده از همگام‌سازی با حسابداری',
            )
        );
        
        if (is_wp_error($result)) {
            // If error is because term exists, try to get it
            if ($result->get_error_code() === 'term_exists') {
                $existing_term_id = $result->get_error_data('term_exists');
                return $existing_term_id;
            }
            return false;
        }
        
        return $result['term_id'];
    }
    
    /**
     * Add product to output list
     *
     * @param int $product_id Product ID
     */
    private function add_to_output($product_id) {
        $product = wc_get_product($product_id);
        if ($product) {
            $this->processed_products[] = $product;
        }
    }
    
    /**
     * Sanitize price value
     *
     * @param string $price Price string
     * @return string Sanitized price
     */
    private function sanitize_price($price) {
        // Remove any non-numeric characters except decimal point
        $price = preg_replace('/[^\d.]/', '', $price);
        return $price;
    }
    
    /**
     * Add error to collection
     *
     * @param int $row_number Row number
     * @param int $product_id Product ID
     * @param string $field Field name
     * @param string $value Current value
     * @param string $error_code Error code
     * @param string $message Error message
     * @param string $suggestion Suggestion
     */
    private function add_error($row_number, $product_id, $field, $value, $error_code, $message, $suggestion) {
        $this->errors[] = array(
            'row_number' => $row_number,
            'product_id' => $product_id,
            'product_name' => '',
            'field_name' => $field,
            'current_value' => $value,
            'error_code' => $error_code,
            'error_message' => $message,
            'suggestion' => $suggestion,
        );
    }
    
    /**
     * Generate output CSV in export format
     *
     * @return array|false Array with content and filename, or false if no products
     */
    private function generate_output_csv() {
        if (empty($this->processed_products)) {
            return false;
        }
        
        // Use exporter to generate CSV in same format
        $output = fopen('php://temp', 'r+');
        
        // Add BOM for UTF-8
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        
        // Write header
        $columns = $this->exporter->get_column_keys();
        fputcsv($output, $columns);
        
        // Write product rows
        foreach ($this->processed_products as $product) {
            $row = $this->get_product_row($product, $columns);
            fputcsv($output, $row);
        }
        
        rewind($output);
        $csv_content = stream_get_contents($output);
        fclose($output);
        
        $filename = 'wprosh-sync-' . date('Y-m-d-H-i-s') . '.csv';
        
        return array(
            'content' => base64_encode($csv_content),
            'filename' => $filename,
        );
    }
    
    /**
     * Get product row data for CSV
     *
     * @param WC_Product $product Product object
     * @param array $columns Column keys
     * @return array Row data
     */
    private function get_product_row($product, $columns) {
        $row = array();
        
        foreach ($columns as $column) {
            $row[] = $this->get_column_value($product, $column);
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
                return $this->get_terms_string($product, 'product_cat');
            case 'tags':
                return $this->get_terms_string($product, 'product_tag');
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
     * Get terms as pipe-separated string
     *
     * @param WC_Product $product Product object
     * @param string $taxonomy Taxonomy name
     * @return string Terms string
     */
    private function get_terms_string($product, $taxonomy) {
        if ($product->is_type('variation')) {
            return '';
        }
        
        $terms = get_the_terms($product->get_id(), $taxonomy);
        
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
            if (is_object($attribute)) {
                $name = $attribute->get_name();
                
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
                    $values = $attribute->get_options();
                }
                
                $attr_data[$name] = is_array($values) ? implode('|', $values) : $values;
            }
        }
        
        if (empty($attr_data)) {
            return '';
        }
        
        return json_encode($attr_data, JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * Generate error report CSV
     *
     * @return array|false Array with content and filename, or false if no errors
     */
    private function generate_error_report() {
        if (empty($this->errors)) {
            return false;
        }
        
        $output = fopen('php://temp', 'r+');
        
        // Add BOM for UTF-8
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        
        // Header
        $header = array(
            'row_number',
            'product_id',
            'product_name',
            'field_name',
            'current_value',
            'error_code',
            'error_message',
            'suggestion',
        );
        fputcsv($output, $header);
        
        // Rows
        foreach ($this->errors as $error) {
            $row = array(
                $error['row_number'],
                $error['product_id'],
                $error['product_name'],
                $error['field_name'],
                $error['current_value'],
                $error['error_code'],
                $error['error_message'],
                $error['suggestion'],
            );
            fputcsv($output, $row);
        }
        
        rewind($output);
        $csv_content = stream_get_contents($output);
        fclose($output);
        
        $filename = 'wprosh-sync-errors-' . date('Y-m-d-H-i-s') . '.csv';
        
        return array(
            'content' => base64_encode($csv_content),
            'filename' => $filename,
        );
    }
    
    /**
     * Get results
     *
     * @return array
     */
    public function get_results() {
        return $this->results;
    }
    
    /**
     * Get errors
     *
     * @return array
     */
    public function get_errors() {
        return $this->errors;
    }
}

