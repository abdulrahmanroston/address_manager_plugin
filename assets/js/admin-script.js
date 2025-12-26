(function($) {
    'use strict';

    $(document).ready(function() {
        
        // Load addresses on page load
        if ($('#scl-addresses-container').length) {
            loadAddresses();
        }

        // Search functionality
        $('#scl-search-customer').on('input', debounce(function() {
            loadAddresses();
        }, 500));

        // Filter by user
        $('#scl-filter-user').on('change', function() {
            loadAddresses();
        });

        // Reset filters
        $('#scl-reset-filters').on('click', function() {
            $('#scl-search-customer').val('');
            $('#scl-filter-user').val('');
            loadAddresses();
        });

        // Add new address button
        $('#scl-add-new-btn').on('click', function(e) {
            e.preventDefault();
            openModal();
        });

        // Edit address
        $(document).on('click', '.scl-edit-address', function() {
            var id = $(this).data('id');
            loadAddressForEdit(id);
        });

        // Delete address
        $(document).on('click', '.scl-delete-address', function() {
            if (!confirm(sclAdminData.strings.confirmDelete)) {
                return;
            }
            var id = $(this).data('id');
            deleteAddress(id);
        });

        // Set default address
        $(document).on('click', '.scl-set-default', function() {
            var id = $(this).data('id');
            var userId = $(this).data('user');
            setDefaultAddress(id, userId);
        });

        // Save address
        $('#scl-save-address-btn').on('click', function() {
            saveAddress();
        });

        // Close modal
        $('.scl-modal-close').on('click', function() {
            closeModal();
        });

        // Close modal on outside click
        $('.scl-modal').on('click', function(e) {
            if ($(e.target).is('.scl-modal')) {
                closeModal();
            }
        });

    });

    function loadAddresses() {
        var search = $('#scl-search-customer').val();
        var userId = $('#scl-filter-user').val();

        $.ajax({
            url: sclAdminData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'scl_admin_get_addresses',
                nonce: sclAdminData.nonce,
                search: search,
                user_id: userId
            },
            success: function(response) {
                if (response.success) {
                    $('#scl-addresses-container').html(response.data.html);
                }
            },
            error: function() {
                $('#scl-addresses-container').html('<div class="notice notice-error"><p>' + sclAdminData.strings.error + '</p></div>');
            }
        });
    }

    function openModal(title) {
        $('#scl-modal-title').text(title || 'Add Address');
        $('#scl-address-form')[0].reset();
        $('#address-id').val('');
        $('#scl-modal').fadeIn(200);
    }

    function closeModal() {
        $('#scl-modal').fadeOut(200);
    }

    function loadAddressForEdit(id) {
        $.ajax({
            url: sclAdminData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'scl_admin_get_address',
                nonce: sclAdminData.nonce,
                id: id
            },
            success: function(response) {
                if (response.success) {
                    var address = response.data;
                    
                    $('#scl-modal-title').text('Edit Address');
                    $('#address-id').val(address.id);
                    $('#user-id').val(address.user_id);
                    $('#address-name').val(address.address_name);
                    $('#customer-name').val(address.customer_name);
                    $('#phone-primary').val(address.phone_primary);
                    $('#phone-secondary').val(address.phone_secondary);
                    $('#address-details').val(address.address_details);
                    $('#zone').val(address.zone);
                    $('#location-url').val(address.location_url);
                    $('#location-lat').val(address.location_lat);
                    $('#location-lng').val(address.location_lng);
                    $('#notes-customer').val(address.notes_customer);
                    $('#notes-internal').val(address.notes_internal);
                    $('#is-default-billing').prop('checked', parseInt(address.is_default_billing) === 1);
                    
                    $('#scl-modal').fadeIn(200);
                }
            }
        });
    }

    function saveAddress() {
        var formData = $('#scl-address-form').serialize();
        formData += '&action=scl_admin_save_address&nonce=' + sclAdminData.nonce;

        $('#scl-save-address-btn').prop('disabled', true).text('Saving...');

        $.ajax({
            url: sclAdminData.ajaxUrl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    closeModal();
                    loadAddresses();
                    showNotice(sclAdminData.strings.success, 'success');
                } else {
                    showNotice(response.data.message || sclAdminData.strings.error, 'error');
                }
            },
            error: function() {
                showNotice(sclAdminData.strings.error, 'error');
            },
            complete: function() {
                $('#scl-save-address-btn').prop('disabled', false).text('Save Address');
            }
        });
    }

    function deleteAddress(id) {
        $.ajax({
            url: sclAdminData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'scl_admin_delete_address',
                nonce: sclAdminData.nonce,
                id: id
            },
            success: function(response) {
                if (response.success) {
                    loadAddresses();
                    showNotice('Address deleted successfully', 'success');
                }
            },
            error: function() {
                showNotice(sclAdminData.strings.error, 'error');
            }
        });
    }

    function setDefaultAddress(id, userId) {
        $.ajax({
            url: sclAdminData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'scl_admin_set_default',
                nonce: sclAdminData.nonce,
                id: id,
                user_id: userId
            },
            success: function(response) {
                if (response.success) {
                    loadAddresses();
                    showNotice('Default address updated', 'success');
                }
            }
        });
    }

    function showNotice(message, type) {
        var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        var notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.scl-admin-wrap h1').after(notice);
        
        setTimeout(function() {
            notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }

    function debounce(func, wait) {
        var timeout;
        return function() {
            var context = this, args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(function() {
                func.apply(context, args);
            }, wait);
        };
    }

})(jQuery);
