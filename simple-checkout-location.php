<?php
/**
 * Plugin Name: Simple Checkout Location Selector
 * Description: Professional address management with location selection for WooCommerce
 * Version: 3.0.6
 * Author: Abdulrahman Roston
 * Text Domain: simple-checkout-location
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SCL_VERSION', '3.1.1' );
define( 'SCL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SCL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include required files
require_once SCL_PLUGIN_DIR . 'includes/class-address-repository.php';
require_once SCL_PLUGIN_DIR . 'includes/class-zones-repository.php';  // ✅ قبل كل شيء
require_once SCL_PLUGIN_DIR . 'includes/class-address-manager.php';
require_once SCL_PLUGIN_DIR . 'includes/class-address-service.php';
require_once SCL_PLUGIN_DIR . 'includes/class-address-rest-controller.php';
require_once SCL_PLUGIN_DIR . 'includes/class-admin-page.php';
require_once SCL_PLUGIN_DIR . 'includes/class-custom-fields-manager.php';


// ==================== Database Installation ====================

function scl_install_tables() {
    global $wpdb;
    $table_name      = $wpdb->prefix . 'scl_addresses';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        address_name VARCHAR(191) NOT NULL DEFAULT '',
        customer_name VARCHAR(191) NOT NULL DEFAULT '',
        phone_primary VARCHAR(50) NOT NULL DEFAULT '',
        phone_secondary VARCHAR(50) DEFAULT '',
        location_url VARCHAR(255) DEFAULT '',
        location_lat DECIMAL(10,7) DEFAULT NULL,
        location_lng DECIMAL(10,7) DEFAULT NULL,
        address_details TEXT DEFAULT NULL,
        zone VARCHAR(191) DEFAULT '',
        notes_customer TEXT DEFAULT NULL,
        notes_internal TEXT DEFAULT NULL,
        is_default_billing TINYINT(1) NOT NULL DEFAULT 0,
        is_default_shipping TINYINT(1) NOT NULL DEFAULT 0,
        status TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY status (status),
        KEY default_billing (user_id,is_default_billing),
        KEY default_shipping (user_id,is_default_shipping)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}

function scl_install_zones_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'scl_zones';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        zone_name VARCHAR(191) NOT NULL,
        zone_name_ar VARCHAR(191) DEFAULT NULL,
        shipping_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        display_order INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        KEY is_active (is_active),
        KEY display_order (display_order)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
    
    // إضافة مناطق افتراضية
    $count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
    if ( $count == 0 ) {
        $default_zones = [
            [ 'Cairo', 'القاهرة', 15.00 ],
            [ 'Giza', 'الجيزة', 15.00 ],
            [ 'Alexandria', 'الإسكندرية', 30.00 ],
            [ 'Other', 'أخرى', 50.00 ],
        ];
        
        $now = current_time( 'mysql' );
        foreach ( $default_zones as $index => $zone ) {
            $wpdb->insert( $table_name, [
                'zone_name' => $zone[0],
                'zone_name_ar' => $zone[1],
                'shipping_cost' => $zone[2],
                'is_active' => 1,
                'display_order' => $index + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ] );
        }
    }
}

function scl_update_zones_table_for_delivery_schedule() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'scl_zones';
    
    $column_exists = $wpdb->get_results( 
        $wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = %s 
            AND TABLE_NAME = %s 
            AND COLUMN_NAME = 'delivery_times'",
            DB_NAME,
            $table_name
        )
    );
    
    if ( empty( $column_exists ) ) {
        $wpdb->query( "ALTER TABLE {$table_name} 
            ADD COLUMN delivery_times LONGTEXT DEFAULT NULL AFTER shipping_cost,
            ADD COLUMN delivery_days_ahead INT DEFAULT 3 AFTER delivery_times,
            ADD COLUMN closed_days VARCHAR(255) DEFAULT NULL AFTER delivery_days_ahead
        " );
        
        error_log( 'SCL: Added delivery schedule columns to zones table' );
    }
}

// ==================== Activation Hooks ====================

register_activation_hook( __FILE__, function() {
    scl_install_tables();
    scl_install_zones_table();
    scl_update_zones_table_for_delivery_schedule();
    scl_flush_rewrite_rules();
} );

add_action( 'plugins_loaded', function() {
    $current_version = get_option( 'scl_db_version', '1.0' );
    $new_version = '3.1';
    
    if ( version_compare( $current_version, $new_version, '<' ) ) {
        scl_update_zones_table_for_delivery_schedule();
        update_option( 'scl_db_version', $new_version );
        error_log( 'SCL: Database updated to version ' . $new_version );
    }
} );

// ==================== REST API & Admin ====================

add_action( 'rest_api_init', function() {
    $controller = new SCL_Address_REST_Controller();
    $controller->register_routes();
} );

add_action( 'plugins_loaded', function() {
    if ( is_admin() ) {
        new SCL_Admin_Page();
    }
} );

// ==================== Shipping Method ====================

add_action( 'woocommerce_shipping_init', function() {
    require_once SCL_PLUGIN_DIR . 'includes/class-zone-shipping-method.php';
} );

add_filter( 'woocommerce_shipping_methods', function( $methods ) {
    $methods['scl_zone_shipping'] = 'SCL_Zone_Shipping_Method';
    return $methods;
} );

add_action( 'woocommerce_checkout_update_order_review', function( $post_data ) {
    WC()->cart->calculate_shipping();
    WC()->cart->calculate_totals();
}, 10, 1 );

// ==================== Main Plugin Class ====================

class Simple_Checkout_Location {

    private static $instance = null;
    private $address_manager;
    private $repo;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

        private function __construct() {
        $this->address_manager = new SCL_Address_Manager();
        $this->repo = new SCL_Address_Repository();

        // Checkout hooks
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ], 20 );
        add_filter( 'woocommerce_checkout_fields', [ $this, 'hide_default_checkout_fields' ], 9999 );
        
        // ✅ تصحيح: استخدام hook داخل الـ form
        add_action( 'woocommerce_checkout_before_customer_details', [ $this, 'custom_checkout_address_field' ], 5 );
        
        // ✅ ملء الحقول قبل أي validation
        add_filter( 'woocommerce_checkout_posted_data', [ $this, 'populate_woocommerce_fields' ], 5 );
        
        add_filter( 'woocommerce_checkout_fields', [ $this, 'add_custom_checkout_fields' ] );
        add_filter( 'woocommerce_checkout_fields', [ $this, 'remove_billing_validation' ], 10000 );
        
        // ✅ validation مخصص بدلاً من WooCommerce
        add_action( 'woocommerce_checkout_process', [ $this, 'validate_custom_address' ] );
        add_action( 'woocommerce_after_checkout_validation', [ $this, 'remove_woocommerce_errors' ], 10, 2 );
        
        add_action( 'woocommerce_checkout_update_order_meta', [ $this, 'save_custom_address_to_order' ], 10, 2 );
        
        // ✅ Hook للتعامل مع الطلبات من REST API
        add_action( 'woocommerce_new_order', [ $this, 'save_location_from_api_order' ], 10, 2 );

        // Shipping hooks
        add_action( 'woocommerce_checkout_update_order_review', [ $this, 'update_shipping_on_zone_change' ] );
        add_filter( 'woocommerce_package_rates', [ $this, 'apply_zone_shipping_rate' ], 10, 2 );
        
        // Admin hooks
        add_action( 'woocommerce_admin_order_data_after_billing_address', [ $this, 'display_address_in_admin' ] );
        add_filter( 'woocommerce_rest_prepare_shop_order_object', [ $this, 'rest_add_address_fields' ], 10, 3 );
        add_action( 'woocommerce_process_shop_order_meta', [ $this, 'update_order_shipping_on_city_change' ], 60, 2 );

        // My Account hooks
        add_filter( 'woocommerce_account_menu_items', [ $this, 'hide_default_addresses_menu' ], 999 );
        add_action( 'template_redirect', [ $this, 'redirect_default_addresses_page' ] );
        add_action( 'init', [ $this, 'add_custom_addresses_endpoint' ] );
        add_filter( 'woocommerce_account_menu_items', [ $this, 'add_custom_addresses_menu' ], 40 );
        add_action( 'woocommerce_account_scl-addresses_endpoint', [ $this, 'custom_addresses_content' ] );

        // AJAX hooks
        add_action( 'wp_ajax_scl_load_address_data', [ $this, 'ajax_load_address_data' ] );
        add_action( 'wp_ajax_nopriv_scl_load_address_data', [ $this, 'ajax_load_address_data' ] );
        add_action( 'wp_ajax_scl_update_address', [ $this, 'ajax_update_address' ] );
        add_action( 'wp_ajax_scl_delete_address', [ $this, 'ajax_delete_address' ] );
        add_action( 'wp_ajax_scl_get_delivery_schedule', [ $this, 'ajax_get_delivery_schedule' ] );
        add_action( 'wp_ajax_nopriv_scl_get_delivery_schedule', [ $this, 'ajax_get_delivery_schedule' ] );
    }


    // ==================== Admin Order Methods ====================

    public function update_order_shipping_on_city_change( $order_id, $post ) {
        if ( ! isset( $_POST['_billing_city'] ) ) {
            return;
        }
        
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }
        
        $completed_statuses = [ 'completed', 'refunded', 'cancelled' ];
        if ( in_array( $order->get_status(), $completed_statuses ) ) {
            return;
        }
        
        $new_city = sanitize_text_field( $_POST['_billing_city'] );
        $old_city = $order->get_billing_city();
        
        if ( ! $new_city || $new_city === $old_city ) {
            return;
        }
        
        require_once SCL_PLUGIN_DIR . 'includes/class-zones-repository.php';
        $zones_repo = new SCL_Zones_Repository();
        $new_shipping_cost = $zones_repo->get_shipping_cost_by_zone_name( $new_city );
        
        if ( $new_shipping_cost === false ) {
            return;
        }
        
        foreach ( $order->get_items( 'shipping' ) as $item_id => $shipping_item ) {
            $order->remove_item( $item_id );
        }
        
        $shipping = new WC_Order_Item_Shipping();
        $shipping->set_method_title( sprintf( __( 'Delivery to %s', 'simple-checkout-location' ), $new_city ) );
        $shipping->set_method_id( 'scl_zone_shipping' );
        $shipping->set_total( $new_shipping_cost );
        
        $order->add_item( $shipping );
        $order->calculate_totals();
        
        $old_cost = $zones_repo->get_shipping_cost_by_zone_name( $old_city );
        $order->add_order_note(
            sprintf(
                __( 'Shipping updated: %s → %s (Zone: %s → %s)', 'simple-checkout-location' ),
                wc_price( $old_cost ),
                wc_price( $new_shipping_cost ),
                $old_city,
                $new_city
            )
        );
        
        $order->save();
    }

    // ==================== Checkout Field Methods ====================

    public function hide_default_checkout_fields( $fields ) {
        if ( isset( $fields['billing'] ) ) {
            foreach ( $fields['billing'] as $key => $field ) {
                if ( ! in_array( $key, [ 'billing_email' ] ) ) {
                    $fields['billing'][ $key ]['required'] = false;
                    $fields['billing'][ $key ]['class'][] = 'scl-hidden-field';
                    $fields['billing'][ $key ]['label'] = '';
                }
            }
        }

        if ( isset( $fields['shipping'] ) ) {
            foreach ( $fields['shipping'] as $key => $field ) {
                $fields['shipping'][ $key ]['required'] = false;
                $fields['shipping'][ $key ]['class'][] = 'scl-hidden-field';
            }
        }

        return $fields;
    }

    public function add_custom_checkout_fields( $fields ) {
    $fields['billing']['billing_zone'] = [
        'type'     => 'text',
        'label'    => __( 'Delivery Zone', 'simple-checkout-location' ),
        'required' => false,
        'class'    => [ 'form-row-wide', 'hidden' ],
        'priority' => 100,
    ];
    
    $fields['billing']['billing_delivery_date'] = [
        'type'     => 'text',
        'label'    => __( 'Delivery Date', 'simple-checkout-location' ),
        'required' => false,
        'class'    => [ 'form-row-wide', 'hidden' ],
        'priority' => 101,
    ];
    
    $fields['billing']['billing_delivery_time'] = [
        'type'     => 'text',
        'label'    => __( 'Delivery Time', 'simple-checkout-location' ),
        'required' => false,
        'class'    => [ 'form-row-wide', 'hidden' ],
        'priority' => 102,
    ];
    
    // ✅ إضافة حقل تعليمات التوصيل
    $fields['billing']['billing_notes_customer'] = [
        'type'     => 'textarea',
        'label'    => __( 'Delivery Notes', 'simple-checkout-location' ),
        'required' => false,
        'class'    => [ 'form-row-wide', 'hidden' ],
        'priority' => 103,
    ];
    
    return $fields;
}


    public function remove_billing_validation( $fields ) {
        if ( isset( $fields['billing'] ) ) {
            foreach ( $fields['billing'] as $key => $field ) {
                if ( $key !== 'billing_email' ) {
                    $fields['billing'][ $key ]['required'] = false;
                    $fields['billing'][ $key ]['validate'] = [];
                    $fields['billing'][ $key ]['class'][] = 'scl-auto-fill';
                }
            }
        }
        
        if ( isset( $fields['shipping'] ) ) {
            foreach ( $fields['shipping'] as $key => $field ) {
                $fields['shipping'][ $key ]['required'] = false;
                $fields['shipping'][ $key ]['validate'] = [];
            }
        }
        
        return $fields;
    }


    // ==================== Render Methods ====================

    public function custom_checkout_address_field( $checkout ) {
        if ( is_user_logged_in() ) {
            $this->render_custom_address_section();
        } else {
            $this->render_guest_address_form();
        }
        
        // ✅ قسم المواعيد مرة واحدة فقط
        $this->render_delivery_schedule_section();
    }


    private function render_delivery_schedule_section() {
        ?>
        <div class="scl-delivery-schedule-section" style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #0071dc;">
            <h3 class="scl-section-title" style="margin-top: 0;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" width="20" height="20" fill="currentColor">
                    <path d="M128 0c17.7 0 32 14.3 32 32V64H288V32c0-17.7 14.3-32 32-32s32 14.3 32 32V64h48c26.5 0 48 21.5 48 48v48H0V112C0 85.5 21.5 64 48 64H96V32c0-17.7 14.3-32 32-32zM0 192H448V464c0 26.5-21.5 48-48 48H48c-26.5 0-48-21.5-48-48V192zm64 80v32c0 8.8 7.2 16 16 16h32c8.8 0 16-7.2 16-16V272c0-8.8-7.2-16-16-16H80c-8.8 0-16 7.2-16 16zm128 0v32c0 8.8 7.2 16 16 16h32c8.8 0 16-7.2 16-16V272c0-8.8-7.2-16-16-16H208c-8.8 0-16 7.2-16 16zm144-16c-8.8 0-16 7.2-16 16v32c0 8.8 7.2 16 16 16h32c8.8 0 16-7.2 16-16V272c0-8.8-7.2-16-16-16H336zM64 400v32c0 8.8 7.2 16 16 16h32c8.8 0 16-7.2 16-16V400c0-8.8-7.2-16-16-16H80c-8.8 0-16 7.2-16 16zm144-16c-8.8 0-16 7.2-16 16v32c0 8.8 7.2 16 16 16h32c8.8 0 16-7.2 16-16V400c0-8.8-7.2-16-16-16H208zm112 16v32c0 8.8 7.2 16 16 16h32c8.8 0 16-7.2 16-16V400c0-8.8-7.2-16-16-16H336c-8.8 0-16 7.2-16 16z"/>
                </svg>
                <?php esc_html_e( 'Delivery Schedule', 'simple-checkout-location' ); ?>
            </h3>
            
            <div id="scl-schedule-fields" style="display: none;">
                <p style="color: #666; margin-bottom: 15px;">
                    <?php esc_html_e( 'Select your preferred delivery date and time', 'simple-checkout-location' ); ?>
                </p>
                
                <div class="scl-form-grid">
                    <div class="scl-form-field">
                        <label for="scl_delivery_date">
                            <?php esc_html_e( 'Delivery Date', 'simple-checkout-location' ); ?> 
                            <span class="required">*</span>
                        </label>
                        <select id="scl_delivery_date" name="delivery_date" required>
                            <option value=""><?php esc_html_e( 'Select delivery date', 'simple-checkout-location' ); ?></option>
                        </select>
                    </div>
                    
                    <div class="scl-form-field">
                        <label for="scl_delivery_time">
                            <?php esc_html_e( 'Delivery Time', 'simple-checkout-location' ); ?> 
                            <span class="required">*</span>
                        </label>
                        <select id="scl_delivery_time" name="delivery_time" required>
                            <option value=""><?php esc_html_e( 'Select delivery time', 'simple-checkout-location' ); ?></option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div id="scl-schedule-placeholder" style="text-align: center; padding: 20px; color: #999;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="40" height="40" fill="currentColor" style="opacity: 0.3; margin-bottom: 10px;">
                    <path d="M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM216 336h24V272H216c-13.3 0-24-10.7-24-24s10.7-24 24-24h48c13.3 0 24 10.7 24 24v88h8c13.3 0 24 10.7 24 24s-10.7 24-24 24H216c-13.3 0-24-10.7-24-24s10.7-24 24-24zm40-208a32 32 0 1 1 0 64 32 32 0 1 1 0-64z"/>
                </svg>
                <p><?php esc_html_e( 'Please select a delivery zone first', 'simple-checkout-location' ); ?></p>
            </div>
        </div>
        <?php
    }

    public function render_custom_address_section() {
        $service   = new SCL_Address_Service();
        $addresses = $service->get_user_addresses();
        $default   = $service->get_default_billing_address();
        ?>
        <div class="scl-checkout-section">
            <h3 class="scl-section-title">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512" width="20" height="20" fill="currentColor">
                    <path d="M215.7 499.2C267 435 384 279.4 384 192C384 86 298 0 192 0S0 86 0 192c0 87.4 117 243 168.3 307.2c12.3 15.3 35.1 15.3 47.4 0zM192 128a64 64 0 1 1 0 128 64 64 0 1 1 0-128z"/>
                </svg>
                <?php esc_html_e( 'Delivery Address', 'simple-checkout-location' ); ?>
            </h3>

            <?php if ( ! empty( $addresses ) ) : ?>
                <div class="scl-saved-addresses">
                    <div class="scl-addresses-grid">
                        <?php foreach ( $addresses as $address ) : 
                            $is_default = (int) $address['is_default_billing'] === 1;
                        ?>
                        <label class="scl-address-card <?php echo $is_default ? 'selected' : ''; ?>" data-address-id="<?php echo esc_attr( $address['id'] ); ?>">
                            <input type="radio" 
                                   name="scl_selected_address_id" 
                                   value="<?php echo esc_attr( $address['id'] ); ?>" 
                                   <?php checked( $is_default ); ?>
                                   class="scl-address-radio">
                            
                            <div class="scl-address-card-header">
                                <strong><?php echo esc_html( $address['address_name'] ); ?></strong>
                                <?php if ( $is_default ) : ?>
                                    <span class="scl-badge-default"><?php esc_html_e( 'Default', 'simple-checkout-location' ); ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="scl-address-card-body">
                                <p class="scl-customer-name"><?php echo esc_html( $address['customer_name'] ); ?></p>
                                <p class="scl-phone"><?php echo esc_html( $address['phone_primary'] ); ?></p>
                                <?php if ( ! empty( $address['address_details'] ) ) : ?>
                                    <p class="scl-details"><?php echo esc_html( $address['address_details'] ); ?></p>
                                <?php endif; ?>
                                <?php if ( ! empty( $address['zone'] ) ) : ?>
                                    <p class="scl-zone"><?php echo esc_html( $address['zone'] ); ?></p>
                                <?php endif; ?>
                            </div>

                            <div class="scl-address-actions">
                                <button type="button" class="scl-edit-address-btn" data-address-id="<?php echo esc_attr( $address['id'] ); ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="12" height="12" fill="currentColor">
                                        <path d="M471.6 21.7c-21.9-21.9-57.3-21.9-79.2 0L362.3 51.7l97.9 97.9 30.1-30.1c21.9-21.9 21.9-57.3 0-79.2L471.6 21.7zm-299.2 220c-6.1 6.1-10.8 13.6-13.5 21.9l-29.6 88.8c-2.9 8.6-.6 18.1 5.8 24.6s15.9 8.7 24.6 5.8l88.8-29.6c8.2-2.7 15.7-7.4 21.9-13.5L437.7 172.3 339.7 74.3 172.4 241.7zM96 64C43 64 0 107 0 160V416c0 53 43 96 96 96H352c53 0 96-43 96-96V320c0-17.7-14.3-32-32-32s-32 14.3-32 32v96c0 17.7-14.3 32-32 32H96c-17.7 0-32-14.3-32-32V160c0-17.7 14.3-32 32-32h96c17.7 0 32-14.3 32-32s-14.3-32-32-32H96z"/>
                                    </svg>
                                    <?php esc_html_e( 'Edit', 'simple-checkout-location' ); ?>
                                </button>
                                <button type="button" class="scl-delete-address-btn" data-address-id="<?php echo esc_attr( $address['id'] ); ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" width="12" height="12" fill="currentColor">
                                        <path d="M135.2 17.7L128 32H32C14.3 32 0 46.3 0 64S14.3 96 32 96H416c17.7 0 32-14.3 32-32s-14.3-32-32-32H320l-7.2-14.3C307.4 6.8 296.3 0 284.2 0H163.8c-12.1 0-23.2 6.8-28.6 17.7zM416 128H32L53.2 467c1.6 25.3 22.6 45 47.9 45H346.9c25.3 0 46.3-19.7 47.9-45L416 128z"/>
                                    </svg>
                                    <?php esc_html_e( 'Delete', 'simple-checkout-location' ); ?>
                                </button>
                            </div>

                            <div class="scl-address-checkmark">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" width="16" height="16" fill="currentColor">
                                    <path d="M438.6 105.4c12.5 12.5 12.5 32.8 0 45.3l-256 256c-12.5 12.5-32.8 12.5-45.3 0l-128-128c-12.5-12.5-12.5-32.8 0-45.3s32.8-12.5 45.3 0L160 338.7 393.4 105.4c12.5-12.5 32.8-12.5 45.3 0z"/>
                                </svg>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>

                    <button type="button" id="scl-show-add-address" class="button scl-add-new-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" width="14" height="14" fill="currentColor">
                            <path d="M256 80c0-17.7-14.3-32-32-32s-32 14.3-32 32V224H48c-17.7 0-32 14.3-32 32s14.3 32 32 32H192V432c0 17.7 14.3 32 32 32s32-14.3 32-32V288H400c17.7 0 32-14.3 32-32s-14.3-32-32-32H256V80z"/>
                        </svg>
                        <?php esc_html_e( 'Add New Address', 'simple-checkout-location' ); ?>
                    </button>
                </div>
            <?php else : ?>
                <p class="scl-no-addresses"><?php esc_html_e( 'No saved addresses. Please add a new delivery address below.', 'simple-checkout-location' ); ?></p>
            <?php endif; ?>

            <div id="scl-add-address-form" class="scl-add-address-form" style="<?php echo ! empty( $addresses ) ? 'display:none;' : ''; ?>">
                <h4 id="scl-form-title"><?php esc_html_e( 'Add New Address', 'simple-checkout-location' ); ?></h4>
                
                <input type="hidden" id="scl_edit_address_id" value="">
                
                <div class="scl-form-grid">
                    <div class="scl-form-field">
                        <label for="scl_address_name"><?php esc_html_e( 'Address Name', 'simple-checkout-location' ); ?> <span class="required">*</span></label>
                        <input type="text" id="scl_address_name" placeholder="<?php esc_attr_e( 'e.g., Home, Office', 'simple-checkout-location' ); ?>" required>
                    </div>

                    <div class="scl-form-field">
                        <label for="scl_customer_name"><?php esc_html_e( 'Recipient Name', 'simple-checkout-location' ); ?> <span class="required">*</span></label>
                        <input type="text" id="scl_customer_name" required>
                    </div>

                    <div class="scl-form-field">
                        <label for="scl_phone_primary"><?php esc_html_e( 'Primary Phone', 'simple-checkout-location' ); ?> <span class="required">*</span></label>
                        <input type="tel" id="scl_phone_primary" required>
                    </div>

                    <div class="scl-form-field">
                        <label for="scl_phone_secondary"><?php esc_html_e( 'Secondary Phone', 'simple-checkout-location' ); ?></label>
                        <input type="tel" id="scl_phone_secondary">
                    </div>

                    <div class="scl-form-field scl-full-width">
                        <label for="scl_address_details"><?php esc_html_e( 'Street Address', 'simple-checkout-location' ); ?></label>
                        <textarea id="scl_address_details" rows="2"></textarea>
                    </div>

                    <div class="scl-form-field">
                        <label for="scl_zone"><?php esc_html_e( 'Delivery Zone', 'simple-checkout-location' ); ?> <span class="required">*</span></label>
                        <select id="scl_zone" required>
                            <option value=""><?php esc_html_e( 'Select delivery zone', 'simple-checkout-location' ); ?></option>
                            <?php
                            require_once SCL_PLUGIN_DIR . 'includes/class-zones-repository.php';
                            $zones_repo = new SCL_Zones_Repository();
                            $zones = $zones_repo->get_all_zones( true );
                            foreach ( $zones as $zone ) :
                            ?>
                                <option value="<?php echo esc_attr( $zone['zone_name'] ); ?>" data-cost="<?php echo esc_attr( $zone['shipping_cost'] ); ?>">
                                    <?php 
                                    $display = $zone['zone_name'];
                                    if ( ! empty( $zone['zone_name_ar'] ) ) {
                                        $display .= ' - ' . $zone['zone_name_ar'];
                                    }
                                    echo esc_html( $display );
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="scl-form-field scl-full-width">
                        <label for="scl_notes_customer"><?php esc_html_e( 'Delivery Notes', 'simple-checkout-location' ); ?></label>
                        <textarea id="scl_notes_customer" rows="2"></textarea>
                    </div>
                </div>

                <div class="scl-location-section">
                    <label><?php esc_html_e( 'Delivery Location on Map', 'simple-checkout-location' ); ?> <span class="required">*</span></label>
                    <button type="button" id="scl-select-location-btn" class="button">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512" width="14" height="14" fill="currentColor">
                            <path d="M215.7 499.2C267 435 384 279.4 384 192C384 86 298 0 192 0S0 86 0 192c0 87.4 117 243 168.3 307.2c12.3 15.3 35.1 15.3 47.4 0zM192 128a64 64 0 1 1 0 128 64 64 0 1 1 0-128z"/>
                        </svg>
                        <?php esc_html_e( 'Select Location on Map', 'simple-checkout-location' ); ?>
                    </button>
                    <span id="scl-location-status" class="scl-location-status"></span>
                    
                    <input type="hidden" id="scl_location_url">
                    <input type="hidden" id="scl_location_lat">
                    <input type="hidden" id="scl_location_lng">
                </div>

                <div class="scl-form-actions">
                    <?php if ( ! empty( $addresses ) ) : ?>
                        <button type="button" id="scl-cancel-add-address" class="button"><?php esc_html_e( 'Cancel', 'simple-checkout-location' ); ?></button>
                    <?php endif; ?>
                    <button type="button" id="scl-save-address-btn" class="button button-primary"><?php esc_html_e( 'Save & Continue', 'simple-checkout-location' ); ?></button>
                </div>
            </div>

            <input type="hidden" id="scl-final-address-id" name="scl_address_id" value="<?php echo $default ? esc_attr( $default['id'] ) : ''; ?>">
            
            <!-- Hidden WooCommerce fields -->
            <input type="hidden" name="billing_first_name" id="billing_first_name" value="">
            <input type="hidden" name="billing_last_name" id="billing_last_name" value=".">
            <input type="hidden" name="billing_company" id="billing_company" value="">
            <input type="hidden" name="billing_phone" id="billing_phone" value="">
            <input type="hidden" name="billing_address_1" id="billing_address_1" value="">
            <input type="hidden" name="billing_address_2" id="billing_address_2" value="">
            <input type="hidden" name="billing_city" id="billing_city" value="">
            <input type="hidden" name="billing_state" id="billing_state" value="">
            <input type="hidden" name="billing_postcode" id="billing_postcode" value="00000">
            <input type="hidden" name="billing_country" id="billing_country" value="EG">
        </div>
        <?php
        $this->render_location_modal();
    }

    private function render_guest_address_form() {
        ?>
        <div class="scl-checkout-section scl-guest-form">
            <h3 class="scl-section-title">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512" width="20" height="20" fill="currentColor">
                    <path d="M215.7 499.2C267 435 384 279.4 384 192C384 86 298 0 192 0S0 86 0 192c0 87.4 117 243 168.3 307.2c12.3 15.3 35.1 15.3 47.4 0zM192 128a64 64 0 1 1 0 128 64 64 0 1 1 0-128z"/>
                </svg>
                <?php esc_html_e( 'Delivery Details', 'simple-checkout-location' ); ?>
            </h3>

            <div class="scl-form-grid">
                <div class="scl-form-field">
                    <label for="scl_guest_name"><?php esc_html_e( 'Full Name', 'simple-checkout-location' ); ?> <span class="required">*</span></label>
                    <input type="text" id="scl_guest_name" name="scl_guest_name" required>
                </div>

                <div class="scl-form-field">
                    <label for="scl_guest_phone"><?php esc_html_e( 'Phone Number', 'simple-checkout-location' ); ?> <span class="required">*</span></label>
                    <input type="tel" id="scl_guest_phone" name="scl_guest_phone" required>
                </div>

                <div class="scl-form-field scl-full-width">
                    <label for="scl_guest_address"><?php esc_html_e( 'Delivery Address', 'simple-checkout-location' ); ?> <span class="required">*</span></label>
                    <textarea id="scl_guest_address" name="scl_guest_address" rows="2" required></textarea>
                </div>

                <div class="scl-form-field">
                    <label for="scl_guest_city"><?php esc_html_e( 'Delivery Zone', 'simple-checkout-location' ); ?> <span class="required">*</span></label>
                    <select id="scl_guest_city" name="scl_guest_city" required>
                        <option value=""><?php esc_html_e( 'Select delivery zone', 'simple-checkout-location' ); ?></option>
                        <?php
                        require_once SCL_PLUGIN_DIR . 'includes/class-zones-repository.php';
                        $zones_repo = new SCL_Zones_Repository();
                        $zones = $zones_repo->get_all_zones( true );
                        foreach ( $zones as $zone ) :
                        ?>
                            <option value="<?php echo esc_attr( $zone['zone_name'] ); ?>" data-cost="<?php echo esc_attr( $zone['shipping_cost'] ); ?>">
                                <?php 
                                $display = $zone['zone_name'];
                                if ( ! empty( $zone['zone_name_ar'] ) ) {
                                    $display .= ' - ' . $zone['zone_name_ar'];
                                }
                                echo esc_html( $display );
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="scl-location-section">
                <label><?php esc_html_e( 'Delivery Location on Map', 'simple-checkout-location' ); ?> <span class="required">*</span></label>
                <button type="button" id="scl-select-location-btn" class="button">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512" width="14" height="14" fill="currentColor">
                        <path d="M215.7 499.2C267 435 384 279.4 384 192C384 86 298 0 192 0S0 86 0 192c0 87.4 117 243 168.3 307.2c12.3 15.3 35.1 15.3 47.4 0zM192 128a64 64 0 1 1 0 128 64 64 0 1 1 0-128z"/>
                    </svg>
                    <?php esc_html_e( 'Select Location on Map', 'simple-checkout-location' ); ?>
                </button>
                <span id="scl-location-status" class="scl-location-status"></span>
                
                <input type="hidden" id="scl_location_url" name="scl_location_url">
                <input type="hidden" id="scl_location_lat" name="scl_location_lat">
                <input type="hidden" id="scl_location_lng" name="scl_location_lng">
            </div>
        </div>
        <?php
        $this->render_location_modal();
    }

        private function render_location_modal() {
        ?>
        <div id="location-modal" class="location-modal" style="display: none;">
            <div class="location-modal-content">
                <div class="location-modal-header">
                    <h3><?php esc_html_e( 'Select Your Delivery Location', 'simple-checkout-location' ); ?></h3>
                    <button type="button" id="close-location-modal" class="close-modal">&times;</button>
                </div>
                <div class="location-modal-body">
                    <div class="location-instructions">
                        <p><?php esc_html_e( 'Choose your delivery location using one of the following methods:', 'simple-checkout-location' ); ?></p>
                    </div>
                    <div class="location-actions-row">
                        <button type="button" id="use-current-location" class="location-method-btn">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="20" height="20" fill="currentColor">
                                <path d="M256 0c17.7 0 32 14.3 32 32V66.7C368.4 80.1 431.9 143.6 445.3 224H480c17.7 0 32 14.3 32 32s-14.3 32-32 32H445.3C431.9 368.4 368.4 431.9 288 445.3V480c0 17.7-14.3 32-32 32s-32-14.3-32-32V445.3C143.6 431.9 80.1 368.4 66.7 288H32c-17.7 0-32-14.3-32-32s14.3-32 32-32H66.7C80.1 143.6 143.6 80.1 224 66.7V32c0-17.7 14.3-32 32-32zM128 256a128 128 0 1 0 256 0 128 128 0 1 0 -256 0zm128-80a80 80 0 1 1 0 160 80 80 0 1 1 0-160z"/>
                            </svg>
                            <span><?php esc_html_e( 'Use My Current Location', 'simple-checkout-location' ); ?></span>
                        </button>
                        <div class="separator-text"><?php esc_html_e( 'OR', 'simple-checkout-location' ); ?></div>
                    </div>
                    <div class="location-search-wrapper">
                        <div class="search-input-container">
                            <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="18" height="18" fill="currentColor">
                                <path d="M416 208c0 45.9-14.9 88.3-40 122.7L502.6 457.4c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L330.7 376c-34.4 25.2-76.8 40-122.7 40C93.1 416 0 322.9 0 208S93.1 0 208 0S416 93.1 416 208zM208 352a144 144 0 1 0 0-288 144 144 0 1 0 0 288z"/>
                            </svg>
                            <input type="text" id="location-search-input" class="location-search" placeholder="<?php esc_attr_e( 'Search for your address...', 'simple-checkout-location' ); ?>">
                        </div>
                    </div>
                    <div id="map-container"></div>
                </div>
                <div class="location-modal-footer">
                    <button type="button" id="confirm-location-btn" class="confirm-location-btn" disabled>
                        <?php esc_html_e( 'Confirm This Location', 'simple-checkout-location' ); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    // ==================== Validation & Save Methods ====================

    public function validate_custom_address() {
        // فقط نتحقق من الـ email
        if ( empty( $_POST['billing_email'] ) ) {
            wc_add_notice( __( 'Please enter your email address.', 'simple-checkout-location' ), 'error' );
        }
    }


public function populate_woocommerce_fields( $data ) {
    error_log('SCL populate_woocommerce_fields called');
    
    if ( is_user_logged_in() ) {
        $address_id = isset( $_POST['scl_address_id'] ) ? (int) $_POST['scl_address_id'] : 0;
        
        if ( $address_id > 0 ) {
            $address = $this->repo->get_address( $address_id, get_current_user_id() );
            
            if ( $address ) {
                error_log('SCL: Populating from address #' . $address_id);
                
                $data['billing_first_name'] = ! empty( $address['customer_name'] ) ? $address['customer_name'] : 'Customer';
                $data['billing_last_name'] = '.';
                $data['billing_company'] = $address['address_name'];
                $data['billing_phone'] = ! empty( $address['phone_primary'] ) ? $address['phone_primary'] : '0000000000';
                $data['billing_address_1'] = ! empty( $address['address_details'] ) ? $address['address_details'] : $address['address_name'];
                $data['billing_address_2'] = '';
                $data['billing_city'] = ! empty( $address['zone'] ) ? $address['zone'] : 'City';
                $data['billing_state'] = '';
                $data['billing_postcode'] = '00000';
                $data['billing_country'] = 'EG';
                $data['billing_zone'] = ! empty( $address['zone'] ) ? $address['zone'] : '';
                
                // ✅ إضافة notes_customer كحقل منفصل
                $data['billing_notes_customer'] = ! empty( $address['notes_customer'] ) ? $address['notes_customer'] : '';
                
                // ✅ إضافة location للمستخدمين المسجلين
                $data['billing_location_url'] = ! empty( $address['location_url'] ) ? $address['location_url'] : '';
                $data['billing_location_lat'] = ! empty( $address['location_lat'] ) ? $address['location_lat'] : '';
                $data['billing_location_lng'] = ! empty( $address['location_lng'] ) ? $address['location_lng'] : '';
                
                $data['shipping_first_name'] = $data['billing_first_name'];
                $data['shipping_last_name'] = '.';
                $data['shipping_company'] = $address['address_name'];
                $data['shipping_address_1'] = $data['billing_address_1'];
                $data['shipping_address_2'] = '';
                $data['shipping_city'] = $data['billing_city'];
                $data['shipping_state'] = '';
                $data['shipping_postcode'] = '00000';
                $data['shipping_country'] = 'EG';
                
                // ✅ تحديث $_POST
                $_POST['billing_first_name'] = $data['billing_first_name'];
                $_POST['billing_last_name'] = $data['billing_last_name'];
                $_POST['billing_phone'] = $data['billing_phone'];
                $_POST['billing_address_1'] = $data['billing_address_1'];
                $_POST['billing_address_2'] = '';
                $_POST['billing_city'] = $data['billing_city'];
                $_POST['billing_postcode'] = $data['billing_postcode'];
                $_POST['billing_country'] = $data['billing_country'];
                $_POST['billing_notes_customer'] = $data['billing_notes_customer'];
                
                // ✅ تحديث location في $_POST للمستخدمين المسجلين
                $_POST['scl_location_url'] = $data['billing_location_url'];
                $_POST['scl_location_lat'] = $data['billing_location_lat'];
                $_POST['scl_location_lng'] = $data['billing_location_lng'];
            }
        }
    } else {
        // Guest Users
        $data['billing_first_name'] = isset( $_POST['scl_guest_name'] ) ? sanitize_text_field( $_POST['scl_guest_name'] ) : 'Guest';
        $data['billing_last_name'] = '.';
        $data['billing_phone'] = isset( $_POST['scl_guest_phone'] ) ? sanitize_text_field( $_POST['scl_guest_phone'] ) : '0000000000';
        $data['billing_address_1'] = isset( $_POST['scl_guest_address'] ) ? sanitize_text_field( $_POST['scl_guest_address'] ) : 'Address';
        $data['billing_address_2'] = '';
        $data['billing_city'] = isset( $_POST['scl_guest_city'] ) ? sanitize_text_field( $_POST['scl_guest_city'] ) : 'City';
        $data['billing_state'] = '';
        $data['billing_postcode'] = '00000';
        $data['billing_country'] = 'EG';
        $data['billing_zone'] = isset( $_POST['scl_guest_city'] ) ? sanitize_text_field( $_POST['scl_guest_city'] ) : '';
        
        // ✅ إضافة location للضيوف (GUEST USERS)
        if ( isset( $_POST['scl_location_url'] ) && ! empty( $_POST['scl_location_url'] ) ) {
            $data['billing_location_url'] = sanitize_text_field( $_POST['scl_location_url'] );
        }
        if ( isset( $_POST['scl_location_lat'] ) && ! empty( $_POST['scl_location_lat'] ) ) {
            $data['billing_location_lat'] = sanitize_text_field( $_POST['scl_location_lat'] );
        }
        if ( isset( $_POST['scl_location_lng'] ) && ! empty( $_POST['scl_location_lng'] ) ) {
            $data['billing_location_lng'] = sanitize_text_field( $_POST['scl_location_lng'] );
        }
        
        $data['shipping_first_name'] = $data['billing_first_name'];
        $data['shipping_last_name'] = '.';
        $data['shipping_address_1'] = $data['billing_address_1'];
        $data['shipping_address_2'] = '';
        $data['shipping_city'] = $data['billing_city'];
        $data['shipping_state'] = '';
        $data['shipping_postcode'] = '00000';
        $data['shipping_country'] = 'EG';
        
        // ✅ تحديث $_POST
        $_POST['billing_first_name'] = $data['billing_first_name'];
        $_POST['billing_last_name'] = $data['billing_last_name'];
        $_POST['billing_phone'] = $data['billing_phone'];
        $_POST['billing_address_1'] = $data['billing_address_1'];
        $_POST['billing_address_2'] = '';
        $_POST['billing_city'] = $data['billing_city'];
        $_POST['billing_postcode'] = $data['billing_postcode'];
        $_POST['billing_country'] = $data['billing_country'];
    }
    
    // ✅ معالجة تاريخ ووقت التوصيل
    if ( isset( $_POST['delivery_date'] ) && ! empty( $_POST['delivery_date'] ) ) {
        $data['billing_delivery_date'] = sanitize_text_field( $_POST['delivery_date'] );
    }
    
    if ( isset( $_POST['delivery_time'] ) && ! empty( $_POST['delivery_time'] ) ) {
        $data['billing_delivery_time'] = sanitize_text_field( $_POST['delivery_time'] );
    }

    // ✅ Debug للتحقق من Location
    error_log( '=== SCL Location Debug in populate_woocommerce_fields ===' );
    error_log( 'Is Logged In: ' . ( is_user_logged_in() ? 'Yes' : 'No' ) );
    error_log( 'Location URL: ' . ( isset( $data['billing_location_url'] ) ? $data['billing_location_url'] : 'NOT SET' ) );
    error_log( 'Location LAT: ' . ( isset( $data['billing_location_lat'] ) ? $data['billing_location_lat'] : 'NOT SET' ) );
    error_log( 'Location LNG: ' . ( isset( $data['billing_location_lng'] ) ? $data['billing_location_lng'] : 'NOT SET' ) );
    error_log( '$_POST scl_location_url: ' . ( isset( $_POST['scl_location_url'] ) ? $_POST['scl_location_url'] : 'NOT SET' ) );
    
    return $data;
}





        public function remove_woocommerce_errors( $data, $errors ) {
        if ( ! is_wp_error( $errors ) ) {
            return;
        }
        
        // إزالة جميع أخطاء الحقول المخفية
        $error_codes = $errors->get_error_codes();
        
        $hidden_fields = [
            'billing_first_name',
            'billing_last_name', 
            'billing_company',
            'billing_address_1',
            'billing_address_2',
            'billing_city',
            'billing_state',
            'billing_postcode',
            'billing_country',
            'billing_phone',
            'shipping_first_name',
            'shipping_last_name',
            'shipping_company',
            'shipping_address_1',
            'shipping_address_2',
            'shipping_city',
            'shipping_state',
            'shipping_postcode',
            'shipping_country'
        ];
        
        foreach ( $error_codes as $code ) {
            foreach ( $hidden_fields as $field ) {
                if ( strpos( $code, $field ) !== false ) {
                    $errors->remove( $code );
                }
            }
        }
    }


    public function save_custom_address_to_order( $order_id, $data ) {
    $order = wc_get_order( $order_id );
    
    if ( is_user_logged_in() && isset( $_POST['scl_address_id'] ) ) {
        $address_id = (int) $_POST['scl_address_id'];
        $address = $this->repo->get_address( $address_id, get_current_user_id() );
        
        if ( $address ) {
            $order->update_meta_data( '_scl_address_id', $address_id );
            
            $billing = [
                'first_name'   => $address['customer_name'],
                'last_name'    => '',
                'company'      => $address['address_name'],
                'address_1'    => $address['address_details'],
                'address_2'    => $address['notes_customer'],
                'city'         => $address['zone'],
                'state'        => '',
                'postcode'     => '',
                'country'      => 'EG',
                'email'        => $order->get_billing_email(),
                'phone'        => $address['phone_primary'],
            ];
            
            $order->set_address( $billing, 'billing' );
            
            $order->update_meta_data( '_billing_phone_secondary', $address['phone_secondary'] );
            $order->update_meta_data( '_billing_address_name', $address['address_name'] );
            $order->update_meta_data( '_billing_location_url', $address['location_url'] );
            $order->update_meta_data( '_billing_location_lat', $address['location_lat'] );
            $order->update_meta_data( '_billing_location_lng', $address['location_lng'] );
            $order->update_meta_data( '_billing_notes_internal', $address['notes_internal'] );
            $order->update_meta_data( '_billing_zone', $address['zone'] );
        }
    } else {
        // ✅ Guest checkout من الموقع (ليس API)
        if ( isset( $_POST['scl_location_url'] ) && ! empty( $_POST['scl_location_url'] ) ) {
            $order->update_meta_data( '_billing_location_url', sanitize_text_field( $_POST['scl_location_url'] ) );
        }
        if ( isset( $_POST['scl_location_lat'] ) && ! empty( $_POST['scl_location_lat'] ) ) {
            $order->update_meta_data( '_billing_location_lat', sanitize_text_field( $_POST['scl_location_lat'] ) );
        }
        if ( isset( $_POST['scl_location_lng'] ) && ! empty( $_POST['scl_location_lng'] ) ) {
            $order->update_meta_data( '_billing_location_lng', sanitize_text_field( $_POST['scl_location_lng'] ) );
        }
        
        // ✅ حفظ zone و notes من guest
        if ( isset( $_POST['scl_guest_city'] ) && ! empty( $_POST['scl_guest_city'] ) ) {
            $order->update_meta_data( '_billing_zone', sanitize_text_field( $_POST['scl_guest_city'] ) );
        }
    }

    if ( isset( $_POST['delivery_date'] ) && ! empty( $_POST['delivery_date'] ) ) {
        $order->update_meta_data( '_scl_delivery_date', sanitize_text_field( $_POST['delivery_date'] ) );
        $order->update_meta_data( '_billing_delivery_date', sanitize_text_field( $_POST['delivery_date'] ) );
    }

    if ( isset( $_POST['delivery_time'] ) && ! empty( $_POST['delivery_time'] ) ) {
        $order->update_meta_data( '_scl_delivery_time', sanitize_text_field( $_POST['delivery_time'] ) );
        $order->update_meta_data( '_billing_delivery_time', sanitize_text_field( $_POST['delivery_time'] ) );
    }

    $order->save();
}


    
    public function display_address_in_admin( $order ) {
    $address_id = $order->get_meta( '_scl_address_id' );
    
    echo '<div class="scl-order-address" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-left: 4px solid #0071dc; border-radius: 4px;">';
    echo '<h4 style="margin: 0 0 10px 0; color: #0071dc;">' . esc_html__( 'Delivery Details', 'simple-checkout-location' ) . '</h4>';
    
    $billing = $order->get_address( 'billing' );
    
    if ( ! empty( $billing['company'] ) ) {
        echo '<p><strong>' . esc_html__( 'Address Name:', 'simple-checkout-location' ) . '</strong> ' . esc_html( $billing['company'] ) . '</p>';
    }
    
    echo '<p><strong>' . esc_html__( 'Recipient:', 'simple-checkout-location' ) . '</strong> ' . esc_html( $billing['first_name'] ) . '</p>';
    echo '<p><strong>' . esc_html__( 'Primary Phone:', 'simple-checkout-location' ) . '</strong> ' . esc_html( $billing['phone'] ) . '</p>';
    
    $phone_secondary = $order->get_meta( '_billing_phone_secondary' );
    if ( $phone_secondary ) {
        echo '<p><strong>' . esc_html__( 'Secondary Phone:', 'simple-checkout-location' ) . '</strong> ' . esc_html( $phone_secondary ) . '</p>';
    }
    
    if ( ! empty( $billing['address_1'] ) ) {
        echo '<p><strong>' . esc_html__( 'Address:', 'simple-checkout-location' ) . '</strong> ' . esc_html( $billing['address_1'] );
        if ( ! empty( $billing['city'] ) ) {
            echo ', ' . esc_html( $billing['city'] );
        }
        echo '</p>';
    }
    
    // ✅ استخدام notes_customer من meta data بدلاً من address_2
    $notes_customer = $order->get_meta( '_billing_notes_customer' );
    if ( $notes_customer ) {
        echo '<p><strong>' . esc_html__( 'Delivery Notes:', 'simple-checkout-location' ) . '</strong> ' . esc_html( $notes_customer ) . '</p>';
    }
    
    $notes_internal = $order->get_meta( '_billing_notes_internal' );
    if ( $notes_internal ) {
        echo '<p style="color: #d63638;"><strong>' . esc_html__( 'Internal Notes (Admin Only):', 'simple-checkout-location' ) . '</strong> ' . esc_html( $notes_internal ) . '</p>';
    }
    
    $location_url = $order->get_meta( '_billing_location_url' );
    if ( $location_url ) {
        echo '<p style="margin-top: 10px;"><a href="' . esc_url( $location_url ) . '" target="_blank" class="button button-small" style="background: #0071dc; color: #fff; border-color: #0071dc;">';
        echo '<span class="dashicons dashicons-location" style="margin-top: 3px;"></span> ';
        echo esc_html__( 'View on Google Maps', 'simple-checkout-location' );
        echo '</a></p>';
    }

    $delivery_date = $order->get_meta( '_scl_delivery_date' );
    $delivery_time = $order->get_meta( '_scl_delivery_time' );

    if ( $delivery_date || $delivery_time ) {
        echo '<hr style="margin: 15px 0; border: none; border-top: 1px solid #e5e7eb;">';
        echo '<h4 style="margin: 10px 0; color: #0071dc;">' . esc_html__( 'Delivery Schedule', 'simple-checkout-location' ) . '</h4>';
        
        if ( $delivery_date ) {
            echo '<p><strong>' . esc_html__( 'Delivery Date:', 'simple-checkout-location' ) . '</strong> ' . esc_html( date( 'l, F j, Y', strtotime( $delivery_date ) ) ) . '</p>';
        }
        
        if ( $delivery_time ) {
            echo '<p><strong>' . esc_html__( 'Delivery Time:', 'simple-checkout-location' ) . '</strong> ' . esc_html( $delivery_time ) . '</p>';
        }
    }
    
    if ( $address_id ) {
        $current_address = $this->repo->get_address( $address_id );
        
        echo '<hr style="margin: 15px 0; border: none; border-top: 1px solid #e5e7eb;">';
        
        if ( $current_address ) {
            echo '<p style="font-size: 12px; color: #6b7280;">';
            echo '<strong>' . esc_html__( 'Linked to:', 'simple-checkout-location' ) . '</strong> ';
            echo sprintf( __( 'Saved Address #%d', 'simple-checkout-location' ), $address_id );
            echo ' <span style="color: #10b981;">●</span> ' . esc_html__( 'Active', 'simple-checkout-location' );
            echo '</p>';
            
            echo '<p style="font-size: 11px; color: #9ca3af; margin: 5px 0 0 0;">';
            echo esc_html__( 'This address is synced automatically when customer updates their saved address.', 'simple-checkout-location' );
            echo '</p>';
        } else {
            echo '<p style="font-size: 12px; color: #d63638;">';
            echo '<strong>⚠</strong> ';
            echo sprintf( __( 'Original saved address (#%d) was deleted', 'simple-checkout-location' ), $address_id );
            echo '</p>';
        }
    } else {
        echo '<hr style="margin: 15px 0; border: none; border-top: 1px solid #e5e7eb;">';
        echo '<p style="font-size: 12px; color: #6b7280;">';
        echo esc_html__( 'Guest order - address not linked to saved addresses', 'simple-checkout-location' );
        echo '</p>';
    }
    
    echo '</div>';
}




    public function rest_add_address_fields( $response, $order, $request ) {
        $data = $response->get_data();
        
        if ( isset( $data['billing'] ) ) {
            $data['billing']['phone_secondary'] = $order->get_meta( '_billing_phone_secondary' );
            $data['billing']['address_name'] = $order->get_meta( '_billing_address_name' );
            $data['billing']['location_url'] = $order->get_meta( '_billing_location_url' );
            $data['billing']['location_lat'] = $order->get_meta( '_billing_location_lat' );
            $data['billing']['location_lng'] = $order->get_meta( '_billing_location_lng' );
            $data['billing']['notes_internal'] = $order->get_meta( '_billing_notes_internal' );
            $data['billing']['scl_address_id'] = $order->get_meta( '_scl_address_id' );
            $data['billing']['zone'] = $order->get_meta( '_billing_zone' );
            $data['billing']['delivery_date'] = $order->get_meta( '_billing_delivery_date' );
            $data['billing']['delivery_time'] = $order->get_meta( '_billing_delivery_time' );
            
            // ✅ إضافة notes_customer
            $data['billing']['notes_customer'] = $order->get_meta( '_billing_notes_customer' );
        }
        
        $response->set_data( $data );
        return $response;
    }


    // ==================== AJAX Methods ====================

    public function ajax_load_address_data() {
        check_ajax_referer( 'scl_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error();
        }

        $address_id = isset( $_POST['address_id'] ) ? (int) $_POST['address_id'] : 0;
        $address = $this->repo->get_address( $address_id, get_current_user_id() );

        if ( ! $address ) {
            wp_send_json_error();
        }

        wp_send_json_success( $address );
    }

    public function ajax_update_address() {
        check_ajax_referer( 'scl_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => __( 'Please log in', 'simple-checkout-location' ) ] );
        }

        $address_id = isset( $_POST['address_id'] ) ? (int) $_POST['address_id'] : 0;
        $user_id = get_current_user_id();

        $data = [
            'address_name'    => isset( $_POST['address_name'] ) ? sanitize_text_field( $_POST['address_name'] ) : '',
            'customer_name'   => isset( $_POST['customer_name'] ) ? sanitize_text_field( $_POST['customer_name'] ) : '',
            'phone_primary'   => isset( $_POST['phone_primary'] ) ? sanitize_text_field( $_POST['phone_primary'] ) : '',
            'phone_secondary' => isset( $_POST['phone_secondary'] ) ? sanitize_text_field( $_POST['phone_secondary'] ) : '',
            'address_details' => isset( $_POST['address_details'] ) ? sanitize_textarea_field( $_POST['address_details'] ) : '',
            'zone'            => isset( $_POST['zone'] ) ? sanitize_text_field( $_POST['zone'] ) : '',
            'notes_customer'  => isset( $_POST['notes_customer'] ) ? sanitize_textarea_field( $_POST['notes_customer'] ) : '',
            'location_url'    => isset( $_POST['location_url'] ) ? esc_url_raw( $_POST['location_url'] ) : '',
            'location_lat'    => isset( $_POST['location_lat'] ) ? sanitize_text_field( $_POST['location_lat'] ) : '',
            'location_lng'    => isset( $_POST['location_lng'] ) ? sanitize_text_field( $_POST['location_lng'] ) : '',
        ];

        $result = $this->repo->update_address( $address_id, $user_id, $data );

        if ( $result !== false ) {
            $this->sync_address_to_orders( $address_id, $user_id );
            wp_send_json_success( [ 'message' => __( 'Address updated successfully', 'simple-checkout-location' ) ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Failed to update address', 'simple-checkout-location' ) ] );
        }
    }

    public function ajax_delete_address() {
        check_ajax_referer( 'scl_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error();
        }

        $address_id = isset( $_POST['address_id'] ) ? (int) $_POST['address_id'] : 0;
        $user_id = get_current_user_id();

        $result = $this->repo->soft_delete_address( $address_id, $user_id );

        if ( $result !== false ) {
            wp_send_json_success();
        } else {
            wp_send_json_error();
        }
    }

    public function ajax_get_delivery_schedule() {
        check_ajax_referer( 'scl_nonce', 'nonce' );
        
        $zone_name = isset( $_POST['zone_name'] ) ? sanitize_text_field( $_POST['zone_name'] ) : '';
        
        if ( empty( $zone_name ) ) {
            wp_send_json_error( [ 'message' => 'Zone name is required' ] );
        }
        
        require_once SCL_PLUGIN_DIR . 'includes/class-zones-repository.php';
        $zones_repo = new SCL_Zones_Repository();
        $zone = $zones_repo->get_zone_with_schedule( $zone_name );
        
        if ( ! $zone ) {
            wp_send_json_error( [ 'message' => 'Zone not found' ] );
        }
        
        $available_dates = $zones_repo->get_available_delivery_dates( 
            $zone_name,
            $zone['delivery_days_ahead'],
            $zone['closed_days']
        );
        
        wp_send_json_success( [
            'delivery_times' => $zone['delivery_times'],
            'available_dates' => $available_dates
        ] );
    }

    private function sync_address_to_orders( $address_id, $user_id ) {
        $address = $this->repo->get_address( $address_id, $user_id );
        
        if ( ! $address ) {
            return;
        }
        
        $orders = wc_get_orders( [
            'limit'      => -1,
            'status'     => [ 'pending', 'processing', 'on-hold' ], 
            'meta_key'   => '_scl_address_id',
            'meta_value' => $address_id,
            'customer'   => $user_id,
            'return'     => 'ids',
        ] );
        
        if ( empty( $orders ) ) {
            return;
        }
        
        foreach ( $orders as $order_id ) {
            $order = wc_get_order( $order_id );
            
            if ( ! $order ) {
                continue;
            }
            
            $billing = [
                'first_name'   => $address['customer_name'],
                'last_name'    => '',
                'company'      => $address['address_name'],
                'address_1'    => $address['address_details'],
                'address_2'    => $address['notes_customer'],
                'city'         => $address['zone'],
                'state'        => '',
                'postcode'     => '',
                'country'      => 'EG',
                'email'        => $order->get_billing_email(),
                'phone'        => $address['phone_primary'],
            ];
            
            $order->set_address( $billing, 'billing' );
            
            $order->update_meta_data( '_billing_phone_secondary', $address['phone_secondary'] );
            $order->update_meta_data( '_billing_address_name', $address['address_name'] );
            $order->update_meta_data( '_billing_location_url', $address['location_url'] );
            $order->update_meta_data( '_billing_location_lat', $address['location_lat'] );
            $order->update_meta_data( '_billing_location_lng', $address['location_lng'] );
            $order->update_meta_data( '_billing_notes_internal', $address['notes_internal'] );
            
            $order->add_order_note(
                sprintf(
                    __( 'Billing address updated automatically from saved address #%d', 'simple-checkout-location' ),
                    $address_id
                )
            );
            
            $order->save();
        }
        
        error_log( sprintf( 'SCL: Synced address #%d to %d orders', $address_id, count( $orders ) ) );
    }

    // ==================== Shipping Methods ====================

    public function update_shipping_on_zone_change( $post_data ) {
        parse_str( $post_data, $data );
        
        $zone_name = '';
        
        if ( isset( $data['scl_guest_city'] ) ) {
            $zone_name = sanitize_text_field( $data['scl_guest_city'] );
        } elseif ( isset( $data['scl_address_id'] ) ) {
            $address_id = (int) $data['scl_address_id'];
            $address = $this->repo->get_address( $address_id );
            if ( $address ) {
                $zone_name = $address['zone'];
            }
        }
        
        if ( $zone_name ) {
            WC()->customer->set_shipping_city( $zone_name );
            WC()->customer->set_billing_city( $zone_name );
        }
    }

    public function apply_zone_shipping_rate( $rates, $package ) {
        $zone_name = WC()->customer->get_shipping_city();
        
        if ( empty( $zone_name ) ) {
            $zone_name = WC()->customer->get_billing_city();
        }
        
        if ( ! empty( $zone_name ) ) {
            require_once SCL_PLUGIN_DIR . 'includes/class-zones-repository.php';
            $zones_repo = new SCL_Zones_Repository();
            $shipping_cost = $zones_repo->get_shipping_cost_by_zone_name( $zone_name );
            
            if ( $shipping_cost !== false ) {
                foreach ( $rates as $rate_id => $rate ) {
                    if ( strpos( $rate_id, 'scl_zone_shipping' ) !== false ) {
                        $rates[ $rate_id ]->cost = $shipping_cost;
                        $rates[ $rate_id ]->label = sprintf(
                            __( 'Delivery to %s', 'simple-checkout-location' ),
                            $zone_name
                        );
                    }
                }
            }
        }
        
        return $rates;
    }

    // ==================== My Account Methods ====================

    public function hide_default_addresses_menu( $items ) {
        unset( $items['edit-address'] );
        return $items;
    }

    public function redirect_default_addresses_page() {
        if ( is_account_page() && is_wc_endpoint_url( 'edit-address' ) ) {
            wp_safe_redirect( wc_get_account_endpoint_url( 'scl-addresses' ) );
            exit;
        }
    }

    public function add_custom_addresses_endpoint() {
        add_rewrite_endpoint( 'scl-addresses', EP_ROOT | EP_PAGES );
    }

    public function add_custom_addresses_menu( $items ) {
        $new_items = [];
        foreach ( $items as $key => $label ) {
            $new_items[ $key ] = $label;
            if ( $key === 'dashboard' ) {
                $new_items['scl-addresses'] = __( 'Addresses', 'simple-checkout-location' );
            }
        }
        return $new_items;
    }

    public function custom_addresses_content() {
        $service   = new SCL_Address_Service();
        $addresses = $service->get_user_addresses();

        echo '<h3>' . esc_html__( 'My Delivery Addresses', 'simple-checkout-location' ) . '</h3>';

        if ( ! empty( $addresses ) ) {
            echo '<div class="scl-myaccount-addresses">';
            foreach ( $addresses as $address ) {
                $this->render_address_card( $address );
            }
            echo '</div>';
        } else {
            echo '<p>' . esc_html__( 'No saved addresses yet.', 'simple-checkout-location' ) . '</p>';
        }

        echo '<p><a href="' . esc_url( wc_get_page_permalink( 'checkout' ) ) . '" class="button">' . 
             esc_html__( 'Add New Address at Checkout', 'simple-checkout-location' ) . '</a></p>';
    }

    private function render_address_card( $address ) {
        $default_badge = (int) $address['is_default_billing'] === 1 ? 
            '<span class="scl-default-badge">' . esc_html__( 'Default', 'simple-checkout-location' ) . '</span>' : '';

        echo '<div class="scl-address-card">';
        echo '<h4>' . esc_html( $address['address_name'] ) . ' ' . $default_badge . '</h4>';
        echo '<p><strong>' . esc_html__( 'Name:', 'simple-checkout-location' ) . '</strong> ' . esc_html( $address['customer_name'] ) . '</p>';
        echo '<p><strong>' . esc_html__( 'Phone:', 'simple-checkout-location' ) . '</strong> ' . esc_html( $address['phone_primary'] ) . '</p>';
        
        if ( ! empty( $address['address_details'] ) ) {
            echo '<p><strong>' . esc_html__( 'Address:', 'simple-checkout-location' ) . '</strong> ' . esc_html( $address['address_details'] ) . '</p>';
        }
        
        if ( ! empty( $address['zone'] ) ) {
            echo '<p><strong>' . esc_html__( 'Zone:', 'simple-checkout-location' ) . '</strong> ' . esc_html( $address['zone'] ) . '</p>';
        }
        
        if ( ! empty( $address['location_url'] ) ) {
            echo '<p><a href="' . esc_url( $address['location_url'] ) . '" target="_blank">' . 
                 esc_html__( 'View on Map', 'simple-checkout-location' ) . '</a></p>';
        }
        
        echo '</div>';
    }

    // ==================== Enqueue Scripts ====================

    public function enqueue_scripts() {
        if ( ! is_checkout() ) {
            return;
        }

        wp_enqueue_script(
            'google-maps-api',
            'https://maps.googleapis.com/maps/api/js?key=AIzaSyAFjkTsmVGgWlAhU2hKQSgeiFeKsEFYKBY&libraries=places',
            [],
            null,
            false
        );

        wp_enqueue_script(
            'scl-checkout',
            SCL_PLUGIN_URL . 'assets/js/checkout.js',
            [ 'jquery', 'google-maps-api' ],
            SCL_VERSION,
            true
        );

        wp_enqueue_style(
            'scl-checkout',
            SCL_PLUGIN_URL . 'assets/css/checkout.css',
            [],
            SCL_VERSION
        );

        wp_localize_script(
            'scl-checkout',
            'sclData',
            [
                'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
                'restUrl'   => esc_url_raw( rest_url( 'scl/v1/addresses' ) ),
                'nonce'     => wp_create_nonce( 'scl_nonce' ),
                'restNonce' => wp_create_nonce( 'wp_rest' ),
                'isLoggedIn'=> is_user_logged_in(),
                'strings'   => [
                    'locationSelected' => __( 'Location selected', 'simple-checkout-location' ),
                    'selectLocation'   => __( 'Please select location', 'simple-checkout-location' ),
                    'saving'           => __( 'Saving...', 'simple-checkout-location' ),
                    'error'            => __( 'An error occurred', 'simple-checkout-location' ),
                    'confirmDelete'    => __( 'Are you sure you want to delete this address?', 'simple-checkout-location' ),
                ],
            ]
        );
    }



    /**
 * حفظ location من REST API orders
 * يتعامل مع الطلبات القادمة من POS أو أي API خارجي
 */
public function save_location_from_api_order( $order_id, $order ) {
    // ✅ نتأكد أنه طلب من API
    if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
        return;
    }
    
    error_log( '=== SCL API Order Handler (New Order) ===' );
    error_log( 'Order ID: ' . $order_id );
    
    // ✅ نحاول قراءة raw request body
    $raw_body = file_get_contents( 'php://input' );
    
    if ( empty( $raw_body ) ) {
        error_log( 'SCL: No raw body found' );
        return;
    }
    
    $request_data = json_decode( $raw_body, true );
    
    if ( ! $request_data ) {
        error_log( 'SCL: Failed to decode request body' );
        return;
    }
    
    error_log( 'SCL: Request Data: ' . print_r( $request_data, true ) );
    
    // ✅ الحالة 1: guest_data من POS
    if ( ! empty( $request_data['guest_data'] ) && is_array( $request_data['guest_data'] ) ) {
        $guest_data = $request_data['guest_data'];
        error_log( 'SCL: Guest Data Found: ' . print_r( $guest_data, true ) );
        
        // حفظ location_url
        if ( ! empty( $guest_data['location_url'] ) ) {
            $location_url = sanitize_text_field( $guest_data['location_url'] );
            $order->update_meta_data( '_billing_location_url', $location_url );
            error_log( 'SCL: Saved location_url: ' . $location_url );
        }
        
        // حفظ location_lat و lng
        if ( ! empty( $guest_data['location_lat'] ) ) {
            $order->update_meta_data( '_billing_location_lat', sanitize_text_field( $guest_data['location_lat'] ) );
        }
        
        if ( ! empty( $guest_data['location_lng'] ) ) {
            $order->update_meta_data( '_billing_location_lng', sanitize_text_field( $guest_data['location_lng'] ) );
        }
        
        // حفظ notes
        if ( ! empty( $guest_data['notes'] ) ) {
            $order->update_meta_data( '_billing_notes_customer', sanitize_textarea_field( $guest_data['notes'] ) );
        }
        
        $order->save();
        error_log( 'SCL: Order meta saved successfully' );
    }
}




}

// ==================== Initialize Plugin ====================

Simple_Checkout_Location::get_instance();

// ==================== Rewrite Rules ====================

register_activation_hook( __FILE__, 'scl_flush_rewrite_rules' );
function scl_flush_rewrite_rules() {
    add_rewrite_endpoint( 'scl-addresses', EP_ROOT | EP_PAGES );
    flush_rewrite_rules();
}
