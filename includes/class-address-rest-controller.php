<?php
/**
 * REST API Controller for Address Management
 * Supports both customer and admin operations
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SCL_Address_REST_Controller extends WP_REST_Controller {

    /** @var SCL_Address_Repository */
    private $repo;
    
    /** @var SCL_Zones_Repository */
    private $zones_repo;

    public function __construct() {
        $this->namespace = 'scl/v1';
        $this->rest_base = 'addresses';
        $this->repo      = new SCL_Address_Repository();
        
        require_once SCL_PLUGIN_DIR . 'includes/class-zones-repository.php';
        $this->zones_repo = new SCL_Zones_Repository();
        
        // ✅ إضافة حقول العنوان في WooCommerce REST API
        add_filter( 'woocommerce_rest_prepare_shop_order_object', [ $this, 'rest_add_address_fields' ], 10, 3 );
    }

    public function register_routes() {
        // ==================== Address Routes ====================
        
        // Get all addresses (filtered by user or all for admin)
        register_rest_route( $this->namespace, '/' . $this->rest_base, [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_items' ],
                'permission_callback' => [ $this, 'get_items_permissions_check' ],
                'args'                => [
                    'user_id' => [
                        'description'       => __( 'User ID to filter addresses', 'simple-checkout-location' ),
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                        'validate_callback' => 'rest_validate_request_arg',
                    ],
                ],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'create_item' ],
                'permission_callback' => [ $this, 'create_item_permissions_check' ],
                'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
            ],
        ] );

        // Get, update, or delete a single address
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_item' ],
                'permission_callback' => [ $this, 'get_item_permissions_check' ],
                'args'                => [
                    'id' => [
                        'description' => __( 'Unique identifier for the address.', 'simple-checkout-location' ),
                        'type'        => 'integer',
                    ],
                ],
            ],
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [ $this, 'update_item' ],
                'permission_callback' => [ $this, 'update_item_permissions_check' ],
                'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [ $this, 'delete_item' ],
                'permission_callback' => [ $this, 'delete_item_permissions_check' ],
            ],
        ] );

        // Set address as default
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/set-default', [
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [ $this, 'set_default' ],
                'permission_callback' => [ $this, 'update_item_permissions_check' ],
                'args'                => [
                    'type' => [
                        'description' => __( 'Address type: billing or shipping', 'simple-checkout-location' ),
                        'type'        => 'string',
                        'default'     => 'billing',
                        'enum'        => [ 'billing', 'shipping' ],
                    ],
                ],
            ],
        ] );

        // Admin: Get addresses by user
        register_rest_route( $this->namespace, '/users/(?P<user_id>[\d]+)/addresses', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_user_addresses' ],
                'permission_callback' => [ $this, 'admin_permissions_check' ],
                'args'                => [
                    'user_id' => [
                        'description' => __( 'User ID', 'simple-checkout-location' ),
                        'type'        => 'integer',
                    ],
                ],
            ],
        ] );
        
        // ==================== Zones Routes ====================
        
        // Get all zones
        register_rest_route( $this->namespace, '/zones', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_zones' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'active_only' => [
                        'description' => __( 'Return only active zones', 'simple-checkout-location' ),
                        'type'        => 'boolean',
                        'default'     => true,
                    ],
                ],
            ],
        ] );
        
        // Get single zone
        register_rest_route( $this->namespace, '/zones/(?P<id>[\d]+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_zone' ],
                'permission_callback' => '__return_true',
            ],
        ] );
        
        // Get zone by name
        register_rest_route( $this->namespace, '/zones/by-name/(?P<name>[a-zA-Z0-9-_]+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_zone_by_name' ],
                'permission_callback' => '__return_true',
            ],
        ] );
        
        // Get delivery schedule for zone
        register_rest_route( $this->namespace, '/zones/(?P<zone_name>[a-zA-Z0-9-_]+)/schedule', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_delivery_schedule' ],
                'permission_callback' => '__return_true',
            ],
        ] );
        
        // ==================== Order Routes ====================
        
        // Create order with address
        register_rest_route( $this->namespace, '/orders', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'create_order_with_address' ],
                'permission_callback' => [ $this, 'create_item_permissions_check' ],
                'args'                => $this->get_order_endpoint_args(),
            ],
        ] );
    }

    // ==================== Permission Callbacks ====================

    public function get_items_permissions_check( $request ) {
        $requested_user_id = $request->get_param( 'user_id' );
        
        if ( $requested_user_id && (int) $requested_user_id !== get_current_user_id() ) {
            return current_user_can( 'manage_woocommerce' );
        }

        return is_user_logged_in();
    }

    public function get_item_permissions_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return false;
        }

        if ( current_user_can( 'manage_woocommerce' ) ) {
            return true;
        }

        $id = (int) $request['id'];
        $address = $this->repo->get_address( $id );

        if ( ! $address ) {
            return new WP_Error(
                'rest_address_not_found',
                __( 'Address not found.', 'simple-checkout-location' ),
                [ 'status' => 404 ]
            );
        }

        return (int) $address['user_id'] === get_current_user_id();
    }

    public function create_item_permissions_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return false;
        }

        $user_id = $request->get_param( 'user_id' );
        
        if ( $user_id && (int) $user_id !== get_current_user_id() ) {
            return current_user_can( 'manage_woocommerce' );
        }

        return true;
    }

    public function update_item_permissions_check( $request ) {
        return $this->get_item_permissions_check( $request );
    }

    public function delete_item_permissions_check( $request ) {
        return $this->get_item_permissions_check( $request );
    }

    public function admin_permissions_check( $request ) {
        return current_user_can( 'manage_woocommerce' );
    }

    // ==================== Address API Methods ====================

    public function get_items( $request ) {
        $user_id = $request->get_param( 'user_id' );

        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        $addresses = $this->repo->get_addresses_by_user( $user_id );

        return rest_ensure_response( [
            'success' => true,
            'data'    => $addresses,
            'count'   => count( $addresses ),
        ] );
    }

    public function get_item( $request ) {
        $id = (int) $request['id'];
        $address = $this->repo->get_address( $id );

        if ( ! $address ) {
            return new WP_Error(
                'rest_address_not_found',
                __( 'Address not found.', 'simple-checkout-location' ),
                [ 'status' => 404 ]
            );
        }

        return rest_ensure_response( [
            'success' => true,
            'data'    => $address,
        ] );
    }

    public function create_item( $request ) {
        $validation = $this->validate_address_data( $request );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }

        $user_id = $request->get_param( 'user_id' );
        
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        if ( (int) $user_id !== get_current_user_id() && ! current_user_can( 'manage_woocommerce' ) ) {
            return new WP_Error(
                'rest_forbidden',
                __( 'You do not have permission to create addresses for other users.', 'simple-checkout-location' ),
                [ 'status' => 403 ]
            );
        }

        $data = $this->prepare_address_data_from_request( $request, $user_id );
        
        error_log( 'SCL Create Address Data: ' . print_r( $data, true ) );
        
        try {
            $id = $this->repo->create_address( $data );

            if ( ! $id || $id === false ) {
                error_log( 'SCL Address Creation Failed - ID is false' );
                return new WP_Error(
                    'rest_address_create_failed',
                    __( 'Failed to create address. Please check all required fields.', 'simple-checkout-location' ),
                    [ 'status' => 500 ]
                );
            }

            if ( ! empty( $data['is_default_billing'] ) ) {
                $this->repo->set_default( $id, $user_id, 'billing' );
            }

            $address = $this->repo->get_address( $id );

            if ( ! $address ) {
                error_log( 'SCL Address Created but not found - ID: ' . $id );
                return new WP_Error(
                    'rest_address_not_found_after_create',
                    __( 'Address was created but could not be retrieved.', 'simple-checkout-location' ),
                    [ 'status' => 500 ]
                );
            }

            return rest_ensure_response( [
                'success' => true,
                'id'      => (int) $id,
                'message' => __( 'Address created successfully.', 'simple-checkout-location' ),
                'data'    => $address,
            ] );

        } catch ( Exception $e ) {
            error_log( 'SCL Address Creation Exception: ' . $e->getMessage() );
            return new WP_Error(
                'rest_address_create_failed',
                $e->getMessage(),
                [ 'status' => 500 ]
            );
        }
    }

    public function update_item( $request ) {
        $id = (int) $request['id'];
        $address = $this->repo->get_address( $id );

        if ( ! $address ) {
            return new WP_Error(
                'rest_address_not_found',
                __( 'Address not found.', 'simple-checkout-location' ),
                [ 'status' => 404 ]
            );
        }

        $validation = $this->validate_address_data( $request, $id );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }

        $user_id = (int) $address['user_id'];
        $data = $this->prepare_address_data_from_request( $request, $user_id );

        try {
            $this->repo->update_address( $id, $user_id, $data );

            if ( ! empty( $data['is_default_billing'] ) ) {
                $this->repo->set_default( $id, $user_id, 'billing' );
            }

            $updated_address = $this->repo->get_address( $id );

            return rest_ensure_response( [
                'success' => true,
                'id'      => $id,
                'message' => __( 'Address updated successfully.', 'simple-checkout-location' ),
                'data'    => $updated_address,
            ] );

        } catch ( Exception $e ) {
            return new WP_Error(
                'rest_address_update_failed',
                $e->getMessage(),
                [ 'status' => 500 ]
            );
        }
    }

    public function delete_item( $request ) {
        $id = (int) $request['id'];
        $address = $this->repo->get_address( $id );

        if ( ! $address ) {
            return new WP_Error(
                'rest_address_not_found',
                __( 'Address not found.', 'simple-checkout-location' ),
                [ 'status' => 404 ]
            );
        }

        $user_id = (int) $address['user_id'];

        try {
            $this->repo->soft_delete_address( $id, $user_id );

            return rest_ensure_response( [
                'success' => true,
                'deleted' => true,
                'message' => __( 'Address deleted successfully.', 'simple-checkout-location' ),
            ] );

        } catch ( Exception $e ) {
            return new WP_Error(
                'rest_address_delete_failed',
                $e->getMessage(),
                [ 'status' => 500 ]
            );
        }
    }

    public function set_default( $request ) {
        $id = (int) $request['id'];
        $type = $request->get_param( 'type' ) ?: 'billing';
        
        $address = $this->repo->get_address( $id );

        if ( ! $address ) {
            return new WP_Error(
                'rest_address_not_found',
                __( 'Address not found.', 'simple-checkout-location' ),
                [ 'status' => 404 ]
            );
        }

        $user_id = (int) $address['user_id'];

        try {
            $this->repo->set_default( $id, $user_id, $type );

            return rest_ensure_response( [
                'success' => true,
                'message' => sprintf(
                    __( 'Address set as default %s address.', 'simple-checkout-location' ),
                    $type
                ),
            ] );

        } catch ( Exception $e ) {
            return new WP_Error(
                'rest_address_set_default_failed',
                $e->getMessage(),
                [ 'status' => 500 ]
            );
        }
    }

    public function get_user_addresses( $request ) {
        $user_id = (int) $request['user_id'];

        if ( ! get_userdata( $user_id ) ) {
            return new WP_Error(
                'rest_user_not_found',
                __( 'User not found.', 'simple-checkout-location' ),
                [ 'status' => 404 ]
            );
        }

        $addresses = $this->repo->get_addresses_by_user( $user_id );

        return rest_ensure_response( [
            'success' => true,
            'user_id' => $user_id,
            'data'    => $addresses,
            'count'   => count( $addresses ),
        ] );
    }

    // ==================== Zones API Methods ====================

    public function get_zones( $request ) {
        $active_only = $request->get_param( 'active_only' ) !== false;
        
        $zones = $this->zones_repo->get_all_zones( $active_only );

        return rest_ensure_response( [
            'success' => true,
            'data'    => $zones,
            'count'   => count( $zones ),
        ] );
    }

    public function get_zone( $request ) {
        $id = (int) $request['id'];
        $zone = $this->zones_repo->get_zone( $id );

        if ( ! $zone ) {
            return new WP_Error(
                'rest_zone_not_found',
                __( 'Zone not found.', 'simple-checkout-location' ),
                [ 'status' => 404 ]
            );
        }

        return rest_ensure_response( [
            'success' => true,
            'data'    => $zone,
        ] );
    }

    public function get_zone_by_name( $request ) {
        $name = sanitize_text_field( $request['name'] );
        $zone = $this->zones_repo->get_zone_by_name( $name );

        if ( ! $zone ) {
            return new WP_Error(
                'rest_zone_not_found',
                __( 'Zone not found.', 'simple-checkout-location' ),
                [ 'status' => 404 ]
            );
        }

        return rest_ensure_response( [
            'success' => true,
            'data'    => $zone,
        ] );
    }

    public function get_delivery_schedule( $request ) {
        $zone_name = sanitize_text_field( $request['zone_name'] );
        
        $zone = $this->zones_repo->get_zone_with_schedule( $zone_name );

        if ( ! $zone ) {
            return new WP_Error(
                'rest_zone_not_found',
                __( 'Zone not found.', 'simple-checkout-location' ),
                [ 'status' => 404 ]
            );
        }

        $available_dates = $this->zones_repo->get_available_delivery_dates(
            $zone_name,
            $zone['delivery_days_ahead'],
            $zone['closed_days']
        );

        return rest_ensure_response( [
            'success'         => true,
            'zone_name'       => $zone['zone_name'],
            'zone_name_ar'    => $zone['zone_name_ar'],
            'shipping_cost'   => $zone['shipping_cost'],
            'delivery_times'  => $zone['delivery_times'],
            'available_dates' => $available_dates,
            'closed_days'     => $zone['closed_days'],
            'days_ahead'      => $zone['delivery_days_ahead'],
        ] );
    }

    // ==================== Order Methods ====================

    public function create_order_with_address( $request ) {
        $address_id = $request->get_param( 'address_id' );
        $delivery_date = $request->get_param( 'delivery_date' );
        $delivery_time = $request->get_param( 'delivery_time' );
        $products = $request->get_param( 'products' );

        if ( ! $address_id ) {
            return new WP_Error(
                'missing_address',
                __( 'Address ID is required.', 'simple-checkout-location' ),
                [ 'status' => 400 ]
            );
        }

        $address = $this->repo->get_address( $address_id, get_current_user_id() );

        if ( ! $address ) {
            return new WP_Error(
                'invalid_address',
                __( 'Invalid address ID.', 'simple-checkout-location' ),
                [ 'status' => 404 ]
            );
        }

        try {
            $order = wc_create_order( [
                'customer_id' => get_current_user_id(),
            ] );

            if ( is_wp_error( $order ) ) {
                return $order;
            }

            // Set billing address
            $order->set_billing_first_name( $address['customer_name'] );
            $order->set_billing_last_name( '' );
            $order->set_billing_company( $address['address_name'] );
            $order->set_billing_address_1( $address['address_details'] );
            $order->set_billing_address_2( '' );
            $order->set_billing_city( $address['zone'] );
            $order->set_billing_postcode( '00000' );
            $order->set_billing_country( 'EG' );
            $order->set_billing_phone( $address['phone_primary'] );

            // Add custom meta
            $order->update_meta_data( '_scl_address_id', $address_id );
            $order->update_meta_data( '_billing_phone_secondary', $address['phone_secondary'] );
            $order->update_meta_data( '_billing_address_name', $address['address_name'] );
            $order->update_meta_data( '_billing_location_url', $address['location_url'] );
            $order->update_meta_data( '_billing_location_lat', $address['location_lat'] );
            $order->update_meta_data( '_billing_location_lng', $address['location_lng'] );
            $order->update_meta_data( '_billing_notes_customer', $address['notes_customer'] );
            $order->update_meta_data( '_billing_notes_internal', $address['notes_internal'] );
            $order->update_meta_data( '_billing_zone', $address['zone'] );

            if ( $delivery_date ) {
                $order->update_meta_data( '_scl_delivery_date', sanitize_text_field( $delivery_date ) );
                $order->update_meta_data( '_billing_delivery_date', sanitize_text_field( $delivery_date ) );
            }

            if ( $delivery_time ) {
                $order->update_meta_data( '_scl_delivery_time', sanitize_text_field( $delivery_time ) );
                $order->update_meta_data( '_billing_delivery_time', sanitize_text_field( $delivery_time ) );
            }

            // Add products
            if ( is_array( $products ) && ! empty( $products ) ) {
                foreach ( $products as $product_data ) {
                    $product_id = isset( $product_data['product_id'] ) ? absint( $product_data['product_id'] ) : 0;
                    $quantity = isset( $product_data['quantity'] ) ? absint( $product_data['quantity'] ) : 1;
                    
                    if ( $product_id > 0 ) {
                        $order->add_product( wc_get_product( $product_id ), $quantity );
                    }
                }
            }

            // Calculate totals
            $order->calculate_totals();
            $order->save();

            return rest_ensure_response( [
                'success'  => true,
                'order_id' => $order->get_id(),
                'message'  => __( 'Order created successfully.', 'simple-checkout-location' ),
                'data'     => [
                    'id'     => $order->get_id(),
                    'number' => $order->get_order_number(),
                    'status' => $order->get_status(),
                    'total'  => $order->get_total(),
                ],
            ] );

        } catch ( Exception $e ) {
            return new WP_Error(
                'order_creation_failed',
                $e->getMessage(),
                [ 'status' => 500 ]
            );
        }
    }

    // ==================== WooCommerce Order Enhancement ====================

    public function rest_add_address_fields( $response, $order, $request ) {
        $data = $response->get_data();
        
        if ( isset( $data['billing'] ) ) {
            $data['billing']['phone_secondary'] = $order->get_meta( '_billing_phone_secondary' );
            $data['billing']['address_name'] = $order->get_meta( '_billing_address_name' );
            $data['billing']['location_url'] = $order->get_meta( '_billing_location_url' );
            $data['billing']['location_lat'] = $order->get_meta( '_billing_location_lat' );
            $data['billing']['location_lng'] = $order->get_meta( '_billing_location_lng' );
            $data['billing']['notes_customer'] = $order->get_meta( '_billing_notes_customer' );
            $data['billing']['notes_internal'] = $order->get_meta( '_billing_notes_internal' );
            $data['billing']['scl_address_id'] = $order->get_meta( '_scl_address_id' );
            $data['billing']['zone'] = $order->get_meta( '_billing_zone' );
            $data['billing']['delivery_date'] = $order->get_meta( '_billing_delivery_date' );
            $data['billing']['delivery_time'] = $order->get_meta( '_billing_delivery_time' );
        }
        
        $response->set_data( $data );
        return $response;
    }

    // ==================== Validation & Data Preparation ====================

    private function validate_address_data( $request, $address_id = null ) {
        $errors = [];

        $required_fields = [ 'address_name', 'customer_name', 'phone_primary' ];
        
        foreach ( $required_fields as $field ) {
            if ( empty( $request->get_param( $field ) ) ) {
                $errors[] = sprintf(
                    __( '%s is required.', 'simple-checkout-location' ),
                    ucwords( str_replace( '_', ' ', $field ) )
                );
            }
        }

        $location_url = $request->get_param( 'location_url' );
        if ( $location_url && ! filter_var( $location_url, FILTER_VALIDATE_URL ) ) {
            $errors[] = __( 'Location URL must be a valid URL.', 'simple-checkout-location' );
        }

        if ( ! empty( $errors ) ) {
            return new WP_Error(
                'rest_invalid_data',
                implode( ' ', $errors ),
                [ 'status' => 400 ]
            );
        }

        return true;
    }

    private function prepare_address_data_from_request( WP_REST_Request $request, $user_id ) {
        $data = [
            'user_id'            => (int) $user_id,
            'address_name'       => sanitize_text_field( $request->get_param( 'address_name' ) ?: '' ),
            'customer_name'      => sanitize_text_field( $request->get_param( 'customer_name' ) ?: '' ),
            'phone_primary'      => sanitize_text_field( $request->get_param( 'phone_primary' ) ?: '' ),
            'phone_secondary'    => sanitize_text_field( $request->get_param( 'phone_secondary' ) ?: '' ),
            'location_url'       => esc_url_raw( $request->get_param( 'location_url' ) ?: '' ),
            'location_lat'       => $request->get_param( 'location_lat' ) ?: null,
            'location_lng'       => $request->get_param( 'location_lng' ) ?: null,
            'address_details'    => sanitize_textarea_field( $request->get_param( 'address_details' ) ?: '' ),
            'zone'               => sanitize_text_field( $request->get_param( 'zone' ) ?: '' ),
            'notes_customer'     => sanitize_textarea_field( $request->get_param( 'notes_customer' ) ?: '' ),
            'notes_internal'     => sanitize_textarea_field( $request->get_param( 'notes_internal' ) ?: '' ),
            'is_default_billing' => ! empty( $request->get_param( 'is_default_billing' ) ) ? 1 : 0,
            'status'             => 1,
        ];
        
        if ( empty( $data['user_id'] ) || empty( $data['address_name'] ) || empty( $data['customer_name'] ) || empty( $data['phone_primary'] ) ) {
            error_log( 'SCL Missing required fields: ' . print_r( $data, true ) );
        }
        
        return $data;
    }

    // ==================== Schema Definitions ====================

    private function get_order_endpoint_args() {
        return [
            'address_id' => [
                'description' => __( 'Address ID', 'simple-checkout-location' ),
                'type'        => 'integer',
                'required'    => true,
            ],
            'delivery_date' => [
                'description' => __( 'Delivery date (YYYY-MM-DD)', 'simple-checkout-location' ),
                'type'        => 'string',
                'format'      => 'date',
            ],
            'delivery_time' => [
                'description' => __( 'Delivery time slot', 'simple-checkout-location' ),
                'type'        => 'string',
            ],
            'products' => [
                'description' => __( 'Array of products with product_id and quantity', 'simple-checkout-location' ),
                'type'        => 'array',
                'items'       => [
                    'type' => 'object',
                    'properties' => [
                        'product_id' => [
                            'type' => 'integer',
                        ],
                        'quantity' => [
                            'type' => 'integer',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function get_endpoint_args_for_item_schema( $method = WP_REST_Server::CREATABLE ) {
        return [
            'user_id' => [
                'description'       => __( 'User ID (admin only)', 'simple-checkout-location' ),
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
            ],
            'address_name' => [
                'description'       => __( 'Address name or label', 'simple-checkout-location' ),
                'type'              => 'string',
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'customer_name' => [
                'description'       => __( 'Customer name', 'simple-checkout-location' ),
                'type'              => 'string',
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'phone_primary' => [
                'description'       => __( 'Primary phone number', 'simple-checkout-location' ),
                'type'              => 'string',
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'phone_secondary' => [
                'description'       => __( 'Secondary phone number', 'simple-checkout-location' ),
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'location_url' => [
                'description'       => __( 'Google Maps location URL', 'simple-checkout-location' ),
                'type'              => 'string',
                'format'            => 'uri',
                'sanitize_callback' => 'esc_url_raw',
            ],
            'location_lat' => [
                'description' => __( 'Latitude', 'simple-checkout-location' ),
                'type'        => 'string',
            ],
            'location_lng' => [
                'description' => __( 'Longitude', 'simple-checkout-location' ),
                'type'        => 'string',
            ],
            'address_details' => [
                'description'       => __( 'Full address details', 'simple-checkout-location' ),
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
            ],
            'zone' => [
                'description'       => __( 'Delivery zone', 'simple-checkout-location' ),
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'notes_customer' => [
                'description'       => __( 'Delivery notes from customer', 'simple-checkout-location' ),
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
            ],
            'notes_internal' => [
                'description'       => __( 'Internal admin notes', 'simple-checkout-location' ),
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
            ],
            'is_default_billing' => [
                'description' => __( 'Set as default billing address', 'simple-checkout-location' ),
                'type'        => 'boolean',
            ],
        ];
    }
}
