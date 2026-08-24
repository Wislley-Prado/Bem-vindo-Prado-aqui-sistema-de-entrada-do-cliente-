<?php
/**
 * Database schema manager for Boas-Vindas Prado Aqui
 */

if (!defined('ABSPATH')) {
    exit;
}

class Prado_Welcome_Database {

    /**
     * Get table name with prefix
     */
    public static function get_table_name($table) {
        global $wpdb;
        return $wpdb->prefix . 'prado_welcome_' . $table;
    }

    /**
     * Create the tables on plugin activation
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // 1. Properties Table
        $table_properties = self::get_table_name('properties');
        $sql_properties = "CREATE TABLE $table_properties (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            name varchar(255) NOT NULL,
            photo_main text,
            photos text,
            logo text,
            description text,
            hostname varchar(255) DEFAULT '',
            host_phone varchar(50) DEFAULT '',
            host_whatsapp varchar(50) DEFAULT '',
            city varchar(100) DEFAULT '',
            state varchar(50) DEFAULT '',
            address text,
            location_link text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id)
        ) $charset_collate;";
        dbDelta($sql_properties);

        // 2. Content Table (stores custom layout section data)
        $table_content = self::get_table_name('content');
        $sql_content = "CREATE TABLE $table_content (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            property_id bigint(20) NOT NULL,
            content_type varchar(50) NOT NULL,
            content_data longtext NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY prop_type (property_id, content_type)
        ) $charset_collate;";
        dbDelta($sql_content);

        // 3. Contacts Table
        $table_contacts = self::get_table_name('contacts');
        $sql_contacts = "CREATE TABLE $table_contacts (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            property_id bigint(20) NOT NULL,
            name varchar(255) NOT NULL,
            type varchar(100) DEFAULT '',
            phone varchar(50) DEFAULT '',
            notes text,
            PRIMARY KEY  (id),
            KEY property_id (property_id)
        ) $charset_collate;";
        dbDelta($sql_contacts);

        // 4. Places Table (What to do in region)
        $table_places = self::get_table_name('places');
        $sql_places = "CREATE TABLE $table_places (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            property_id bigint(20) NOT NULL,
            name varchar(255) NOT NULL,
            type varchar(100) DEFAULT '',
            description text,
            image text,
            address text,
            phone varchar(50) DEFAULT '',
            link text,
            location text,
            PRIMARY KEY  (id),
            KEY property_id (property_id)
        ) $charset_collate;";
        dbDelta($sql_places);

        // 5. Guests Table
        $table_guests = self::get_table_name('guests');
        $sql_guests = "CREATE TABLE $table_guests (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            name varchar(255) NOT NULL,
            phone varchar(50) DEFAULT '',
            pax int(11) DEFAULT 1,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id)
        ) $charset_collate;";
        dbDelta($sql_guests);

        // 6. Reservations Table
        $table_reservations = self::get_table_name('reservations');
        $sql_reservations = "CREATE TABLE $table_reservations (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            property_id bigint(20) NOT NULL,
            guest_id bigint(20) NOT NULL,
            checkin_date date NOT NULL,
            checkout_date date NOT NULL,
            guest_name varchar(255) DEFAULT '',
            guest_phone varchar(50) DEFAULT '',
            guest_pax int(11) DEFAULT 1,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY property_id (property_id),
            KEY guest_id (guest_id)
        ) $charset_collate;";
        dbDelta($sql_reservations);

        // 7. Guides Table
        $table_guides = self::get_table_name('guides');
        $sql_guides = "CREATE TABLE $table_guides (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            reservation_id bigint(20) NOT NULL,
            property_id bigint(20) NOT NULL,
            guest_id bigint(20) NOT NULL,
            status varchar(50) DEFAULT 'active',
            activation_date datetime DEFAULT NULL,
            expiration_date datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY reservation_id (reservation_id),
            KEY user_id (user_id)
        ) $charset_collate;";
        dbDelta($sql_guides);

        // 8. Tokens Table
        $table_tokens = self::get_table_name('tokens');
        $sql_tokens = "CREATE TABLE $table_tokens (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            guide_id bigint(20) NOT NULL,
            token varchar(100) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY token (token),
            KEY guide_id (guide_id)
        ) $charset_collate;";
        dbDelta($sql_tokens);

        // 9. Subscriptions Table (syncs WooCommerce subscriptions status)
        $table_subscriptions = self::get_table_name('subscriptions');
        $sql_subscriptions = "CREATE TABLE $table_subscriptions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            subscription_id varchar(100) DEFAULT '',
            product_id bigint(20) DEFAULT NULL,
            plan_name varchar(255) DEFAULT '',
            status varchar(50) DEFAULT 'active',
            start_date datetime DEFAULT NULL,
            end_date datetime DEFAULT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY user_id (user_id)
        ) $charset_collate;";
        dbDelta($sql_subscriptions);
    }
}
