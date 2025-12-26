<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Custom Shipping Method for Zone-based delivery
 */
class SCL_Zone_Shipping_Method extends WC_Shipping_Method {

    public function __construct( $instance_id = 0 ) {
        $this->id                 = 'scl_zone_shipping';
        $this->instance_id        = absint( $instance_id );
        $this->method_title       = __( 'Zone-Based Shipping', 'simple-checkout-location' );
        $this->method_description = __( 'Shipping cost based on delivery zone', 'simple-checkout-location' );
        $this->supports           = [
            'shipping-zones',
            'instance-settings',
        ];

        $this->init();
    }

    public function init() {
        $this->init_form_fields();
        $this->init_settings();

        $this->enabled = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'yes';
        $this->title   = isset( $this->settings['title'] ) ? $this->settings['title'] : $this->method_title;

        add_action( 'woocommerce_update_options_shipping_' . $this->id, [ $this, 'process_admin_options' ] );
    }

    public function init_form_fields() {
        $this->form_fields = [
            'enabled' => [
                'title'   => __( 'Enable/Disable', 'simple-checkout-location' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable zone-based shipping', 'simple-checkout-location' ),
                'default' => 'yes',
            ],
            'title' => [
                'title'       => __( 'Method Title', 'simple-checkout-location' ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'simple-checkout-location' ),
                'default'     => __( 'Zone Delivery', 'simple-checkout-location' ),
                'desc_tip'    => true,
            ],
        ];
    }

    public function calculate_shipping( $package = [] ) {
        $zone_name = $this->get_zone_from_package( $package );
        
        if ( ! $zone_name ) {
            return;
        }

        require_once SCL_PLUGIN_DIR . 'includes/class-zones-repository.php';
        $zones_repo = new SCL_Zones_Repository();
        $shipping_cost = $zones_repo->get_shipping_cost_by_zone_name( $zone_name );

        if ( $shipping_cost !== false ) {
            $rate = [
                'id'    => $this->id,
                'label' => $this->title,
                'cost'  => $shipping_cost,
                'meta_data' => [
                    'zone_name' => $zone_name,
                ],
            ];

            $this->add_rate( $rate );
        }
    }

    private function get_zone_from_package( $package ) {
        // Try to get zone from customer data
        if ( isset( $package['destination']['city'] ) && ! empty( $package['destination']['city'] ) ) {
            return $package['destination']['city'];
        }

        // Fallback: get from session/checkout form
        if ( ! empty( $_POST['scl_guest_city'] ) ) {
            return sanitize_text_field( $_POST['scl_guest_city'] );
        }

        if ( ! empty( $_POST['scl_address_id'] ) ) {
            require_once SCL_PLUGIN_DIR . 'includes/class-address-repository.php';
            $repo = new SCL_Address_Repository();
            $address = $repo->get_address( (int) $_POST['scl_address_id'] );
            
            if ( $address && ! empty( $address['zone'] ) ) {
                return $address['zone'];
            }
        }

        return null;
    }
}
