<?php
/**
 * Custom Fields Manager
 * Handles dynamic custom fields for addresses
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SCL_Custom_Fields_Manager {

    const OPTION_KEY = 'scl_custom_address_fields';

    /**
     * Get all custom fields
     */
    public function get_custom_fields() {
        $fields = get_option( self::OPTION_KEY, [] );
        return is_array( $fields ) ? $fields : [];
    }

    /**
     * Add a new custom field
     */
    public function add_field( $field_data ) {
        $fields = $this->get_custom_fields();

        // Validate field key uniqueness
        $key = sanitize_key( $field_data['key'] );
        
        if ( $this->field_exists( $key ) ) {
            return new WP_Error( 'field_exists', __( 'A field with this key already exists.', 'simple-checkout-location' ) );
        }

        // Validate against core fields
        $core_fields = $this->get_core_field_keys();
        if ( in_array( $key, $core_fields, true ) ) {
            return new WP_Error( 'core_field', __( 'Cannot use a core field key.', 'simple-checkout-location' ) );
        }

        $new_field = [
            'key'      => $key,
            'label'    => sanitize_text_field( $field_data['label'] ),
            'type'     => sanitize_text_field( $field_data['type'] ),
            'required' => ! empty( $field_data['required'] ),
            'order'    => count( $fields ),
        ];

        $fields[ $key ] = $new_field;

        return update_option( self::OPTION_KEY, $fields );
    }

    /**
     * Update an existing field
     */
    public function update_field( $key, $field_data ) {
        $fields = $this->get_custom_fields();

        if ( ! isset( $fields[ $key ] ) ) {
            return new WP_Error( 'field_not_found', __( 'Field not found.', 'simple-checkout-location' ) );
        }

        $fields[ $key ] = array_merge( $fields[ $key ], [
            'label'    => sanitize_text_field( $field_data['label'] ),
            'type'     => sanitize_text_field( $field_data['type'] ),
            'required' => ! empty( $field_data['required'] ),
        ] );

        return update_option( self::OPTION_KEY, $fields );
    }

    /**
     * Delete a custom field
     */
    public function delete_field( $key ) {
        $fields = $this->get_custom_fields();

        if ( ! isset( $fields[ $key ] ) ) {
            return new WP_Error( 'field_not_found', __( 'Field not found.', 'simple-checkout-location' ) );
        }

        unset( $fields[ $key ] );

        return update_option( self::OPTION_KEY, $fields );
    }

    /**
     * Check if field exists
     */
    public function field_exists( $key ) {
        $fields = $this->get_custom_fields();
        return isset( $fields[ $key ] );
    }

    /**
     * Get core field keys that cannot be used
     */
    public function get_core_field_keys() {
        return [
            'id',
            'user_id',
            'address_name',
            'customer_name',
            'phone_primary',
            'phone_secondary',
            'location_url',
            'location_lat',
            'location_lng',
            'address_details',
            'zone',
            'notes_customer',
            'notes_internal',
            'is_default_billing',
            'is_default_shipping',
            'status',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * Get field by key
     */
    public function get_field( $key ) {
        $fields = $this->get_custom_fields();
        return isset( $fields[ $key ] ) ? $fields[ $key ] : null;
    }

    /**
     * Reorder fields
     */
    public function reorder_fields( $ordered_keys ) {
        $fields = $this->get_custom_fields();
        $reordered = [];

        foreach ( $ordered_keys as $index => $key ) {
            if ( isset( $fields[ $key ] ) ) {
                $fields[ $key ]['order'] = $index;
                $reordered[ $key ] = $fields[ $key ];
            }
        }

        return update_option( self::OPTION_KEY, $reordered );
    }

    /**
     * Render custom field HTML
     */
    public function render_field( $field, $value = '' ) {
        $required = ! empty( $field['required'] ) ? 'required' : '';
        $field_id = 'scl_custom_' . $field['key'];

        $html = '<div class="scl-form-row">';
        $html .= '<label for="' . esc_attr( $field_id ) . '">';
        $html .= esc_html( $field['label'] );
        if ( ! empty( $field['required'] ) ) {
            $html .= ' <span class="required">*</span>';
        }
        $html .= '</label>';

        switch ( $field['type'] ) {
            case 'textarea':
                $html .= '<textarea id="' . esc_attr( $field_id ) . '" name="custom_' . esc_attr( $field['key'] ) . '" ' . $required . '>' . esc_textarea( $value ) . '</textarea>';
                break;

            case 'email':
                $html .= '<input type="email" id="' . esc_attr( $field_id ) . '" name="custom_' . esc_attr( $field['key'] ) . '" value="' . esc_attr( $value ) . '" ' . $required . '>';
                break;

            case 'number':
                $html .= '<input type="number" id="' . esc_attr( $field_id ) . '" name="custom_' . esc_attr( $field['key'] ) . '" value="' . esc_attr( $value ) . '" ' . $required . '>';
                break;

            case 'url':
                $html .= '<input type="url" id="' . esc_attr( $field_id ) . '" name="custom_' . esc_attr( $field['key'] ) . '" value="' . esc_attr( $value ) . '" ' . $required . '>';
                break;

            case 'select':
                $html .= '<select id="' . esc_attr( $field_id ) . '" name="custom_' . esc_attr( $field['key'] ) . '" ' . $required . '>';
                if ( ! empty( $field['options'] ) ) {
                    foreach ( $field['options'] as $option_value => $option_label ) {
                        $selected = selected( $value, $option_value, false );
                        $html .= '<option value="' . esc_attr( $option_value ) . '" ' . $selected . '>' . esc_html( $option_label ) . '</option>';
                    }
                }
                $html .= '</select>';
                break;

            case 'checkbox':
                $checked = checked( $value, '1', false );
                $html .= '<label><input type="checkbox" id="' . esc_attr( $field_id ) . '" name="custom_' . esc_attr( $field['key'] ) . '" value="1" ' . $checked . '> ' . esc_html( $field['label'] ) . '</label>';
                break;

            default: // text
                $html .= '<input type="text" id="' . esc_attr( $field_id ) . '" name="custom_' . esc_attr( $field['key'] ) . '" value="' . esc_attr( $value ) . '" ' . $required . '>';
                break;
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Validate custom field value
     */
    public function validate_field( $field, $value ) {
        if ( ! empty( $field['required'] ) && empty( $value ) ) {
            return new WP_Error( 'required_field', sprintf( __( '%s is required.', 'simple-checkout-location' ), $field['label'] ) );
        }

        switch ( $field['type'] ) {
            case 'email':
                if ( ! empty( $value ) && ! is_email( $value ) ) {
                    return new WP_Error( 'invalid_email', sprintf( __( '%s must be a valid email address.', 'simple-checkout-location' ), $field['label'] ) );
                }
                break;

            case 'url':
                if ( ! empty( $value ) && ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
                    return new WP_Error( 'invalid_url', sprintf( __( '%s must be a valid URL.', 'simple-checkout-location' ), $field['label'] ) );
                }
                break;

            case 'number':
                if ( ! empty( $value ) && ! is_numeric( $value ) ) {
                    return new WP_Error( 'invalid_number', sprintf( __( '%s must be a number.', 'simple-checkout-location' ), $field['label'] ) );
                }
                break;
        }

        return true;
    }

    /**
     * Get all fields (core + custom) for rendering
     */
    public function get_all_fields_for_form() {
        $core_fields = $this->get_core_fields_config();
        $custom_fields = $this->get_custom_fields();

        // Merge and sort by order
        $all_fields = array_merge( $core_fields, $custom_fields );

        usort( $all_fields, function( $a, $b ) {
            $order_a = isset( $a['order'] ) ? $a['order'] : 0;
            $order_b = isset( $b['order'] ) ? $b['order'] : 0;
            return $order_a - $order_b;
        } );

        return $all_fields;
    }

    /**
     * Core fields configuration
     */
    private function get_core_fields_config() {
        return [
            [
                'key'      => 'address_name',
                'label'    => __( 'Address Name', 'simple-checkout-location' ),
                'type'     => 'text',
                'required' => true,
                'order'    => 0,
                'core'     => true,
            ],
            [
                'key'      => 'customer_name',
                'label'    => __( 'Customer Name', 'simple-checkout-location' ),
                'type'     => 'text',
                'required' => true,
                'order'    => 1,
                'core'     => true,
            ],
            [
                'key'      => 'phone_primary',
                'label'    => __( 'Primary Phone', 'simple-checkout-location' ),
                'type'     => 'text',
                'required' => true,
                'order'    => 2,
                'core'     => true,
            ],
            [
                'key'      => 'phone_secondary',
                'label'    => __( 'Secondary Phone', 'simple-checkout-location' ),
                'type'     => 'text',
                'required' => false,
                'order'    => 3,
                'core'     => true,
            ],
            [
                'key'      => 'address_details',
                'label'    => __( 'Address Details', 'simple-checkout-location' ),
                'type'     => 'textarea',
                'required' => false,
                'order'    => 4,
                'core'     => true,
            ],
            [
                'key'      => 'zone',
                'label'    => __( 'Zone', 'simple-checkout-location' ),
                'type'     => 'text',
                'required' => false,
                'order'    => 5,
                'core'     => true,
            ],
            [
                'key'      => 'notes_customer',
                'label'    => __( 'Customer Notes', 'simple-checkout-location' ),
                'type'     => 'textarea',
                'required' => false,
                'order'    => 6,
                'core'     => true,
            ],
            [
                'key'      => 'notes_internal',
                'label'    => __( 'Internal Notes', 'simple-checkout-location' ),
                'type'     => 'textarea',
                'required' => false,
                'order'    => 7,
                'core'     => true,
            ],
        ];
    }
}
