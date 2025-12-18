<?php
/**
 * Wprosh Validator Class
 *
 * Handles validation of product data with comprehensive error handling
 *
 * @package Wprosh
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Wprosh_Validator Class
 */
class Wprosh_Validator {
    
    /**
     * Error codes with Persian messages
     *
     * @var array
     */
    const ERROR_CODES = array(
        // خطاهای شناسایی محصول
        'PRODUCT_NOT_FOUND' => 'محصول با این شناسه یافت نشد',
        'INVALID_PRODUCT_ID' => 'شناسه محصول باید عدد صحیح مثبت باشد',
        'PRODUCT_TRASHED' => 'این محصول در سطل زباله است',
        'PRODUCT_TYPE_MISMATCH' => 'نوع محصول با نوع ذخیره شده مطابقت ندارد',
        
        // خطاهای قیمت
        'INVALID_REGULAR_PRICE' => 'قیمت اصلی باید عدد مثبت باشد',
        'INVALID_SALE_PRICE' => 'قیمت فروش ویژه باید عدد مثبت باشد',
        'SALE_PRICE_EXCEEDS_REGULAR' => 'قیمت فروش ویژه نمی‌تواند از قیمت اصلی بیشتر باشد',
        'VARIABLE_PRODUCT_NO_PRICE' => 'محصول متغیر نباید قیمت مستقیم داشته باشد (قیمت در واریاسیون‌ها تنظیم می‌شود)',
        'EMPTY_PRICE_FOR_SIMPLE' => 'محصول ساده باید قیمت داشته باشد',
        
        // خطاهای موجودی
        'INVALID_STOCK_QUANTITY' => 'تعداد موجودی باید عدد صحیح باشد',
        'NEGATIVE_STOCK' => 'تعداد موجودی نمی‌تواند منفی باشد',
        'INVALID_STOCK_STATUS' => 'وضعیت موجودی باید instock، outofstock یا onbackorder باشد',
        'STOCK_WITHOUT_MANAGE' => 'برای تنظیم تعداد موجودی، مدیریت موجودی باید yes باشد',
        'INVALID_BACKORDERS' => 'مقدار پیش‌سفارش باید no، notify یا yes باشد',
        'INVALID_LOW_STOCK' => 'حد هشدار موجودی باید عدد صحیح مثبت باشد',
        
        // خطاهای ابعاد
        'INVALID_WEIGHT' => 'وزن باید عدد مثبت باشد',
        'INVALID_LENGTH' => 'طول باید عدد مثبت باشد',
        'INVALID_WIDTH' => 'عرض باید عدد مثبت باشد',
        'INVALID_HEIGHT' => 'ارتفاع باید عدد مثبت باشد',
        
        // خطاهای دسته‌بندی و برچسب
        'CATEGORY_NOT_FOUND' => 'دسته‌بندی "%s" یافت نشد',
        'TAG_NOT_FOUND' => 'برچسب "%s" یافت نشد',
        'INVALID_CATEGORY_FORMAT' => 'فرمت دسته‌بندی نامعتبر است (دسته‌بندی‌ها را با | جدا کنید)',
        'INVALID_TAG_FORMAT' => 'فرمت برچسب نامعتبر است (برچسب‌ها را با | جدا کنید)',
        
        // خطاهای ویژگی
        'INVALID_ATTRIBUTE_JSON' => 'فرمت JSON ویژگی‌ها نامعتبر است',
        'ATTRIBUTE_NOT_FOUND' => 'ویژگی "%s" در سیستم تعریف نشده است',
        'INVALID_ATTRIBUTE_TERM' => 'مقدار "%s" برای ویژگی "%s" در سیستم موجود نیست',
        'INVALID_ATTRIBUTE_FORMAT' => 'فرمت ویژگی نامعتبر است (باید JSON معتبر باشد)',
        
        // خطاهای واریاسیون
        'PARENT_NOT_FOUND' => 'محصول والد با شناسه %d یافت نشد',
        'PARENT_NOT_VARIABLE' => 'محصول والد از نوع متغیر (variable) نیست',
        'VARIATION_MISSING_ATTRIBUTES' => 'واریاسیون باید حداقل یک ویژگی داشته باشد',
        'INVALID_PARENT_ID' => 'شناسه والد باید عدد صحیح مثبت باشد',
        
        // خطاهای SKU
        'DUPLICATE_SKU' => 'این SKU (%s) قبلاً برای محصول دیگری (ID: %d) استفاده شده',
        'INVALID_SKU_FORMAT' => 'فرمت SKU نامعتبر است (فقط حروف، اعداد، خط تیره و زیرخط مجاز است)',
        
        // خطاهای وضعیت
        'INVALID_STATUS' => 'وضعیت باید یکی از این مقادیر باشد: publish، draft، pending، private',
        'INVALID_CATALOG_VISIBILITY' => 'نمایش کاتالوگ باید یکی از این مقادیر باشد: visible، catalog، search، hidden',
        
        // خطاهای تاریخ
        'INVALID_DATE_FORMAT' => 'فرمت تاریخ نامعتبر است (از فرمت YYYY-MM-DD استفاده کنید)',
        'SALE_DATE_CONFLICT' => 'تاریخ پایان تخفیف (%s) باید بعد از تاریخ شروع (%s) باشد',
        'INVALID_SALE_DATE_FROM' => 'فرمت تاریخ شروع تخفیف نامعتبر است',
        'INVALID_SALE_DATE_TO' => 'فرمت تاریخ پایان تخفیف نامعتبر است',
        
        // خطاهای بولین
        'INVALID_BOOLEAN' => 'مقدار "%s" برای فیلد "%s" نامعتبر است (باید yes/no یا 1/0 باشد)',
        'INVALID_VIRTUAL' => 'مقدار مجازی باید yes/no یا 1/0 باشد',
        'INVALID_DOWNLOADABLE' => 'مقدار دانلودی باید yes/no یا 1/0 باشد',
        'INVALID_FEATURED' => 'مقدار ویژه باید yes/no یا 1/0 باشد',
        'INVALID_SOLD_INDIVIDUALLY' => 'مقدار فروش تکی باید yes/no یا 1/0 باشد',
        'INVALID_MANAGE_STOCK' => 'مقدار مدیریت موجودی باید yes/no یا 1/0 باشد',
        
        // خطاهای مالیات
        'INVALID_TAX_STATUS' => 'وضعیت مالیات باید یکی از این مقادیر باشد: taxable، shipping، none',
        'INVALID_TAX_CLASS' => 'کلاس مالیاتی "%s" در سیستم تعریف نشده است',
        
        // خطاهای محصولات مرتبط
        'INVALID_UPSELL_IDS' => 'شناسه‌های محصولات پیشنهادی نامعتبر است',
        'UPSELL_PRODUCT_NOT_FOUND' => 'محصول پیشنهادی با شناسه %d یافت نشد',
        'INVALID_CROSS_SELL_IDS' => 'شناسه‌های محصولات مرتبط نامعتبر است',
        'CROSS_SELL_PRODUCT_NOT_FOUND' => 'محصول مرتبط با شناسه %d یافت نشد',
        
        // خطاهای سیستمی
        'DATABASE_ERROR' => 'خطا در ذخیره‌سازی در دیتابیس: %s',
        'PERMISSION_DENIED' => 'شما دسترسی برای ویرایش این محصول را ندارید',
        'WOOCOMMERCE_ERROR' => 'خطای ووکامرس: %s',
        'UNKNOWN_FIELD' => 'فیلد "%s" ناشناخته است و نادیده گرفته شد',
        'CSV_PARSE_ERROR' => 'خطا در خواندن ردیف %d فایل CSV',
        'EMPTY_REQUIRED_FIELD' => 'فیلد اجباری "%s" نمی‌تواند خالی باشد',
        'INVALID_MENU_ORDER' => 'ترتیب نمایش باید عدد صحیح باشد',
        'INVALID_SLUG' => 'نامک فقط می‌تواند شامل حروف کوچک، اعداد و خط تیره باشد',
        'DUPLICATE_SLUG' => 'این نامک (%s) قبلاً برای محصول دیگری استفاده شده',
    );
    
    /**
     * Suggestions for error fixes
     *
     * @var array
     */
    const ERROR_SUGGESTIONS = array(
        'PRODUCT_NOT_FOUND' => 'مطمئن شوید شناسه محصول صحیح است و محصول حذف نشده',
        'INVALID_PRODUCT_ID' => 'یک عدد صحیح مثبت مانند 123 وارد کنید',
        'PRODUCT_TRASHED' => 'ابتدا محصول را از سطل زباله بازیابی کنید',
        'PRODUCT_TYPE_MISMATCH' => 'نوع محصول قابل تغییر نیست',
        
        'INVALID_REGULAR_PRICE' => 'یک عدد مثبت مانند 50000 وارد کنید (بدون کاما یا علامت ارز)',
        'INVALID_SALE_PRICE' => 'یک عدد مثبت مانند 45000 وارد کنید یا خالی بگذارید',
        'SALE_PRICE_EXCEEDS_REGULAR' => 'قیمت فروش ویژه را کمتر از قیمت اصلی تنظیم کنید',
        'VARIABLE_PRODUCT_NO_PRICE' => 'برای محصولات متغیر، قیمت را خالی بگذارید و در واریاسیون‌ها تنظیم کنید',
        'EMPTY_PRICE_FOR_SIMPLE' => 'یک قیمت معتبر برای محصول وارد کنید',
        
        'INVALID_STOCK_QUANTITY' => 'یک عدد صحیح مانند 10 وارد کنید',
        'NEGATIVE_STOCK' => 'عدد موجودی را 0 یا بیشتر قرار دهید',
        'INVALID_STOCK_STATUS' => 'از مقادیر instock، outofstock یا onbackorder استفاده کنید',
        'STOCK_WITHOUT_MANAGE' => 'ابتدا manage_stock را yes قرار دهید',
        'INVALID_BACKORDERS' => 'از مقادیر no، notify یا yes استفاده کنید',
        'INVALID_LOW_STOCK' => 'یک عدد صحیح مثبت مانند 5 وارد کنید',
        
        'INVALID_WEIGHT' => 'یک عدد مثبت مانند 1.5 وارد کنید',
        'INVALID_LENGTH' => 'یک عدد مثبت مانند 20 وارد کنید',
        'INVALID_WIDTH' => 'یک عدد مثبت مانند 15 وارد کنید',
        'INVALID_HEIGHT' => 'یک عدد مثبت مانند 10 وارد کنید',
        
        'CATEGORY_NOT_FOUND' => 'نام دسته‌بندی را دقیقاً مطابق با دسته‌بندی‌های موجود در فروشگاه وارد کنید',
        'TAG_NOT_FOUND' => 'نام برچسب را دقیقاً مطابق با برچسب‌های موجود وارد کنید یا ابتدا برچسب را ایجاد کنید',
        'INVALID_CATEGORY_FORMAT' => 'دسته‌بندی‌ها را با | جدا کنید. مثال: لباس|مردانه|پیراهن',
        'INVALID_TAG_FORMAT' => 'برچسب‌ها را با | جدا کنید. مثال: جدید|حراج|پرفروش',
        
        'INVALID_ATTRIBUTE_JSON' => 'از فرمت JSON صحیح استفاده کنید. مثال: {"رنگ":"قرمز","سایز":"XL"}',
        'ATTRIBUTE_NOT_FOUND' => 'ابتدا ویژگی را در ووکامرس ایجاد کنید یا نام صحیح آن را وارد کنید',
        'INVALID_ATTRIBUTE_TERM' => 'مقدار ویژگی را از مقادیر تعریف شده انتخاب کنید',
        'INVALID_ATTRIBUTE_FORMAT' => 'از فرمت JSON استفاده کنید: {"نام_ویژگی":"مقدار"}',
        
        'PARENT_NOT_FOUND' => 'شناسه والد صحیح را وارد کنید یا ابتدا محصول والد را ایجاد کنید',
        'PARENT_NOT_VARIABLE' => 'والد واریاسیون باید یک محصول متغیر باشد',
        'VARIATION_MISSING_ATTRIBUTES' => 'حداقل یک ویژگی برای واریاسیون تعریف کنید',
        'INVALID_PARENT_ID' => 'یک عدد صحیح مثبت برای شناسه والد وارد کنید',
        
        'DUPLICATE_SKU' => 'یک SKU منحصر به فرد استفاده کنید یا این فیلد را خالی بگذارید',
        'INVALID_SKU_FORMAT' => 'فقط از حروف انگلیسی، اعداد، خط تیره (-) و زیرخط (_) استفاده کنید',
        
        'INVALID_STATUS' => 'از مقادیر publish، draft، pending یا private استفاده کنید',
        'INVALID_CATALOG_VISIBILITY' => 'از مقادیر visible، catalog، search یا hidden استفاده کنید',
        
        'INVALID_DATE_FORMAT' => 'از فرمت YYYY-MM-DD استفاده کنید. مثال: 2024-12-31',
        'SALE_DATE_CONFLICT' => 'تاریخ پایان را بعد از تاریخ شروع قرار دهید',
        'INVALID_SALE_DATE_FROM' => 'از فرمت YYYY-MM-DD استفاده کنید یا خالی بگذارید',
        'INVALID_SALE_DATE_TO' => 'از فرمت YYYY-MM-DD استفاده کنید یا خالی بگذارید',
        
        'INVALID_BOOLEAN' => 'از مقادیر yes/no یا 1/0 استفاده کنید',
        'INVALID_VIRTUAL' => 'از yes/no یا 1/0 استفاده کنید',
        'INVALID_DOWNLOADABLE' => 'از yes/no یا 1/0 استفاده کنید',
        'INVALID_FEATURED' => 'از yes/no یا 1/0 استفاده کنید',
        'INVALID_SOLD_INDIVIDUALLY' => 'از yes/no یا 1/0 استفاده کنید',
        'INVALID_MANAGE_STOCK' => 'از yes/no یا 1/0 استفاده کنید',
        
        'INVALID_TAX_STATUS' => 'از مقادیر taxable، shipping یا none استفاده کنید',
        'INVALID_TAX_CLASS' => 'نام کلاس مالیاتی را دقیقاً مطابق با کلاس‌های تعریف شده وارد کنید',
        
        'INVALID_UPSELL_IDS' => 'شناسه‌ها را با | جدا کنید. مثال: 123|456|789',
        'UPSELL_PRODUCT_NOT_FOUND' => 'مطمئن شوید محصول با این شناسه وجود دارد',
        'INVALID_CROSS_SELL_IDS' => 'شناسه‌ها را با | جدا کنید. مثال: 123|456|789',
        'CROSS_SELL_PRODUCT_NOT_FOUND' => 'مطمئن شوید محصول با این شناسه وجود دارد',
        
        'DATABASE_ERROR' => 'دوباره تلاش کنید. اگر مشکل ادامه داشت با پشتیبانی تماس بگیرید',
        'PERMISSION_DENIED' => 'با حساب کاربری دارای دسترسی مدیر محصولات وارد شوید',
        'WOOCOMMERCE_ERROR' => 'پیام خطا را بررسی کرده و مشکل را رفع کنید',
        'UNKNOWN_FIELD' => 'این فیلد نادیده گرفته شده. فیلدهای معتبر را در راهنما ببینید',
        'CSV_PARSE_ERROR' => 'فایل CSV را در اکسل بررسی و ذخیره مجدد کنید',
        'EMPTY_REQUIRED_FIELD' => 'این فیلد را پر کنید',
        'INVALID_MENU_ORDER' => 'یک عدد صحیح مانند 0 یا 5 وارد کنید',
        'INVALID_SLUG' => 'فقط از حروف کوچک انگلیسی، اعداد و خط تیره استفاده کنید',
        'DUPLICATE_SLUG' => 'یک نامک منحصر به فرد استفاده کنید',
    );
    
    /**
     * Valid product statuses
     *
     * @var array
     */
    const VALID_STATUSES = array('publish', 'draft', 'pending', 'private');
    
    /**
     * Valid stock statuses
     *
     * @var array
     */
    const VALID_STOCK_STATUSES = array('instock', 'outofstock', 'onbackorder');
    
    /**
     * Valid catalog visibilities
     *
     * @var array
     */
    const VALID_CATALOG_VISIBILITIES = array('visible', 'catalog', 'search', 'hidden');
    
    /**
     * Valid tax statuses
     *
     * @var array
     */
    const VALID_TAX_STATUSES = array('taxable', 'shipping', 'none');
    
    /**
     * Valid backorder options
     *
     * @var array
     */
    const VALID_BACKORDERS = array('no', 'notify', 'yes');
    
    /**
     * Validation errors collection
     *
     * @var array
     */
    private $errors = array();
    
    /**
     * Current row being validated
     *
     * @var int
     */
    private $current_row = 0;
    
    /**
     * Current product data
     *
     * @var array
     */
    private $current_data = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->errors = array();
    }
    
    /**
     * Set current row number for error reporting
     *
     * @param int $row Row number
     */
    public function set_current_row($row) {
        $this->current_row = $row;
    }
    
    /**
     * Set current product data
     *
     * @param array $data Product data
     */
    public function set_current_data($data) {
        $this->current_data = $data;
    }
    
    /**
     * Add an error
     *
     * @param string $field Field name
     * @param string $error_code Error code
     * @param mixed $current_value Current value in CSV
     * @param array $params Parameters for error message
     */
    public function add_error($field, $error_code, $current_value = '', $params = array()) {
        $message = isset(self::ERROR_CODES[$error_code]) ? self::ERROR_CODES[$error_code] : $error_code;
        
        // Replace placeholders in message
        if (!empty($params)) {
            $message = vsprintf($message, $params);
        }
        
        $suggestion = isset(self::ERROR_SUGGESTIONS[$error_code]) ? self::ERROR_SUGGESTIONS[$error_code] : '';
        
        $this->errors[] = array(
            'row_number' => $this->current_row,
            'product_id' => isset($this->current_data['id']) ? $this->current_data['id'] : '',
            'product_name' => isset($this->current_data['name']) ? $this->current_data['name'] : '',
            'field_name' => $field,
            'current_value' => $current_value,
            'error_code' => $error_code,
            'error_message' => $message,
            'suggestion' => $suggestion,
        );
    }
    
    /**
     * Get all errors
     *
     * @return array
     */
    public function get_errors() {
        return $this->errors;
    }
    
    /**
     * Check if there are errors
     *
     * @return bool
     */
    public function has_errors() {
        return !empty($this->errors);
    }
    
    /**
     * Clear errors
     */
    public function clear_errors() {
        $this->errors = array();
    }
    
    /**
     * Validate product ID
     *
     * @param mixed $id Product ID
     * @return bool|int
     */
    public function validate_product_id($id) {
        if (empty($id)) {
            $this->add_error('id', 'EMPTY_REQUIRED_FIELD', $id, array('id'));
            return false;
        }
        
        if (!is_numeric($id) || intval($id) <= 0) {
            $this->add_error('id', 'INVALID_PRODUCT_ID', $id);
            return false;
        }
        
        $id = intval($id);
        $product = wc_get_product($id);
        
        if (!$product) {
            // Check if product is trashed
            $post = get_post($id);
            if ($post && $post->post_status === 'trash') {
                $this->add_error('id', 'PRODUCT_TRASHED', $id);
            } else {
                $this->add_error('id', 'PRODUCT_NOT_FOUND', $id);
            }
            return false;
        }
        
        return $id;
    }
    
    /**
     * Validate price
     *
     * @param mixed $price Price value
     * @param string $field Field name
     * @param string $product_type Product type
     * @return bool|string
     */
    public function validate_price($price, $field = 'regular_price', $product_type = 'simple') {
        // Empty price is valid for sale_price and variable products
        if ($price === '' || $price === null) {
            return '';
        }
        
        // Remove any currency symbols and spaces
        $price = preg_replace('/[^\d.]/', '', $price);
        
        if (!is_numeric($price) || floatval($price) < 0) {
            $error_code = $field === 'regular_price' ? 'INVALID_REGULAR_PRICE' : 'INVALID_SALE_PRICE';
            $this->add_error($field, $error_code, $price);
            return false;
        }
        
        return $price;
    }
    
    /**
     * Validate sale price against regular price
     *
     * @param mixed $sale_price Sale price
     * @param mixed $regular_price Regular price
     * @return bool
     */
    public function validate_sale_price_against_regular($sale_price, $regular_price) {
        if ($sale_price === '' || $regular_price === '') {
            return true;
        }
        
        if (floatval($sale_price) > floatval($regular_price)) {
            $this->add_error('sale_price', 'SALE_PRICE_EXCEEDS_REGULAR', $sale_price);
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate stock quantity
     *
     * @param mixed $quantity Stock quantity
     * @return bool|int
     */
    public function validate_stock_quantity($quantity) {
        if ($quantity === '' || $quantity === null) {
            return '';
        }
        
        if (!is_numeric($quantity)) {
            $this->add_error('stock_quantity', 'INVALID_STOCK_QUANTITY', $quantity);
            return false;
        }
        
        $quantity = intval($quantity);
        
        if ($quantity < 0) {
            $this->add_error('stock_quantity', 'NEGATIVE_STOCK', $quantity);
            return false;
        }
        
        return $quantity;
    }
    
    /**
     * Validate stock status
     *
     * @param string $status Stock status
     * @return bool|string
     */
    public function validate_stock_status($status) {
        if ($status === '' || $status === null) {
            return '';
        }
        
        $status = strtolower(trim($status));
        
        if (!in_array($status, self::VALID_STOCK_STATUSES)) {
            $this->add_error('stock_status', 'INVALID_STOCK_STATUS', $status);
            return false;
        }
        
        return $status;
    }
    
    /**
     * Validate boolean value
     *
     * @param mixed $value Value to validate
     * @param string $field Field name
     * @return bool|string
     */
    public function validate_boolean($value, $field) {
        if ($value === '' || $value === null) {
            return '';
        }
        
        $value = strtolower(trim($value));
        
        if (in_array($value, array('yes', '1', 'true'))) {
            return 'yes';
        }
        
        if (in_array($value, array('no', '0', 'false'))) {
            return 'no';
        }
        
        $this->add_error($field, 'INVALID_BOOLEAN', $value, array($value, $field));
        return false;
    }
    
    /**
     * Validate product status
     *
     * @param string $status Status value
     * @return bool|string
     */
    public function validate_status($status) {
        if ($status === '' || $status === null) {
            return '';
        }
        
        $status = strtolower(trim($status));
        
        if (!in_array($status, self::VALID_STATUSES)) {
            $this->add_error('status', 'INVALID_STATUS', $status);
            return false;
        }
        
        return $status;
    }
    
    /**
     * Validate catalog visibility
     *
     * @param string $visibility Visibility value
     * @return bool|string
     */
    public function validate_catalog_visibility($visibility) {
        if ($visibility === '' || $visibility === null) {
            return '';
        }
        
        $visibility = strtolower(trim($visibility));
        
        if (!in_array($visibility, self::VALID_CATALOG_VISIBILITIES)) {
            $this->add_error('catalog_visibility', 'INVALID_CATALOG_VISIBILITY', $visibility);
            return false;
        }
        
        return $visibility;
    }
    
    /**
     * Validate tax status
     *
     * @param string $status Tax status
     * @return bool|string
     */
    public function validate_tax_status($status) {
        if ($status === '' || $status === null) {
            return '';
        }
        
        $status = strtolower(trim($status));
        
        if (!in_array($status, self::VALID_TAX_STATUSES)) {
            $this->add_error('tax_status', 'INVALID_TAX_STATUS', $status);
            return false;
        }
        
        return $status;
    }
    
    /**
     * Validate backorders
     *
     * @param string $backorders Backorders value
     * @return bool|string
     */
    public function validate_backorders($backorders) {
        if ($backorders === '' || $backorders === null) {
            return '';
        }
        
        $backorders = strtolower(trim($backorders));
        
        if (!in_array($backorders, self::VALID_BACKORDERS)) {
            $this->add_error('backorders', 'INVALID_BACKORDERS', $backorders);
            return false;
        }
        
        return $backorders;
    }
    
    /**
     * Validate dimension (weight, length, width, height)
     *
     * @param mixed $value Dimension value
     * @param string $field Field name
     * @return bool|string
     */
    public function validate_dimension($value, $field) {
        if ($value === '' || $value === null) {
            return '';
        }
        
        // Remove any units
        $value = preg_replace('/[^\d.]/', '', $value);
        
        if (!is_numeric($value) || floatval($value) < 0) {
            $error_code = 'INVALID_' . strtoupper($field);
            if (!isset(self::ERROR_CODES[$error_code])) {
                $error_code = 'INVALID_WEIGHT';
            }
            $this->add_error($field, $error_code, $value);
            return false;
        }
        
        return $value;
    }
    
    /**
     * Validate date format
     *
     * @param string $date Date string
     * @param string $field Field name
     * @return bool|string
     */
    public function validate_date($date, $field) {
        if ($date === '' || $date === null) {
            return '';
        }
        
        $date = trim($date);
        
        // Check format YYYY-MM-DD
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $error_code = $field === 'sale_date_from' ? 'INVALID_SALE_DATE_FROM' : 'INVALID_SALE_DATE_TO';
            $this->add_error($field, $error_code, $date);
            return false;
        }
        
        // Validate actual date
        $parts = explode('-', $date);
        if (!checkdate($parts[1], $parts[2], $parts[0])) {
            $error_code = $field === 'sale_date_from' ? 'INVALID_SALE_DATE_FROM' : 'INVALID_SALE_DATE_TO';
            $this->add_error($field, $error_code, $date);
            return false;
        }
        
        return $date;
    }
    
    /**
     * Validate sale dates (from must be before to)
     *
     * @param string $date_from Sale date from
     * @param string $date_to Sale date to
     * @return bool
     */
    public function validate_sale_dates($date_from, $date_to) {
        if ($date_from === '' || $date_to === '') {
            return true;
        }
        
        if (strtotime($date_to) < strtotime($date_from)) {
            $this->add_error('sale_date_to', 'SALE_DATE_CONFLICT', $date_to, array($date_to, $date_from));
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate SKU
     *
     * @param string $sku SKU value
     * @param int $product_id Current product ID
     * @return bool|string
     */
    public function validate_sku($sku, $product_id = 0) {
        if ($sku === '' || $sku === null) {
            return '';
        }
        
        $sku = trim($sku);
        
        // Check format (alphanumeric, dash, underscore)
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $sku)) {
            $this->add_error('sku', 'INVALID_SKU_FORMAT', $sku);
            return false;
        }
        
        // Check for duplicate
        $existing_id = wc_get_product_id_by_sku($sku);
        if ($existing_id && $existing_id !== $product_id) {
            $this->add_error('sku', 'DUPLICATE_SKU', $sku, array($sku, $existing_id));
            return false;
        }
        
        return $sku;
    }
    
    /**
     * Validate slug
     *
     * @param string $slug Slug value
     * @param int $product_id Current product ID
     * @return bool|string
     */
    public function validate_slug($slug, $product_id = 0) {
        if ($slug === '' || $slug === null) {
            return '';
        }
        
        $slug = sanitize_title($slug);
        
        // Check for duplicate
        global $wpdb;
        $existing_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type IN ('product', 'product_variation') AND ID != %d",
            $slug,
            $product_id
        ));
        
        if ($existing_id) {
            $this->add_error('slug', 'DUPLICATE_SLUG', $slug, array($slug));
            return false;
        }
        
        return $slug;
    }
    
    /**
     * Validate categories
     *
     * @param string $categories Categories string (pipe separated)
     * @return bool|array
     */
    public function validate_categories($categories) {
        if ($categories === '' || $categories === null) {
            return array();
        }
        
        $categories = trim($categories);
        $category_names = array_map('trim', explode('|', $categories));
        $valid_ids = array();
        
        foreach ($category_names as $name) {
            if (empty($name)) {
                continue;
            }
            
            $term = get_term_by('name', $name, 'product_cat');
            
            if (!$term) {
                // Try by slug
                $term = get_term_by('slug', sanitize_title($name), 'product_cat');
            }
            
            if (!$term) {
                $this->add_error('categories', 'CATEGORY_NOT_FOUND', $name, array($name));
            } else {
                $valid_ids[] = $term->term_id;
            }
        }
        
        return $valid_ids;
    }
    
    /**
     * Validate tags
     *
     * @param string $tags Tags string (pipe separated)
     * @return bool|array
     */
    public function validate_tags($tags) {
        if ($tags === '' || $tags === null) {
            return array();
        }
        
        $tags = trim($tags);
        $tag_names = array_map('trim', explode('|', $tags));
        $valid_ids = array();
        
        foreach ($tag_names as $name) {
            if (empty($name)) {
                continue;
            }
            
            $term = get_term_by('name', $name, 'product_tag');
            
            if (!$term) {
                // Try by slug
                $term = get_term_by('slug', sanitize_title($name), 'product_tag');
            }
            
            if (!$term) {
                $this->add_error('tags', 'TAG_NOT_FOUND', $name, array($name));
            } else {
                $valid_ids[] = $term->term_id;
            }
        }
        
        return $valid_ids;
    }
    
    /**
     * Validate attributes (JSON format)
     *
     * @param string $attributes Attributes JSON string
     * @return bool|array
     */
    public function validate_attributes($attributes) {
        if ($attributes === '' || $attributes === null) {
            return array();
        }
        
        $attributes = trim($attributes);
        
        // Try to decode JSON
        $decoded = json_decode($attributes, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->add_error('attributes', 'INVALID_ATTRIBUTE_JSON', $attributes);
            return false;
        }
        
        if (!is_array($decoded)) {
            $this->add_error('attributes', 'INVALID_ATTRIBUTE_FORMAT', $attributes);
            return false;
        }
        
        $valid_attributes = array();
        
        foreach ($decoded as $attr_name => $attr_value) {
            // Check if attribute taxonomy exists
            $taxonomy = wc_attribute_taxonomy_name($attr_name);
            $attribute_id = wc_attribute_taxonomy_id_by_name($attr_name);
            
            if ($attribute_id) {
                // Global attribute
                $terms = is_array($attr_value) ? $attr_value : array($attr_value);
                $valid_terms = array();
                
                foreach ($terms as $term_name) {
                    $term = get_term_by('name', $term_name, $taxonomy);
                    if (!$term) {
                        $term = get_term_by('slug', sanitize_title($term_name), $taxonomy);
                    }
                    
                    if ($term) {
                        $valid_terms[] = $term->name;
                    } else {
                        $this->add_error('attributes', 'INVALID_ATTRIBUTE_TERM', $term_name, array($term_name, $attr_name));
                    }
                }
                
                if (!empty($valid_terms)) {
                    $valid_attributes[$attr_name] = array(
                        'name' => $taxonomy,
                        'value' => $valid_terms,
                        'is_taxonomy' => true,
                    );
                }
            } else {
                // Custom attribute
                $valid_attributes[$attr_name] = array(
                    'name' => $attr_name,
                    'value' => is_array($attr_value) ? implode('|', $attr_value) : $attr_value,
                    'is_taxonomy' => false,
                );
            }
        }
        
        return $valid_attributes;
    }
    
    /**
     * Validate parent ID for variations
     *
     * @param mixed $parent_id Parent product ID
     * @return bool|int
     */
    public function validate_parent_id($parent_id) {
        if ($parent_id === '' || $parent_id === null || $parent_id === 0 || $parent_id === '0') {
            return 0;
        }
        
        if (!is_numeric($parent_id) || intval($parent_id) <= 0) {
            $this->add_error('parent_id', 'INVALID_PARENT_ID', $parent_id);
            return false;
        }
        
        $parent_id = intval($parent_id);
        $parent = wc_get_product($parent_id);
        
        if (!$parent) {
            $this->add_error('parent_id', 'PARENT_NOT_FOUND', $parent_id, array($parent_id));
            return false;
        }
        
        if (!$parent->is_type('variable')) {
            $this->add_error('parent_id', 'PARENT_NOT_VARIABLE', $parent_id);
            return false;
        }
        
        return $parent_id;
    }
    
    /**
     * Validate product IDs (for upsells, cross-sells)
     *
     * @param string $ids IDs string (pipe separated)
     * @param string $field Field name
     * @return array
     */
    public function validate_product_ids($ids, $field) {
        if ($ids === '' || $ids === null) {
            return array();
        }
        
        $ids = trim($ids);
        $id_array = array_map('trim', explode('|', $ids));
        $valid_ids = array();
        
        foreach ($id_array as $id) {
            if (empty($id) || !is_numeric($id)) {
                continue;
            }
            
            $id = intval($id);
            $product = wc_get_product($id);
            
            if (!$product) {
                $error_code = $field === 'upsell_ids' ? 'UPSELL_PRODUCT_NOT_FOUND' : 'CROSS_SELL_PRODUCT_NOT_FOUND';
                $this->add_error($field, $error_code, $id, array($id));
            } else {
                $valid_ids[] = $id;
            }
        }
        
        return $valid_ids;
    }
    
    /**
     * Validate menu order
     *
     * @param mixed $order Menu order value
     * @return bool|int
     */
    public function validate_menu_order($order) {
        if ($order === '' || $order === null) {
            return '';
        }
        
        if (!is_numeric($order)) {
            $this->add_error('menu_order', 'INVALID_MENU_ORDER', $order);
            return false;
        }
        
        return intval($order);
    }
    
    /**
     * Validate low stock amount
     *
     * @param mixed $amount Low stock amount
     * @return bool|int|string
     */
    public function validate_low_stock_amount($amount) {
        if ($amount === '' || $amount === null) {
            return '';
        }
        
        if (!is_numeric($amount) || intval($amount) < 0) {
            $this->add_error('low_stock_amount', 'INVALID_LOW_STOCK', $amount);
            return false;
        }
        
        return intval($amount);
    }
    
    /**
     * Get error message by code
     *
     * @param string $code Error code
     * @return string
     */
    public static function get_error_message($code) {
        return isset(self::ERROR_CODES[$code]) ? self::ERROR_CODES[$code] : $code;
    }
    
    /**
     * Get suggestion by error code
     *
     * @param string $code Error code
     * @return string
     */
    public static function get_suggestion($code) {
        return isset(self::ERROR_SUGGESTIONS[$code]) ? self::ERROR_SUGGESTIONS[$code] : '';
    }
}

