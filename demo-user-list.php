<?php
/**
 * Plugin Name: Demo User List
 * Plugin URI: https://wordpress.net/plugins/demo-user-list
 * Description: A demo plugin to create and list users
 * Version: 1.0.0
 * Requires at least: 2.5.0
 * Requires PHP: 8.0 or above
 * Author: Eliasu Abraman
 * Author URI: https://github.com/sabali33
 * License: MIT
 * License URI: https://opensource.org/license/mit
 * Update URI: false
 * Text Domain: demo-user-list
 * Domain Path: /languages
 *
 *
 * @package DemoUserList
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'init', 'dul_init' );
/**
 * Setup database when the plugin is activated
 *
 * @return void
 */
function dul_activate(): void {
    maybe_create_my_table();
}
/**
 * Remove database when the plugin is deactivated
 *
 * @return void
 */
function dul_deactivate() {
    global $wpdb;
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}demo_user" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}
/**
 * Call in the plugin functionalities on an init hook call
 *
 * @return void
 */
function dul_init(): void {

    register_activation_hook( __FILE__, 'dul_activate' );
    register_deactivation_hook( __FILE__, 'dul_deactivate' );

    add_shortcode( 'dul_user_form', 'dul_shortcode_form' );
    add_shortcode( 'dul_user_list', 'dul_user_list_shortcode' );
    add_action( 'rest_api_init', 'dul_register_endpoint' );
}

/**
 * Create a custom database table
 *
 * @return void
 */
function maybe_create_my_table(): void {
    global $wpdb;
    $table_name      = "{$wpdb->prefix}demo_user";
    $charset_collate = $wpdb->get_charset_collate();
    $sql             = <<<SQL
        CREATE TABLE IF NOT EXISTS `$table_name` (
        id INT PRIMARY KEY AUTO_INCREMENT,
        first_name VARCHAR(255) UNIQUE NOT NULL,
        age INT NOT NULL CHECK (age >= 0 AND age <= 120),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )  $charset_collate;
    SQL;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    dbDelta( $sql );
}

/**
 * Display a form to collect user inputs
 *
 * @return string
 */
function dul_shortcode_form(): string {
    dul_insert_user();
    $name_label = __( 'Name', 'demo-user-list' );
    $age_label  = __( 'Age', 'demo-user-list' );
    $nonce      = wp_nonce_field( 'dul_nonce_action', 'dul_nonce', true, false );

    return <<<FORM
        <form method="post" action="" >  
        $nonce
        <p>
            <label>
                $name_label
                <input type="text" name="first_name">  
            </label>
        </p>
        <p>
            <label>
                $age_label
                <input type="number" name="user_age" min="1">  
            </label>
        </p>
        
        <input type="submit" value="Create User">
        
        </form>
    FORM;
}

/**
 * Display a list of users from a custom table
 *
 * @param array $attr Attributes can be passed to the shortcode.
 * @return string
 */
function dul_user_list_shortcode( array $attr ): string {
    [
        'page' => $page,
        'per_page' => $per_page,
        'orderby' => $order_by,
        'order' => $order,
        'search' => $search
    ] = shortcode_atts(
        dul_default_query_args(),
        $attr
    );

    $users = dul_get_users( $page, $per_page, $order_by, $order, $search );

    $output = '<ul>';
    foreach ( $users as $user ) {
        $name    = esc_html( $user['first_name'] );
        $age     = esc_html( $user['age'] );
        $output .= "<li> Name: $name, Age: $age </li>";
    }
    $output .= '</u>';

    return $output;
}

/**
 * Query users from custom table
 *
 * @param int    $page The current pagination page of query.
 * @param int    $per_page The number of users to query at a time.
 * @param string $orderby The field by which the results should be ordered.
 * @param string $order Whether to order in ASC or DESC.
 * @param string $search A search query string.
 * @return array
 */
function dul_get_users( int $page, int $per_page, string $orderby, string $order, string $search ): array {
    global $wpdb;
    $offset = $page > 1 ? $page * $per_page : 0;

    $sql = "SELECT * FROM {$wpdb->prefix}demo_user";

    $wild         = '%';
    $search_like  = $wild . $wpdb->esc_like( $search ) . $wild;
    $where_clause = $search ? 'WHERE first_name LIKE %s' : null;

    if ( $where_clause ) {
        $sql .= ' ' . $where_clause;
        $sql  = $wpdb->prepare( $sql, $search_like ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    $sql = "$sql ORDER BY $orderby $order LIMIT %d OFFSET %d";

    $sql = $wpdb->prepare( $sql, $per_page, $offset ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

    return $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

/**
 * Insert a user into database.
 *
 * @return void
 */
function dul_insert_user(): void {
    if ( ! isset( $_POST['first_name'] ) ) {
        return;
    }
    if ( ! isset( $_POST['dul_nonce'] ) || ! wp_verify_nonce( $_POST['dul_nonce'], 'dul_nonce_action' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        return;
    }
    $name = sanitize_text_field( wp_unslash( $_POST['first_name'] ) );
    $age  = isset( $_POST['user_age'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['user_age'] ) ) : false;
    if ( ! $name || ! $age ) {
        echo $name ? '`age` must contain a value' : '`name` must contain a value';
        return;
    }
    if ( $age < 0 || $age > 120 ) {
        echo 'Age value must be between 0 and 120';
        return;
    }
    global $wpdb;

    $wpdb->show_errors = false;

    $table_name = $wpdb->prefix . 'demo_user';

    $sql = $wpdb->prepare( "INSERT into {$wpdb->prefix}demo_user (first_name, age) VALUES (%s,%d)", $name, $age ); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders

    $results = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

    if ( ! $results ) {
        /* translators: %s: last error message */
        printf( esc_html__( 'Unable to save user data: %s', 'demo-user-list' ), esc_html( $wpdb->last_error ) );
        return;
    }

    /* translators: 1: user name, 2: User age */
    printf( esc_html__( '%1$s, %2$d values has been saved', 'demo-user-list' ), esc_html( $name ), esc_html( $age ) );
}

/**
 * Register a custom endpoint for user list
 *
 * @return void
 */
function dul_register_endpoint(): void {
    register_rest_route(
        'dul-demo-user/v1',
        '/users',
        array(
            'methods'     => 'GET',
            'callback'    => 'dul_get_users_callback',
            'permissions' => 'dul_has_user_capability',
        )
    );
}

/**
 * Register custom REST route callback
 *
 * @param WP_REST_Request $request WP REST Request object.
 * @return array
 */
function dul_get_users_callback( WP_REST_Request $request ): array {
    $allowed_fields = array(
        'page',
        'per_page',
        'search',
        'order',
        'orderby',
    );
    $params         = $request->get_query_params();
    $filter_params  = array_filter(
        $params,
        function( $param, $key ) use ( $allowed_fields ) {

            return in_array( $key, $allowed_fields, true );
        },
        ARRAY_FILTER_USE_BOTH
    );

    [
        'page' => $page,
        'per_page' => $per_page,
        'orderby' => $order_by,
        'order' => $order,
        'search' => $search
    ] = wp_parse_args( $filter_params, dul_default_query_args() );

    return dul_get_users( $page, $per_page, $order_by, $order, $search );
}

/**
 * Check if current user can query users
 *
 * @return bool
 */
function dul_has_user_capability(): bool {
    return current_user_can( 'manage_options' );
}

/**
 * Default arguments for querying users
 *
 * @return int[]
 */
function dul_default_query_args(): array {
    return array(
        'page'     => 1,
        'per_page' => 10,
        'orderby'  => 'age',
        'order'    => 'DESC',
        'search'   => '',
    );
}
