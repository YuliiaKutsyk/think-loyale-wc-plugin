<?php
/*
 * Plugin Name:       Loyale Woocommerce Plugin
 * Plugin URI:        https://loyale.io/
 * Description:       A plugin integration for Loyale on Woocommerce!
 * Version:           1.5
 * Author:            Loyale
 * Author URI:        https://loyale.io/ 
 */

require_once(plugin_dir_path(__FILE__) . 'sso-login.php');

class Loyale
{
    private $endpoint;
    private $scheme_id;
    private $admin_email;
    private $admin_password;
    private $sso_url;
    private $success_url;
    private $error_url;
    private $outlet_id;

    function __construct()
    {

        require_once(plugin_dir_path(__FILE__) . 'Admin.php');


        add_action('init', function () {
            $localized_vars = array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'currency' => get_woocommerce_currency_symbol()
            );

            wp_localize_script('loyale-script', 'backend_vars', $localized_vars);
        });

        // Set Live Loyale or Staged Loyale
        $this->setLoyaleMode();

        // Check Live or Staging mode

        $mode = get_option('loyale_mode') ? get_option('loyale_mode') : 1; // live mode is default
        if ($mode == 1) {
            // Set admin live credentials
            if (get_option('l_scheme_id')) {
                $this->scheme_id = esc_attr(get_option('l_scheme_id'));
            }
            if (get_option('l_admin_email')) {
                $this->admin_email = esc_attr(get_option('l_admin_email'));
            }
            if (get_option('l_admin_password')) {
                $this->admin_password = esc_attr(get_option('l_admin_password'));
            }
            if (get_option('l_outlet_id')) {
                $this->outlet_id = esc_attr(get_option('l_outlet_id'));
            }
        } else {
            // Set admin staging credentials
            if (get_option('st_scheme_id')) {
                $this->scheme_id = esc_attr(get_option('st_scheme_id'));
            }
            if (get_option('st_admin_email')) {
                $this->admin_email = esc_attr(get_option('st_admin_email'));
            }
            if (get_option('st_admin_password')) {
                $this->admin_password = esc_attr(get_option('st_admin_password'));
            }
            if (get_option('st_outlet_id')) {
                $this->outlet_id = esc_attr(get_option('st_outlet_id'));
            }
        }

        if (get_option('l_sso_url')) {
            $this->sso_url = esc_attr(get_option('l_sso_url'));
        }
        if (get_option('l_success_redirect')) {
            $this->success_url = esc_attr(get_option('l_success_redirect'));
        }
        if (get_option('l_error_redirect')) {
            $this->error_url = esc_attr(get_option('l_error_redirect'));
        }

        // User. Manage user logics
        $this->manageUserLogic();

        // Loyale html. Display Loyale interface elements if Auto mode is on

        if ($this->scheme_id && $this->admin_email && $this->admin_password && get_option('loyale_gain_rate') && get_option('loyale_rounding')) {
            $this->displayElementsAutomatically();
        }

        // Cron. Update admin token, update gainrate
        $this->loyaleCron();

        $this->loyale_update_gainrate_handler();
        $this->loyale_update_admin_token();
        $this->loyale_get_admin_token();

        add_action('wp', [$this, 'loyale_set_loyale_pts_data']);
        add_action('woocommerce_order_status_changed', [$this, 'loyale_process_partial_refund'], 10, 3);
        // Partial refund
        // add_action('woocommerce_order_partially_refunded', [$this, 'loyale_process_refund'], 10, 4);
        // Apply redeem
        add_action('wp_ajax_loyale_apply_redeem', [$this, 'loyale_apply_redeem_handler']);

        // Button callback. Update gainrate
        add_action('wp_ajax_update_gainrate', [$this, 'loyale_update_gainrate_handler']);

        // Button callback. Test configuration
        add_action('wp_ajax_test_configuration', [$this, 'loyale_test_configuration_handler']);

        add_action('wp_ajax_loyale_remove_redeem', [$this, 'loyale_remove_redeem_handler']);

        //Apply redeem from session on cart
        add_filter('woocommerce_calculated_total', [$this, 'discounted_calculated_total'], 10, 2);

        // Save checkout meta to order
        add_action('woocommerce_checkout_update_order_meta', [$this, 'loyale_save_order_custom_field']);


        add_action('init', [$this, 'add_role_capability']);

        add_action('woocommerce_checkout_order_processed', function ($order_id) {
            if (!$order_id) {
                return;
            }
            $order = wc_get_order($order_id);
            $user_id = $order->get_user_id();
            $loyale_customer_id = get_user_meta($user_id, 'loyale_customer_id', true);
            if ($loyale_customer_id) {
                $this->loyale_send_order($order_id);
            }
            WC()->session->set('redeem_amount', null);
        });

        add_action('woocommerce_order_status_changed', [$this, 'loyale_refund_points_from_order'], 10, 4);

        add_action('woocommerce_admin_order_totals_after_total', [$this, 'ritz_add_total_pts_to_order_admin']);
        add_action('woocommerce_admin_order_totals_after_discount', [$this, 'ritz_add_redeem_to_order_admin']);


        // Shortcodes. Display points by shortcodes
        add_shortcode('loyale-sso-button', [$this, 'getLoginButton']);
        add_shortcode('loyale-product-points', [$this, 'shortcodeProductPoints']); //price * quantity in order
        add_shortcode('loyale-order-total-points', [$this, 'getTotalPointsInOrder']); // total points =  total value - delivery charge - coupon value - redeem value
        add_shortcode('loyale-redeem-points-form', [$this, 'shortcodeRedeemPoints']); // total points =  total value - delivery charge - coupon value - redeem value
        add_shortcode('loyale-customer-points', [$this, 'getUserPoints']);
        // Shortcodes

        // Update checkout fragments
        add_filter('woocommerce_update_order_review_fragments', [$this, 'loyale_shipping_table_update']);
        add_action('woocommerce_after_checkout_form', [$this, 'insertRemoveScript']); // insert script for Remove button

        add_filter('woocommerce_get_order_item_totals', [$this, 'loyale_order_item_insert'], 10, 2);

        add_action('wp_footer', [$this, 'placeButtonSettings']);

    }

    public function setLoyaleMode()
    {
        $mode = get_option('loyale_mode') ? get_option('loyale_mode') : 1; // live mode is default
        if ($mode == 1) {
            $this->endpoint = 'https://api.loyale.io';
        } else {
            $this->endpoint = 'https://api.staging.loyale.io';
        }
        update_option('loyale_endpoint', $this->endpoint);
    }

    public function manageUserLogic()
    {
        add_action('show_user_profile', [$this, 'loyale_add_user_loyale_id']);
        add_action('edit_user_profile', [$this, 'loyale_add_user_loyale_id']);
        add_filter('manage_users_columns', [$this, 'loyale_add_user_column']);
        add_filter('manage_users_custom_column', [$this, 'loyale_display_is_user_linked'], 10, 3);
        add_action('personal_options_update', [$this, 'update_extra_profile_fields']);
        add_action('edit_user_profile_update', [$this, 'update_extra_profile_fields']);
    }

    public function displayElementsAutomatically()
    {
        //My account menu page
        add_action('init', [$this, 'loyale_custom_endpoint']);
        add_filter('woocommerce_account_menu_items', [$this, 'loyale_customize_account_menu_items']);
        add_filter('woocommerce_get_endpoint_url', [$this, 'loyale_custom_woo_endpoint'], 10, 2);
        add_action('woocommerce_account_points_endpoint', [$this, 'loyale_add_content_to_endpoint']);

        $mode = get_option('display_mode') ? get_option('display_mode') : 1; // automatic mode is default
        if ($mode == 1) {
            add_action('woocommerce_before_customer_login_form', [$this, 'displayLoginButton'], 11);
            add_action('woocommerce_after_shop_loop_item_title', [$this, 'displayProductPoints'], 11); // Shop page - product card
            add_action('woocommerce_single_product_summary', [$this, 'displayProductPoints'], 15); // Product page
            add_filter('woocommerce_cart_item_subtotal', [$this, 'getMultipleProductsPoints'], 10, 3);
            add_filter('woocommerce_cart_totals_after_order_total', [$this, 'displayTotalPointsInOrder'], 20); // total on cart page
            add_action('woocommerce_review_order_before_order_total', [$this, 'displayRedeemInOrder']); // total on checkout page
            add_action('woocommerce_review_order_after_order_total', [$this, 'displayTotalPointsInOrder']); // total on checkout page

            add_action('woocommerce_review_order_before_payment', function () {
                echo '<div class="promocode-wrap redeem"></div>';
            });
        }
    }

    public function shortcodeRedeemPoints()
    {
        echo '<div class="promocode-wrap redeem"></div>';
        $this->insertRemoveScript();
    }

    public function getUserPoints($atts)
    {
        $a = shortcode_atts([
            'value-type' => 0,
        ], $atts);
        if ((int) $a['value-type'] == 1) {
            return wc_price($this->loyale_get_points_value($this->loyale_get_customer_points()));
        }
        return $this->loyale_get_customer_points();
    }

    public function loyaleCron()
    {
        add_filter('cron_schedules', function ($schedules) {
            $schedules['sixdays'] = array(
                'interval' => 518400,
                // time in seconds
                'display' => 'Every Six Days'
            );
            return $schedules;
        });
        if (!wp_next_scheduled('loyale_update_gainrate')) {
            wp_schedule_event(strtotime('00:00:00'), 'daily', 'loyale_update_gainrate');
        }
        if (!wp_next_scheduled('loyale_update_gainrate')) {
            wp_schedule_event(strtotime('00:00:00'), 'sixdays', 'loyale_update_admin_token');
        }
        add_action('loyale_update_gainrate', [$this, 'loyale_update_gainrate_handler'], 10, 0);
        add_action('loyale_update_admin_token', [$this, 'loyale_update_admin_token'], 10, 0);
    }

    /**
     * Add loyale customer ID field to user profile
     * @param $user
     */
    public function loyale_add_user_loyale_id($user)
    {
        ?>
        <h2>Loyale</h2>
        <table class="form-table">
            <tr>
                <th>
                    <label for="loyale_customer_id">
                        <?php _e('Loyale Customer ID'); ?>
                    </label>
                </th>
                <td>
                    <input type="text" name="loyale_customer_id" id="loyale_customer_id"
                        value="<?php echo esc_attr(get_the_author_meta('loyale_customer_id', $user->ID)); ?>"
                        class="regular-text" />
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Show user list "Loyale linked" column
     * @param $column
     * @return mixed
     */
    public function loyale_add_user_column($column)
    {
        $column['is_loyale_linked'] = 'Loyale linked';
        return $column;
    }

    public function loyale_display_is_user_linked($val, $column_name, $user_id)
    {
        switch ($column_name) {
            case 'is_loyale_linked':
                $is_linked = false;
                $customer_id = get_the_author_meta('loyale_customer_id', $user_id);
                if ($customer_id) {
                    $is_linked = true;
                }
                $output = '<span style="color: #FF7758; font-weight:500;">Not Linked</span>';
                if ($is_linked) {
                    $output = '<span style="color: #3eaf03; font-weight:500;">Linked</span>';
                }
                return $output;
            default:
        }
        return $val;
    }


    /**
     * Save loyale custom ID to user profile
     * @param $user_id
     */
    function update_extra_profile_fields($user_id)
    {
        if (current_user_can('edit_user', $user_id))
            update_user_meta($user_id, 'loyale_customer_id', $_POST['loyale_customer_id']);
    }

    /**
     * Get Loyale admin token
     * @return false
     */
    function loyale_get_admin_token()
    {
        if (!get_option('admin_token')) {
            $token = $this->loyale_update_admin_token();
        } else {
            $token = get_option('admin_token');
        }

        return $token;
    }

    public function loyale_update_admin_token()
    {
        $loyale_scheme_id = $this->scheme_id;
        $token = false;

        $url = $this->endpoint . '/api/AdminToken';

        $response = wp_remote_post($url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Scheme' => $loyale_scheme_id
            ),
            'body' => json_encode(
                array(
                    'email' => esc_attr($this->admin_email),
                    'password' => esc_attr($this->admin_password)
                )
            )
        )
        );

        if (!is_wp_error($response)) {
            $decoded = json_decode(wp_remote_retrieve_body($response));
            $token = $decoded->token;
        }

        update_option('admin_token', $token);
        return $token;
    }


    /**
     * Get loyale customer data
     * @param $customer_id
     * @return false|mixed
     */
    function loyale_get_customer($customer_id)
    {

        $loyale_scheme_id = $this->scheme_id;
        $admin_token = $this->loyale_get_admin_token();

        $url = $this->endpoint . '/api/Customer/' . $customer_id;
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Scheme' => $loyale_scheme_id,
                'Authorization' => 'Bearer ' . $admin_token
            )
        )
        );

        if (!is_wp_error($response)) {
            $customer = json_decode(wp_remote_retrieve_body($response), true);
        } else {
            $customer = false;
        }
        return $customer;
    }

    /**
     * Update Loyale customer meta
     * @param $customer_id
     * @param $key
     * @param $name
     * @param $value
     * @return false|mixed
     */
    function loyale_update_customer_meta($customer_id, $key, $name, $value)
    {
        $loyale_scheme_id = $this->scheme_id;
        $admin_token = $this->loyale_get_admin_token();

        $url = $this->endpoint . '/api/AdditionalCustomerFields';
        $response = wp_remote_request($url, array(
            'method' => 'PUT',
            'headers' => array(
                'Content-Type' => 'application/json',
                'Scheme' => $loyale_scheme_id,
                'Authorization' => 'Bearer ' . $admin_token
            ),
            'body' => json_encode(
                array(
                    "customerId" => $customer_id,
                    "schemeId" => $loyale_scheme_id,
                    "name" => $name,
                    "key" => $key,
                    "value" => $value
                )
            )
        )
        );
        if (!is_wp_error($response)) {
            $returnDecode = json_decode(wp_remote_retrieve_body($response), true);
        } else {
            $returnDecode = false;
        }
        return $returnDecode;
    }

    /**
     * Get loyale customer points balance
     * @return false
     */
    function loyale_get_customer_points()
    {
        $points = 0;
        $loyale_scheme_id = $this->scheme_id;
        $user_id = get_current_user_id();
        if ($user_id) {
            $customer_id = get_user_meta($user_id, 'loyale_customer_id', true);
            $admin_token = $this->loyale_get_admin_token();

            $url = $this->endpoint . '/api/PointBalance?Filters=customerId==' . $customer_id . ',schemeId==' . $loyale_scheme_id;

            $response = wp_remote_get($url, array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Scheme' => $loyale_scheme_id,
                    'Authorization' => 'Bearer ' . $admin_token
                )
            )
            );

            $decoded = json_decode(wp_remote_retrieve_body($response));
        }
        if (!is_wp_error($response)) {
            $points = $decoded[0]->pointsValue;
            WC()->session->set('customer_points', $points);
        } else {
            $points = false;
        }
        return $points;
    }

    /**
     * Update points data on page load
     */
    function loyale_set_loyale_pts_data()
    {
        $gainRate = intval(get_option('loyale_gain_rate'));
        $rounding = intval(get_option('loyale_rounding'));
        if (!isset($gainRate) || !isset($rounding)) {
            loyale_update_gainrate_handler();
        }
    }

    /**
     * Get points for product
     * @param $product_id
     * @return int
     */
    function loyale_get_product_points($product_id)
    {
        $points = 0;
        $product = wc_get_product($product_id);
        if ($product) {

            $gainRate = intval(get_option('loyale_gain_rate'));
            $rounding = intval(get_option('loyale_rounding'));
            $price = $product->get_price();
            switch ($rounding) {
                case 0:
                    $price = round((float) $price);
                    break;
                case 1:
                    $price = ceil((float) $price);
                    break;
                case 2:
                    $price = floor((float) $price);
                    break;
            }
            if (isset($gainRate)) {
                $points = $gainRate * (float) $price;
            }
        }
        return (int) $points;
    }

    /**
     * Get points for value
     * @param $price
     * @return int
     */
    function loyale_get_price_points($price)
    {
        $points = 0;
        $gainRate = intval(get_option('loyale_gain_rate'));
        $rounding = intval(get_option('loyale_rounding'));
        switch ($rounding) {
            case 0:
                $price = round($price);
                break;
            case 1:
                $price = ceil($price);
                break;
            case 2:
                $price = floor($price);
                break;
        }
        if (isset($gainRate)) {
            $points = $gainRate * (float) $price;
        }
        return (int) $points;
    }

    /**
     * Get value for points
     * @param $points
     * @return int
     */
    function loyale_get_points_value($points)
    {
        $value = 0;
        $gainRate = intval(get_option('loyale_gain_rate'));
        if (isset($gainRate) && $gainRate > 0) {
            $value = $points / $gainRate / 100;
        }
        return (int) $value;
    }

    /**
     * Get points redemption value
     * @return int
     */
    function loyale_get_points_redemption()
    {
        $loyale_scheme_id = $this->scheme_id;
        $value = 0;

        $url = $this->endpoint . '/api/Scheme/' . $loyale_scheme_id;
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Content-Type' => 'application/json'
            )
        )
        );

        if (!is_wp_error($response)) {
            $decoded = json_decode(wp_remote_retrieve_body($response));
            $value = $decoded->pointRedemptionPerCurrency;
        }

        return $value;
    }

    function loyale_is_points_available()
    {
        $is_points = false;
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            if (current_user_can('loyalty') && get_user_meta($user_id, 'loyale_customer_id', true)) {
                $is_points = true;
                return $is_points;
            }
        }
        return $is_points;
    }

    /**
     * Send staff order to loyale
     * @param $order_id
     */
    function loyale_send_order($order_id)
    {
        $loyale_scheme_id = $this->scheme_id;
        $admin_token = $this->loyale_get_admin_token();
        $order = new WC_Order($order_id);

        if (get_post_meta($order_id, 'is_sent_to_loyale', true) != true) {
            $order_data = $order->get_data();
            $line_items = array();
            $k = 0;
            foreach ($order->get_items() as $item_key => $item) {
                $item_id = $item->get_id();

                $product = $item->get_product();
                $product_id = $item->get_product_id();
                $quantity = $item->get_quantity();
                $cats = $product->get_category_ids();
                if (!empty($cats)) {
                    $cats_string = implode(',', $cats);
                } else {
                    $cats_string = null;
                }
                $line_items[$k]['id'] = $product_id;
                $line_items[$k]['quantity'] = $quantity;
                $line_items[$k]['unitPrice'] = $product->get_price();
                $line_items[$k]['description'] = $product->get_title();
                $line_items[$k]['groupId'] = $cats_string;
                $k++;
            }

            $order_date_created = $order_data['date_created']->date('Y-m-d\TH:i:s');

            $externalRefIdPrice = md5(uniqid(rand(), 1)) . "_loyale_" . $order_id;
            $externalRefIdRedeemAmount = md5(uniqid(rand(), 1)) . "_loyale_" . $order_id;
            $user_id = $order->get_user_id();
            $loyale_id = get_user_meta($user_id, 'loyale_customer_id', true);
            if (!empty($loyale_id)) {
                $url = $this->endpoint . '/api/Transaction';
                $redeem_amount = (float) get_post_meta($order_id, 'redeem_amount', true);
                if ($redeem_amount > 0) {
                    $data_points = [
                        "value" => (float) $redeem_amount,
                        "cashRedeemed" => 0,
                        "saleCurrency" => get_woocommerce_currency(),
                        "lineItems" => [
                        ],
                        "couponsUsed" => [
                        ],
                        "customerId" => $loyale_id,
                        "valueType" => 0,
                        "transactionType" => 3,
                        "posId" => "loyale",
                        "posType" => "API",
                        "outletId" => $this->outlet_id,
                        "externalRefId" => $externalRefIdRedeemAmount,
                        "description" => "",
                        "transactionDate" => $order_date_created
                    ];


                    $order->add_order_note(json_encode($data_points));

                    $result = wp_remote_post($url, array(
                        'body' => json_encode($data_points),
                        'headers' => array(
                            'Content-Type' => 'application/json',
                            'Scheme' => $loyale_scheme_id,
                            'Authorization' => 'Bearer ' . $admin_token
                        )
                    )
                    );
                    $body = wp_remote_retrieve_body($result);
                    $order->add_order_note($body);
                }

                $total = $order->get_total() - $order->get_total_tax();
                $data = [
                    "value" => (float) $total,
                    "cashRedeemed" => 0,
                    "saleCurrency" => get_woocommerce_currency(),
                    "lineItems" => $line_items,
                    "couponsUsed" => [
                    ],
                    "customerId" => $loyale_id,
                    "valueType" => 0,
                    "transactionType" => 0,
                    "posId" => "loyale",
                    "posType" => "API",
                    "outletId" => $this->outlet_id,
                    "externalRefId" => $externalRefIdPrice,
                    "description" => "",
                    "transactionDate" => $order_date_created
                ];

                $result = wp_remote_post($url, array(
                    'body' => json_encode($data),
                    'headers' => array(
                        'Content-Type' => 'application/json',
                        'Scheme' => $loyale_scheme_id,
                        'Authorization' => 'Bearer ' . $admin_token
                    )
                )
                );
                $body = wp_remote_retrieve_body($result);
                $order->add_order_note("Loyale response:\n" . $body);
                update_post_meta($order_id, 'is_sent_to_loyale', true);

            }
        }
    }
    function loyale_update_gainrate_handler()
    {
        $loyale_scheme_id = $this->scheme_id;

        $url = $this->endpoint . '/api/Transaction/GainRate';

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Scheme' => $loyale_scheme_id
            )
        )
        );

        $vars = array();
        if (!is_wp_error($response)) {
            $decoded = json_decode(wp_remote_retrieve_body($response));
            update_option('loyale_gain_rate', $decoded->gainRate);
            update_option('loyale_rounding', $decoded->rounding);
        }
    }

    // function loyale_process_refund($order_id, $old_status, $new_status){


    //     $refund_statuses = array('refunded');
    //     $cancel_statuses = array('failed','cancelled');

    //     if(in_array($new_status, $refund_statuses) || in_array($new_status, $cancel_statuses) ) {
    //         //changed status to refund
    //         $loyale_scheme_id = $this->scheme_id;
    //         $admin_token = $this->loyale_get_admin_token();
    //         $order = wc_get_order($order_id);
    //         $order_subtotal = $order->get_subtotal();
    //         $order_total = $order->get_total();
    //         $shipping_total = $order->get_shipping_total();
    //         $coupons_total = $order->get_total_discount();
    //         $redeem_total = (float)get_post_meta($order_id,'redeem_amount',true);

    //         update_post_meta( $order_id, 'order_total_points', (float)$total_points - (float)$refunded_amount);

    //         if(get_post_meta( $order_id, 'is_sent_to_loyale', true)) {
    //             $order_data = $order->get_data();
    //             $total_points = get_post_meta( $order_id, 'order_total_points', true);
    //             $user_id = $order->get_user_id();
    //             $loyale_id = get_user_meta($user_id, 'loyale_customer_id', true);

    //             if($total_points && $loyale_id) {
    //                 $order_date_created = date( 'Y-m-d\TH:i:s' );
    //                 $externalRefIdRedeemAmount = md5( uniqid( rand(), 1 ) ) . "_loyale_" . $order_id;

    //                 $url = $this->endpoint . '/api/Transaction';

    //                 $refund = 0;
    //                 if(in_array($new_status, $refund_statuses) ){

    //                     $refund_value = $order->get_total_refunded();
    //                     $total_paid_value = $order_total - $shipping_total; //get total - shipping of the $order_data
    //                     $refund = $refund_value > $total_paid_value ? $total_paid_value : $refund_value;
    //                 }
    //                 if(in_array($new_status, $cancel_statuses) ){
    //                     $refund = $order_subtotal - $coupons_total - $redeem_total; // subtotal - coupons - redeem
    //                 }
    //                 else {
    //                     $refund = $order_subtotal - $coupons_total - $redeem_total;
    //                 }

    //                 $data_points = [
    //                     "value"           => $refund,
    //                     "cashRedeemed"    => 0,
    //                     "saleCurrency"    => get_woocommerce_currency(),
    //                     "lineItems"       => [],
    //                     "couponsUsed"     => [],
    //                     "customerId"      => $loyale_id,
    //                     "valueType"       => 0,
    //                     "transactionType" => 4,
    //                     "posId"           => "loyale",
    //                     "posType"         => "API",
    //                     "externalRefId"   => $externalRefIdRedeemAmount,
    //                     "description"     => "Refund for order #" . $order_id,
    //                     "transactionDate" => $order_date_created
    //                 ];

    //                 $data_points_str = json_encode($data_points);
    //                 $order->add_order_note("Refund transaction req: \n {$data_points_str}");

    //                 $result = wp_remote_post($url,array(
    //                     'body' => json_encode($data_points),
    //                     'headers' => array(
    //                         'Content-Type' => 'application/json',
    //                         'Scheme' => $loyale_scheme_id,
    //                         'Authorization' =>  'Bearer ' . $admin_token
    //                     )
    //                 ));
    //                 $body = wp_remote_retrieve_body($result);
    //                 $order->add_order_note("Refund transaction res:\n {$body}");
    //             }
    //         }
    //     }


    // }

    // Perform custom actions on partial refund

    function loyale_process_partial_refund($order_id, $refund_id)
    {
        // Get the order object
        $order = wc_get_order($order_id);
        // Perform your custom actions here
        // For example, you can update custom fields, send notifications, etc.

        // Get the refunded amount
        $refunded_amount = get_post_meta($refund_id, '_refund_amount', true);
        $total_points = get_post_meta($order_id, 'order_total_points', true);
        update_post_meta($order_id, 'order_total_points', (float) $total_points - (float) $refunded_amount);

        if (get_post_meta($order_id, 'is_sent_to_loyale', true)) {
            $order_data = $order->get_data();
            $total_points = get_post_meta($order_id, 'order_total_points', true);
            $user_id = $order->get_user_id();
            $loyale_id = get_user_meta($user_id, 'loyale_customer_id', true);
            if ($total_points && $loyale_id) {
                $order_date_created = date('Y-m-d\TH:i:s');
                $externalRefIdPrice = md5(uniqid(rand(), 1)) . "_loyale_" . $order_id;
                $externalRefIdRedeemAmount = md5(uniqid(rand(), 1)) . "_loyale_" . $order_id;

                $url = $this->endpoint . '/api/Transaction';
                $redeem_amount = (float) $refunded_amount;
                $points_redemption = $this->loyale_get_points_redemption();
                if ($redeem_amount > 0) {
                    $data_points = [
                        "value" => intval($redeem_amount * $points_redemption),
                        "cashRedeemed" => 0,
                        "saleCurrency" => get_woocommerce_currency(),
                        "lineItems" => [],
                        "couponsUsed" => [],
                        "customerId" => $loyale_id,
                        "valueType" => 1,
                        "transactionType" => 1,
                        "posId" => "loyale",
                        "posType" => "API",
                        "outletId" => $this->outlet_id,
                        "externalRefId" => $externalRefIdRedeemAmount,
                        "description" => "Refund for order #" . $order_id,
                        "transactionDate" => $order_date_created
                    ];


                    $order->add_order_note(json_encode($data_points));

                    $result = wp_remote_post($url, array(
                        'body' => json_encode($data_points),
                        'headers' => array(
                            'Content-Type' => 'application/json',
                            'Scheme' => $loyale_scheme_id,
                            'Authorization' => 'Bearer ' . $admin_token
                        )
                    )
                    );
                    $body = wp_remote_retrieve_body($result);
                    $order->add_order_note("Refunded redeem:\n" . $body);
                }
                $earned_points = get_post_meta($order_id, 'order_total_points', true);

                $data = [
                    "value" => intval($earned_points),
                    "cashRedeemed" => 0,
                    "saleCurrency" => get_woocommerce_currency(),
                    "lineItems" => [],
                    "couponsUsed" => [],
                    "customerId" => $loyale_id,
                    "valueType" => 1,
                    "transactionType" => 2,
                    "posId" => "loyale",
                    "posType" => "API",
                    "outletId" => $this->outlet_id,
                    "externalRefId" => $externalRefIdPrice,
                    "description" => "Refund for order #" . $order_id,
                    "transactionDate" => $order_date_created
                ];

                $result = wp_remote_post($url, array(
                    'body' => json_encode($data),
                    'headers' => array(
                        'Content-Type' => 'application/json',
                        'Scheme' => $loyale_scheme_id,
                        'Authorization' => 'Bearer ' . $admin_token
                    )
                )
                );
                $body = wp_remote_retrieve_body($result);
                $order->add_order_note("Loyale refund response:\n" . $body);
            }
        }

        // Log the refunded amount
        error_log('Partial refund processed. Refunded amount: ' . $refunded_amount);
    }

    /**
     * Refund points and redeem on order failed,cancelled or refunded
     * Refund points and redeem on order failed,cancelled or refunded
     * @param $order_id
     * @param $old_status
     * @param $new_status
     */
    function loyale_refund_points_from_order($order_id, $old_status, $new_status)
    {
        $statuses = array('refunded', 'failed', 'cancelled');
        if (in_array($new_status, $statuses)) {
            $loyale_scheme_id = $this->scheme_id;
            $admin_token = $this->loyale_get_admin_token();
            $order = wc_get_order($order_id);
            $order_total = $order->get_total();
            if (get_post_meta($order_id, 'is_sent_to_loyale', true)) {
                $order_data = $order->get_data();
                $total_points = get_post_meta($order_id, 'order_total_points', true);
                $user_id = $order->get_user_id();
                $loyale_id = get_user_meta($user_id, 'loyale_customer_id', true);
                if ($total_points && $loyale_id) {
                    $order_date_created = date('Y-m-d\TH:i:s');
                    $externalRefIdPrice = md5(uniqid(rand(), 1)) . "_loyale_" . $order_id;
                    $externalRefIdRedeemAmount = md5(uniqid(rand(), 1)) . "_loyale_" . $order_id;

                    $url = $this->endpoint . '/api/Transaction';
                    $redeem_amount = (float) get_post_meta($order_id, 'redeem_amount', true);
                    $points_redemption = $this->loyale_get_points_redemption();
                    if ($redeem_amount > 0) {
                        $data_points = [
                            "value" => intval($redeem_amount * $points_redemption),
                            "cashRedeemed" => 0,
                            "saleCurrency" => get_woocommerce_currency(),
                            "lineItems" => [],
                            "couponsUsed" => [],
                            "customerId" => $loyale_id,
                            "valueType" => 0,
                            "transactionType" => 4,
                            "posId" => "loyale",
                            "posType" => "API",
                            "outletId" => $this->outlet_id,
                            "externalRefId" => $externalRefIdRedeemAmount,
                            "description" => "Refund for order #" . $order_id,
                            "transactionDate" => $order_date_created
                        ];


                        $order->add_order_note(json_encode($data_points));

                        $result = wp_remote_post($url, array(
                            'body' => json_encode($data_points),
                            'headers' => array(
                                'Content-Type' => 'application/json',
                                'Scheme' => $loyale_scheme_id,
                                'Authorization' => 'Bearer ' . $admin_token
                            )
                        )
                        );
                        $body = wp_remote_retrieve_body($result);
                        $order->add_order_note("Refunded redeem:\n" . $body);
                    }
                    $earned_points = get_post_meta($order_id, 'order_total_points', true);

                    $data = [
                        "value" => intval($earned_points),
                        "cashRedeemed" => 0,
                        "saleCurrency" => get_woocommerce_currency(),
                        "lineItems" => [],
                        "couponsUsed" => [],
                        "customerId" => $loyale_id,
                        "valueType" => 1,
                        "transactionType" => 2,
                        "posId" => "loyale",
                        "posType" => "API",
                        "outletId" => $this->outlet_id,
                        "externalRefId" => $externalRefIdPrice,
                        "description" => "Refund for order #" . $order_id,
                        "transactionDate" => $order_date_created
                    ];

                    $result = wp_remote_post($url, array(
                        'body' => json_encode($data),
                        'headers' => array(
                            'Content-Type' => 'application/json',
                            'Scheme' => $loyale_scheme_id,
                            'Authorization' => 'Bearer ' . $admin_token
                        )
                    )
                    );
                    $body = wp_remote_retrieve_body($result);
                    $order->add_order_note("Loyale refund response:\n" . $body);
                }
            }
        }
    }

    public function displayLoginButton($atts)
    {

        echo $this->getLoginButton();
    }

    public function getLoginButton($atts = [])
    {

        $a = shortcode_atts([
            'route' => '',
            'label' => '',
        ], $atts);
        $route = $a['route'] != '' ? $a['route'] : 'signin';
        $label = $a['label'] != '' ? $a['label'] : 'Login';

        return '
        <div data-loyale-sso
        data-loyale-other="' . ($this->success_url != '' ? $this->success_url : home_url()) . '"
        data-loyale-route="' . $route . '"
        data-loyale-label="' . $label . '"
        style="text-align: center;"></div>';
    }

    public function placeButtonSettings()
    {
        $structure = get_option('permalink_structure');
        $rest_url = home_url() . '/wp-json/';
        if ($structure == '') {
            $rest_url = get_rest_url();
        }
        ;
        $rest_url = $rest_url . 'sso-login/v1/token/';
        $rest_url = str_replace('?', '%3F', $rest_url);
        echo '
        <script src="' . $this->sso_url . '"></script>

                <script>
                    loyalesso({
                        schemeId: \'' . $this->scheme_id . '\',
                        successUrl: \'' . $rest_url . '\',
                        errorUrl: \'' . $this->error_url . '\',
                        ' . (get_option('loyale_mode') != 1 ? 'environment: \'staging\'' : ' ') . '
                    });
                </script>
        ';
    }

    function loyale_custom_endpoint()
    {
        add_rewrite_endpoint('points', EP_ROOT | EP_PAGES);
    }

    function loyale_customize_account_menu_items($menu_items)
    {
        // Add new Custom URL in My Account Menu
        $new_menu_item = array('points' => 'My Points');

        $menu_items = array_slice($menu_items, 0, 2, true) + $new_menu_item +
            array_slice($menu_items, 2, count($menu_items) - 1, true);

        return $menu_items;
    }

    // point the endpoint to a custom URL
    function loyale_custom_woo_endpoint($url, $endpoint)
    {
        if ($endpoint == 'points') {
            $url = get_permalink(get_option('woocommerce_myaccount_page_id')) . 'points';
        }
        return $url;
    }

    function loyale_add_content_to_endpoint()
    {
        $total_user_points = $this->loyale_get_customer_points();
        $points_redemption = $this->loyale_get_points_redemption();
        $points_redemption = $points_redemption > 0 ? $points_redemption : 1;
        $redeem_balance = (float) $total_user_points / $points_redemption;
        $redeem_amount = WC()->session->get('redeem_amount');
        $redeem_amount = $redeem_amount !== null ? $redeem_amount : 0;
        echo '
                <div class="profile-content_top">
                    <h6 class="ptofile-content_title">' . __('My Points') . '</h6>
                </div>
                <div class="account-points_top">
                    <div class="points-top_holder" style="align-items: flex-end; display: flex; justify-content: space-between;">
                        <div class="left">
                            <p class="top">' . __('Current Balance') . '</p>
                            <h4 class="points-balance">' . $this->loyale_get_customer_points() . ' <span>' . __('pts') . '</span></h4>
                        </div>
                        <div class="right">
                            <h6 class="points-value"><span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol"></span>' . wc_price($redeem_balance) . '</bdi></span></h6>
                        </div>
                    </div>
                </div>
            ';
    }

    public function displayProductPoints()
    {
        echo $this->getProductPoints();
    }

    public function getProductPoints()
    {
        global $product;
        $additional_class = get_option('class_product_points');
        return '<div class="points-value ' . $additional_class . '">+ ' . $this->loyale_get_product_points($product->get_id()) . __('pts') . '</div>';
    }

    function shortcodeProductPoints(array $atts, $content = null)
    {
        $a = shortcode_atts([
            'product_id' => null,
            'quantity' => 1,
        ], $atts);
        $html = '';
        if ($a['product_id']) {
            $additional_class = get_option('class_product_points');
            $html .= '<div class="points-value ' . $additional_class . '">+ ' . $this->loyale_get_product_points($a['product_id']) * $a['quantity'] . __('pts') . '</div>';

        } else {
            $html .= $this->getProductPoints();
        }
        return $content . $html;
    }

    public function getMultipleProductsPoints($content, $cart_item)
    {
        $product_id = $cart_item['product_id'];
        $product_quantity = $cart_item['quantity'];
        $additional_class = get_option('class_product_points');
        $html = '<div class="points-value ' . $additional_class . '">+ ' . $this->loyale_get_product_points($product_id) * $product_quantity . __('pts') . '</div>';

        return $content . $html;
    }

    public function displayTotalPointsInOrder()
    {
        echo $this->getTotalPointsInOrder();
    }

    public function getTotalPointsInOrder()
    {
        $additional_class = get_option('class_order_total_points');
        return '
        <tr class="order-total  ' . $additional_class . '">
            <th>Points</th>
            <td data-title="Total"><strong><span class="woocommerce-Price-amount amount"><bdi>+ ' . $this->loyale_get_price_points($this->calculateCleanTotal()) . __('pts') . '</bdi></span></strong> </td>
        </tr>
        <input type="hidden" name="order_total_points" value="' . $this->loyale_get_price_points($this->calculateCleanTotal()) . '">
        ';
    }

    public function displayRedeemInOrder()
    {
        echo $this->getRedeemInOrder();
    }

    public function getRedeemInOrder()
    {
        $session_redeem = WC()->session->get('redeem_amount');
        if ($session_redeem != null) {
            return '
        <tr class="cart-discount">
                <th>Redeemed:</th>
                <td>-' . wc_price($session_redeem) . ' <a href="#" class="remove-redeem">[Remove]</a></td>
            </tr>';
        } else {
            return '';
        }
    }

    public function calculateCleanTotal()
    {
        $clean_total = WC()->cart->total;
        if (WC()->cart->get_cart_tax()) {
            $clean_total = $clean_total - WC()->cart->get_cart_tax();
        }
        if (WC()->session->get('redeem_points')) {
            $clean_total = $clean_total - WC()->session->get('redeem_points');
        }
        if (WC()->cart->get_shipping_total()) {
            $clean_total = $clean_total - WC()->cart->get_shipping_total();
        }
        return $clean_total;
    }

    public function displayRedeemPoints()
    {
        echo $this->getRedeemPoints();
    }

    // Apply redeem
    function loyale_apply_redeem_handler()
    {
        $redeem_amount = (float) $_POST['redeem_value'];
        $total_user_points = $this->loyale_get_customer_points();
        $points_redemption = $this->loyale_get_points_redemption();
        $points_redemption = $points_redemption > 0 ? $points_redemption : 1;
        $redeem_balance = (float) $total_user_points / $points_redemption;
        $subtotal = WC()->cart->get_subtotal();
        $min = min($redeem_balance, $subtotal);
        $response = array();
        $response['html'] = '';
        $response['is_applied'] = false;
        if ($redeem_amount > 0) {
            if ($redeem_amount > $min) {
                $redeem_amount = $min;
            }
            WC()->session->set('redeem_amount', $redeem_amount);
            WC()->cart->calculate_totals();
            $response['is_applied'] = true;
        }
        echo json_encode($response);
        wp_die();
    }

    function loyale_remove_redeem_handler()
    {
        WC()->session->set('redeem_amount', null);
        WC()->cart->calculate_totals();
        $response = array();
        echo true;
        wp_die();
    }

    // Update checkout fragments
    function loyale_shipping_table_update($fragments)
    {
        ob_start();
        $this->displayRedeemHTML();
        $woocommerce_redeem = ob_get_clean();
        $fragments['.promocode-wrap.redeem'] = $woocommerce_redeem;
        return $fragments;
    }

    //Apply redeem from session on cart
    function discounted_calculated_total($total, WC_Cart $cart)
    {

        $amount_to_discount_subtotal = $cart->subtotal;

        $session_redeem = WC()->session->get('redeem_amount');
        $redeem_amount = $session_redeem !== null ? $session_redeem : 0;

        $new_total = $total - $redeem_amount;

        if (is_cart()) {
            $new_total = $total;
        }

        return round($new_total, $cart->dp);
    }

    // Save checkout meta to order
    function loyale_save_order_custom_field($order_id)
    {
        if ($this->loyale_is_points_available()) {
            if (WC()->session->get('redeem_amount') != null) {
                update_post_meta($order_id, 'redeem_amount', WC()->session->get('redeem_amount'));
            }
            if (isset($_POST['order_total_points'])) {
                update_post_meta($order_id, 'order_total_points', (float) $_POST['order_total_points']);
            }
        }
    }

    function displayRedeemHTML()
    {
        echo $this->getRedeemHTML();
    }

    function getRedeemHTML()
    {
        $html = '';

        if ($this->loyale_is_points_available()) {
            $total_user_points = $this->loyale_get_customer_points();
            $points_redemption = $this->loyale_get_points_redemption();
            $points_redemption = $points_redemption > 0 ? $points_redemption : 1;
            $redeem_balance = (float) $total_user_points / $points_redemption;
            $redeem_amount = WC()->session->get('redeem_amount');
            $redeem_amount = $redeem_amount !== null ? $redeem_amount : 0;

            $additional_class = get_option('class_redeem_points');
            $html .= '
                <div class="promocode-wrap redeem ' . ($additional_class ? $additional_class : '') . '">
                <label for="points_code">Redeem Points
                    <p class="points-balance">Balance:' . wc_price($redeem_balance) . '</p>
                </label>
                <div class="coupon-wrap redeem">
                    <input type="text" name="points_code" class="input-text" id="redeem_input" value="' . get_woocommerce_currency_symbol() . $redeem_amount . '" max="' . $redeem_balance . '" placeholder="Enter Redeem Amount">
                    <button type="submit" class="button black-button" id="redeem-button" name="apply_points" value="Apply points">Apply</button>
                </div>';
            $html .= '</div>';
        } elseif (!is_user_logged_in()) {
            $html .= '<div class="sign-in-to-add-your">
                <p>' . get_option('redeem_points_text') . '</p>
            </div>';
        }
        return $html;
    }

    public function add_role_capability()
    {
        if ($GLOBALS['wp_roles']->is_role('customer')) {
            $role = get_role('customer');
            if (!$role->has_cap('loyalty')) {
                $role->add_cap('loyalty');
            }
        } else {
            add_role(
                'customer',
                'Customer',
                array(
                    'read' => true,
                    'loyalty' => true,
                )
            );
        }
    }

    function ritz_add_total_pts_to_order_admin($order_id)
    {
        $order = wc_get_order($order_id);
        if ($order) {
            $total_pts = get_post_meta($order_id, 'order_total_points', true);
            if ($total_pts && $total_pts > 0) {
                ?>
                <tr>
                    <td class="label">
                        <?php esc_html_e('Points Earned:', 'woocommerce'); ?>
                    </td>
                    <td width="1%"></td>
                    <td class="total">
                        <?php echo '<p><strong>' . '+' . $total_pts . 'pts</strong></p>'; ?>
                    </td>
                </tr>
                <?php
            }
        }
    }

    function ritz_add_redeem_to_order_admin($order_id)
    {
        $order = wc_get_order($order_id);
        if ($order) {
            $redeem = get_post_meta($order_id, 'redeem_amount', true);
            if ($redeem && $redeem > 0) {
                ?>
                <tr>
                    <td class="label">
                        <?php esc_html_e('Redeem Amount:', 'woocommerce'); ?>
                    </td>
                    <td width="1%"></td>
                    <td class="total">
                        <?php echo '-' . wc_price($redeem, array('currency' => $order->get_currency())); ?>
                    </td>
                </tr>
                <?php
            }
        }
    }

    public function insertRemoveScript()
    {
        ?>
        <script>

            (function ($) {
                jQuery('body').on('click', '.remove-redeem', function (e) {
                    e.preventDefault();
                    let ajax_url = '<?php echo admin_url('admin-ajax.php') ?>';
                    let redeemValue = jQuery('#redeem_input').val();
                    redeemValue = parseFloat(redeemValue.replace(/[^0-9.]/g, ''));
                    console.log(redeemValue);
                    if (redeemValue > 0) {
                        let data = {
                            action: 'loyale_remove_redeem'
                        };
                        jQuery.ajax({
                            url: ajax_url,
                            data: data,
                            method: 'POST',
                            success: function (response) {
                                jQuery('body').trigger('update_checkout', { update_shipping_method: true });
                            },
                            error: function (response) {
                            }
                        });
                    }
                });
                let curr = '<?php echo get_woocommerce_currency_symbol() ?>';
                curr = $("<div/>").html(curr).text();

                $('body').on('keyup', '#redeem_input', function (e) {
                    let value = parseInt($(this).val().replace(/\D/g, ''));
                    let valueCalc = parseInt($(this).val().replace(/\D/g, ''));
                    let max_points = parseFloat($(this).attr('max'));
                    let price = parseFloat($('.cart-subtotal bdi').text().replace(/\D/g, '')) / 100;
                    let max = Math.min(max_points, price);

                    if (e.keyCode != 46) {
                        if (isNaN(value)) {
                            value = 0;
                        }
                        if (valueCalc > 0) {
                            valueCalc /= 100;
                        }
                        if (valueCalc > max) {
                            value = max.toFixed(2);
                            $(this).val(curr + value);
                        }

                        format(this, curr);
                        console.log(valueCalc);
                    }

                });

                function format(el, curr) {
                    el.value = curr + el.value.replace(/[^\d]/g, '').replace(/(\d\d?)$/, '.$1');
                }
            })(jQuery);
        </script>
        <?php
    }

    function loyale_order_item_insert($total_rows = null, $order = null): array
    {
        if ($order) {
            $redeem_value = get_post_meta($order->id, 'redeem_amount', true);
            if ($redeem_value && $redeem_value > 0) {
                $redeem_row = [
                    'cart_redeem' => array(
                        'label' => __('Redeemed:', 'woocommerce'),
                        'value' => '-' . wc_price($redeem_value),
                    )
                ];
                $total_rows = $this->array_insert_after($total_rows, 'cart_subtotal', $redeem_row);
            }
            $points_value = get_post_meta($order->id, 'order_total_points', true);
            if ($points_value && $points_value > 0) {
                $points_row = [
                    'cart_points' => array(
                        'label' => __('Points:', 'woocommerce'),
                        'value' => '+' . $points_value . ' pts',
                    )
                ];
                $total_rows = $this->array_insert_after($total_rows, 'cart_total', $points_row);
            }
        }
        return $total_rows;
    }

    function array_insert_after(array $array, $key, array $new)
    {
        $keys = array_keys($array);
        $index = array_search($key, $keys);
        $pos = false === $index ? count($array) : $index + 1;

        return array_merge(array_slice($array, 0, $pos), $new, array_slice($array, $pos));
    }

}

register_activation_hook(__FILE__, 'is_woocommerce_activate');
function is_woocommerce_activate()
{
    // Require parent plugin
    if (!is_plugin_active('woocommerce/woocommerce.php') and current_user_can('activate_plugins')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('Please install and activate WooCommerce.'), 'Plugin dependency check', ['back_link' => true]);
    } else {
        add_rewrite_endpoint('points', EP_ROOT | EP_PAGES);
        flush_rewrite_rules();
    }
}

$loyale = new Loyale();

add_action('wp_enqueue_scripts', function () {
    if (!wp_script_is('loyale-script', 'enqueued')) {
        wp_enqueue_script('jquery');
    }
}, 10);
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script('loyale-script', plugin_dir_url(__FILE__) . 'script.js', ['jquery'], null, true);
    wp_localize_script(
        'loyale-script',
        'backend_vars',
        array('ajax_url' => admin_url('admin-ajax.php'))
    );

}, 20);

function enqueue_admin_settings($hook_suffix)
{
    if ($hook_suffix == 'toplevel_page_loyale') {
        wp_enqueue_script('settings-page-js', plugin_dir_url(__FILE__) . 'assets/js/settings-page.js', ['jquery'], null, true);
        wp_localize_script(
            'settings-page-js',
            'backend_vars',
            array('ajax_url' => admin_url('admin-ajax.php'))
        );

    }
}
add_action('admin_enqueue_scripts', 'enqueue_admin_settings');