<?php
/**
 * Admin Page for Managing Customer Addresses
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SCL_Admin_Page {

    /** @var SCL_Address_Repository */
    private $repo;

    /** @var SCL_Custom_Fields_Manager */
    private $fields_manager;

    public function __construct() {
        $this->repo = new SCL_Address_Repository();
        $this->fields_manager = new SCL_Custom_Fields_Manager();

        // Add admin menu
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );

        // Enqueue admin scripts
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );

        // Handle AJAX requests
        add_action( 'wp_ajax_scl_admin_get_addresses', [ $this, 'ajax_get_addresses' ] );
        add_action( 'wp_ajax_scl_admin_delete_address', [ $this, 'ajax_delete_address' ] );
        add_action( 'wp_ajax_scl_admin_get_address', [ $this, 'ajax_get_address' ] );
        add_action( 'wp_ajax_scl_admin_save_address', [ $this, 'ajax_save_address' ] );
        add_action( 'wp_ajax_scl_admin_set_default', [ $this, 'ajax_set_default' ] );
    }

    public function add_admin_menu() {
        add_menu_page(
            __( 'Customer Addresses', 'simple-checkout-location' ),
            __( 'Addresses', 'simple-checkout-location' ),
            'manage_woocommerce',
            'scl-addresses',
            [ $this, 'render_admin_page' ],
            'dashicons-location',
            56
        );

        add_submenu_page(
            'scl-addresses',
            __( 'Manage Addresses', 'simple-checkout-location' ),
            __( 'All Addresses', 'simple-checkout-location' ),
            'manage_woocommerce',
            'scl-addresses',
            [ $this, 'render_admin_page' ]
        );

        add_submenu_page(
            'scl-addresses',
            __( 'Delivery Zones', 'simple-checkout-location' ),
            __( 'Delivery Zones', 'simple-checkout-location' ),
            'manage_woocommerce',
            'scl-zones',
            [ $this, 'render_zones_page' ]
        );


        add_submenu_page(
            'scl-addresses',
            __( 'Custom Fields', 'simple-checkout-location' ),
            __( 'Custom Fields', 'simple-checkout-location' ),
            'manage_woocommerce',
            'scl-custom-fields',
            [ $this, 'render_fields_page' ]
        );
    }

    public function enqueue_admin_scripts( $hook ) {
        if ( strpos( $hook, 'scl-' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'scl-admin-style',
            SCL_PLUGIN_URL . 'assets/css/admin-style.css',
            [],
            SCL_VERSION
        );

        wp_enqueue_script(
            'scl-admin-script',
            SCL_PLUGIN_URL . 'assets/js/admin-script.js',
            [ 'jquery' ],
            SCL_VERSION,
            true
        );

        wp_localize_script(
            'scl-admin-script',
            'sclAdminData',
            [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'scl_admin_nonce' ),
                'strings' => [
                    'confirmDelete' => __( 'Are you sure you want to delete this address?', 'simple-checkout-location' ),
                    'error'         => __( 'An error occurred. Please try again.', 'simple-checkout-location' ),
                    'success'       => __( 'Operation completed successfully.', 'simple-checkout-location' ),
                ],
            ]
        );
    }

    public function render_admin_page() {
        ?>
        <div class="wrap scl-admin-wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Customer Addresses', 'simple-checkout-location' ); ?></h1>
            <a href="#" id="scl-add-new-btn" class="page-title-action"><?php esc_html_e( 'Add New', 'simple-checkout-location' ); ?></a>
            <hr class="wp-header-end">

            <!-- Filters -->
            <div class="scl-filters">
                <input type="text" id="scl-search-customer" placeholder="<?php esc_attr_e( 'Search by customer name, phone, or email...', 'simple-checkout-location' ); ?>" class="scl-search-input">
                <select id="scl-filter-user" class="scl-filter-select">
                    <option value=""><?php esc_html_e( 'All Customers', 'simple-checkout-location' ); ?></option>
                    <?php
                    $users = get_users( [ 'role__in' => [ 'customer', 'administrator' ] ] );
                    foreach ( $users as $user ) {
                        echo '<option value="' . esc_attr( $user->ID ) . '">' . esc_html( $user->display_name ) . ' (' . esc_html( $user->user_email ) . ')</option>';
                    }
                    ?>
                </select>
                <button type="button" id="scl-reset-filters" class="button"><?php esc_html_e( 'Reset', 'simple-checkout-location' ); ?></button>
            </div>

            <!-- Addresses Table -->
            <div id="scl-addresses-container">
                <div class="scl-loading"><?php esc_html_e( 'Loading addresses...', 'simple-checkout-location' ); ?></div>
            </div>

            <!-- Edit/Add Modal -->
            <div id="scl-modal" class="scl-modal" style="display: none;">
                <div class="scl-modal-content">
                    <div class="scl-modal-header">
                        <h2 id="scl-modal-title"><?php esc_html_e( 'Add Address', 'simple-checkout-location' ); ?></h2>
                        <button type="button" class="scl-modal-close">&times;</button>
                    </div>
                    <div class="scl-modal-body">
                        <form id="scl-address-form">
                            <input type="hidden" id="address-id" name="address_id">
                            
                            <div class="scl-form-row">
                                <label for="user-id"><?php esc_html_e( 'Customer', 'simple-checkout-location' ); ?> <span class="required">*</span></label>
                                <select id="user-id" name="user_id" required>
                                    <option value=""><?php esc_html_e( 'Select Customer', 'simple-checkout-location' ); ?></option>
                                    <?php
                                    foreach ( $users as $user ) {
                                        echo '<option value="' . esc_attr( $user->ID ) . '">' . esc_html( $user->display_name ) . ' (' . esc_html( $user->user_email ) . ')</option>';
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="scl-form-row">
                                <label for="address-name"><?php esc_html_e( 'Address Name', 'simple-checkout-location' ); ?> <span class="required">*</span></label>
                                <input type="text" id="address-name" name="address_name" required>
                            </div>

                            <div class="scl-form-row">
                                <label for="customer-name"><?php esc_html_e( 'Customer Name', 'simple-checkout-location' ); ?> <span class="required">*</span></label>
                                <input type="text" id="customer-name" name="customer_name" required>
                            </div>

                            <div class="scl-form-row scl-form-row-half">
                                <div class="scl-form-col">
                                    <label for="phone-primary"><?php esc_html_e( 'Primary Phone', 'simple-checkout-location' ); ?> <span class="required">*</span></label>
                                    <input type="text" id="phone-primary" name="phone_primary" required>
                                </div>
                                <div class="scl-form-col">
                                    <label for="phone-secondary"><?php esc_html_e( 'Secondary Phone', 'simple-checkout-location' ); ?></label>
                                    <input type="text" id="phone-secondary" name="phone_secondary">
                                </div>
                            </div>

                            <div class="scl-form-row">
                                <label for="address-details"><?php esc_html_e( 'Address Details', 'simple-checkout-location' ); ?></label>
                                <textarea id="address-details" name="address_details" rows="3"></textarea>
                            </div>

                            <div class="scl-form-row">
                                <label for="zone"><?php esc_html_e( 'Zone/Area', 'simple-checkout-location' ); ?></label>
                                <input type="text" id="zone" name="zone">
                            </div>

                            <div class="scl-form-row scl-form-row-half">
                                <div class="scl-form-col">
                                    <label for="location-url"><?php esc_html_e( 'Location URL', 'simple-checkout-location' ); ?></label>
                                    <input type="url" id="location-url" name="location_url">
                                </div>
                                <div class="scl-form-col">
                                    <label for="location-lat"><?php esc_html_e( 'Latitude', 'simple-checkout-location' ); ?></label>
                                    <input type="text" id="location-lat" name="location_lat">
                                </div>
                            </div>

                            <div class="scl-form-row">
                                <label for="location-lng"><?php esc_html_e( 'Longitude', 'simple-checkout-location' ); ?></label>
                                <input type="text" id="location-lng" name="location_lng">
                            </div>

                            <div class="scl-form-row">
                                <label for="notes-customer"><?php esc_html_e( 'Customer Notes', 'simple-checkout-location' ); ?></label>
                                <textarea id="notes-customer" name="notes_customer" rows="2"></textarea>
                            </div>

                            <div class="scl-form-row">
                                <label for="notes-internal"><?php esc_html_e( 'Internal Notes (Admin Only)', 'simple-checkout-location' ); ?></label>
                                <textarea id="notes-internal" name="notes_internal" rows="2"></textarea>
                            </div>

                            <div class="scl-form-row">
                                <label>
                                    <input type="checkbox" id="is-default-billing" name="is_default_billing" value="1">
                                    <?php esc_html_e( 'Set as default billing address', 'simple-checkout-location' ); ?>
                                </label>
                            </div>
                        </form>
                    </div>
                    <div class="scl-modal-footer">
                        <button type="button" class="button button-secondary scl-modal-close"><?php esc_html_e( 'Cancel', 'simple-checkout-location' ); ?></button>
                        <button type="button" id="scl-save-address-btn" class="button button-primary"><?php esc_html_e( 'Save Address', 'simple-checkout-location' ); ?></button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_fields_page() {
        $fields = $this->fields_manager->get_custom_fields();
        ?>
        <div class="wrap scl-admin-wrap">
            <h1><?php esc_html_e( 'Custom Address Fields', 'simple-checkout-location' ); ?></h1>
            <p><?php esc_html_e( 'Manage custom fields for address forms. Note: Core fields (Address Name, Customer Name, Phones, Location) cannot be removed.', 'simple-checkout-location' ); ?></p>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Field Name', 'simple-checkout-location' ); ?></th>
                        <th><?php esc_html_e( 'Field Key', 'simple-checkout-location' ); ?></th>
                        <th><?php esc_html_e( 'Type', 'simple-checkout-location' ); ?></th>
                        <th><?php esc_html_e( 'Required', 'simple-checkout-location' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'simple-checkout-location' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'simple-checkout-location' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $core_fields = [
                        'address_name'    => [ 'label' => __( 'Address Name', 'simple-checkout-location' ), 'type' => 'text', 'required' => true ],
                        'customer_name'   => [ 'label' => __( 'Customer Name', 'simple-checkout-location' ), 'type' => 'text', 'required' => true ],
                        'phone_primary'   => [ 'label' => __( 'Primary Phone', 'simple-checkout-location' ), 'type' => 'text', 'required' => true ],
                        'phone_secondary' => [ 'label' => __( 'Secondary Phone', 'simple-checkout-location' ), 'type' => 'text', 'required' => false ],
                        'location_url'    => [ 'label' => __( 'Location URL', 'simple-checkout-location' ), 'type' => 'url', 'required' => false ],
                        'location_lat'    => [ 'label' => __( 'Latitude', 'simple-checkout-location' ), 'type' => 'text', 'required' => false ],
                        'location_lng'    => [ 'label' => __( 'Longitude', 'simple-checkout-location' ), 'type' => 'text', 'required' => false ],
                        'address_details' => [ 'label' => __( 'Address Details', 'simple-checkout-location' ), 'type' => 'textarea', 'required' => false ],
                        'zone'            => [ 'label' => __( 'Zone', 'simple-checkout-location' ), 'type' => 'text', 'required' => false ],
                        'notes_customer'  => [ 'label' => __( 'Customer Notes', 'simple-checkout-location' ), 'type' => 'textarea', 'required' => false ],
                        'notes_internal'  => [ 'label' => __( 'Internal Notes', 'simple-checkout-location' ), 'type' => 'textarea', 'required' => false ],
                    ];

                    foreach ( $core_fields as $key => $field ) {
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html( $field['label'] ); ?></strong></td>
                            <td><code><?php echo esc_html( $key ); ?></code></td>
                            <td><?php echo esc_html( $field['type'] ); ?></td>
                            <td><?php echo $field['required'] ? '<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>' : '—'; ?></td>
                            <td><span class="scl-badge scl-badge-core"><?php esc_html_e( 'Core Field', 'simple-checkout-location' ); ?></span></td>
                            <td><?php esc_html_e( 'Cannot be removed', 'simple-checkout-location' ); ?></td>
                        </tr>
                        <?php
                    }

                    if ( ! empty( $fields ) ) {
                        foreach ( $fields as $field ) {
                            ?>
                            <tr>
                                <td><?php echo esc_html( $field['label'] ); ?></td>
                                <td><code><?php echo esc_html( $field['key'] ); ?></code></td>
                                <td><?php echo esc_html( $field['type'] ); ?></td>
                                <td><?php echo ! empty( $field['required'] ) ? '<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>' : '—'; ?></td>
                                <td><span class="scl-badge scl-badge-custom"><?php esc_html_e( 'Custom Field', 'simple-checkout-location' ); ?></span></td>
                                <td>
                                    <button type="button" class="button button-small scl-delete-field" data-key="<?php echo esc_attr( $field['key'] ); ?>">
                                        <?php esc_html_e( 'Delete', 'simple-checkout-location' ); ?>
                                    </button>
                                </td>
                            </tr>
                            <?php
                        }
                    }
                    ?>
                </tbody>
            </table>

            <hr>

            <h2><?php esc_html_e( 'Add New Custom Field', 'simple-checkout-location' ); ?></h2>
            <form id="scl-add-field-form" method="post">
                <?php wp_nonce_field( 'scl_add_field', 'scl_field_nonce' ); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="field-label"><?php esc_html_e( 'Field Label', 'simple-checkout-location' ); ?> <span class="required">*</span></label></th>
                        <td><input type="text" id="field-label" name="field_label" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="field-key"><?php esc_html_e( 'Field Key', 'simple-checkout-location' ); ?> <span class="required">*</span></label></th>
                        <td>
                            <input type="text" id="field-key" name="field_key" class="regular-text" required>
                            <p class="description"><?php esc_html_e( 'Unique identifier (lowercase, no spaces, use underscores)', 'simple-checkout-location' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="field-type"><?php esc_html_e( 'Field Type', 'simple-checkout-location' ); ?></label></th>
                        <td>
                            <select id="field-type" name="field_type">
                                <option value="text"><?php esc_html_e( 'Text', 'simple-checkout-location' ); ?></option>
                                <option value="textarea"><?php esc_html_e( 'Textarea', 'simple-checkout-location' ); ?></option>
                                <option value="email"><?php esc_html_e( 'Email', 'simple-checkout-location' ); ?></option>
                                <option value="number"><?php esc_html_e( 'Number', 'simple-checkout-location' ); ?></option>
                                <option value="url"><?php esc_html_e( 'URL', 'simple-checkout-location' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="field-required"><?php esc_html_e( 'Required', 'simple-checkout-location' ); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="field-required" name="field_required" value="1">
                                <?php esc_html_e( 'Make this field required', 'simple-checkout-location' ); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Add Custom Field', 'simple-checkout-location' ); ?></button>
                </p>
            </form>

            <div class="scl-notice" style="margin-top: 20px; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107;">
                <p><strong><?php esc_html_e( 'Note:', 'simple-checkout-location' ); ?></strong> <?php esc_html_e( 'Custom fields feature is currently in development. Adding fields here will be functional in the next update.', 'simple-checkout-location' ); ?></p>
            </div>
        </div>
        <?php
    }

    // AJAX Handlers

    public function ajax_get_addresses() {
        check_ajax_referer( 'scl_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Insufficient permissions', 'simple-checkout-location' ) ] );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'scl_addresses';

        $user_id = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;
        $search  = isset( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : '';

        $where = [ 'status = 1' ];
        $params = [];

        if ( $user_id > 0 ) {
            $where[] = 'user_id = %d';
            $params[] = $user_id;
        }

        if ( ! empty( $search ) ) {
            $where[] = '(customer_name LIKE %s OR phone_primary LIKE %s OR address_name LIKE %s)';
            $params[] = '%' . $wpdb->esc_like( $search ) . '%';
            $params[] = '%' . $wpdb->esc_like( $search ) . '%';
            $params[] = '%' . $wpdb->esc_like( $search ) . '%';
        }

        $where_clause = implode( ' AND ', $where );

        if ( ! empty( $params ) ) {
            $sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY created_at DESC", $params );
        } else {
            $sql = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY created_at DESC";
        }

        $addresses = $wpdb->get_results( $sql, ARRAY_A );

        ob_start();
        if ( ! empty( $addresses ) ) {
            ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Customer', 'simple-checkout-location' ); ?></th>
                        <th><?php esc_html_e( 'Address Name', 'simple-checkout-location' ); ?></th>
                        <th><?php esc_html_e( 'Name', 'simple-checkout-location' ); ?></th>
                        <th><?php esc_html_e( 'Phone', 'simple-checkout-location' ); ?></th>
                        <th><?php esc_html_e( 'Zone', 'simple-checkout-location' ); ?></th>
                        <th><?php esc_html_e( 'Default', 'simple-checkout-location' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'simple-checkout-location' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $addresses as $address ) : 
                        $user = get_user_by( 'id', $address['user_id'] );
                        $user_name = $user ? $user->display_name : __( 'Unknown', 'simple-checkout-location' );
                    ?>
                    <tr>
                        <td><?php echo esc_html( $user_name ); ?></td>
                        <td><strong><?php echo esc_html( $address['address_name'] ); ?></strong></td>
                        <td><?php echo esc_html( $address['customer_name'] ); ?></td>
                        <td><?php echo esc_html( $address['phone_primary'] ); ?></td>
                        <td><?php echo esc_html( $address['zone'] ); ?></td>
                        <td>
                            <?php if ( (int) $address['is_default_billing'] === 1 ) : ?>
                                <span class="dashicons dashicons-star-filled" style="color: #ffc107;" title="<?php esc_attr_e( 'Default Address', 'simple-checkout-location' ); ?>"></span>
                            <?php else : ?>
                                <button type="button" class="button button-small scl-set-default" data-id="<?php echo esc_attr( $address['id'] ); ?>" data-user="<?php echo esc_attr( $address['user_id'] ); ?>">
                                    <?php esc_html_e( 'Set Default', 'simple-checkout-location' ); ?>
                                </button>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="button button-small scl-edit-address" data-id="<?php echo esc_attr( $address['id'] ); ?>">
                                <?php esc_html_e( 'Edit', 'simple-checkout-location' ); ?>
                            </button>
                            <button type="button" class="button button-small scl-delete-address" data-id="<?php echo esc_attr( $address['id'] ); ?>">
                                <?php esc_html_e( 'Delete', 'simple-checkout-location' ); ?>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php
        } else {
            echo '<div class="scl-no-results">' . esc_html__( 'No addresses found.', 'simple-checkout-location' ) . '</div>';
        }
        $html = ob_get_clean();

        wp_send_json_success( [ 'html' => $html ] );
    }

    public function ajax_get_address() {
        check_ajax_referer( 'scl_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error();
        }

        $id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
        $address = $this->repo->get_address( $id );

        if ( ! $address ) {
            wp_send_json_error();
        }

        wp_send_json_success( $address );
    }

    public function ajax_save_address() {
        check_ajax_referer( 'scl_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Insufficient permissions', 'simple-checkout-location' ) ] );
        }

        $data = [
            'user_id'              => isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0,
            'address_name'         => isset( $_POST['address_name'] ) ? sanitize_text_field( $_POST['address_name'] ) : '',
            'customer_name'        => isset( $_POST['customer_name'] ) ? sanitize_text_field( $_POST['customer_name'] ) : '',
            'phone_primary'        => isset( $_POST['phone_primary'] ) ? sanitize_text_field( $_POST['phone_primary'] ) : '',
            'phone_secondary'      => isset( $_POST['phone_secondary'] ) ? sanitize_text_field( $_POST['phone_secondary'] ) : '',
            'location_url'         => isset( $_POST['location_url'] ) ? esc_url_raw( $_POST['location_url'] ) : '',
            'location_lat'         => isset( $_POST['location_lat'] ) ? sanitize_text_field( $_POST['location_lat'] ) : '',
            'location_lng'         => isset( $_POST['location_lng'] ) ? sanitize_text_field( $_POST['location_lng'] ) : '',
            'address_details'      => isset( $_POST['address_details'] ) ? sanitize_text_field( $_POST['address_details'] ) : '',
            'zone'                 => isset( $_POST['zone'] ) ? sanitize_text_field( $_POST['zone'] ) : '',
            'notes_customer'       => isset( $_POST['notes_customer'] ) ? sanitize_textarea_field( $_POST['notes_customer'] ) : '',
            'notes_internal'       => isset( $_POST['notes_internal'] ) ? sanitize_textarea_field( $_POST['notes_internal'] ) : '',
            'is_default_billing'   => isset( $_POST['is_default_billing'] ) ? 1 : 0,
        ];

        $address_id = isset( $_POST['address_id'] ) ? (int) $_POST['address_id'] : 0;

        if ( $address_id > 0 ) {
            // Update
            $this->repo->update_address( $address_id, $data['user_id'], $data );
        } else {
            // Create
            $address_id = $this->repo->create_address( $data );
        }

        if ( $data['is_default_billing'] ) {
            $this->repo->set_default( $address_id, $data['user_id'], 'billing' );
        }

        wp_send_json_success( [ 'id' => $address_id ] );
    }

    public function ajax_delete_address() {
        check_ajax_referer( 'scl_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error();
        }

        $id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
        $address = $this->repo->get_address( $id );

        if ( ! $address ) {
            wp_send_json_error();
        }

        $this->repo->soft_delete_address( $id, $address['user_id'] );

        wp_send_json_success();
    }

    public function ajax_set_default() {
        check_ajax_referer( 'scl_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error();
        }

        $id      = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
        $user_id = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;

        $this->repo->set_default( $id, $user_id, 'billing' );

        wp_send_json_success();
    }

   
 public function render_zones_page() {
    require_once SCL_PLUGIN_DIR . 'includes/class-zones-repository.php';
    $zones_repo = new SCL_Zones_Repository();
    $zones = $zones_repo->get_all_zones( false );
    
    // Handle form submission
    if ( isset( $_POST['scl_save_zone'] ) && check_admin_referer( 'scl_save_zone', 'scl_zone_nonce' ) ) {
    $zone_id = isset( $_POST['zone_id'] ) ? (int) $_POST['zone_id'] : 0;
    
    // ✅ معالجة delivery_times
    $delivery_times = [];
    if ( isset( $_POST['delivery_times'] ) && is_array( $_POST['delivery_times'] ) ) {
        foreach ( $_POST['delivery_times'] as $time ) {
            $time = sanitize_text_field( trim( $time ) );
            if ( ! empty( $time ) ) {
                $delivery_times[] = $time;
            }
        }
    }
    
    // ✅ معالجة closed_days
    $closed_days = [];
    if ( isset( $_POST['closed_days'] ) && is_array( $_POST['closed_days'] ) ) {
        $closed_days = array_map( 'intval', $_POST['closed_days'] );
    } else {
        $closed_days = [5]; // Friday default
    }
    
    $data = [
        'zone_name' => sanitize_text_field( $_POST['zone_name'] ),
        'zone_name_ar' => isset( $_POST['zone_name_ar'] ) ? sanitize_text_field( $_POST['zone_name_ar'] ) : '',
        'shipping_cost' => floatval( $_POST['shipping_cost'] ),
        'is_active' => isset( $_POST['is_active'] ) ? 1 : 0,
        'display_order' => isset( $_POST['display_order'] ) ? (int) $_POST['display_order'] : 0,
        'delivery_times' => $delivery_times,
        'delivery_days_ahead' => isset( $_POST['delivery_days_ahead'] ) ? (int) $_POST['delivery_days_ahead'] : 3,
        'closed_days' => $closed_days,
    ];
    
    // ✅ Debug logging
        // ✅ Debug logging
    error_log( 'SCL Zone Save Data: ' . print_r( $data, true ) );
    
    // ✅ حفظ أو تحديث المنطقة
    if ( $zone_id > 0 ) {
        $zones_repo->update_zone( $zone_id, $data );
        echo '<div class="notice notice-success"><p>' . __( 'Zone updated successfully!', 'simple-checkout-location' ) . '</p></div>';
    } else {
        $zones_repo->create_zone( $data );
        echo '<div class="notice notice-success"><p>' . __( 'Zone created successfully!', 'simple-checkout-location' ) . '</p></div>';
    }
    
    $zones = $zones_repo->get_all_zones( false );
}  // ✅ إغلاق if statement
    
// Handle delete
if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['zone_id'] ) ) {

        check_admin_referer( 'scl_delete_zone_' . $_GET['zone_id'] );
        $zones_repo->delete_zone( (int) $_GET['zone_id'] );
        echo '<div class="notice notice-success"><p>' . __( 'Zone deleted successfully!', 'simple-checkout-location' ) . '</p></div>';
        $zones = $zones_repo->get_all_zones( false );
    }
    
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Delivery Zones & Shipping Costs', 'simple-checkout-location' ); ?></h1>
        
        <div style="background: #fff3cd; padding: 15px; margin: 20px 0; border-left: 4px solid #ffc107; border-radius: 4px;">
            <p style="margin: 0;">
                <strong><?php esc_html_e( 'Note:', 'simple-checkout-location' ); ?></strong>
                <?php esc_html_e( 'Configure delivery schedules for each zone. Customers will only see available time slots and dates based on your settings.', 'simple-checkout-location' ); ?>
            </p>
        </div>
        
        <div style="background: #fff; padding: 20px; margin: 20px 0; border-left: 4px solid #0071dc;">
            <h3 style="margin-top: 0;"><?php esc_html_e( 'Add New Zone', 'simple-checkout-location' ); ?></h3>
            <form method="post">
                <?php wp_nonce_field( 'scl_save_zone', 'scl_zone_nonce' ); ?>
                <input type="hidden" name="zone_id" id="edit_zone_id" value="">
                
                <table class="form-table">
                    <tr>
                        <th><label for="zone_name"><?php esc_html_e( 'Zone Name (English)', 'simple-checkout-location' ); ?> <span class="required">*</span></label></th>
                        <td><input type="text" id="zone_name" name="zone_name" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="zone_name_ar"><?php esc_html_e( 'Zone Name (Arabic)', 'simple-checkout-location' ); ?></label></th>
                        <td><input type="text" id="zone_name_ar" name="zone_name_ar" class="regular-text" dir="rtl"></td>
                    </tr>
                    <tr>
                        <th><label for="shipping_cost"><?php esc_html_e( 'Shipping Cost', 'simple-checkout-location' ); ?> <span class="required">*</span></label></th>
                        <td>
                            <input type="number" id="shipping_cost" name="shipping_cost" step="0.01" min="0" value="0" class="regular-text" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label><?php esc_html_e( 'Delivery Time Slots', 'simple-checkout-location' ); ?></label></th>
                        <td>
                            <div id="delivery-times-container">
                                <div class="delivery-time-slot" style="margin-bottom: 10px;">
                                    <input type="text" name="delivery_times[]" placeholder="e.g., 10:00 AM - 12:00 PM" style="width: 250px;">
                                    <button type="button" class="button remove-time-slot">Remove</button>
                                </div>
                            </div>
                            <button type="button" id="add-time-slot" class="button">+ Add Time Slot</button>
                            <p class="description"><?php esc_html_e( 'Add available delivery time slots for this zone', 'simple-checkout-location' ); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="delivery_days_ahead"><?php esc_html_e( 'Delivery Days Ahead', 'simple-checkout-location' ); ?></label></th>
                        <td>
                            <input type="number" id="delivery_days_ahead" name="delivery_days_ahead" min="1" max="30" value="3">
                            <p class="description"><?php esc_html_e( 'Number of days in advance customers can book delivery', 'simple-checkout-location' ); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label><?php esc_html_e( 'Closed Days', 'simple-checkout-location' ); ?></label></th>
                        <td>
                            <label><input type="checkbox" name="closed_days[]" value="1"> <?php esc_html_e( 'Monday', 'simple-checkout-location' ); ?></label><br>
                            <label><input type="checkbox" name="closed_days[]" value="2"> <?php esc_html_e( 'Tuesday', 'simple-checkout-location' ); ?></label><br>
                            <label><input type="checkbox" name="closed_days[]" value="3"> <?php esc_html_e( 'Wednesday', 'simple-checkout-location' ); ?></label><br>
                            <label><input type="checkbox" name="closed_days[]" value="4"> <?php esc_html_e( 'Thursday', 'simple-checkout-location' ); ?></label><br>
                            <label><input type="checkbox" name="closed_days[]" value="5" checked> <?php esc_html_e( 'Friday', 'simple-checkout-location' ); ?></label><br>
                            <label><input type="checkbox" name="closed_days[]" value="6"> <?php esc_html_e( 'Saturday', 'simple-checkout-location' ); ?></label><br>
                            <label><input type="checkbox" name="closed_days[]" value="7"> <?php esc_html_e( 'Sunday', 'simple-checkout-location' ); ?></label>
                            <p class="description"><?php esc_html_e( 'Days when delivery is not available', 'simple-checkout-location' ); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="display_order"><?php esc_html_e( 'Display Order', 'simple-checkout-location' ); ?></label></th>
                        <td><input type="number" id="display_order" name="display_order" value="0" min="0"></td>
                    </tr>
                    <tr>
                        <th><label for="is_active"><?php esc_html_e( 'Active', 'simple-checkout-location' ); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="is_active" name="is_active" value="1" checked>
                                <?php esc_html_e( 'Enable this zone', 'simple-checkout-location' ); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" name="scl_save_zone" class="button button-primary" id="save_zone_btn">
                        <?php esc_html_e( 'Add Zone', 'simple-checkout-location' ); ?>
                    </button>
                    <button type="button" class="button" id="cancel_edit_zone" style="display:none;">
                        <?php esc_html_e( 'Cancel', 'simple-checkout-location' ); ?>
                    </button>
                </p>
            </form>
        </div>

        <h2><?php esc_html_e( 'Existing Zones', 'simple-checkout-location' ); ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Order', 'simple-checkout-location' ); ?></th>
                    <th><?php esc_html_e( 'Zone Name', 'simple-checkout-location' ); ?></th>
                    <th><?php esc_html_e( 'Arabic Name', 'simple-checkout-location' ); ?></th>
                    <th><?php esc_html_e( 'Shipping Cost', 'simple-checkout-location' ); ?></th>
                    <th><?php esc_html_e( 'Time Slots', 'simple-checkout-location' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'simple-checkout-location' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'simple-checkout-location' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $zones ) ) : ?>
                    <?php foreach ( $zones as $zone ) : ?>
                        <tr>
                            <td><?php echo esc_html( $zone['display_order'] ); ?></td>
                            <td><strong><?php echo esc_html( $zone['zone_name'] ); ?></strong></td>
                            <td><?php echo esc_html( $zone['zone_name_ar'] ); ?></td>
                            <td><?php echo wc_price( $zone['shipping_cost'] ); ?></td>
                            <td>
                                <?php 
                                if ( ! empty( $zone['delivery_times'] ) ) {
                                    $times = json_decode( $zone['delivery_times'], true );
                                    if ( is_array( $times ) && count( $times ) > 0 ) {
                                        echo '<span style="color: #10b981;">● ' . count( $times ) . ' slots</span>';
                                    } else {
                                        echo '<span style="color: #d63638;">No slots</span>';
                                    }
                                } else {
                                    echo '<span style="color: #d63638;">No slots</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ( (int) $zone['is_active'] === 1 ) : ?>
                                    <span style="color: #10b981;">● <?php esc_html_e( 'Active', 'simple-checkout-location' ); ?></span>
                                <?php else : ?>
                                    <span style="color: #d63638;">● <?php esc_html_e( 'Inactive', 'simple-checkout-location' ); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="button button-small scl-edit-zone" 
                                    data-id="<?php echo esc_attr( $zone['id'] ); ?>"
                                    data-name="<?php echo esc_attr( $zone['zone_name'] ); ?>"
                                    data-name-ar="<?php echo esc_attr( $zone['zone_name_ar'] ); ?>"
                                    data-cost="<?php echo esc_attr( $zone['shipping_cost'] ); ?>"
                                    data-order="<?php echo esc_attr( $zone['display_order'] ); ?>"
                                    data-active="<?php echo esc_attr( $zone['is_active'] ); ?>"
                                    data-delivery-times='<?php echo ! empty( $zone['delivery_times'] ) ? esc_attr( $zone['delivery_times'] ) : '[]'; ?>'
                                    data-days-ahead="<?php echo esc_attr( ! empty( $zone['delivery_days_ahead'] ) ? $zone['delivery_days_ahead'] : 3 ); ?>"
                                    data-closed-days='<?php echo ! empty( $zone['closed_days'] ) ? esc_attr( $zone['closed_days'] ) : '[5]'; ?>'>
                                    <?php esc_html_e( 'Edit', 'simple-checkout-location' ); ?>
                                </button>
                                <a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=scl-zones&action=delete&zone_id=' . $zone['id'] ), 'scl_delete_zone_' . $zone['id'] ); ?>" 
                                   class="button button-small" 
                                   onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this zone?', 'simple-checkout-location' ); ?>');">
                                    <?php esc_html_e( 'Delete', 'simple-checkout-location' ); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="7"><?php esc_html_e( 'No zones found. Add your first zone above.', 'simple-checkout-location' ); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <script>
        jQuery(document).ready(function($) {
            // Add time slot
            $('#add-time-slot').on('click', function() {
                var html = '<div class="delivery-time-slot" style="margin-bottom: 10px;">' +
                    '<input type="text" name="delivery_times[]" placeholder="e.g., 2:00 PM - 4:00 PM" style="width: 250px;">' +
                    '<button type="button" class="button remove-time-slot">Remove</button>' +
                    '</div>';
                $('#delivery-times-container').append(html);
            });
            
            // Remove time slot
            $(document).on('click', '.remove-time-slot', function() {
                if ($('.delivery-time-slot').length > 1) {
                    $(this).closest('.delivery-time-slot').remove();
                }
            });
            
            // Edit zone
            $('.scl-edit-zone').on('click', function() {
                var $btn = $(this);
                $('#edit_zone_id').val($btn.data('id'));
                $('#zone_name').val($btn.data('name'));
                $('#zone_name_ar').val($btn.data('name-ar'));
                $('#shipping_cost').val($btn.data('cost'));
                $('#delivery_days_ahead').val($btn.data('days-ahead') || 3);
                $('#display_order').val($btn.data('order'));
                $('#is_active').prop('checked', $btn.data('active') == 1);
                
                // Load delivery times
                try {
                    var deliveryTimes = $btn.data('delivery-times');
                    if (typeof deliveryTimes === 'string') {
                        deliveryTimes = JSON.parse(deliveryTimes);
                    }
                    
                    $('#delivery-times-container').html('');
                    if (deliveryTimes && Array.isArray(deliveryTimes) && deliveryTimes.length > 0) {
                        deliveryTimes.forEach(function(time) {
                            var html = '<div class="delivery-time-slot" style="margin-bottom: 10px;">' +
                                '<input type="text" name="delivery_times[]" value="' + time + '" style="width: 250px;">' +
                                '<button type="button" class="button remove-time-slot">Remove</button>' +
                                '</div>';
                            $('#delivery-times-container').append(html);
                        });
                    } else {
                        $('#delivery-times-container').html('<div class="delivery-time-slot" style="margin-bottom: 10px;">' +
                            '<input type="text" name="delivery_times[]" placeholder="e.g., 10:00 AM - 12:00 PM" style="width: 250px;">' +
                            '<button type="button" class="button remove-time-slot">Remove</button>' +
                            '</div>');
                    }
                } catch(e) {
                    console.error('Error parsing delivery times:', e);
                }
                
                // Load closed days
                try {
                    var closedDays = $btn.data('closed-days');
                    if (typeof closedDays === 'string') {
                        closedDays = JSON.parse(closedDays);
                    }
                    
                    $('input[name="closed_days[]"]').prop('checked', false);
                    if (closedDays && Array.isArray(closedDays) && closedDays.length > 0) {
                        closedDays.forEach(function(day) {
                            $('input[name="closed_days[]"][value="' + day + '"]').prop('checked', true);
                        });
                    } else {
                        $('input[name="closed_days[]"][value="5"]').prop('checked', true);
                    }
                } catch(e) {
                    console.error('Error parsing closed days:', e);
                }
                
                $('#save_zone_btn').text('<?php esc_html_e( 'Update Zone', 'simple-checkout-location' ); ?>');
                $('#cancel_edit_zone').show();
                
                $('html, body').animate({
                    scrollTop: $('h3').first().offset().top - 100
                }, 500);
            });
            
            $('#cancel_edit_zone').on('click', function() {
                location.reload();
            });
        });
        </script>
    </div>
    <?php
}




/**
 * ✅ تحديث أسعار الشحن للطلبات المرتبطة عند تغيير المنطقة
 */
private function update_orders_shipping_on_zone_change( $zone_name, $new_shipping_cost ) {
    // جلب جميع الطلبات غير المكتملة التي تحتوي على هذه المنطقة
    $orders = wc_get_orders( [
        'limit' => -1,
        'status' => [ 'pending', 'processing', 'on-hold' ],
        'return' => 'ids',
    ] );
    
    if ( empty( $orders ) ) {
        return;
    }
    
    $updated_count = 0;
    
    foreach ( $orders as $order_id ) {
        $order = wc_get_order( $order_id );
        
        if ( ! $order ) {
            continue;
        }
        
        $billing = $order->get_address( 'billing' );
        
        // التحقق من أن المنطقة تطابق
        if ( isset( $billing['city'] ) && $billing['city'] === $zone_name ) {
            // حذف رسوم الشحن القديمة
            foreach ( $order->get_items( 'shipping' ) as $item_id => $shipping_item ) {
                $order->remove_item( $item_id );
            }
            
            // إضافة رسوم الشحن الجديدة
            $shipping = new WC_Order_Item_Shipping();
            $shipping->set_method_title( __( 'Zone Shipping', 'simple-checkout-location' ) );
            $shipping->set_method_id( 'scl_zone_shipping' );
            $shipping->set_total( $new_shipping_cost );
            
            $order->add_item( $shipping );
            $order->calculate_totals();
            
            $order->add_order_note(
                sprintf(
                    __( 'Shipping cost updated automatically to %s based on zone change', 'simple-checkout-location' ),
                    wc_price( $new_shipping_cost )
                )
            );
            
            $order->save();
            $updated_count++;
        }
    }
    
    if ( $updated_count > 0 ) {
        error_log( sprintf( 'SCL: Updated shipping cost for %d orders in zone %s', $updated_count, $zone_name ) );
    }
}



}
