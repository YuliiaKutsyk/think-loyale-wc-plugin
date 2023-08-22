<?php

add_action( 'rest_api_init', 'hansa_add_sso_endpoints' );
function hansa_add_sso_endpoints() {
    $success = register_rest_route( 'sso-login/v1', '/token', array(
        'methods' => 'GET',
        'callback' => 'hansa_sso_auth_handler',
        'permission_callback' => '__return_true',
    ) );

    if( ! $success ) {
        return wp_die('Couldn\'t create rest endpoint');
    }
}

// General SSO authentification handler
function hansa_sso_auth_handler( WP_REST_Request $request ) {
    $token = $request->get_param('jwt');
    if( empty($token) ) {
        wp_die('Couldn\'t find token in request');
    }
    $token_data = hansa_sso_verify_token($token);
    $is_valid_token = isset($token_data['valid']) && $token_data['valid'];
    $response = '';
    if($is_valid_token) {
        $response = hansa_sso_auth_user($token_data);
        if(!$response) {
            wp_safe_redirect( get_the_permalink(225) . '?login_error=b2b' );
            exit();
        }
    }
    wp_safe_redirect( $request->get_param('other') );
    exit();
}

// Verify JWT token
function hansa_sso_verify_token( $token ) {
    $loyale_scheme_id = get_option( 'l_scheme_id' );
    $admin_token = get_option( 'admin_token' );
    $endpoint_part = get_option('loyale_endpoint') ? get_option('loyale_endpoint') : 'https://api.staging.loyale.io';
    $url = $endpoint_part . '/api/Customer/VerifyToken';
    $res = wp_remote_post($url,array(
        'body' => json_encode(array( 'token' => $token )),
        'headers' => array(
            'Content-Type' => 'application/json',
            'Scheme' => $loyale_scheme_id,
            'Authorization' =>  'Bearer ' . $admin_token
        )
    ));

    if( is_wp_error( $res ) ) {
        wp_die( $res->get_error_messages() );
    }
    $body = wp_remote_retrieve_body($res);
    $decodedBody = json_decode($body, true);
    if (!empty($decodedBody)) {
        return $decodedBody;
    } else {
        wp_die('No data in response from API');
    }
}

function hansa_sso_auth_user($body) {
    // find if user from api exist in WordPress
    $customer_id = $body['customerId'];
    $user = null;
    global $loyale;
    $users = get_users(array(
        'meta_key' => 'loyale_customer_id',
        'meta_value'   => $customer_id,
        'meta_compare' => '=',
    ));
    if(!empty($users)) {
        $user = $users[0];
        $user_id = $user->ID;
    } else {
        $user_id = 0;
        $customer_data = $loyale->loyale_get_customer($customer_id);
        if($customer_data) {
            $loyale_customer_email = $customer_data['email'];
            $user = get_user_by('email', $loyale_customer_email);
            if($user) {
                $user_id = $user->ID;
            } else {
                $user_id = hansa_sso_create_user($customer_data);
                if($user_id) {
                    $user = get_user_by('ID', $user_id);
                }
            }
            if($user_id) {
                $loyale->loyale_update_customer_meta($customer_id,'WordpressCustomerId','Wordpress Customer Id',$user_id);
                update_user_meta($user_id,'loyale_customer_id',$customer_id);
            }
        }
    }
    if($user) {
        if(!in_array('b2b_customer', (array)$user->roles)) {
            nocache_headers();
            wp_clear_auth_cookie();
            wp_set_current_user($user->ID, $user->user_login);
            wp_set_auth_cookie($user->ID);
        } else {
            return false;
        }
    }
    return $user_id;
}

function hansa_sso_create_user($data) {
    $customer_id = $data['id'];
    $email = $data['email'];
    $first_name = $data['firstName'];
    $last_name = $data['lastName'];
    $userdata = array(
        'user_login'    => $email,
        'user_email'    => $email,
        'display_name'  => $first_name . ' ' . $last_name,
        'first_name'    => $first_name,
        'last_name'     => $last_name,
        'user_pass'     => wp_generate_password(),
        'role'          => 'customer',
    );
    $new_user_id = wp_insert_user($userdata);

    if( is_wp_error( $new_user_id ) ) {
        $new_user_id = false;
    } else {
        if($data['dob']) {
            update_user_meta($new_user_id, 'birthdate', date('Y-m-d', strtotime($data['dob'])));
        }
    }
    return $new_user_id;
}