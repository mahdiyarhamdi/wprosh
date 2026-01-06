<?php
/**
 * Wprosh Importer Class
 *
 * Handles CSV import and product updates with error reporting
 *
 * @package Wprosh
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Wprosh_Importer Class
 */
class Wprosh_Importer {
    
    /**
     * Validator instance
     *
     * @var Wprosh_Validator
     */
    private $validator;
    
    /**
     * Import results
     *
     * @var array
     */
    private $results = array(
        'total' => 0,
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
     * Fields that can be updated
     *
     * @var array
     */
    private $updatable_fields = array(
        'sku',
        'name',
        'slug',
        'status',
        'description',
        'short_description',
        'regular_price',
        'sale_price',
        'sale_date_from',
        'sale_date_to',
        'tax_status',
        'tax_class',
        'stock_status',
        'stock_quantity',
        'manage_stock',
        'backorders',
        'low_stock_amount',
        'weight',
        'length',
        'width',
        'height',
        'categories',
        'tags',
        'attributes',
        'menu_order',
        'virtual',
        'downloadable',
        'purchase_note',
        'catalog_visibility',
        'featured',
        'sold_individually',
        'upsell_ids',
        'cross_sell_ids',
    );
    
    /**
     * Read-only fields (cannot be updated)
     *
     * @var array
     */
    private $readonly_fields = array(
        'id',
        'type',
        'parent_id',
    );
    
    /**
     * Blacklisted fields - NEVER process these to prevent accidental data loss
     * Images are excluded to prevent accidental deletion
     *
     * @var array
     */
    private $blacklisted_fields = array(
        'image_id',
        'image',
        'images',
        'gallery_image_ids',
        'gallery_images',
        'featured_image',
        'product_image',
        'thumbnail',
        'thumbnail_id',
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->validator = new Wprosh_Validator();
    }
    
    /**
     * Import products from CSV file
     *
     * @param string $file_path Path to CSV file
     * @return array Import results
     */
    public function import($file_path) {
        // Reset results
        $this->reset_results();
        
        // Validate file
        if (!file_exists($file_path)) {
            return array(
                'success' => false,
                'message' => 'فایل یافت نشد.',
            );
        }
        
        // Parse CSV
        $rows = $this->parse_csv($file_path);
        
        if (is_wp_error($rows)) {
            return array(
                'success' => false,
                'message' => $rows->get_error_message(),
            );
        }
        
        if (empty($rows)) {
            return array(
                'success' => false,
                'message' => 'فایل CSV خالی است یا فرمت آن نامعتبر است.',
            );
        }
        
        // Process each row
        $this->results['total'] = count($rows);
        
        foreach ($rows as $index => $row) {
            $row_number = $index + 2; // +2 because: 1 for 0-based index, 1 for header row
            $this->process_row($row, $row_number);
        }
        
        // Generate error report
        $error_report = $this->generate_error_report();
        
        return array(
            'success' => true,
            'results' => $this->results,
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
            'updated' => 0,
            'failed' => 0,
            'skipped' => 0,
        );
        $this->errors = array();
        $this->validator->clear_errors();
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
        
        // Try to auto-detect delimiter
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
                    $header = array_map('strtolower', $header);
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
            // Read first line
            $line = fgets($handle);
            fclose($handle);
            
            foreach ($delimiters as $delimiter) {
                $count = count(str_getcsv($line, $delimiter));
                $results[$delimiter] = $count;
            }
        }
        
        // Return delimiter with most columns
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
        // Set up validator for this row
        $this->validator->set_current_row($row_number);
        $this->validator->set_current_data($row);
        
        // Validate product ID
        if (!isset($row['id']) || empty($row['id'])) {
            $this->validator->add_error('id', 'EMPTY_REQUIRED_FIELD', '', array('id'));
            $this->add_errors_from_validator();
            $this->results['failed']++;
            return;
        }
        
        $product_id = $this->validator->validate_product_id($row['id']);
        
        if ($product_id === false) {
            $this->add_errors_from_validator();
            $this->results['failed']++;
            return;
        }
        
        // Get product
        $product = wc_get_product($product_id);
        
        if (!$product) {
            $this->validator->add_error('id', 'PRODUCT_NOT_FOUND', $row['id']);
            $this->add_errors_from_validator();
            $this->results['failed']++;
            return;
        }
        
        // Check user permission
        if (!current_user_can('edit_product', $product_id)) {
            $this->validator->add_error('id', 'PERMISSION_DENIED', $row['id']);
            $this->add_errors_from_validator();
            $this->results['failed']++;
            return;
        }
        
        // FIRST: Check if row has any changes before processing
        $has_changes = $this->row_has_changes($row, $product);
        
        if (!$has_changes) {
            // No changes in this row, skip it
            $this->results['skipped']++;
            $this->validator->clear_errors(); // Clear any validation errors
            return;
        }
        
        // Validate and prepare data (only for changed fields)
        $update_data = $this->prepare_update_data($row, $product);
        
        // Check for critical errors before update
        $critical_errors = $this->get_critical_errors();
        if (!empty($critical_errors)) {
            $this->add_errors_from_validator();
            $this->results['failed']++;
            return;
        }
        
        // If no actual data to update (all validations failed), count as failed
        if (empty($update_data)) {
            $this->add_errors_from_validator();
            if ($this->validator->has_errors()) {
                $this->results['failed']++;
            } else {
                $this->results['skipped']++;
            }
            return;
        }
        
        // Update product
        $update_result = $this->update_product($product, $update_data);
        
        if (is_wp_error($update_result)) {
            $this->validator->add_error('id', 'DATABASE_ERROR', '', array($update_result->get_error_message()));
            $this->add_errors_from_validator();
            $this->results['failed']++;
            return;
        }
        
        // Add any non-critical errors (field-level errors)
        $this->add_errors_from_validator();
        
        // Count as updated
        $this->results['updated']++;
    }
    
    /**
     * Check if a row has any changes compared to current product data
     *
     * @param array $row Row data from CSV
     * @param WC_Product $product Product object
     * @return bool True if row has changes
     */
    private function row_has_changes($row, $product) {
        foreach ($this->updatable_fields as $field) {
            // Skip blacklisted fields (images, etc.)
            if (in_array($field, $this->blacklisted_fields)) {
                continue;
            }
            
            if (!isset($row[$field])) {
                continue;
            }
            
            $csv_value = $row[$field];
            $current_value = $this->get_current_value($product, $field);
            
            // Compare values - if different, row has changes
            if (!$this->values_are_equal($csv_value, $current_value, $field)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Prepare update data with validation
     *
     * @param array $row Row data
     * @param WC_Product $product Product object
     * @return array Validated update data
     */
    private function prepare_update_data($row, $product) {
        $update_data = array();
        $product_type = $product->get_type();
        $product_id = $product->get_id();
        
        foreach ($this->updatable_fields as $field) {
            // Skip blacklisted fields (images, etc.) - NEVER process these
            if (in_array($field, $this->blacklisted_fields)) {
                continue;
            }
            
            if (!isset($row[$field])) {
                continue;
            }
            
            $value = $row[$field];
            
            // Skip if value hasn't changed
            $current_value = $this->get_current_value($product, $field);
            if ($this->values_are_equal($value, $current_value, $field)) {
                continue;
            }
            
            // Validate based on field type
            switch ($field) {
                case 'sku':
                    $validated = $this->validator->validate_sku($value, $product_id);
                    if ($validated !== false) {
                        $update_data[$field] = $validated;
                    }
                    break;
                
                case 'slug':
                    $validated = $this->validator->validate_slug($value, $product_id);
                    if ($validated !== false) {
                        $update_data[$field] = $validated;
                    }
                    break;
                
                case 'name':
                    if (!empty($value)) {
                        $update_data[$field] = sanitize_text_field($value);
                    }
                    break;
                
                case 'status':
                    $validated = $this->validator->validate_status($value);
                    if ($validated !== false && $validated !== '') {
                        $update_data[$field] = $validated;
                    }
                    break;
                
                case 'description':
                case 'short_description':
                    $update_data[$field] = wp_kses_post($value);
                    break;
                
                case 'regular_price':
                    // Variable products shouldn't have direct price
                    if ($product_type === 'variable' && !empty($value)) {
                        $this->validator->add_error($field, 'VARIABLE_PRODUCT_NO_PRICE', $value);
                    } else {
                        $validated = $this->validator->validate_price($value, $field, $product_type);
                        if ($validated !== false) {
                            $update_data[$field] = $validated;
                        }
                    }
                    break;
                
                case 'sale_price':
                    if ($product_type === 'variable' && !empty($value)) {
                        $this->validator->add_error($field, 'VARIABLE_PRODUCT_NO_PRICE', $value);
                    } else {
                        $validated = $this->validator->validate_price($value, $field, $product_type);
                        if ($validated !== false) {
                            $update_data[$field] = $validated;
                            
                            // Validate against regular price
                            $regular_price = isset($update_data['regular_price']) ? $update_data['regular_price'] : $product->get_regular_price();
                            if ($validated !== '' && $regular_price !== '') {
                                $this->validator->validate_sale_price_against_regular($validated, $regular_price);
                            }
                        }
                    }
                    break;
                
                case 'sale_date_from':
                case 'sale_date_to':
                    $validated = $this->validator->validate_date($value, $field);
                    if ($validated !== false) {
                        $update_data[$field] = $validated;
                    }
                    break;
                
                case 'tax_status':
                    $validated = $this->validator->validate_tax_status($value);
                    if ($validated !== false && $validated !== '') {
                        $update_data[$field] = $validated;
                    }
                    break;
                
                case 'tax_class':
                    $update_data[$field] = sanitize_text_field($value);
                    break;
                
                case 'stock_status':
                    $validated = $this->validator->validate_stock_status($value);
                    if ($validated !== false && $validated !== '') {
                        $update_data[$field] = $validated;
                    }
                    break;
                
                case 'stock_quantity':
                    $validated = $this->validator->validate_stock_quantity($value);
                    if ($validated !== false) {
                        // Check if manage_stock is enabled
                        $manage_stock = isset($update_data['manage_stock']) ? $update_data['manage_stock'] : ($product->get_manage_stock() ? 'yes' : 'no');
                        if ($manage_stock !== 'yes' && $validated !== '') {
                            $this->validator->add_error($field, 'STOCK_WITHOUT_MANAGE', $value);
                        } else {
                            $update_data[$field] = $validated;
                        }
                    }
                    break;
                
                case 'manage_stock':
                    $validated = $this->validator->validate_boolean($value, $field);
                    if ($validated !== false) {
                        $update_data[$field] = $validated;
                    }
                    break;
                
                case 'backorders':
                    $validated = $this->validator->validate_backorders($value);
                    if ($validated !== false && $validated !== '') {
                        $update_data[$field] = $validated;
                    }
                    break;
                
                case 'low_stock_amount':
                    $validated = $this->validator->validate_low_stock_amount($value);
                    if ($validated !== false) {
                        $update_data[$field] = $validated;
                    }
                    break;
                
                case 'weight':
                case 'length':
                case 'width':
                case 'height':
                    $validated = $this->validator->validate_dimension($value, $field);
                    if ($validated !== false) {
                        $update_data[$field] = $validated;
                    }
                    break;
                
                case 'categories':
                    // Variations don't have categories
                    if ($product_type === 'variation') {
                        continue 2;
                    }
                    $validated = $this->validator->validate_categories($value);
                    if ($validated !== false) {
                        $update_data[$field] = $validated;
                    }
                    break;
                
                case 'tags':
                    // Variations don't have tags
                    if ($product_type === 'variation') {
                        continue 2;
                    }
                    $validated = $this->validator->validate_tags($value);
                    if ($validated !== false) {
                        $update_data[$field] = $validated;
                    }
                    break;
                
                case 'attributes':
                    $validated = $this->validator->validate_attributes($value);
                    if ($validated !== false) {
                        $update_data[$field] = $validated;
                    }
                    break;
                
                case 'menu_order':
                    $validated = $this->validator->validate_menu_order($value);
                    if ($validated !== false) {
                        $update_data[$field] = $validated;
                    }
                    break;
                
                case 'virtual':
                case 'downloadable':
                case 'featured':
                case 'sold_individually':
                    $validated = $this->validator->validate_boolean($value, $field);
                    if ($validated !== false) {
                        $update_data[$field] = $validated;
                    }
                    break;
                
                case 'purchase_note':
                    $update_data[$field] = wp_kses_post($value);
                    break;
                
                case 'catalog_visibility':
                    $validated = $this->validator->validate_catalog_visibility($value);
                    if ($validated !== false && $validated !== '') {
                        $update_data[$field] = $validated;
                    }
                    break;
                
                case 'upsell_ids':
                    $validated = $this->validator->validate_product_ids($value, $field);
                    $update_data[$field] = $validated;
                    break;
                
                case 'cross_sell_ids':
                    $validated = $this->validator->validate_product_ids($value, $field);
                    $update_data[$field] = $validated;
                    break;
            }
        }
        
        // Validate sale dates relationship
        if (isset($update_data['sale_date_from']) || isset($update_data['sale_date_to'])) {
            $date_from = isset($update_data['sale_date_from']) ? $update_data['sale_date_from'] : ($product->get_date_on_sale_from() ? $product->get_date_on_sale_from()->format('Y-m-d') : '');
            $date_to = isset($update_data['sale_date_to']) ? $update_data['sale_date_to'] : ($product->get_date_on_sale_to() ? $product->get_date_on_sale_to()->format('Y-m-d') : '');
            $this->validator->validate_sale_dates($date_from, $date_to);
        }
        
        return $update_data;
    }
    
    /**
     * Get current value of a field from product
     *
     * @param WC_Product $product Product object
     * @param string $field Field name
     * @return mixed Current value
     */
    private function get_current_value($product, $field) {
        switch ($field) {
            case 'sku':
                return $product->get_sku();
            case 'name':
                return $product->get_name();
            case 'slug':
                return $product->get_slug();
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
     * Check if two values are equal
     *
     * @param mixed $new_value New value
     * @param mixed $current_value Current value
     * @param string $field Field name
     * @return bool
     */
    private function values_are_equal($new_value, $current_value, $field) {
        // Normalize empty values
        if (($new_value === '' || $new_value === null) && ($current_value === '' || $current_value === null)) {
            return true;
        }
        
        // Compare as strings for most fields
        return (string)$new_value === (string)$current_value;
    }
    
    /**
     * Update product with validated data
     *
     * @param WC_Product $product Product object
     * @param array $data Update data
     * @return bool|WP_Error
     */
    private function update_product($product, $data) {
        if (empty($data)) {
            return true;
        }
        
        try {
            foreach ($data as $field => $value) {
                switch ($field) {
                    case 'sku':
                        $product->set_sku($value);
                        break;
                    case 'name':
                        $product->set_name($value);
                        break;
                    case 'slug':
                        $product->set_slug($value);
                        break;
                    case 'status':
                        $product->set_status($value);
                        break;
                    case 'description':
                        $product->set_description($value);
                        break;
                    case 'short_description':
                        $product->set_short_description($value);
                        break;
                    case 'regular_price':
                        $product->set_regular_price($value);
                        break;
                    case 'sale_price':
                        $product->set_sale_price($value);
                        break;
                    case 'sale_date_from':
                        if (!empty($value)) {
                            $product->set_date_on_sale_from($value);
                        } else {
                            $product->set_date_on_sale_from('');
                        }
                        break;
                    case 'sale_date_to':
                        if (!empty($value)) {
                            $product->set_date_on_sale_to($value);
                        } else {
                            $product->set_date_on_sale_to('');
                        }
                        break;
                    case 'tax_status':
                        $product->set_tax_status($value);
                        break;
                    case 'tax_class':
                        $product->set_tax_class($value);
                        break;
                    case 'stock_status':
                        $product->set_stock_status($value);
                        break;
                    case 'stock_quantity':
                        if ($value !== '') {
                            $product->set_stock_quantity($value);
                        }
                        break;
                    case 'manage_stock':
                        $product->set_manage_stock($value === 'yes');
                        break;
                    case 'backorders':
                        $product->set_backorders($value);
                        break;
                    case 'low_stock_amount':
                        if ($value !== '') {
                            $product->set_low_stock_amount($value);
                        }
                        break;
                    case 'weight':
                        $product->set_weight($value);
                        break;
                    case 'length':
                        $product->set_length($value);
                        break;
                    case 'width':
                        $product->set_width($value);
                        break;
                    case 'height':
                        $product->set_height($value);
                        break;
                    case 'categories':
                        if (!$product->is_type('variation')) {
                            $product->set_category_ids($value);
                        }
                        break;
                    case 'tags':
                        if (!$product->is_type('variation')) {
                            $product->set_tag_ids($value);
                        }
                        break;
                    case 'attributes':
                        $this->set_product_attributes($product, $value);
                        break;
                    case 'menu_order':
                        $product->set_menu_order($value);
                        break;
                    case 'virtual':
                        $product->set_virtual($value === 'yes');
                        break;
                    case 'downloadable':
                        $product->set_downloadable($value === 'yes');
                        break;
                    case 'purchase_note':
                        $product->set_purchase_note($value);
                        break;
                    case 'catalog_visibility':
                        $product->set_catalog_visibility($value);
                        break;
                    case 'featured':
                        $product->set_featured($value === 'yes');
                        break;
                    case 'sold_individually':
                        $product->set_sold_individually($value === 'yes');
                        break;
                    case 'upsell_ids':
                        $product->set_upsell_ids($value);
                        break;
                    case 'cross_sell_ids':
                        $product->set_cross_sell_ids($value);
                        break;
                }
            }
            
            $product->save();
            return true;
            
        } catch (WC_Data_Exception $e) {
            return new WP_Error('wc_error', $e->getMessage());
        } catch (Exception $e) {
            return new WP_Error('error', $e->getMessage());
        }
    }
    
    /**
     * Set product attributes
     *
     * @param WC_Product $product Product object
     * @param array $attributes Attributes data
     */
    private function set_product_attributes($product, $attributes) {
        if (empty($attributes)) {
            return;
        }
        
        $product_attributes = array();
        $position = 0;
        
        foreach ($attributes as $name => $attr_data) {
            if ($attr_data['is_taxonomy']) {
                // Taxonomy attribute
                $attribute = new WC_Product_Attribute();
                $attribute->set_id(wc_attribute_taxonomy_id_by_name($name));
                $attribute->set_name($attr_data['name']);
                
                // Get term IDs
                $term_ids = array();
                foreach ($attr_data['value'] as $term_name) {
                    $term = get_term_by('name', $term_name, $attr_data['name']);
                    if ($term) {
                        $term_ids[] = $term->term_id;
                    }
                }
                
                $attribute->set_options($term_ids);
                $attribute->set_visible(true);
                $attribute->set_variation($product->is_type('variable'));
                $attribute->set_position($position);
                
                $product_attributes[$attr_data['name']] = $attribute;
            } else {
                // Custom attribute
                $attribute = new WC_Product_Attribute();
                $attribute->set_id(0);
                $attribute->set_name($attr_data['name']);
                $attribute->set_options(explode('|', $attr_data['value']));
                $attribute->set_visible(true);
                $attribute->set_variation(false);
                $attribute->set_position($position);
                
                $product_attributes[sanitize_title($attr_data['name'])] = $attribute;
            }
            
            $position++;
        }
        
        $product->set_attributes($product_attributes);
    }
    
    /**
     * Get critical errors that should stop the update
     *
     * @return array
     */
    private function get_critical_errors() {
        $critical_codes = array(
            'PRODUCT_NOT_FOUND',
            'INVALID_PRODUCT_ID',
            'PRODUCT_TRASHED',
            'PERMISSION_DENIED',
            'EMPTY_REQUIRED_FIELD',
        );
        
        $errors = $this->validator->get_errors();
        
        return array_filter($errors, function($error) use ($critical_codes) {
            return in_array($error['error_code'], $critical_codes);
        });
    }
    
    /**
     * Add errors from validator to main errors array
     */
    private function add_errors_from_validator() {
        $validator_errors = $this->validator->get_errors();
        $this->errors = array_merge($this->errors, $validator_errors);
        $this->validator->clear_errors();
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
        
        // Generate CSV content in memory
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
        
        // Generate filename
        $filename = 'wprosh-errors-' . date('Y-m-d-H-i-s') . '.csv';
        
        return array(
            'content' => base64_encode($csv_content),
            'filename' => $filename,
        );
    }
    
    /**
     * Get import results
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

