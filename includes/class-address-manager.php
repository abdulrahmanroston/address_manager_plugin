<?php
/**
 * Address Manager Class
 * Handles all address and location related operations
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SCL_Address_Manager {

    /** @var SCL_Address_Repository */
    private $repo;

    public function __construct() {
        $this->repo = new SCL_Address_Repository();

        // Hook example – حالياً لا نحتاج تنفيذ شيء هنا لكن نتركه للتوسعة
        add_action( 'woocommerce_checkout_update_customer_data', [ $this, 'sync_location_with_address' ], 10, 2 );
    }

    /**
     * Get location for current selected billing address
     * يختار عنوان العميل الافتراضي من الجدول ثم يرجع لوكيشنه
     */
    public function get_current_address_location() {
        if ( ! is_user_logged_in() ) {
            return [
                'url' => '',
                'lat' => '',
                'lng' => '',
            ];
        }

        $user_id   = get_current_user_id();
        $addresses = $this->repo->get_addresses_by_user( $user_id );

        if ( empty( $addresses ) ) {
            return [
                'url' => '',
                'lat' => '',
                'lng' => '',
            ];
        }

        // ابحث عن الافتراضي للفوترة
        $default = null;
        foreach ( $addresses as $addr ) {
            if ( (int) $addr['is_default_billing'] === 1 ) {
                $default = $addr;
                break;
            }
        }
        if ( ! $default ) {
            $default = $addresses[0];
        }

        return [
            'url' => ! empty( $default['location_url'] ) ? $default['location_url'] : '',
            'lat' => ! empty( $default['location_lat'] ) ? $default['location_lat'] : '',
            'lng' => ! empty( $default['location_lng'] ) ? $default['location_lng'] : '',
        ];
    }

    /**
     * Get location for specific address id (address_key = id in custom table)
     */
    public function get_address_location( $address_key ) {
        if ( ! is_user_logged_in() ) {
            return [
                'url' => '',
                'lat' => '',
                'lng' => '',
            ];
        }

        $user_id = get_current_user_id();
        $id      = (int) $address_key;

        $address = $this->repo->get_address( $id, $user_id );

        if ( ! $address ) {
            // Fallback: استخدم العنوان الافتراضي إن لم يوجد هذا العنوان
            return $this->get_current_address_location();
        }

        return [
            'url' => ! empty( $address['location_url'] ) ? $address['location_url'] : '',
            'lat' => ! empty( $address['location_lat'] ) ? $address['location_lat'] : '',
            'lng' => ! empty( $address['location_lng'] ) ? $address['location_lng'] : '',
        ];
    }

    /**
     * Get current billing address as formatted string
     * الآن نعتمد على العنوان الافتراضي من الجدول وليس WC_Customer فقط
     */
    public function get_current_billing_address() {
        if ( ! is_user_logged_in() ) {
            return '';
        }

        $user_id   = get_current_user_id();
        $addresses = $this->repo->get_addresses_by_user( $user_id );

        if ( empty( $addresses ) ) {
            return '';
        }

        $default = null;
        foreach ( $addresses as $addr ) {
            if ( (int) $addr['is_default_billing'] === 1 ) {
                $default = $addr;
                break;
            }
        }
        if ( ! $default ) {
            $default = $addresses[0];
        }

        return $this->format_address_row( $default );
    }

    /**
     * Sync location when address is updated (placeholder for future use)
     */
    public function sync_location_with_address( $customer, $data ) {
        // حالياً لا نحتاج منطق هنا لأن اللوكيشن يُدار عبر الجدول المخصص
    }

    /**
     * Save location to specific address (update only location fields in custom table)
     */
    public function save_location_to_address( $user_id, $address_key, $location_data ) {
        if ( empty( $location_data['url'] ) ) {
            return false;
        }

        $id = (int) $address_key;

        $data = [
            'location_url' => sanitize_text_field( $location_data['url'] ),
        ];

        if ( ! empty( $location_data['lat'] ) ) {
            $data['location_lat'] = sanitize_text_field( $location_data['lat'] );
        }

        if ( ! empty( $location_data['lng'] ) ) {
            $data['location_lng'] = sanitize_text_field( $location_data['lng'] );
        }

        $this->repo->update_address( $id, $user_id, $data );

        return true;
    }

    /**
     * Get all saved addresses with their locations (optional helper)
     */
    public function get_all_user_addresses_with_locations( $user_id ) {
        $addresses = $this->repo->get_addresses_by_user( $user_id );
        $result    = [];

        foreach ( $addresses as $addr ) {
            $result[] = [
                'id'       => (int) $addr['id'],
                'name'     => $addr['address_name'],
                'address'  => $this->format_address_row( $addr ),
                'location' => [
                    'url' => $addr['location_url'],
                    'lat' => $addr['location_lat'],
                    'lng' => $addr['location_lng'],
                ],
                'default_billing'  => (int) $addr['is_default_billing'] === 1,
                'default_shipping' => (int) $addr['is_default_shipping'] === 1,
            ];
        }

        return $result;
    }

    /**
     * Format a single address row from scl_addresses table to a string
     */
    private function format_address_row( $row ) {
        $parts = [];

        if ( ! empty( $row['address_details'] ) ) {
            $parts[] = $row['address_details'];
        }
        if ( ! empty( $row['zone'] ) ) {
            $parts[] = $row['zone'];
        }
        if ( ! empty( $row['phone_primary'] ) ) {
            $parts[] = $row['phone_primary'];
        }

        return implode( ', ', $parts );
    }
}
