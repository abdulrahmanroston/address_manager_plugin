(function($) {
    'use strict';

    let map, marker, searchBox;
    let selectedLocation = null;
    const defaultCenter = { lat: 30.0444, lng: 31.2357 };
    let currentMode = 'add';
    let editingAddressId = null;

    $(document).ready(function() {
        initAddressManager();
        initLocationSelector();
        
        // ✅ تحميل العنوان الافتراضي
        const $defaultAddress = $('input[name="scl_selected_address_id"]:checked');
        if ($defaultAddress.length > 0) {
            const addressId = $defaultAddress.val();
            if (addressId) {
                setTimeout(function() {
                    loadAddressAndUpdateCheckout(addressId);
                }, 500);
            }
        }
        
        // ✅ للضيوف: ملء الحقول والمواعيد
        if (!sclData.isLoggedIn) {
            const guestCity = $('#scl_guest_city').val();
            if (guestCity) {
                loadDeliverySchedule(guestCity);
            }
        } else {
            // ✅ للمستخدمين: تحميل المواعيد من المنطقة المحددة
            const selectedZone = $('#scl_zone').val();
            if (selectedZone) {
                loadDeliverySchedule(selectedZone);
            }
        }
    });

    function initAddressManager() {
        // ✅ اختيار عنوان محفوظ
        $(document).on('change', 'input[name="scl_selected_address_id"]', function() {
            const addressId = $(this).val();
            
            $('.scl-address-card').removeClass('selected');
            $(this).closest('.scl-address-card').addClass('selected');
            $('#scl-final-address-id').val(addressId);
            
            loadAddressAndUpdateCheckout(addressId);
        });

        // إظهار فورم إضافة عنوان
        $('#scl-show-add-address').on('click', function() {
            currentMode = 'add';
            editingAddressId = null;
            $('#scl-form-title').text('Add New Address');
            $('#scl-save-address-btn').text('Save & Continue');
            resetAddForm();
            $('#scl-add-address-form').slideDown(300);
            $('.scl-saved-addresses').slideUp(300);
        });

        // إلغاء
        $('#scl-cancel-add-address').on('click', function() {
            currentMode = 'add';
            editingAddressId = null;
            resetAddForm();
            $('#scl-add-address-form').slideUp(300);
            $('.scl-saved-addresses').slideDown(300);
        });

        // تعديل
        $(document).on('click', '.scl-edit-address-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            editAddress($(this).data('address-id'));
        });

        // حذف
        $(document).on('click', '.scl-delete-address-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (confirm(sclData.strings.confirmDelete)) {
                deleteAddress($(this).data('address-id'));
            }
        });

        // حفظ/تحديث
        $('#scl-save-address-btn').on('click', function() {
            if (currentMode === 'edit' && editingAddressId) {
                updateAddress();
            } else {
                saveNewAddress();
            }
        });

        // ✅ تحديث عند تغيير المنطقة
        $(document).on('change', '#scl_zone, #scl_guest_city', function() {
            const zoneName = $(this).val();
            
            $('input[name="billing_city"]').val(zoneName).trigger('change');
            $(document.body).trigger('update_checkout');
            
            loadDeliverySchedule(zoneName);
        });
        
        // ✅ للضيوف: ملء الحقول فوراً
        $(document).on('change blur keyup', '#scl_guest_name, #scl_guest_phone, #scl_guest_address, #scl_guest_city', function() {
            const guestName = $('#scl_guest_name').val() || 'Guest';
            const guestPhone = $('#scl_guest_phone').val() || '0000000000';
            const guestAddress = $('#scl_guest_address').val() || 'Address';
            const guestCity = $('#scl_guest_city').val() || 'City';
            
            // Billing fields
            $('input[name="billing_first_name"]').val(guestName).trigger('change');
            $('input[name="billing_last_name"]').val('.').trigger('change');
            $('input[name="billing_phone"]').val(guestPhone).trigger('change');
            $('input[name="billing_address_1"]').val(guestAddress).trigger('change');
            $('input[name="billing_city"]').val(guestCity).trigger('change');
            $('input[name="billing_postcode"]').val('00000').trigger('change');
            $('input[name="billing_country"]').val('EG').trigger('change');
            $('input[name="billing_state"]').val('').trigger('change');
            
            // Shipping fields
            $('input[name="shipping_first_name"]').val(guestName).trigger('change');
            $('input[name="shipping_last_name"]').val('.').trigger('change');
            $('input[name="shipping_address_1"]').val(guestAddress).trigger('change');
            $('input[name="shipping_city"]').val(guestCity).trigger('change');
            $('input[name="shipping_postcode"]').val('00000').trigger('change');
            $('input[name="shipping_country"]').val('EG').trigger('change');
            $('input[name="shipping_state"]').val('').trigger('change');
            
            console.log('SCL: Updated guest fields');
        });

    }

    // ✅ تحميل العنوان وتحديث الفاتورة
  

    function loadAddressAndUpdateCheckout(addressId) {
    $.ajax({
        url: sclData.ajaxUrl,
        type: 'POST',
        data: {
            action: 'scl_load_address_data',
            nonce: sclData.nonce,
            address_id: addressId
        },
        success: function(response) {
            if (response.success && response.data) {
                const addr = response.data;
                
                // ✅ ملء Location
                if (addr.location_url && addr.location_lat && addr.location_lng) {
                    $('#scl_location_url').val(addr.location_url);
                    $('#scl_location_lat').val(addr.location_lat);
                    $('#scl_location_lng').val(addr.location_lng);
                    
                    selectedLocation = {
                        lat: parseFloat(addr.location_lat),
                        lng: parseFloat(addr.location_lng)
                    };
                    
                    updateLocationStatus(true);
                }
                
                // ✅ ملء الحقول المخفية فوراً
                $('input[name="billing_first_name"]').val(addr.customer_name || 'Customer').trigger('change');
                $('input[name="billing_last_name"]').val('.').trigger('change');
                $('input[name="billing_company"]').val(addr.address_name || '').trigger('change');
                $('input[name="billing_phone"]').val(addr.phone_primary || '0000000000').trigger('change');
                $('input[name="billing_address_1"]').val(addr.address_details || addr.address_name || 'Address').trigger('change');
                $('input[name="billing_address_2"]').val(addr.notes_customer || '').trigger('change');
                $('input[name="billing_city"]').val(addr.zone || 'City').trigger('change');
                $('input[name="billing_state"]').val('').trigger('change');
                $('input[name="billing_postcode"]').val('00000').trigger('change');
                $('input[name="billing_country"]').val('EG').trigger('change');
                
                $('input[name="shipping_first_name"]').val(addr.customer_name || 'Customer').trigger('change');
                $('input[name="shipping_last_name"]').val('.').trigger('change');
                $('input[name="shipping_company"]').val(addr.address_name || '').trigger('change');
                $('input[name="shipping_address_1"]').val(addr.address_details || addr.address_name || 'Address').trigger('change');
                $('input[name="shipping_address_2"]').val(addr.notes_customer || '').trigger('change');
                $('input[name="shipping_city"]').val(addr.zone || 'City').trigger('change');
                $('input[name="shipping_state"]').val('').trigger('change');
                $('input[name="shipping_postcode"]').val('00000').trigger('change');
                $('input[name="shipping_country"]').val('EG').trigger('change');
                
                console.log('SCL: Filled all fields from address #' + addressId);
                
                // Trigger WooCommerce checkout update
                $(document.body).trigger('update_checkout');
                
                // تحميل المواعيد
                if (addr.zone) {
                    loadDeliverySchedule(addr.zone);
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('Load address error:', error);
        }
    });
}



    // ✅ تحميل جدول المواعيد
    function loadDeliverySchedule(zoneName) {
        if (!zoneName) {
            $('#scl-schedule-fields').hide();
            $('#scl-schedule-placeholder').show();
            $('#scl_delivery_date').html('<option value="">Select delivery date</option>');
            $('#scl_delivery_time').html('<option value="">Select delivery time</option>');
            return;
        }
        
        $.ajax({
            url: sclData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'scl_get_delivery_schedule',
                nonce: sclData.nonce,
                zone_name: zoneName
            },
            success: function(response) {
                if (response.success && response.data) {
                    const schedule = response.data;
                    
                    $('#scl_delivery_date').html('<option value="">Select delivery date</option>');
                    if (schedule.available_dates && schedule.available_dates.length > 0) {
                        schedule.available_dates.forEach(function(dateObj) {
                            $('#scl_delivery_date').append(
                                '<option value="' + dateObj.date + '">' + dateObj.display + '</option>'
                            );
                        });
                    } else {
                        $('#scl_delivery_date').append('<option value="" disabled>No dates available</option>');
                    }
                    
                    $('#scl_delivery_time').html('<option value="">Select delivery time</option>');
                    if (schedule.delivery_times && schedule.delivery_times.length > 0) {
                        schedule.delivery_times.forEach(function(time) {
                            $('#scl_delivery_time').append(
                                '<option value="' + time + '">' + time + '</option>'
                            );
                        });
                    } else {
                        $('#scl_delivery_time').append('<option value="" disabled>No time slots available</option>');
                    }
                    
                    $('#scl-schedule-placeholder').hide();
                    $('#scl-schedule-fields').fadeIn(300);
                } else {
                    console.error('Failed to load delivery schedule');
                    $('#scl-schedule-fields').hide();
                    $('#scl-schedule-placeholder').show();
                }
            },
            error: function(xhr, status, error) {
                console.error('Delivery schedule error:', error);
                $('#scl-schedule-fields').hide();
                $('#scl-schedule-placeholder').show();
            }
        });
    }

    function editAddress(addressId) {
        $.ajax({
            url: sclData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'scl_load_address_data',
                nonce: sclData.nonce,
                address_id: addressId
            },
            success: function(response) {
                if (response.success && response.data) {
                    const addr = response.data;
                    
                    currentMode = 'edit';
                    editingAddressId = addressId;
                    $('#scl_edit_address_id').val(addressId);
                    
                    $('#scl_address_name').val(addr.address_name);
                    $('#scl_customer_name').val(addr.customer_name);
                    $('#scl_phone_primary').val(addr.phone_primary);
                    $('#scl_phone_secondary').val(addr.phone_secondary || '');
                    $('#scl_address_details').val(addr.address_details || '');
                    $('#scl_zone').val(addr.zone || '');
                    $('#scl_notes_customer').val(addr.notes_customer || '');
                    
                    if (addr.location_url && addr.location_lat && addr.location_lng) {
                        $('#scl_location_url').val(addr.location_url);
                        $('#scl_location_lat').val(addr.location_lat);
                        $('#scl_location_lng').val(addr.location_lng);
                        
                        selectedLocation = {
                            lat: parseFloat(addr.location_lat),
                            lng: parseFloat(addr.location_lng)
                        };
                        
                        updateLocationStatus(true);
                    }
                    
                    $('#scl-form-title').text('Edit Address');
                    $('#scl-save-address-btn').text('Update Address');
                    
                    $('#scl-add-address-form').slideDown(300);
                    $('.scl-saved-addresses').slideUp(300);
                    
                    $('html, body').animate({
                        scrollTop: $('#scl-add-address-form').offset().top - 100
                    }, 500);
                }
            },
            error: function() {
                alert('Failed to load address data');
            }
        });
    }

  function updateAddress() {
    const addressData = {
        action: 'scl_update_address',
        nonce: sclData.nonce,
        address_id: editingAddressId,
        address_name: $('#scl_address_name').val().trim(),
        customer_name: $('#scl_customer_name').val().trim(),
        phone_primary: $('#scl_phone_primary').val().trim(),
        phone_secondary: $('#scl_phone_secondary').val().trim(),
        address_details: $('#scl_address_details').val().trim(),
        zone: $('#scl_zone').val(),
        notes_customer: $('#scl_notes_customer').val().trim(),
        location_url: $('#scl_location_url').val(),
        location_lat: $('#scl_location_lat').val(),
        location_lng: $('#scl_location_lng').val()
    };

    // ✅ Validation شامل
    if (!addressData.address_name) {
        alert('Please enter Address Name (e.g., Home, Work)');
        $('#scl_address_name').focus();
        return;
    }
    
    if (!addressData.customer_name) {
        alert('Please enter Recipient Name');
        $('#scl_customer_name').focus();
        return;
    }
    
    if (!addressData.phone_primary) {
        alert('Please enter Primary Phone Number');
        $('#scl_phone_primary').focus();
        return;
    }
    
    // ✅ التحقق من صحة رقم الهاتف
    const phoneRegex = /^(01)[0-9]{9}$/;
    const cleanPhone = addressData.phone_primary.replace(/[\s\-\+]/g, '').replace(/^(\+20)/, '0');
    
    if (!phoneRegex.test(cleanPhone)) {
        alert('Please enter a valid Egyptian phone number (e.g., 01012345678)');
        $('#scl_phone_primary').focus();
        return;
    }
    
    if (!addressData.zone || addressData.zone === '') {
        alert('Please select your delivery zone');
        $('#scl_zone').focus();
        return;
    }
    
    if (!addressData.address_details || addressData.address_details.length < 10) {
        alert('Please enter detailed address (street, building, floor, apartment)');
        $('#scl_address_details').focus();
        return;
    }

    if (!addressData.location_url || !addressData.location_lat || !addressData.location_lng) {
        alert('Please select your exact location on the map');
        $('#scl-select-location-btn').focus();
        return;
    }

    const $btn = $('#scl-save-address-btn');
    const originalText = $btn.text();
    $btn.prop('disabled', true).text('Updating...');

    $.ajax({
        url: sclData.ajaxUrl,
        type: 'POST',
        data: addressData,
        success: function(response) {
            if (response.success) {
                showSuccessMessage('Address updated successfully!');
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                alert(response.data.message || 'Failed to update address');
                $btn.prop('disabled', false).text(originalText);
            }
        },
        error: function() {
            alert('Failed to update address');
            $btn.prop('disabled', false).text(originalText);
        }
    });
}

    function deleteAddress(addressId) {
        const $card = $('.scl-address-card[data-address-id="' + addressId + '"]');
        $card.css('opacity', '0.5');

        $.ajax({
            url: sclData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'scl_delete_address',
                nonce: sclData.nonce,
                address_id: addressId
            },
            success: function(response) {
                if (response.success) {
                    $card.slideUp(300, function() {
                        $(this).remove();
                        
                        if ($('.scl-address-card').length === 0) {
                            $('.scl-addresses-grid').html(
                                '<p class="scl-no-addresses">No saved addresses. Please add a new delivery address below.</p>'
                            );
                            $('#scl-show-add-address').trigger('click');
                        }
                    });
                    
                    showSuccessMessage('Address deleted successfully');
                } else {
                    $card.css('opacity', '1');
                    alert('Failed to delete address');
                }
            },
            error: function() {
                $card.css('opacity', '1');
                alert('Failed to delete address');
            }
        });
    }


    function saveNewAddress() {
    const addressData = {
        address_name: $('#scl_address_name').val().trim(),
        customer_name: $('#scl_customer_name').val().trim(),
        phone_primary: $('#scl_phone_primary').val().trim(),
        phone_secondary: $('#scl_phone_secondary').val().trim(),
        address_details: $('#scl_address_details').val().trim(),
        zone: $('#scl_zone').val(),
        notes_customer: $('#scl_notes_customer').val().trim(),
        location_url: $('#scl_location_url').val(),
        location_lat: $('#scl_location_lat').val(),
        location_lng: $('#scl_location_lng').val(),
        is_default_billing: 1
    };

    // ✅ Validation شامل
    if (!addressData.address_name) {
        alert('Please enter Address Name (e.g., Home, Work)');
        $('#scl_address_name').focus();
        return;
    }
    
    if (!addressData.customer_name) {
        alert('Please enter Recipient Name');
        $('#scl_customer_name').focus();
        return;
    }
    
    if (!addressData.phone_primary) {
        alert('Please enter Primary Phone Number');
        $('#scl_phone_primary').focus();
        return;
    }
    
    // ✅ التحقق من صحة رقم الهاتف المصري
    const phoneRegex = /^(01)[0-9]{9}$/;
    const cleanPhone = addressData.phone_primary.replace(/[\s\-\+]/g, '').replace(/^(\+20)/, '0');
    
    if (!phoneRegex.test(cleanPhone)) {
        alert('Please enter a valid Egyptian phone number (e.g., 01012345678)');
        $('#scl_phone_primary').focus();
        return;
    }
    
    if (!addressData.zone || addressData.zone === '') {
        alert('Please select your delivery zone');
        $('#scl_zone').focus();
        return;
    }
    
    if (!addressData.address_details || addressData.address_details.length < 10) {
        alert('Please enter detailed address (street, building, floor, apartment)');
        $('#scl_address_details').focus();
        return;
    }

    if (!addressData.location_url || !addressData.location_lat || !addressData.location_lng) {
        alert('Please select your exact location on the map');
        $('#scl-select-location-btn').focus();
        return;
    }

    const $btn = $('#scl-save-address-btn');
    const originalText = $btn.text();
    $btn.prop('disabled', true).text(sclData.strings.saving);

    $.ajax({
        url: sclData.restUrl,
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(addressData),
        beforeSend: function(xhr) {
            xhr.setRequestHeader('X-WP-Nonce', sclData.restNonce);
        },
        success: function(response) {
            console.log('SCL: Create address response:', response);
            
            let addressId = null;
            
            if (response && response.success) {
                addressId = response.id || (response.data && response.data.id);
            } else if (response && response.id) {
                addressId = response.id;
            }
            
            console.log('SCL: Extracted address ID:', addressId);
            
            if (addressId && addressId > 0) {
                $('#scl-final-address-id').val(addressId);
                $('#scl_location_url').val(addressData.location_url);
                $('#scl_location_lat').val(addressData.location_lat);
                $('#scl_location_lng').val(addressData.location_lng);
                
                showSuccessMessage('Address saved successfully!');
                
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                console.error('SCL: Invalid address ID in response:', response);
                alert('Error: Failed to create address (invalid response)');
                $btn.prop('disabled', false).text(originalText);
            }
        },
        error: function(xhr, status, error) {
            console.error('SCL: Create address error:', { xhr, status, error });
            
            let errorMsg = 'Failed to save address';
            
            if (xhr.responseJSON) {
                console.log('SCL: Error response JSON:', xhr.responseJSON);
                
                if (xhr.responseJSON.success && xhr.responseJSON.id) {
                    console.log('SCL: Actually succeeded! Using error handler fallback');
                    
                    const addressId = xhr.responseJSON.id;
                    $('#scl-final-address-id').val(addressId);
                    $('#scl_location_url').val(addressData.location_url);
                    $('#scl_location_lat').val(addressData.location_lat);
                    $('#scl_location_lng').val(addressData.location_lng);
                    
                    showSuccessMessage('Address saved successfully!');
                    
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                    return;
                }
                
                if (xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
            } else if (xhr.status === 0) {
                errorMsg = 'Network error. Please check your connection.';
            } else if (xhr.status === 403) {
                errorMsg = 'Permission denied. Please log in again.';
            } else if (xhr.status === 500) {
                errorMsg = 'Server error. Please try again.';
            }
            
            alert(errorMsg);
            $btn.prop('disabled', false).text(originalText);
        }
    });
}


    function resetAddForm() {
        $('#scl_edit_address_id').val('');
        $('#scl_address_name').val('');
        $('#scl_customer_name').val('');
        $('#scl_phone_primary').val('');
        $('#scl_phone_secondary').val('');
        $('#scl_address_details').val('');
        $('#scl_zone').val('');
        $('#scl_notes_customer').val('');
        $('#scl_location_url').val('');
        $('#scl_location_lat').val('');
        $('#scl_location_lng').val('');
        selectedLocation = null;
        updateLocationStatus(false);
    }

    // ================== Location Selector ==================

    function initLocationSelector() {
        $(document).on('click', '#scl-select-location-btn', function(e) {
            e.preventDefault();
            openModal();
        });

        $(document).on('click', '#close-location-modal', function(e) {
            e.preventDefault();
            closeModal();
        });

        $(document).on('click', '#location-modal', function(e) {
            if ($(e.target).is('#location-modal')) {
                closeModal();
            }
        });

        $(document).on('click', '#confirm-location-btn', function(e) {
            e.preventDefault();
            if (selectedLocation) {
                saveLocation();
                closeModal();
            }
        });

        $(document).on('click', '#use-current-location', function(e) {
            e.preventDefault();
            requestCurrentLocation();
        });
    }

    function openModal() {
        $('#location-modal').fadeIn(200);
        
        setTimeout(function() {
            if (!map) {
                initMap();
            } else {
                google.maps.event.trigger(map, 'resize');
                if (selectedLocation) {
                    map.setCenter(selectedLocation);
                    marker.setPosition(selectedLocation);
                    marker.setVisible(true);
                }
            }
        }, 300);
    }

    function closeModal() {
        $('#location-modal').fadeOut(200);
    }

    function initMap() {
        if (map) {
            google.maps.event.trigger(map, 'resize');
            return;
        }

        const mapContainer = document.getElementById('map-container');
        if (!mapContainer) return;

        const center = selectedLocation || defaultCenter;

        try {
            map = new google.maps.Map(mapContainer, {
                center: center,
                zoom: selectedLocation ? 15 : 12,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: false,
                zoomControl: true,
                gestureHandling: 'greedy'
            });

            marker = new google.maps.Marker({
                map: map,
                draggable: true,
                animation: google.maps.Animation.DROP
            });

            if (selectedLocation) {
                marker.setPosition(selectedLocation);
                marker.setVisible(true);
            }

            map.addListener('click', function(e) {
                setLocation(e.latLng);
            });

            marker.addListener('dragend', function() {
                setLocation(marker.getPosition());
            });

            initSearchBox();
        } catch (error) {
            console.error('Map initialization error:', error);
        }
    }

    function initSearchBox() {
        var input = document.getElementById('location-search-input');
        if (!input) return;

        try {
            searchBox = new google.maps.places.SearchBox(input);

            map.addListener('bounds_changed', function() {
                searchBox.setBounds(map.getBounds());
            });

            var pacContainerFixer = setInterval(function() {
                var pacContainer = document.querySelector('.pac-container');
                if (pacContainer && pacContainer.children.length > 0) {
                    pacContainer.style.display = 'block';
                    pacContainer.style.zIndex = '10000000';
                    pacContainer.style.position = 'absolute';
                }
            }, 100);

            input.addEventListener('input', function(e) {
                setTimeout(function() {
                    var pac = document.querySelector('.pac-container');
                    if (pac && pac.children.length > 0) {
                        pac.style.display = 'block';
                        pac.style.zIndex = '10000000';
                    }
                }, 200);
            });

            setTimeout(function() {
                clearInterval(pacContainerFixer);
            }, 30000);

            searchBox.addListener('places_changed', function() {
                var places = searchBox.getPlaces();
                if (!places || places.length === 0) return;

                var place = places[0];
                if (!place.geometry || !place.geometry.location) return;

                var lat = place.geometry.location.lat();
                var lng = place.geometry.location.lng();

                marker.setPosition({ lat: lat, lng: lng });
                marker.setVisible(true);

                if (place.geometry.viewport) {
                    map.fitBounds(place.geometry.viewport);
                } else {
                    map.setCenter({ lat: lat, lng: lng });
                    map.setZoom(15);
                }

                var latLng = new google.maps.LatLng(lat, lng);
                setLocation(latLng);
            });
        } catch (error) {
            console.error('SearchBox initialization error:', error);
        }
    }

    function setLocation(latLng) {
        selectedLocation = {
            lat: latLng.lat(),
            lng: latLng.lng()
        };

        marker.setPosition(latLng);
        marker.setVisible(true);
        map.panTo(latLng);

        $('#confirm-location-btn').prop('disabled', false).addClass('active');
    }

    function requestCurrentLocation() {
        if (!navigator.geolocation) {
            alert('Geolocation not supported');
            return;
        }

        const btn = $('#use-current-location');
        const originalHtml = btn.html();
        btn.html('<span>Getting location...</span>').prop('disabled', true);

        navigator.geolocation.getCurrentPosition(
            function(position) {
                var pos = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude
                };

                var latLng = new google.maps.LatLng(pos.lat, pos.lng);
                setLocation(latLng);
                map.setCenter(pos);
                map.setZoom(15);

                btn.html(originalHtml).prop('disabled', false);
            },
            function(error) {
                btn.html(originalHtml).prop('disabled', false);
                alert('Unable to get your location. Please enable location permissions.');
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            }
        );
    }

    function saveLocation() {
        if (!selectedLocation) return;

        const mapUrl = `https://www.google.com/maps?q=${selectedLocation.lat},${selectedLocation.lng}`;

        $('#scl_location_url').val(mapUrl);
        $('#scl_location_lat').val(selectedLocation.lat);
        $('#scl_location_lng').val(selectedLocation.lng);

        updateLocationStatus(true);
        showSuccessMessage(sclData.strings.locationSelected);
    }

    function updateLocationStatus(hasLocation) {
        const $status = $('#scl-location-status');
        if (hasLocation) {
            $status.html('✓ ' + sclData.strings.locationSelected).addClass('success');
        } else {
            $status.html(sclData.strings.selectLocation).removeClass('success');
        }
    }

    function showSuccessMessage(message) {
        const $msg = $('<div class="scl-success-msg">' + message + '</div>');
        $('.scl-checkout-section').prepend($msg);
        
        setTimeout(function() {
            $msg.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }


    // ✅ ملء الحقول المخفية قبل إرسال الطلب
$(document.body).on('checkout_place_order', function() {
    console.log('SCL: Place order triggered');
    
    if (sclData.isLoggedIn) {
        // للمستخدمين المسجلين
        const selectedAddressId = $('#scl-final-address-id').val() || $('input[name="scl_selected_address_id"]:checked').val();
        
        console.log('SCL: Selected address ID:', selectedAddressId);
        
        if (!selectedAddressId || selectedAddressId === '' || selectedAddressId === '0') {
            alert('Please select a delivery address');
            return false;
        }
        
        const $selectedCard = $('.scl-address-card.selected');
        if ($selectedCard.length > 0) {
            const addressName = $selectedCard.find('.scl-address-card-header strong').text().trim();
            const customerName = $selectedCard.find('.scl-customer-name').text().trim();
            const phone = $selectedCard.find('.scl-phone').text().trim();
            const zone = $selectedCard.find('.scl-zone').text().trim();
            const details = $selectedCard.find('.scl-details').text().trim();
            
            console.log('SCL: Filling fields from card:', { addressName, customerName, phone, zone });
            
            $('input[name="billing_first_name"]').val(customerName || 'Customer');
            $('input[name="billing_last_name"]').val('.');
            $('input[name="billing_company"]').val(addressName || 'Address');
            $('input[name="billing_phone"]').val(phone || '0000000000');
            $('input[name="billing_address_1"]').val(details || addressName || 'Address');
            $('input[name="billing_city"]').val(zone || 'City');
            $('input[name="billing_postcode"]').val('00000');
            $('input[name="billing_country"]').val('EG');
            $('input[name="billing_state"]').val('');
            
            $('input[name="shipping_first_name"]').val(customerName || 'Customer');
            $('input[name="shipping_last_name"]').val('.');
            $('input[name="shipping_company"]').val(addressName || 'Address');
            $('input[name="shipping_address_1"]').val(details || addressName || 'Address');
            $('input[name="shipping_city"]').val(zone || 'City');
            $('input[name="shipping_postcode"]').val('00000');
            $('input[name="shipping_country"]').val('EG');
            $('input[name="shipping_state"]').val('');
        }
    } else {
        // ✅ للضيوف - validation كامل
        const guestName = $('#scl_guest_name').val().trim();
        const guestPhone = $('#scl_guest_phone').val().trim();
        const guestAddress = $('#scl_guest_address').val().trim();
        const guestCity = $('#scl_guest_city').val();
        
        console.log('SCL: Guest data:', { guestName, guestPhone, guestAddress, guestCity });
        
        if (!guestName || guestName === '') {
            alert('Please enter your name');
            $('#scl_guest_name').focus();
            return false;
        }
        
        if (!guestPhone || guestPhone === '') {
            alert('Please enter your phone number');
            $('#scl_guest_phone').focus();
            return false;
        }
        
        // ✅ التحقق من رقم الهاتف للضيوف
        const phoneRegex = /^(01)[0-9]{9}$/;
        const cleanPhone = guestPhone.replace(/[\s\-\+]/g, '').replace(/^(\+20)/, '0');
        
        if (!phoneRegex.test(cleanPhone)) {
            alert('Please enter a valid Egyptian phone number (e.g., 01012345678)');
            $('#scl_guest_phone').focus();
            return false;
        }
        
        if (!guestAddress || guestAddress.length < 10) {
            alert('Please enter detailed delivery address');
            $('#scl_guest_address').focus();
            return false;
        }
        
        if (!guestCity || guestCity === '') {
            alert('Please select your city/zone');
            $('#scl_guest_city').focus();
            return false;
        }
        
        $('input[name="billing_first_name"]').val(guestName);
        $('input[name="billing_last_name"]').val('.');
        $('input[name="billing_phone"]').val(guestPhone);
        $('input[name="billing_address_1"]').val(guestAddress);
        $('input[name="billing_city"]').val(guestCity);
        $('input[name="billing_postcode"]').val('00000');
        $('input[name="billing_country"]').val('EG');
        $('input[name="billing_state"]').val('');
        
        $('input[name="shipping_first_name"]').val(guestName);
        $('input[name="shipping_last_name"]').val('.');
        $('input[name="shipping_address_1"]').val(guestAddress);
        $('input[name="shipping_city"]').val(guestCity);
        $('input[name="shipping_postcode"]').val('00000');
        $('input[name="shipping_country"]').val('EG');
        $('input[name="shipping_state"]').val('');
    }
    
    // ✅ التحقق من الموقع
    const locationUrl = $('#scl_location_url').val();
    if (!locationUrl || locationUrl === '') {
        alert('Please select your exact delivery location on the map');
        $('#scl-select-location-btn').focus();
        return false;
    }
    
    // ✅ التحقق من التاريخ والوقت
    const deliveryDate = $('#scl_delivery_date').val();
    const deliveryTime = $('#scl_delivery_time').val();
    
    if (!deliveryDate || deliveryDate === '') {
        alert('Please select a delivery date');
        $('#scl_delivery_date').focus();
        return false;
    }
    
    if (!deliveryTime || deliveryTime === '') {
        alert('Please select a delivery time slot');
        $('#scl_delivery_time').focus();
        return false;
    }
    
    // ✅ إنشاء hidden fields وإرسال البيانات
    if (!$('input[name="delivery_date"]').length) {
        $('<input>').attr({ type: 'hidden', name: 'delivery_date' }).appendTo('form.checkout');
    }
    if (!$('input[name="delivery_time"]').length) {
        $('<input>').attr({ type: 'hidden', name: 'delivery_time' }).appendTo('form.checkout');
    }
    
    $('input[name="delivery_date"]').val(deliveryDate);
    $('input[name="delivery_time"]').val(deliveryTime);
    
    console.log('SCL: Delivery data set ->', { deliveryDate, deliveryTime });
    console.log('SCL: All validations passed ✅');
    
    return true;
});


})(jQuery);
