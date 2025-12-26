<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SCL_Address_Service {

    /** @var SCL_Address_Repository */
    private $repo;

    public function __construct() {
        $this->repo = new SCL_Address_Repository();
    }

    public function get_user_addresses( $user_id = null ) {
        if ( ! $user_id && is_user_logged_in() ) {
            $user_id = get_current_user_id();
        }
        if ( ! $user_id ) {
            return [];
        }
        return $this->repo->get_addresses_by_user( $user_id );
    }

    public function get_default_billing_address( $user_id = null ) {
        $addresses = $this->get_user_addresses( $user_id );
        foreach ( $addresses as $addr ) {
            if ( (int) $addr['is_default_billing'] === 1 ) {
                return $addr;
            }
        }
        return ! empty( $addresses ) ? $addresses[0] : null;
    }

    public function fill_wc_billing_from_address( $address, WC_Customer $customer ) {
        // يملي كائن customer بالبيانات لتنعكس على checkout
        $customer->set_billing_first_name( $address['customer_name'] );
        $customer->set_billing_phone( $address['phone_primary'] );
        $customer->set_billing_address_1( $address['address_details'] );
        // zone -> city أو state حسب استخدامك
        if ( ! empty( $address['zone'] ) ) {
            $customer->set_billing_city( $address['zone'] );
        }

        // حفظ اللوكيشن في user_meta الافتراضية أيضًا
        update_user_meta( $customer->get_id(), 'billing_location_url', $address['location_url'] );
        update_user_meta( $customer->get_id(), 'billing_location_lat', $address['location_lat'] );
        update_user_meta( $customer->get_id(), 'billing_location_lng', $address['location_lng'] );
    }

    public function address_to_billing_array( $address ) {
        return [
            'first_name'  => $address['customer_name'],
            'last_name'   => '',
            'company'     => '',
            'address_1'   => $address['address_details'],
            'address_2'   => '',
            'city'        => $address['zone'],
            'state'       => '',
            'postcode'    => '',
            'country'     => '',
            'email'       => '',
            'phone'       => $address['phone_primary'],
            'location_url'=> $address['location_url'],
        ];
    }
}
