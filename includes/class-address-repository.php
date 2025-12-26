<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SCL_Address_Repository {

    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'scl_addresses';
    }

    public function get_addresses_by_user( $user_id ) {
        global $wpdb;
        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE user_id = %d AND status = 1 ORDER BY is_default_billing DESC, created_at DESC",
            $user_id
        );
        return $wpdb->get_results( $sql, ARRAY_A );
    }

    public function get_address( $id, $user_id = null ) {
        global $wpdb;
        if ( $user_id ) {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE id = %d AND user_id = %d AND status = 1",
                $id,
                $user_id
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE id = %d AND status = 1",
                $id
            );
        }
        return $wpdb->get_row( $sql, ARRAY_A );
    }

    public function create_address( $data ) {
    global $wpdb;
    $now = current_time( 'mysql' );
    
    // ✅ تحديد الحقول المطلوبة فقط
    $allowed_fields = [
        'user_id', 'address_name', 'customer_name', 'phone_primary', 
        'phone_secondary', 'location_url', 'location_lat', 'location_lng',
        'address_details', 'zone', 'notes_customer', 'notes_internal',
        'is_default_billing', 'is_default_shipping', 'status'
    ];
    
    // ✅ تصفية البيانات
    $insert_data = [];
    foreach ( $allowed_fields as $field ) {
        if ( isset( $data[ $field ] ) ) {
            $insert_data[ $field ] = $data[ $field ];
        }
    }
    
    // ✅ التأكد من الحقول المطلوبة
    if ( empty( $insert_data['user_id'] ) || 
         empty( $insert_data['address_name'] ) || 
         empty( $insert_data['customer_name'] ) || 
         empty( $insert_data['phone_primary'] ) ) {
        error_log( 'SCL: Missing required fields - ' . print_r( $insert_data, true ) );
        return false;
    }
    
    // ✅ القيم الافتراضية
    $insert_data = wp_parse_args( $insert_data, [
        'phone_secondary'    => '',
        'location_url'       => '',
        'location_lat'       => null,
        'location_lng'       => null,
        'address_details'    => '',
        'zone'               => '',
        'notes_customer'     => '',
        'notes_internal'     => '',
        'is_default_billing' => 0,
        'is_default_shipping'=> 0,
        'status'             => 1,
    ] );
    
    $insert_data['created_at'] = $now;
    $insert_data['updated_at'] = $now;
    
    // ✅ تنظيف الـ NULL values
    foreach ( $insert_data as $key => $value ) {
        if ( is_null( $value ) && ! in_array( $key, [ 'location_lat', 'location_lng', 'address_details', 'notes_customer', 'notes_internal' ] ) ) {
            $insert_data[ $key ] = '';
        }
    }
    
    error_log( 'SCL: Attempting insert with data - ' . print_r( $insert_data, true ) );
    
    $result = $wpdb->insert( $this->table, $insert_data );
    
    if ( false === $result ) {
        error_log( 'SCL: Insert FAILED - Error: ' . $wpdb->last_error );
        error_log( 'SCL: Last Query: ' . $wpdb->last_query );
        return false;
    }
    
    $insert_id = (int) $wpdb->insert_id;
    error_log( 'SCL: Insert SUCCESS - ID: ' . $insert_id );
    
    return $insert_id;
}


    public function update_address( $id, $user_id, $data ) {
        global $wpdb;
        
        $data['updated_at'] = current_time( 'mysql' );
        
        // ✅ إذا كان default، امسح الـ default السابق أولاً
        if ( ! empty( $data['is_default_billing'] ) ) {
            $this->unset_default_flags( $user_id, 'billing' );
        }
        
        $result = $wpdb->update(
            $this->table,
            $data,
            [ 'id' => $id, 'user_id' => $user_id ]
        );
        
        if ( false === $result ) {
            error_log( 'SCL Address Update Error: ' . $wpdb->last_error );
            return false;
        }
        
        return $result;
    }

    public function soft_delete_address( $id, $user_id ) {
        global $wpdb;
        return $wpdb->update(
            $this->table,
            [
                'status'     => 0,
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'id' => $id, 'user_id' => $user_id ]
        );
    }

    public function unset_default_flags( $user_id, $type = 'billing' ) {
        global $wpdb;
        $field = $type === 'shipping' ? 'is_default_shipping' : 'is_default_billing';
        $wpdb->update(
            $this->table,
            [ $field => 0 ],
            [ 'user_id' => $user_id ]
        );
    }

    public function set_default( $id, $user_id, $type = 'billing' ) {
        $this->unset_default_flags( $user_id, $type );
        global $wpdb;
        $field = $type === 'shipping' ? 'is_default_shipping' : 'is_default_billing';
        return $wpdb->update(
            $this->table,
            [ $field => 1, 'updated_at' => current_time( 'mysql' ) ],
            [ 'id' => $id, 'user_id' => $user_id ]
        );
    }
}
