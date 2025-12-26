<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SCL_Zones_Repository {

    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'scl_zones';
    }

    public function get_all_zones( $active_only = true ) {
        global $wpdb;
        
        $where = $active_only ? 'WHERE is_active = 1' : '';
        $sql = "SELECT * FROM {$this->table} {$where} ORDER BY display_order ASC, zone_name ASC";
        
        return $wpdb->get_results( $sql, ARRAY_A );
    }

    public function get_zone( $id ) {
        global $wpdb;
        $sql = $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id );
        return $wpdb->get_row( $sql, ARRAY_A );
    }

    public function get_zone_by_name( $name ) {
        global $wpdb;
        $sql = $wpdb->prepare( 
            "SELECT * FROM {$this->table} WHERE zone_name = %s OR zone_name_ar = %s LIMIT 1", 
            $name, 
            $name 
        );
        return $wpdb->get_row( $sql, ARRAY_A );
    }

    public function get_zone_with_schedule( $zone_name ) {
    global $wpdb;
    $sql = $wpdb->prepare( 
        "SELECT * FROM {$this->table} WHERE (zone_name = %s OR zone_name_ar = %s) AND is_active = 1 LIMIT 1",
        $zone_name,
        $zone_name
    );
    $zone = $wpdb->get_row( $sql, ARRAY_A );
    
    if ( $zone ) {
        if ( ! empty( $zone['delivery_times'] ) ) {
            $zone['delivery_times'] = json_decode( $zone['delivery_times'], true );
        } else {
            $zone['delivery_times'] = [];
        }
        
        if ( ! empty( $zone['closed_days'] ) ) {
            $zone['closed_days'] = json_decode( $zone['closed_days'], true );
        } else {
            $zone['closed_days'] = [5];
        }
        
        if ( empty( $zone['delivery_days_ahead'] ) ) {
            $zone['delivery_days_ahead'] = 3;
        }
    }
    
    return $zone;
}

// ✅ أضف دالة حساب التواريخ
public function get_available_delivery_dates( $zone_name, $days_ahead = null, $closed_days = null ) {
    if ( $days_ahead === null || $closed_days === null ) {
        $zone = $this->get_zone_with_schedule( $zone_name );
        if ( ! $zone ) {
            return [];
        }
        $days_ahead = (int) $zone['delivery_days_ahead'];
        $closed_days = $zone['closed_days'];
    }
    
    $dates = [];
    $current_date = strtotime( 'today' );
    $days_added = 0;
    $counter = 0;
    
    if ( ! is_array( $closed_days ) ) {
        $closed_days = [5];
    }
    
    while ( $days_added < $days_ahead && $counter < 30 ) {
        $counter++;
        $check_date = strtotime( "+{$counter} days", $current_date );
        $day_of_week = (int) date( 'N', $check_date );
        
        if ( in_array( $day_of_week, $closed_days ) ) {
            continue;
        }
        
        $dates[] = [
            'date' => date( 'Y-m-d', $check_date ),
            'display' => date( 'l, F j, Y', $check_date ),
            'display_ar' => $this->get_arabic_date( $check_date )
        ];
        
        $days_added++;
    }
    
    return $dates;
}

private function get_arabic_date( $timestamp ) {
    $days_ar = [
        'Monday' => 'الإثنين', 'Tuesday' => 'الثلاثاء', 'Wednesday' => 'الأربعاء',
        'Thursday' => 'الخميس', 'Friday' => 'الجمعة', 'Saturday' => 'السبت', 'Sunday' => 'الأحد'
    ];
    
    $months_ar = [
        'January' => 'يناير', 'February' => 'فبراير', 'March' => 'مارس',
        'April' => 'أبريل', 'May' => 'مايو', 'June' => 'يونيو',
        'July' => 'يوليو', 'August' => 'أغسطس', 'September' => 'سبتمبر',
        'October' => 'أكتوبر', 'November' => 'نوفمبر', 'December' => 'ديسمبر'
    ];
    
    $day = date( 'l', $timestamp );
    $month = date( 'F', $timestamp );
    $date_num = date( 'j', $timestamp );
    $year = date( 'Y', $timestamp );
    
    return $days_ar[$day] . '، ' . $date_num . ' ' . $months_ar[$month] . ' ' . $year;
}

    public function create_zone( $data ) {
    global $wpdb;
    $now = current_time( 'mysql' );
    
    $insert_data = [
        'zone_name' => $data['zone_name'],
        'zone_name_ar' => isset( $data['zone_name_ar'] ) ? $data['zone_name_ar'] : '',
        'shipping_cost' => floatval( $data['shipping_cost'] ),
        'is_active' => isset( $data['is_active'] ) ? (int) $data['is_active'] : 1,
        'display_order' => isset( $data['display_order'] ) ? (int) $data['display_order'] : 0,
        'created_at' => $now,
        'updated_at' => $now,
    ];
    
    // ✅ معالجة delivery_times
    if ( isset( $data['delivery_times'] ) && is_array( $data['delivery_times'] ) ) {
        $delivery_times = array_values( array_filter( $data['delivery_times'], function($time) {
            return ! empty( trim( $time ) );
        }));
        
        if ( ! empty( $delivery_times ) ) {
            $insert_data['delivery_times'] = json_encode( $delivery_times, JSON_UNESCAPED_UNICODE );
            error_log( 'SCL: Saving delivery_times: ' . $insert_data['delivery_times'] );
        }
    }
    
    // ✅ معالجة delivery_days_ahead
    if ( isset( $data['delivery_days_ahead'] ) ) {
        $insert_data['delivery_days_ahead'] = (int) $data['delivery_days_ahead'];
    } else {
        $insert_data['delivery_days_ahead'] = 3;
    }
    
    // ✅ معالجة closed_days
    if ( isset( $data['closed_days'] ) && is_array( $data['closed_days'] ) ) {
        $closed_days = array_map( 'intval', $data['closed_days'] );
        $insert_data['closed_days'] = json_encode( $closed_days );
        error_log( 'SCL: Saving closed_days: ' . $insert_data['closed_days'] );
    } else {
        $insert_data['closed_days'] = json_encode( [5] );
    }
    
    $result = $wpdb->insert( $this->table, $insert_data );
    
    if ( $result === false ) {
        error_log( 'SCL Zone Insert Error: ' . $wpdb->last_error );
        error_log( 'SCL Insert Data: ' . print_r( $insert_data, true ) );
        return false;
    }
    
    $zone_id = (int) $wpdb->insert_id;
    error_log( 'SCL: Zone created successfully with ID: ' . $zone_id );
    
    return $zone_id;
}


public function update_zone( $id, $data ) {
    global $wpdb;
    
    $update_data = [
        'zone_name' => $data['zone_name'],
        'zone_name_ar' => isset( $data['zone_name_ar'] ) ? $data['zone_name_ar'] : '',
        'shipping_cost' => floatval( $data['shipping_cost'] ),
        'is_active' => isset( $data['is_active'] ) ? (int) $data['is_active'] : 1,
        'display_order' => isset( $data['display_order'] ) ? (int) $data['display_order'] : 0,
        'updated_at' => current_time( 'mysql' ),
    ];
    
    // ✅ معالجة delivery_times
    if ( isset( $data['delivery_times'] ) && is_array( $data['delivery_times'] ) ) {
        $delivery_times = array_values( array_filter( $data['delivery_times'], function($time) {
            return ! empty( trim( $time ) );
        }));
        
        if ( ! empty( $delivery_times ) ) {
            $update_data['delivery_times'] = json_encode( $delivery_times, JSON_UNESCAPED_UNICODE );
        } else {
            $update_data['delivery_times'] = null;
        }
    }
    
    // ✅ معالجة delivery_days_ahead
    if ( isset( $data['delivery_days_ahead'] ) ) {
        $update_data['delivery_days_ahead'] = (int) $data['delivery_days_ahead'];
    }
    
    // ✅ معالجة closed_days
    if ( isset( $data['closed_days'] ) && is_array( $data['closed_days'] ) ) {
        $closed_days = array_map( 'intval', $data['closed_days'] );
        $update_data['closed_days'] = json_encode( $closed_days );
    }
    
    error_log( 'SCL Zone Update Data: ' . print_r( $update_data, true ) );
    
    $result = $wpdb->update( $this->table, $update_data, [ 'id' => $id ] );
    
    if ( $result === false ) {
        error_log( 'SCL Zone Update Error: ' . $wpdb->last_error );
        return false;
    }
    
    error_log( 'SCL: Zone #' . $id . ' updated successfully' );
    
    return $result;
}

    

    public function delete_zone( $id ) {
        global $wpdb;
        return $wpdb->delete( $this->table, [ 'id' => $id ] );
    }

    public function get_shipping_cost_by_zone_name( $zone_name ) {
        global $wpdb;
        $sql = $wpdb->prepare( 
            "SELECT shipping_cost FROM {$this->table} WHERE (zone_name = %s OR zone_name_ar = %s) AND is_active = 1 LIMIT 1",
            $zone_name,
            $zone_name
        );
        return (float) $wpdb->get_var( $sql );
    }
}
