<?php
/**
 * REST API Endpoints for Boas-Vindas Prado Aqui
 */

if (!defined('ABSPATH')) {
    exit;
}

class Prado_Welcome_REST_API {

    /**
     * Initialize REST routes hook
     */
    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }

    /**
     * Register REST API routes
     */
    public static function register_routes() {
        $namespace = 'prado-welcome/v1';

        // Permissions callback
        $logged_in_check = array(__CLASS__, 'permissions_check');

        // Dashboard
        register_rest_route($namespace, '/dashboard', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'get_dashboard'),
            'permission_callback' => $logged_in_check,
        ));

        // Properties
        register_rest_route($namespace, '/properties', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array(__CLASS__, 'get_properties'),
                'permission_callback' => $logged_in_check,
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array(__CLASS__, 'create_property'),
                'permission_callback' => $logged_in_check,
            )
        ));

        register_rest_route($namespace, '/properties/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array(__CLASS__, 'update_property'),
                'permission_callback' => $logged_in_check,
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array(__CLASS__, 'delete_property'),
                'permission_callback' => $logged_in_check,
            )
        ));

        // Property Content
        register_rest_route($namespace, '/properties/(?P<id>\d+)/content', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array(__CLASS__, 'get_property_content'),
                'permission_callback' => $logged_in_check,
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array(__CLASS__, 'save_property_content'),
                'permission_callback' => $logged_in_check,
            )
        ));

        // Property Contacts
        register_rest_route($namespace, '/properties/(?P<id>\d+)/contacts', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array(__CLASS__, 'get_property_contacts'),
                'permission_callback' => $logged_in_check,
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array(__CLASS__, 'save_property_contact'),
                'permission_callback' => $logged_in_check,
            )
        ));

        register_rest_route($namespace, '/properties/(?P<id>\d+)/contacts/(?P<contact_id>\d+)', array(
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => array(__CLASS__, 'delete_property_contact'),
            'permission_callback' => $logged_in_check,
        ));

        // Property Places (Tourism)
        register_rest_route($namespace, '/properties/(?P<id>\d+)/places', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array(__CLASS__, 'get_property_places'),
                'permission_callback' => $logged_in_check,
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array(__CLASS__, 'save_property_place'),
                'permission_callback' => $logged_in_check,
            )
        ));

        register_rest_route($namespace, '/properties/(?P<id>\d+)/places/(?P<place_id>\d+)', array(
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => array(__CLASS__, 'delete_property_place'),
            'permission_callback' => $logged_in_check,
        ));

        // Guests
        register_rest_route($namespace, '/guests', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array(__CLASS__, 'get_guests'),
                'permission_callback' => $logged_in_check,
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array(__CLASS__, 'create_guest'),
                'permission_callback' => $logged_in_check,
            )
        ));

        register_rest_route($namespace, '/guests/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array(__CLASS__, 'update_guest'),
                'permission_callback' => $logged_in_check,
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array(__CLASS__, 'delete_guest'),
                'permission_callback' => $logged_in_check,
            )
        ));

        // Reservations
        register_rest_route($namespace, '/reservations', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array(__CLASS__, 'get_reservations'),
                'permission_callback' => $logged_in_check,
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array(__CLASS__, 'create_reservation'),
                'permission_callback' => $logged_in_check,
            )
        ));

        register_rest_route($namespace, '/reservations/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array(__CLASS__, 'update_reservation'),
                'permission_callback' => $logged_in_check,
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array(__CLASS__, 'delete_reservation'),
                'permission_callback' => $logged_in_check,
            )
        ));

        // Revoke Guide Manual Toggle
        register_rest_route($namespace, '/reservations/(?P<id>\d+)/revoke', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'revoke_reservation_guide'),
            'permission_callback' => $logged_in_check,
        ));

        // Subscription Status Check
        register_rest_route($namespace, '/subscription', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'get_subscription_status'),
            'permission_callback' => $logged_in_check,
        ));

        // DB Setup Utility
        register_rest_route($namespace, '/db-setup', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'run_db_setup'),
            'permission_callback' => $logged_in_check,
        ));
    }

    /**
     * Check if user is logged in
     */
    public static function permissions_check($request) {
        return is_user_logged_in();
    }

    /**
     * Helper: check property ownership
     */
    private static function check_property_ownership($property_id) {
        if (current_user_can('manage_options')) {
            return true; // Admin can manage all
        }

        global $wpdb;
        $table = Prado_Welcome_Database::get_table_name('properties');
        $owner = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM $table WHERE id = %d",
            $property_id
        ));

        return intval($owner) === intval(get_current_user_id());
    }

    /**
     * 1. GET Dashboard Statistics
     */
    public static function get_dashboard($request) {
        global $wpdb;
        $user_id = get_current_user_id();
        $is_admin = current_user_can('manage_options');

        $table_properties   = Prado_Welcome_Database::get_table_name('properties');
        $table_reservations = Prado_Welcome_Database::get_table_name('reservations');
        $table_guides       = Prado_Welcome_Database::get_table_name('guides');

        // Scoping
        $scope_sql = $is_admin ? "1=1" : "user_id = $user_id";

        // Properties count
        $properties_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_properties WHERE $scope_sql");

        // Active Guides (guides with status 'active' and date checks)
        $active_guides = $wpdb->get_var("SELECT COUNT(*) FROM $table_guides WHERE status = 'active' AND $scope_sql");

        // Expired Guides
        $expired_guides = $wpdb->get_var("SELECT COUNT(*) FROM $table_guides WHERE status = 'expired' AND $scope_sql");

        // Active Reservations (where checkin_date <= today and checkout_date >= today)
        $today = date('Y-m-d');
        $active_reservations = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_reservations WHERE checkin_date <= %s AND checkout_date >= %s AND $scope_sql",
            $today, $today
        ));

        // Upcoming guests list (next 5 checkins)
        $upcoming_query = $wpdb->prepare(
            "SELECT r.*, p.name as property_name, g.status as guide_status, t.token
             FROM $table_reservations r
             LEFT JOIN $table_properties p ON r.property_id = p.id
             LEFT JOIN $table_guides g ON r.id = g.reservation_id
             LEFT JOIN " . Prado_Welcome_Database::get_table_name('tokens') . " t ON g.id = t.guide_id
             WHERE r.checkout_date >= %s AND " . ($is_admin ? "1=1" : "r.user_id = $user_id") . "
             ORDER BY r.checkin_date ASC LIMIT 5",
            $today
        );
        $upcoming = $wpdb->get_results($upcoming_query);

        // Check if database tables exist
        $tables = array('properties', 'reservations', 'guests', 'guides', 'tokens', 'content', 'contacts', 'places', 'subscriptions');
        $missing = array();
        foreach ($tables as $t) {
            $table_name = Prado_Welcome_Database::get_table_name($t);
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
            if (!$exists) {
                $missing[] = $t;
            }
        }

        return new WP_REST_Response(array(
            'properties' => intval($properties_count),
            'active_reservations' => intval($active_reservations),
            'active_guides' => intval($active_guides),
            'expired_guides' => intval($expired_guides),
            'upcoming' => $upcoming,
            'plan_status' => Prado_Welcome_WC_Integration::get_user_plan_status($user_id),
            'db_status' => array(
                'ok' => empty($missing),
                'missing' => $missing
            )
        ), 200);
    }

    /**
     * 2. GET/POST/PUT/DELETE Properties
     */
    public static function get_properties($request) {
        global $wpdb;
        $user_id = get_current_user_id();
        $is_admin = current_user_can('manage_options');
        $table = Prado_Welcome_Database::get_table_name('properties');

        if ($is_admin) {
            $properties = $wpdb->get_results("SELECT * FROM $table ORDER BY name ASC");
        } else {
            $properties = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE user_id = %d ORDER BY name ASC",
                $user_id
            ));
        }

        return new WP_REST_Response($properties, 200);
    }

    public static function create_property($request) {
        global $wpdb;
        $user_id = get_current_user_id();
        $table = Prado_Welcome_Database::get_table_name('properties');

        // Check subscription allowance (e.g. block if expired)
        $plan_status = Prado_Welcome_WC_Integration::get_user_plan_status($user_id);
        if ($plan_status === 'expired') {
            return new WP_Error('payment_required', 'Sua assinatura expirou. Renove para cadastrar novos imóveis.', array('status' => 402));
        }

        $params = $request->get_json_params();
        if (empty($params['name'])) {
            return new WP_Error('missing_field', 'O nome do imóvel é obrigatório.', array('status' => 400));
        }

        $data = array(
            'user_id' => $user_id,
            'name' => sanitize_text_field($params['name']),
            'photo_main' => esc_url_raw($params['photo_main'] ?? ''),
            'photos' => sanitize_text_field(json_encode($params['photos'] ?? array())),
            'logo' => esc_url_raw($params['logo'] ?? ''),
            'description' => sanitize_textarea_field($params['description'] ?? ''),
            'hostname' => sanitize_text_field($params['hostname'] ?? ''),
            'host_phone' => sanitize_text_field($params['host_phone'] ?? ''),
            'host_whatsapp' => sanitize_text_field($params['host_whatsapp'] ?? ''),
            'city' => sanitize_text_field($params['city'] ?? ''),
            'state' => sanitize_text_field($params['state'] ?? ''),
            'address' => sanitize_textarea_field($params['address'] ?? ''),
            'location_link' => esc_url_raw($params['location_link'] ?? ''),
            'created_at' => current_time('mysql')
        );

        $inserted = $wpdb->insert($table, $data);
        if ($inserted === false) {
            return new WP_Error('db_error', 'Erro ao salvar imóvel no banco de dados.', array('status' => 500));
        }

        return new WP_REST_Response(array('id' => $wpdb->insert_id, 'message' => 'Imóvel criado com sucesso!'), 201);
    }

    public static function update_property($request) {
        global $wpdb;
        $id = intval($request['id']);
        $table = Prado_Welcome_Database::get_table_name('properties');

        if (!self::check_property_ownership($id)) {
            return new WP_Error('forbidden', 'Acesso negado para este imóvel.', array('status' => 403));
        }

        $params = $request->get_json_params();
        if (empty($params['name'])) {
            return new WP_Error('missing_field', 'O nome do imóvel é obrigatório.', array('status' => 400));
        }

        $data = array(
            'name' => sanitize_text_field($params['name']),
            'photo_main' => esc_url_raw($params['photo_main'] ?? ''),
            'photos' => sanitize_text_field(json_encode($params['photos'] ?? array())),
            'logo' => esc_url_raw($params['logo'] ?? ''),
            'description' => sanitize_textarea_field($params['description'] ?? ''),
            'hostname' => sanitize_text_field($params['hostname'] ?? ''),
            'host_phone' => sanitize_text_field($params['host_phone'] ?? ''),
            'host_whatsapp' => sanitize_text_field($params['host_whatsapp'] ?? ''),
            'city' => sanitize_text_field($params['city'] ?? ''),
            'state' => sanitize_text_field($params['state'] ?? ''),
            'address' => sanitize_textarea_field($params['address'] ?? ''),
            'location_link' => esc_url_raw($params['location_link'] ?? '')
        );

        $wpdb->update($table, $data, array('id' => $id));
        return new WP_REST_Response(array('message' => 'Imóvel atualizado com sucesso!'), 200);
    }

    public static function delete_property($request) {
        global $wpdb;
        $id = intval($request['id']);

        if (!self::check_property_ownership($id)) {
            return new WP_Error('forbidden', 'Acesso negado para este imóvel.', array('status' => 403));
        }

        $wpdb->delete(Prado_Welcome_Database::get_table_name('properties'), array('id' => $id));
        
        // Clean up linked data
        $wpdb->delete(Prado_Welcome_Database::get_table_name('content'), array('property_id' => $id));
        $wpdb->delete(Prado_Welcome_Database::get_table_name('contacts'), array('property_id' => $id));
        $wpdb->delete(Prado_Welcome_Database::get_table_name('places'), array('property_id' => $id));

        return new WP_REST_Response(array('message' => 'Imóvel excluído com sucesso!'), 200);
    }

    /**
     * 3. Property Content (Welcome, WiFi, Checkin, Checkout, Rules, Structure, HowToUse)
     */
    public static function get_property_content($request) {
        global $wpdb;
        $property_id = intval($request['id']);

        if (!self::check_property_ownership($property_id)) {
            return new WP_Error('forbidden', 'Acesso negado para este imóvel.', array('status' => 403));
        }

        $table = Prado_Welcome_Database::get_table_name('content');
        $contents = $wpdb->get_results($wpdb->prepare(
            "SELECT content_type, content_data FROM $table WHERE property_id = %d",
            $property_id
        ));

        $formatted = array();
        foreach ($contents as $c) {
            $formatted[$c->content_type] = json_decode($c->content_data, true);
        }

        return new WP_REST_Response($formatted, 200);
    }

    public static function save_property_content($request) {
        global $wpdb;
        $property_id = intval($request['id']);

        if (!self::check_property_ownership($property_id)) {
            return new WP_Error('forbidden', 'Acesso negado para este imóvel.', array('status' => 403));
        }

        $params = $request->get_json_params();
        $type = sanitize_text_field($params['type'] ?? '');
        $data = $params['data'] ?? array();

        if (empty($type)) {
            return new WP_Error('missing_type', 'O tipo de conteúdo é obrigatório.', array('status' => 400));
        }

        // Clean/sanitize array structures recursively
        $sanitized_data = self::sanitize_nested_array($data);
        $json_data = json_encode($sanitized_data);

        $table = Prado_Welcome_Database::get_table_name('content');
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE property_id = %d AND content_type = %s",
            $property_id, $type
        ));

        if ($exists) {
            $wpdb->update($table, array('content_data' => $json_data), array('property_id' => $property_id, 'content_type' => $type));
        } else {
            $wpdb->insert($table, array(
                'property_id' => $property_id,
                'content_type' => $type,
                'content_data' => $json_data
            ));
        }

        return new WP_REST_Response(array('message' => 'Conteúdo salvo com sucesso!'), 200);
    }

    private static function sanitize_nested_array($arr) {
        if (!is_array($arr)) {
            return sanitize_text_field($arr);
        }
        $sanitized = array();
        foreach ($arr as $k => $v) {
            if (is_array($v)) {
                $sanitized[sanitize_key($k)] = self::sanitize_nested_array($v);
            } else {
                $sanitized[sanitize_key($k)] = sanitize_textarea_field($v);
            }
        }
        return $sanitized;
    }

    /**
     * 4. Property Contacts
     */
    public static function get_property_contacts($request) {
        global $wpdb;
        $property_id = intval($request['id']);

        if (!self::check_property_ownership($property_id)) {
            return new WP_Error('forbidden', 'Acesso negado para este imóvel.', array('status' => 403));
        }

        $table = Prado_Welcome_Database::get_table_name('contacts');
        $contacts = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE property_id = %d ORDER BY name ASC",
            $property_id
        ));

        return new WP_REST_Response($contacts, 200);
    }

    public static function save_property_contact($request) {
        global $wpdb;
        $property_id = intval($request['id']);

        if (!self::check_property_ownership($property_id)) {
            return new WP_Error('forbidden', 'Acesso negado.', array('status' => 403));
        }

        $params = $request->get_json_params();
        if (empty($params['name']) || empty($params['phone'])) {
            return new WP_Error('missing_fields', 'Nome e telefone são obrigatórios.', array('status' => 400));
        }

        $table = Prado_Welcome_Database::get_table_name('contacts');
        $data = array(
            'property_id' => $property_id,
            'name' => sanitize_text_field($params['name']),
            'type' => sanitize_text_field($params['type'] ?? 'outros'),
            'phone' => sanitize_text_field($params['phone']),
            'notes' => sanitize_text_field($params['notes'] ?? '')
        );

        $inserted = $wpdb->insert($table, $data);
        return new WP_REST_Response(array('id' => $wpdb->insert_id, 'message' => 'Contato cadastrado!'), 201);
    }

    public static function delete_property_contact($request) {
        global $wpdb;
        $property_id = intval($request['id']);
        $contact_id = intval($request['contact_id']);

        if (!self::check_property_ownership($property_id)) {
            return new WP_Error('forbidden', 'Acesso negado.', array('status' => 403));
        }

        $wpdb->delete(Prado_Welcome_Database::get_table_name('contacts'), array(
            'id' => $contact_id,
            'property_id' => $property_id
        ));

        return new WP_REST_Response(array('message' => 'Contato excluído.'), 200);
    }

    /**
     * 5. Property Places (Tourism)
     */
    public static function get_property_places($request) {
        global $wpdb;
        $property_id = intval($request['id']);

        if (!self::check_property_ownership($property_id)) {
            return new WP_Error('forbidden', 'Acesso negado.', array('status' => 403));
        }

        $table = Prado_Welcome_Database::get_table_name('places');
        $places = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE property_id = %d ORDER BY name ASC",
            $property_id
        ));

        return new WP_REST_Response($places, 200);
    }

    public static function save_property_place($request) {
        global $wpdb;
        $property_id = intval($request['id']);

        if (!self::check_property_ownership($property_id)) {
            return new WP_Error('forbidden', 'Acesso negado.', array('status' => 403));
        }

        $params = $request->get_json_params();
        if (empty($params['name'])) {
            return new WP_Error('missing_name', 'O nome do local é obrigatório.', array('status' => 400));
        }

        $table = Prado_Welcome_Database::get_table_name('places');
        $data = array(
            'property_id' => $property_id,
            'name' => sanitize_text_field($params['name']),
            'type' => sanitize_text_field($params['type'] ?? 'restaurante'),
            'description' => sanitize_text_field($params['description'] ?? ''),
            'image' => esc_url_raw($params['image'] ?? ''),
            'address' => sanitize_text_field($params['address'] ?? ''),
            'phone' => sanitize_text_field($params['phone'] ?? ''),
            'link' => esc_url_raw($params['link'] ?? ''),
            'location' => esc_url_raw($params['location'] ?? '')
        );

        $wpdb->insert($table, $data);
        return new WP_REST_Response(array('id' => $wpdb->insert_id, 'message' => 'Local cadastrado!'), 201);
    }

    public static function delete_property_place($request) {
        global $wpdb;
        $property_id = intval($request['id']);
        $place_id = intval($request['place_id']);

        if (!self::check_property_ownership($property_id)) {
            return new WP_Error('forbidden', 'Acesso negado.', array('status' => 403));
        }

        $wpdb->delete(Prado_Welcome_Database::get_table_name('places'), array(
            'id' => $place_id,
            'property_id' => $property_id
        ));

        return new WP_REST_Response(array('message' => 'Local excluído.'), 200);
    }

    /**
     * 6. GET/POST/PUT/DELETE Guests
     */
    public static function get_guests($request) {
        global $wpdb;
        $user_id = get_current_user_id();
        $is_admin = current_user_can('manage_options');
        $table = Prado_Welcome_Database::get_table_name('guests');

        if ($is_admin) {
            $guests = $wpdb->get_results("SELECT * FROM $table ORDER BY name ASC");
        } else {
            $guests = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE user_id = %d ORDER BY name ASC",
                $user_id
            ));
        }

        return new WP_REST_Response($guests, 200);
    }

    public static function create_guest($request) {
        global $wpdb;
        $user_id = get_current_user_id();
        $table = Prado_Welcome_Database::get_table_name('guests');

        $params = $request->get_json_params();
        if (empty($params['name'])) {
            return new WP_Error('missing_name', 'O nome do hóspede é obrigatório.', array('status' => 400));
        }

        $data = array(
            'user_id' => $user_id,
            'name' => sanitize_text_field($params['name']),
            'phone' => sanitize_text_field($params['phone'] ?? ''),
            'pax' => intval($params['pax'] ?? 1),
            'notes' => sanitize_text_field($params['notes'] ?? ''),
            'created_at' => current_time('mysql')
        );

        $wpdb->insert($table, $data);
        return new WP_REST_Response(array('id' => $wpdb->insert_id, 'message' => 'Hóspede cadastrado com sucesso!'), 201);
    }

    public static function update_guest($request) {
        global $wpdb;
        $id = intval($request['id']);
        $user_id = get_current_user_id();
        $table = Prado_Welcome_Database::get_table_name('guests');

        // Check ownership
        $owner = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM $table WHERE id = %d", $id));
        if (intval($owner) !== $user_id && !current_user_can('manage_options')) {
            return new WP_Error('forbidden', 'Acesso negado.', array('status' => 403));
        }

        $params = $request->get_json_params();
        if (empty($params['name'])) {
            return new WP_Error('missing_name', 'O nome do hóspede é obrigatório.', array('status' => 400));
        }

        $data = array(
            'name' => sanitize_text_field($params['name']),
            'phone' => sanitize_text_field($params['phone'] ?? ''),
            'pax' => intval($params['pax'] ?? 1),
            'notes' => sanitize_text_field($params['notes'] ?? '')
        );

        $wpdb->update($table, $data, array('id' => $id));
        return new WP_REST_Response(array('message' => 'Hóspede atualizado!'), 200);
    }

    public static function delete_guest($request) {
        global $wpdb;
        $id = intval($request['id']);
        $user_id = get_current_user_id();
        $table = Prado_Welcome_Database::get_table_name('guests');

        $owner = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM $table WHERE id = %d", $id));
        if (intval($owner) !== $user_id && !current_user_can('manage_options')) {
            return new WP_Error('forbidden', 'Acesso negado.', array('status' => 403));
        }

        $wpdb->delete($table, array('id' => $id));
        return new WP_REST_Response(array('message' => 'Hóspede excluído.'), 200);
    }

    /**
     * 7. GET/POST/PUT/DELETE Reservations
     */
    public static function get_reservations($request) {
        global $wpdb;
        $user_id = get_current_user_id();
        $is_admin = current_user_can('manage_options');

        $table_res = Prado_Welcome_Database::get_table_name('reservations');
        $table_prop = Prado_Welcome_Database::get_table_name('properties');
        $table_guides = Prado_Welcome_Database::get_table_name('guides');
        $table_tokens = Prado_Welcome_Database::get_table_name('tokens');

        $scope = $is_admin ? "1=1" : "r.user_id = $user_id";

        $query = "SELECT r.*, p.name as property_name, g.status as guide_status, g.id as guide_id, t.token
                  FROM $table_res r
                  LEFT JOIN $table_prop p ON r.property_id = p.id
                  LEFT JOIN $table_guides g ON r.id = g.reservation_id
                  LEFT JOIN $table_tokens t ON g.id = t.guide_id
                  WHERE $scope
                  ORDER BY r.checkin_date DESC";

        $reservations = $wpdb->get_results($query);
        return new WP_REST_Response($reservations, 200);
    }

    public static function create_reservation($request) {
        global $wpdb;
        $user_id = get_current_user_id();
        
        $table_res    = Prado_Welcome_Database::get_table_name('reservations');
        $table_guides = Prado_Welcome_Database::get_table_name('guides');
        $table_tokens = Prado_Welcome_Database::get_table_name('tokens');
        $table_guests = Prado_Welcome_Database::get_table_name('guests');

        $params = $request->get_json_params();
        
        $property_id    = intval($params['property_id'] ?? 0);
        $checkin_date   = sanitize_text_field($params['checkin_date'] ?? '');
        $checkout_date  = sanitize_text_field($params['checkout_date'] ?? '');
        $guest_name     = sanitize_text_field($params['guest_name'] ?? '');
        $guest_phone    = sanitize_text_field($params['guest_phone'] ?? '');
        $guest_pax      = intval($params['guest_pax'] ?? 1);
        $notes          = sanitize_text_field($params['notes'] ?? '');

        if (!$property_id || empty($checkin_date) || empty($checkout_date) || empty($guest_name)) {
            return new WP_Error('missing_fields', 'Imóvel, datas e nome do hóspede são obrigatórios.', array('status' => 400));
        }

        // Verify property exists and owned by user
        if (!self::check_property_ownership($property_id)) {
            return new WP_Error('forbidden', 'Imóvel inválido ou não pertencente ao proprietário.', array('status' => 403));
        }

        // 1. Resolve or Create Guest Profile
        $guest_id = 0;
        if (!empty($guest_phone)) {
            $guest_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_guests WHERE user_id = %d AND phone = %s",
                $user_id, $guest_phone
            ));
        }
        if (!$guest_id) {
            $wpdb->insert($table_guests, array(
                'user_id' => $user_id,
                'name' => $guest_name,
                'phone' => $guest_phone,
                'pax' => $guest_pax,
                'notes' => 'Cadastrado via reserva.',
                'created_at' => current_time('mysql')
            ));
            $guest_id = $wpdb->insert_id;
        }

        // 2. Create Reservation
        $res_data = array(
            'user_id' => $user_id,
            'property_id' => $property_id,
            'guest_id' => $guest_id,
            'checkin_date' => $checkin_date,
            'checkout_date' => $checkout_date,
            'guest_name' => $guest_name,
            'guest_phone' => $guest_phone,
            'guest_pax' => $guest_pax,
            'notes' => $notes,
            'created_at' => current_time('mysql')
        );

        $inserted = $wpdb->insert($table_res, $res_data);
        if ($inserted === false) {
            return new WP_Error('db_error', 'Erro ao salvar reserva.', array('status' => 500));
        }
        $reservation_id = $wpdb->insert_id;

        // 3. Generate Guide
        $guide_data = array(
            'user_id' => $user_id,
            'reservation_id' => $reservation_id,
            'property_id' => $property_id,
            'guest_id' => $guest_id,
            'status' => 'active',
            'activation_date' => $checkin_date . ' 00:00:00',
            'expiration_date' => $checkout_date . ' 23:59:59',
            'created_at' => current_time('mysql')
        );
        $wpdb->insert($table_guides, $guide_data);
        $guide_id = $wpdb->insert_id;

        // 4. Generate Secure Token
        $token = '';
        $unique = false;
        while (!$unique) {
            $token = strtoupper(wp_generate_password(8, false, false));
            // Keep strictly alphanumeric to avoid issues with browser URLs
            $token = preg_replace('/[^A-Z0-9]/', '', $token);
            if (strlen($token) < 8) {
                continue;
            }
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_tokens WHERE token = %s", $token));
            if (!$exists) {
                $unique = true;
            }
        }

        $wpdb->insert($table_tokens, array(
            'guide_id' => $guide_id,
            'token' => $token,
            'created_at' => current_time('mysql'),
            'expires_at' => $checkout_date . ' 23:59:59'
        ));

        return new WP_REST_Response(array(
            'id' => $reservation_id,
            'token' => $token,
            'message' => 'Reserva e Guia gerados automaticamente com sucesso!'
        ), 201);
    }

    public static function update_reservation($request) {
        global $wpdb;
        $id = intval($request['id']);
        $user_id = get_current_user_id();
        $table_res = Prado_Welcome_Database::get_table_name('reservations');
        $table_guides = Prado_Welcome_Database::get_table_name('guides');

        // Check ownership
        $owner = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM $table_res WHERE id = %d", $id));
        if (intval($owner) !== $user_id && !current_user_can('manage_options')) {
            return new WP_Error('forbidden', 'Acesso negado.', array('status' => 403));
        }

        $params = $request->get_json_params();
        $checkin_date   = sanitize_text_field($params['checkin_date'] ?? '');
        $checkout_date  = sanitize_text_field($params['checkout_date'] ?? '');
        $guest_name     = sanitize_text_field($params['guest_name'] ?? '');
        $guest_phone    = sanitize_text_field($params['guest_phone'] ?? '');
        $guest_pax      = intval($params['guest_pax'] ?? 1);
        $notes          = sanitize_text_field($params['notes'] ?? '');

        if (empty($checkin_date) || empty($checkout_date) || empty($guest_name)) {
            return new WP_Error('missing_fields', 'Datas e nome do hóspede são obrigatórios.', array('status' => 400));
        }

        $data = array(
            'checkin_date' => $checkin_date,
            'checkout_date' => $checkout_date,
            'guest_name' => $guest_name,
            'guest_phone' => $guest_phone,
            'guest_pax' => $guest_pax,
            'notes' => $notes
        );

        $wpdb->update($table_res, $data, array('id' => $id));

        // Update Guide expiration dates as well
        $wpdb->update(
            $table_guides,
            array(
                'activation_date' => $checkin_date . ' 00:00:00',
                'expiration_date' => $checkout_date . ' 23:59:59'
            ),
            array('reservation_id' => $id)
        );

        return new WP_REST_Response(array('message' => 'Reserva atualizada com sucesso!'), 200);
    }

    public static function delete_reservation($request) {
        global $wpdb;
        $id = intval($request['id']);
        $user_id = get_current_user_id();
        $table_res = Prado_Welcome_Database::get_table_name('reservations');
        $table_guides = Prado_Welcome_Database::get_table_name('guides');
        $table_tokens = Prado_Welcome_Database::get_table_name('tokens');

        // Check ownership
        $owner = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM $table_res WHERE id = %d", $id));
        if (intval($owner) !== $user_id && !current_user_can('manage_options')) {
            return new WP_Error('forbidden', 'Acesso negado.', array('status' => 403));
        }

        // Get guide id to delete tokens
        $guide_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_guides WHERE reservation_id = %d", $id));
        if ($guide_id) {
            $wpdb->delete($table_tokens, array('guide_id' => $guide_id));
            $wpdb->delete($table_guides, array('id' => $guide_id));
        }

        $wpdb->delete($table_res, array('id' => $id));
        return new WP_REST_Response(array('message' => 'Reserva excluída com sucesso.'), 200);
    }

    /**
     * 8. Revoke Guide manually (status = 'revoked')
     */
    public static function revoke_reservation_guide($request) {
        global $wpdb;
        $reservation_id = intval($request['id']);
        $user_id = get_current_user_id();

        $table_res = Prado_Welcome_Database::get_table_name('reservations');
        $table_guides = Prado_Welcome_Database::get_table_name('guides');

        // Verify reservation ownership
        $owner = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM $table_res WHERE id = %d", $reservation_id));
        if (intval($owner) !== $user_id && !current_user_can('manage_options')) {
            return new WP_Error('forbidden', 'Acesso negado.', array('status' => 403));
        }

        $params = $request->get_json_params();
        $action = sanitize_text_field($params['action'] ?? 'revoke'); // 'revoke' or 'activate'

        $new_status = ($action === 'activate') ? 'active' : 'revoked';

        $wpdb->update(
            $table_guides,
            array('status' => $new_status),
            array('reservation_id' => $reservation_id)
        );

        return new WP_REST_Response(array(
            'status' => $new_status,
            'message' => ($new_status === 'revoked') ? 'Guia revogado com sucesso!' : 'Guia ativado com sucesso!'
        ), 200);
    }

    /**
     * 9. GET Subscription Status details
     */
    public static function get_subscription_status($request) {
        global $wpdb;
        $user_id = get_current_user_id();
        $table = Prado_Welcome_Database::get_table_name('subscriptions');

        $sub = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d",
            $user_id
        ));

        if (!$sub) {
            // Fallback status for admin or onboarding
            $status = Prado_Welcome_WC_Integration::get_user_plan_status($user_id);
            return new WP_REST_Response(array(
                'status' => $status,
                'plan_name' => 'Plano Demonstrativo',
                'end_date' => 'Ilimitado',
                'active_days' => 'N/A'
            ), 200);
        }

        return new WP_REST_Response($sub, 200);
    }

    /**
     * Re-run database setup and verify
     */
    public static function run_db_setup($request) {
        global $wpdb;
        Prado_Welcome_Database::create_tables();

        $tables = array('properties', 'reservations', 'guests', 'guides', 'tokens', 'content', 'contacts', 'places', 'subscriptions');
        $missing = array();
        foreach ($tables as $t) {
            $table_name = Prado_Welcome_Database::get_table_name($t);
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
            if (!$exists) {
                $missing[] = $table_name;
            }
        }

        if (!empty($missing)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Algumas tabelas não puderam ser criadas: ' . implode(', ', $missing),
                'last_error' => $wpdb->last_error
            ), 500);
        }

        update_option('prado_welcome_db_version', '1.0.0');
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Banco de dados configurado e verificado com sucesso!'
        ), 200);
    }
}
