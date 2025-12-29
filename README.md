# Simple Checkout Location Selector for WooCommerce

[![Version](https://img.shields.io/badge/version-3.1.1-blue.svg)](https://github.com/abdulrahmanroston/address_manager_plugin)
[![WordPress](https://img.shields.io/badge/wordpress-5.0%2B-green.svg)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/woocommerce-5.0%2B-purple.svg)](https://woocommerce.com/)
[![License](https://img.shields.io/badge/license-GPL--3.0-red.svg)](LICENSE)

**Professional address management system with interactive location selection for WooCommerce checkout.**

Transform your WooCommerce store's checkout experience with a modern, user-friendly address management system that integrates Google Maps, delivery scheduling, and multi-zone shipping.

---

## 🌟 Key Features

### For Customers

- **📍 Interactive Map Selection**: Choose delivery location directly on Google Maps with real-time coordinates
- **💾 Multiple Saved Addresses**: Save unlimited delivery addresses (home, office, etc.)
- **🔄 Quick Address Switching**: Select from saved addresses at checkout instantly
- **📱 Guest Checkout Support**: Full functionality for non-registered users
- **📅 Delivery Scheduling**: Choose preferred delivery date and time slots per zone
- **✏️ Easy Address Management**: Edit or delete saved addresses from My Account page
- **🏠 Default Address**: Set your preferred address as default

### For Store Owners

- **🗺️ Zone-Based Shipping**: Configure different shipping costs per delivery zone
- **📅 Delivery Schedule Control**: Set available delivery times and closed days per zone
- **🔧 Flexible Zone Management**: Create unlimited zones with Arabic/English names
- **📊 Complete Order Details**: View full address info including map location in admin
- **🔄 Auto-Sync Orders**: Saved addresses sync automatically with existing orders
- **🔌 REST API Ready**: Full API for mobile apps and third-party integrations
- **📱 Mobile Responsive**: Perfect checkout experience on all devices

---

## 📋 Table of Contents

- [Installation](#installation)
- [Configuration](#configuration)
- [Usage Guide](#usage-guide)
- [Technical Documentation](#technical-documentation)
- [REST API Reference](#rest-api-reference)
- [Hooks & Filters](#hooks--filters)
- [Database Schema](#database-schema)
- [Troubleshooting](#troubleshooting)
- [Changelog](#changelog)
- [Support](#support)

---

## 🚀 Installation

### Requirements

- WordPress 5.0 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher
- Google Maps API Key

### Step 1: Download Plugin

```bash
git clone https://github.com/abdulrahmanroston/address_manager_plugin.git
```

Or download the ZIP file from the [latest release](https://github.com/abdulrahmanroston/address_manager_plugin/releases).

### Step 2: Install

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the WordPress 'Plugins' menu
3. Plugin will automatically create required database tables

### Step 3: Configure Google Maps API

1. Get your API key from [Google Cloud Console](https://console.cloud.google.com/)
2. Enable **Maps JavaScript API** and **Places API**
3. Add the API key in `simple-checkout-location.php` line 434:

```php
'https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY_HERE&libraries=places'
```

### Step 4: Configure Zones

1. Go to **WooCommerce → Settings → Shipping**
2. Add a new shipping zone
3. Add **"Zone-Based Delivery"** shipping method
4. Configure your delivery zones in **WooCommerce → Delivery Zones**

---

## ⚙️ Configuration

### Delivery Zones Setup

Navigate to **WooCommerce → Delivery Zones** (Admin menu)

#### Basic Zone Settings

| Field | Description | Example |
|-------|-------------|---------|
| **Zone Name (English)** | English name for the zone | Cairo |
| **Zone Name (Arabic)** | Arabic name (optional) | القاهرة |
| **Shipping Cost** | Delivery fee for this zone | 15.00 |
| **Is Active** | Enable/disable zone | ✓ Yes |
| **Display Order** | Sort order in dropdown | 1 |

#### Delivery Schedule Settings

**Delivery Times** (JSON format):
```json
{
  "morning": {"label": "Morning (9 AM - 12 PM)", "label_ar": "صباحاً (9 ص - 12 م)"},
  "afternoon": {"label": "Afternoon (12 PM - 5 PM)", "label_ar": "ظهراً (12 م - 5 م)"},
  "evening": {"label": "Evening (5 PM - 9 PM)", "label_ar": "مساءً (5 م - 9 م)"}
}
```

**Closed Days** (Comma-separated):
```
Friday,Saturday
```

**Days Ahead**: Minimum days required before delivery (e.g., `3` = orders can be delivered 3 days from now)

---

## 📖 Usage Guide

### For Customers

#### 1. Adding First Address (Checkout)

1. Go to checkout page
2. Fill in delivery details:
   - Address Name (e.g., "Home", "Office")
   - Recipient Name
   - Phone Numbers
   - Street Address
   - Select Delivery Zone
3. Click **"Select Location on Map"**
4. Choose your exact location or search address
5. Click **"Save & Continue"**
6. Select delivery date and time
7. Complete order

#### 2. Using Saved Addresses

1. At checkout, click on any saved address card
2. Address details auto-fill
3. Optionally select delivery schedule
4. Complete order

#### 3. Managing Addresses

Go to **My Account → Addresses**:
- View all saved addresses
- Edit address details
- Delete unwanted addresses
- See default address marked

### For Administrators

#### Viewing Order Address Details

In order admin page, you'll see:
- Complete delivery information
- Customer notes
- **View on Google Maps** button (opens exact location)
- Delivery schedule (date & time)
- Link to saved address (if logged-in customer)

#### Editing Order City

When you change the city/zone in order admin:
- Shipping cost automatically recalculates
- Order total updates
- Note added to order history

---

## 🔧 Technical Documentation

### Architecture

The plugin follows **Repository Pattern** and **SOLID principles** for maintainable, scalable code.

#### Core Components

```
simple-checkout-location.php          # Main plugin file & orchestrator
├── includes/
│   ├── class-address-repository.php      # Database operations
│   ├── class-address-service.php         # Business logic layer
│   ├── class-address-manager.php         # Address CRUD manager
│   ├── class-address-rest-controller.php # REST API endpoints
│   ├── class-zones-repository.php        # Zones database operations
│   ├── class-zone-shipping-method.php    # WooCommerce shipping integration
│   ├── class-admin-page.php              # Admin UI management
│   └── class-custom-fields-manager.php   # Custom field handling
└── assets/
    ├── js/
    │   ├── checkout.js                   # Frontend checkout logic
    │   └── admin.js                      # Admin panel scripts
    └── css/
        ├── checkout.css                  # Checkout styling
        └── admin.css                     # Admin panel styling
```

### Class Overview

#### `Simple_Checkout_Location` (Main Class)
- Singleton pattern implementation
- Hooks registration
- Checkout field management
- Order meta handling

#### `SCL_Address_Repository`
- CRUD operations for addresses
- User address retrieval
- Default address management
- Soft delete implementation

#### `SCL_Address_Service`
- Business logic layer
- Address validation
- Data transformation
- User-specific operations

#### `SCL_Address_REST_Controller`
- RESTful API endpoints
- Authentication & permissions
- Data validation & sanitization
- Response formatting

#### `SCL_Zones_Repository`
- Zone management
- Shipping cost calculation
- Delivery schedule handling
- Available dates generation

---

## 🔌 REST API Reference

Base URL: `https://yoursite.com/wp-json/scl/v1/`

### Authentication

All endpoints require **authentication** except zone listing.

**Methods:**
- Cookie Authentication (for logged-in users)
- JWT Token (for mobile apps)
- Basic Auth (for testing only)

---

### Addresses Endpoints

#### Get All Addresses
```http
GET /addresses
```

**Query Parameters:**
- `user_id` (optional, admin only): Filter by user ID

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "user_id": 5,
      "address_name": "Home",
      "customer_name": "John Doe",
      "phone_primary": "01234567890",
      "phone_secondary": "",
      "location_url": "https://maps.google.com/?q=30.123,31.456",
      "location_lat": "30.123000",
      "location_lng": "31.456000",
      "address_details": "123 Main St, Apt 4",
      "zone": "Cairo",
      "notes_customer": "Ring doorbell twice",
      "notes_internal": "",
      "is_default_billing": 1,
      "is_default_shipping": 0,
      "status": 1,
      "created_at": "2024-01-15 10:30:00",
      "updated_at": "2024-01-15 10:30:00"
    }
  ],
  "count": 1
}
```

---

#### Get Single Address
```http
GET /addresses/{id}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "address_name": "Home",
    ...
  }
}
```

---

#### Create Address
```http
POST /addresses
```

**Request Body:**
```json
{
  "address_name": "Office",
  "customer_name": "John Doe",
  "phone_primary": "01234567890",
  "phone_secondary": "01098765432",
  "location_url": "https://maps.google.com/?q=30.123,31.456",
  "location_lat": "30.123000",
  "location_lng": "31.456000",
  "address_details": "456 Business St, Floor 3",
  "zone": "Giza",
  "notes_customer": "Call when arriving",
  "is_default_billing": false
}
```

**Response:**
```json
{
  "success": true,
  "id": 2,
  "message": "Address created successfully.",
  "data": { ... }
}
```

---

#### Update Address
```http
PUT /addresses/{id}
PATCH /addresses/{id}
```

**Request Body:** (same as create, all fields optional)

---

#### Delete Address
```http
DELETE /addresses/{id}
```

**Response:**
```json
{
  "success": true,
  "deleted": true,
  "message": "Address deleted successfully."
}
```

---

#### Set Default Address
```http
PUT /addresses/{id}/set-default
```

**Request Body:**
```json
{
  "type": "billing"
}
```

---

### Zones Endpoints

#### Get All Zones
```http
GET /zones?active_only=true
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "zone_name": "Cairo",
      "zone_name_ar": "القاهرة",
      "shipping_cost": "15.00",
      "is_active": 1,
      "display_order": 1,
      "delivery_times": {...},
      "delivery_days_ahead": 3,
      "closed_days": "Friday,Saturday"
    }
  ],
  "count": 4
}
```

---

#### Get Zone by Name
```http
GET /zones/by-name/{zone_name}
```

---

#### Get Delivery Schedule
```http
GET /zones/{zone_name}/schedule
```

**Response:**
```json
{
  "success": true,
  "zone_name": "Cairo",
  "zone_name_ar": "القاهرة",
  "shipping_cost": "15.00",
  "delivery_times": {
    "morning": {
      "label": "Morning (9 AM - 12 PM)",
      "label_ar": "صباحاً (9 ص - 12 م)"
    }
  },
  "available_dates": [
    {
      "value": "2024-01-18",
      "label": "Thursday, January 18, 2024",
      "label_ar": "الخميس، 18 يناير 2024"
    }
  ],
  "closed_days": "Friday,Saturday",
  "days_ahead": 3
}
```

---

### Orders Endpoint

#### Create Order with Address
```http
POST /orders
```

**Request Body:**
```json
{
  "address_id": 1,
  "delivery_date": "2024-01-20",
  "delivery_time": "morning",
  "products": [
    {
      "product_id": 123,
      "quantity": 2
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "order_id": 456,
  "message": "Order created successfully.",
  "data": {
    "id": 456,
    "number": "456",
    "status": "pending",
    "total": "150.00"
  }
}
```

---

## 🎣 Hooks & Filters

### Actions

#### `scl_before_address_save`
Fired before saving a new address.

```php
do_action('scl_before_address_save', $address_data, $user_id);
```

#### `scl_after_address_save`
Fired after successfully saving address.

```php
do_action('scl_after_address_save', $address_id, $address_data, $user_id);
```

#### `scl_before_address_delete`
Fired before deleting an address.

```php
do_action('scl_before_address_delete', $address_id, $user_id);
```

#### `scl_order_address_synced`
Fired after syncing address to orders.

```php
do_action('scl_order_address_synced', $address_id, $order_ids);
```

---

### Filters

#### `scl_address_validation_rules`
Modify address validation rules.

```php
add_filter('scl_address_validation_rules', function($rules) {
    $rules['phone_primary']['pattern'] = '/^[0-9]{11}$/';
    return $rules;
});
```

#### `scl_checkout_fields`
Customize checkout form fields.

```php
add_filter('scl_checkout_fields', function($fields) {
    $fields['custom_field'] = [
        'label' => 'Custom Field',
        'required' => false
    ];
    return $fields;
});
```

#### `scl_shipping_cost_calculation`
Modify shipping cost calculation.

```php
add_filter('scl_shipping_cost_calculation', function($cost, $zone_name, $cart_total) {
    if ($cart_total > 500) {
        return 0; // Free shipping
    }
    return $cost;
}, 10, 3);
```

#### `scl_available_delivery_dates`
Filter available delivery dates.

```php
add_filter('scl_available_delivery_dates', function($dates, $zone_name) {
    // Remove Mondays from Cairo
    if ($zone_name === 'Cairo') {
        return array_filter($dates, function($date) {
            return date('l', strtotime($date['value'])) !== 'Monday';
        });
    }
    return $dates;
}, 10, 2);
```

---

## 🗄️ Database Schema

### Table: `wp_scl_addresses`

| Column | Type | Null | Description |
|--------|------|------|-------------|
| `id` | BIGINT(20) UNSIGNED | NO | Primary key |
| `user_id` | BIGINT(20) UNSIGNED | NO | WordPress user ID |
| `address_name` | VARCHAR(191) | NO | Address label |
| `customer_name` | VARCHAR(191) | NO | Recipient name |
| `phone_primary` | VARCHAR(50) | NO | Primary phone |
| `phone_secondary` | VARCHAR(50) | YES | Secondary phone |
| `location_url` | VARCHAR(255) | YES | Google Maps URL |
| `location_lat` | DECIMAL(10,7) | YES | Latitude |
| `location_lng` | DECIMAL(10,7) | YES | Longitude |
| `address_details` | TEXT | YES | Full address |
| `zone` | VARCHAR(191) | YES | Delivery zone |
| `notes_customer` | TEXT | YES | Customer notes |
| `notes_internal` | TEXT | YES | Admin notes |
| `is_default_billing` | TINYINT(1) | NO | Default billing flag |
| `is_default_shipping` | TINYINT(1) | NO | Default shipping flag |
| `status` | TINYINT(1) | NO | Active status (1=active) |
| `created_at` | DATETIME | NO | Creation timestamp |
| `updated_at` | DATETIME | NO | Update timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- KEY `user_id` (`user_id`)
- KEY `status` (`status`)
- KEY `default_billing` (`user_id`, `is_default_billing`)
- KEY `default_shipping` (`user_id`, `is_default_shipping`)

---

### Table: `wp_scl_zones`

| Column | Type | Null | Description |
|--------|------|------|-------------|
| `id` | BIGINT(20) UNSIGNED | NO | Primary key |
| `zone_name` | VARCHAR(191) | NO | English zone name |
| `zone_name_ar` | VARCHAR(191) | YES | Arabic zone name |
| `shipping_cost` | DECIMAL(10,2) | NO | Delivery cost |
| `delivery_times` | LONGTEXT | YES | JSON delivery times |
| `delivery_days_ahead` | INT | YES | Min days before delivery |
| `closed_days` | VARCHAR(255) | YES | Comma-separated closed days |
| `is_active` | TINYINT(1) | NO | Active status |
| `display_order` | INT | NO | Sort order |
| `created_at` | DATETIME | NO | Creation timestamp |
| `updated_at` | DATETIME | NO | Update timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- KEY `is_active` (`is_active`)
- KEY `display_order` (`display_order`)

---

## 🐛 Troubleshooting

### Google Maps not showing

**Problem:** Modal opens but map doesn't load.

**Solution:**
1. Check API key is correct in `simple-checkout-location.php`
2. Verify Maps JavaScript API is enabled in Google Console
3. Check browser console for API errors
4. Ensure domain is authorized in API key restrictions

---

### Addresses not saving

**Problem:** Address form submits but nothing saves.

**Solution:**
1. Check database tables were created: `wp_scl_addresses`, `wp_scl_zones`
2. Verify user is logged in (for registered users)
3. Check browser console for JavaScript errors
4. Review PHP error log for database errors
5. Ensure all required fields are filled

---

### Shipping cost not updating

**Problem:** Zone changes but shipping cost stays same.

**Solution:**
1. Clear WooCommerce cart cache
2. Go to **WooCommerce → Settings → Shipping**
3. Ensure "Zone-Based Delivery" method is added to zones
4. Check zone name matches exactly in both tables
5. Try clearing browser cache

---

### Delivery schedule not appearing

**Problem:** Schedule section doesn't show after selecting zone.

**Solution:**
1. Verify zone has `delivery_times` JSON configured
2. Check JSON format is valid in admin
3. Ensure zone is active (`is_active = 1`)
4. Check JavaScript console for errors
5. Review AJAX request in Network tab

---

### Order address not syncing

**Problem:** Address updates don't reflect in existing orders.

**Solution:**

Only **pending**, **processing**, and **on-hold** orders sync automatically. Completed/cancelled orders don't sync for data integrity.

Manual sync:
```php
// In functions.php or custom plugin
$plugin = Simple_Checkout_Location::get_instance();
$plugin->sync_address_to_orders($address_id, $user_id);
```

---

## 📝 Changelog

### Version 3.1.1 (Current)
- ✅ Added delivery scheduling system
- ✅ Implemented zone-based delivery times
- ✅ Fixed location saving in guest checkout
- ✅ Enhanced REST API with delivery schedule endpoints
- ✅ Improved order admin display with schedule info
- ✅ Added automatic shipping recalculation on zone change

### Version 3.0.6
- ✅ Complete REST API implementation
- ✅ Order creation via API
- ✅ Enhanced admin order display
- ✅ Auto-sync addresses to orders
- ✅ Guest checkout improvements

### Version 2.5.0
- ✅ Multi-zone shipping system
- ✅ Dynamic shipping cost calculation
- ✅ Admin zones management page
- ✅ Arabic language support for zones

### Version 2.0.0
- ✅ Interactive Google Maps integration
- ✅ Real-time location selection
- ✅ Address search functionality
- ✅ Current location detection

### Version 1.0.0
- 🎉 Initial release
- ✅ Multiple address management
- ✅ Guest checkout support
- ✅ Basic zone system

---

## 💡 Best Practices

### For Developers

1. **Always use Repository classes** for database operations
2. **Sanitize and validate** all user inputs
3. **Use WordPress hooks** instead of modifying core files
4. **Log errors** during development: `error_log('SCL: Your message')`
5. **Test with guest users** and logged-in users separately

### For Store Owners

1. **Set clear zone boundaries** to avoid customer confusion
2. **Configure delivery schedules** realistically based on capacity
3. **Test checkout flow** regularly with different devices
4. **Monitor order addresses** for accuracy
5. **Update Google Maps API usage** if traffic increases

---

## 🤝 Contributing

Contributions are welcome! Please follow these steps:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

### Coding Standards

- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- Use meaningful variable names
- Comment complex logic
- Write PHPDoc for all functions
- Test thoroughly before submitting PR

---

## 📄 License

This project is licensed under the **GPL-3.0 License** - see the [LICENSE](LICENSE) file for details.

---

## 📞 Support

### Documentation
- [GitHub Wiki](https://github.com/abdulrahmanroston/address_manager_plugin/wiki)
- [API Reference](https://github.com/abdulrahmanroston/address_manager_plugin/wiki/API)

### Issues
Report bugs or request features: [GitHub Issues](https://github.com/abdulrahmanroston/address_manager_plugin/issues)

### Community
- Email: abdulrahmanroston@example.com
- WooCommerce Forum: [Plugin Support](https://wordpress.org/support/plugin/simple-checkout-location)

---

## 🙏 Credits

**Developer:** Abdulrahman Roston

**Built with:**
- WordPress / WooCommerce
- Google Maps JavaScript API
- Leaflet.js (optional fallback)
- Modern JavaScript (ES6+)

---

## 📊 Statistics

![GitHub stars](https://img.shields.io/github/stars/abdulrahmanroston/address_manager_plugin?style=social)
![GitHub forks](https://img.shields.io/github/forks/abdulrahmanroston/address_manager_plugin?style=social)
![GitHub watchers](https://img.shields.io/github/watchers/abdulrahmanroston/address_manager_plugin?style=social)

---

**Made with ❤️ for the WooCommerce community**

[⬆ Back to top](#simple-checkout-location-selector-for-woocommerce)