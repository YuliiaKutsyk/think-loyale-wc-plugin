<?php

class Admin
{
    function __construct()
    {
        add_action('admin_menu', [$this, 'add_page']);
        add_action('admin_init', [$this, 'add_settings']);
    }

    function add_page()
    {
        $icon = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIGlkPSJMYXllcl8yIiB2aWV3Qm94PSIwIDAgMjQwIDI0MCIgZmlsbD0ibm9uZSI+CiAgPHBhdGggY2xhc3M9ImNscy0yIiBkPSJNMjA3Ljk1LDcwLjUyTDE0OS43NiwxMi4zM2MtNy45NS03Ljk1LTE4LjUyLTEyLjMzLTI5Ljc2LTEyLjMzSDU4LjgyYy0xNi4wNSwwLTI5LjExLDEzLjA2LTI5LjExLDI5LjExdjExNS4yM2MwLDIuMTMsLjg0LDQuMTYsMi4zNSw1LjY3bDgxLjQ4LDgxLjQ4YzUuNjcsNS42NywxMy4xMyw4LjUxLDIwLjU4LDguNTFzMTQuOTEtMi44NCwyMC41OC04LjUxbDQzLjI2LTQzLjI2YzcuOTUtNy45NSwxMi4zMy0xOC41MiwxMi4zMy0yOS43NlY3Ni4xOGMwLTIuMTMtLjg0LTQuMTYtMi4zNS01LjY2Wk01OC44MiwxNi4wMmg2MS4xOGM2Ljk2LDAsMTMuNTEsMi43MSwxOC40Myw3LjY0bDQxLjA5LDQxLjA5YzEuMjYsMS4yNiwuMzcsMy40Mi0xLjQyLDMuNDJoLTU4LjFjLTExLjE3LDAtMjEuNjksNC4zMy0yOS42NSwxMi4yMWwtNDEuMiw0MS4yYy0xLjI2LDEuMjYtMy40MiwuMzctMy40Mi0xLjQyVjI5LjExYzAtNy4yMyw1Ljg2LTEzLjA5LDEzLjA5LTEzLjA5Wm0xMTkuMjgsNjguMTdjMS43OCwwLDIuNjgsMi4xNiwxLjQyLDMuNDJsLTQxLjA4LDQxLjA4Yy00LjkzLDQuOTMtMTEuNDcsNy42NC0xOC40Myw3LjY0SDYxLjljLTEuNzgsMC0yLjY4LTIuMTYtMS40Mi0zLjQybDQxLjE4LTQxLjE4YzQuOTEtNC44NywxMS40Mi03LjU1LDE4LjM0LTcuNTVoNTguMVptOC41Myw5Mi43MWwtNDMuMjYsNDMuMjZjLTUuMSw1LjEtMTMuNDEsNS4xLTE4LjUxLDBMNjAuNDgsMTU1Ljc3Yy0xLjI2LTEuMjYtLjM3LTMuNDIsMS40Mi0zLjQyaDU4LjFjMTEuMjQsMCwyMS44MS00LjM4LDI5Ljc2LTEyLjMzbDQxLjA5LTQxLjA5YzEuMjYtMS4yNiwzLjQyLS4zNywzLjQyLDEuNDJ2NTguMTFjMCw2Ljk2LTIuNzEsMTMuNTEtNy42NCwxOC40M1oiLz4KPC9zdmc+Cg==';
        add_menu_page('Loyale', 'Loyale', 'manage_options', 'loyale', [$this, 'set_loyale_page'], $icon, 26);
    }

    function set_loyale_page()
    {
        ?>
        <img height="80" src="<?=plugin_dir_url(__FILE__ );?>assets/logo-rendered.svg">
        <div class="wrap">
            <form method="post" action="options.php">
                <?php
                settings_fields( 'loyale_group' );
                do_settings_sections( 'loyale' );
                submit_button();
                ?>
            </form>
        </div>
        <?php

    }

    function add_settings()
    {

        register_setting( 'loyale_group', 'loyale_mode' );
        register_setting( 'loyale_group', 'display_mode' );

        add_settings_section( 'loyale_modes', '<h1>Options</h1>', [$this, 'live_text'], 'loyale' );
        add_settings_field( 'loyale_mode', '<p>Enable Live</p><small style="font-weight: 200;">When enabled the live credentials specified below will be used.</small>', [$this, 'loyale_mode_callback'], 'loyale', 'loyale_modes' );
        add_settings_field( 'display_mode', '<p>Enable Shortcodes</p><small style="font-weight: 200;">When enabled functinality can be controlled through the provided shortcodes.</small>', [$this, 'display_mode_callback'], 'loyale', 'loyale_modes' );

        // Live Settings
        register_setting( 'loyale_group', 'l_scheme_id' );
        register_setting( 'loyale_group', 'l_admin_email' );
        register_setting( 'loyale_group', 'l_admin_password' );
        register_setting( 'loyale_group', 'l_outlet_id' );

        // Staging Settings

        register_setting( 'loyale_group', 'st_scheme_id' );
        register_setting( 'loyale_group', 'st_admin_email' );
        register_setting( 'loyale_group', 'st_admin_password' );
        register_setting( 'loyale_group', 'st_outlet_id' );

        add_settings_section( 'credentials', '<h1>Scheme Configuration</h1>', [$this, 'credentials_description'], 'loyale' );

        add_settings_field( 'live_text', '<h3 style="margin: 0">Live</h3>', [$this, 'live_text'], 'loyale', 'credentials' );

        add_settings_field( 'l_scheme_id', 'Live Scheme ID', [$this, 'scheme_id_callback'], 'loyale', 'credentials' );
        add_settings_field( 'l_admin_email', 'Live Admin Email', [$this, 'admin_email_callback'], 'loyale', 'credentials' );
        add_settings_field( 'l_admin_password', 'Live Admin Password', [$this, 'admin_password_callback'], 'loyale', 'credentials' );
        add_settings_field( 'l_outlet_id', 'Live Outlet ID', [$this, 'outlet_id_callback'], 'loyale', 'credentials' );
        if((get_option('l_scheme_id') != '' && get_option('l_admin_email') != '' && get_option('l_admin_password') != '')) {
            //            add_settings_field( 'l_test_configuration', 'Test Configuration', [$this, 'admin_test_configuration'], 'loyale', 'credentials' );
           add_settings_field( 'l_update_gainrate', 'Update Gainrate/Rounding', [$this, 'admin_update_gainrate'], 'loyale', 'credentials' );
        }

        add_settings_field( 'staging_text', '<h3 style="margin: 0">Staging</h3>', [$this, 'live_text'], 'loyale', 'credentials' );
        add_settings_field( 'st_scheme_id', 'Staging Scheme ID', [$this, 'st_scheme_id_callback'], 'loyale', 'credentials' );
        add_settings_field( 'st_admin_email', 'Staging Admin Email', [$this, 'st_admin_email_callback'], 'loyale', 'credentials' );
        add_settings_field( 'st_admin_password', 'Staging Admin Password', [$this, 'st_admin_password_callback'], 'loyale', 'credentials' );
        add_settings_field( 'st_outlet_id', 'Staging Outlet ID', [$this, 'st_outlet_id_callback'], 'loyale', 'credentials' );
        if((get_option('st_scheme_id') != '' && get_option('st_admin_email') != '' && get_option('st_admin_password') != '')) {
//            add_settings_field( 'l_test_configuration', 'Test Configuration', [$this, 'admin_test_configuration'], 'loyale', 'credentials' );
            add_settings_field( 'st_update_gainrate', 'Update Gainrate/Rounding', [$this, 'admin_update_gainrate'], 'loyale', 'credentials' );
        }

        register_setting( 'loyale_group', 'l_sso_url' );
        register_setting( 'loyale_group', 'l_sso_url' );
        register_setting( 'loyale_group', 'l_success_redirect' );
        register_setting( 'loyale_group', 'l_error_redirect' );

        add_settings_section( 'sso_configuration', '<h1>SSO Configuration</h1>', [$this, 'sso_description'], 'loyale' );
        add_settings_field( 'l_sso_url', 'SSO URL', [$this, 'sso_url_callback'], 'loyale', 'sso_configuration' );
        add_settings_field( 'l_success_redirect', 'Success Redirect', [$this, 'success_redirect_callback'], 'loyale', 'sso_configuration' );
        add_settings_field( 'l_error_redirect', 'Error Redirect', [$this, 'error_redirect_callback'], 'loyale', 'sso_configuration' );

        add_settings_section( 'shortcodes', '<h1>Shortcodes</h1>', [$this, 'shortcodes_description'], 'loyale' );
        add_settings_field( 'l_shortcode_button', 'SSO Login/Register Button', [$this, 'shortcode_button_info'], 'loyale', 'shortcodes' );
        add_settings_field( 'l_shortcode_points', 'Product Points', [$this, 'shortcode_points_info'], 'loyale', 'shortcodes' );
        add_settings_field( 'l_shortcode_total', 'Order Total Points', [$this, 'shortcode_total_info'], 'loyale', 'shortcodes' );
        add_settings_field( 'l_shortcode_redeem', 'Redeem Points Form', [$this, 'shortcode_redeem_info'], 'loyale', 'shortcodes' );
        add_settings_field( 'l_shortcode_user_points', 'User Points', [$this, 'shortcode_user_points_info'], 'loyale', 'shortcodes' );

        register_setting( 'loyale_group', 'class_product_points' );
        register_setting( 'loyale_group', 'class_order_total_points' );
        register_setting( 'loyale_group', 'class_redeem_points' );

        add_settings_section( 'additional_classes', '<h1>Custom Classes</h1>', [$this, 'additional_classes_description'], 'loyale' );
        add_settings_field( 'class_product_points', 'Product Points', [$this, 'class_product_points_callback'], 'loyale', 'additional_classes' );
        add_settings_field( 'class_order_total_points', 'Order Total Points', [$this, 'class_total_points_callback'], 'loyale', 'additional_classes' );
        add_settings_field( 'class_redeem_points', 'Redeem Points Form', [$this, 'class_redeem_points_callback'], 'loyale', 'additional_classes' );

        register_setting( 'loyale_group', 'redeem_points_text' );
        register_setting( 'loyale_group', 'class_order_total_points' );
        register_setting( 'loyale_group', 'class_redeem_points' );

        add_settings_section( 'additional_settings', '<h1>Custom Content</h1>', [$this, 'additional_content_description'], 'loyale' );
        add_settings_field( 'redeem_points_text', 'Redeem form "sign in" text', [$this, 'redeem_points_text_callback'], 'loyale', 'additional_settings' );

    }
    function live_text()
    {
        echo '';
    }
    // function staging_text()
    // {
    //     echo '<h3>Yalla Habibi</h3>';
    // }

    function credentials_description()
    {
        echo '<p></p>';
    }

    function sso_description()
    {
        echo '<p></p>';
    }

    function shortcodes_description()
    {
        echo "<p>Requires 'Enable Shortcodes' to be enabled.</p>";
    }

    function additional_classes_description()
    {
        echo '<p>Specify any custom classes to be appened to the elements. This can be used for custom styling.</p>';
    }
    // Live Credentials callbacks
    function scheme_id_callback()
    {
        echo "<input id='l_scheme_id' name='l_scheme_id' type='text' style='width: 300px;' value='" . esc_attr( get_option('l_scheme_id') ) . "' />";
    }

    function admin_email_callback()
    {
        echo "<input id='l_admin_email' name='l_admin_email' type='text' style='width: 300px;' value='" . esc_attr( get_option('l_admin_email') ) . "' />";
    }

    function admin_password_callback()
    {
        echo "<input id='l_admin_password' name='l_admin_password' type='password' style='width: 300px;' value='" . esc_attr( get_option('l_admin_password') ) . "' />";
    }

    function outlet_id_callback()
    {
        echo "<input id='l_outlet_id' name='l_outlet_id' type='text' style='width: 300px;' value='" . esc_attr( get_option('l_outlet_id') ) . "' />";
    }

    // Staging Credentials callbacks

    function st_scheme_id_callback()
    {
        echo "<input id='st_scheme_id' name='st_scheme_id' type='text' style='width: 300px;' value='" . esc_attr( get_option('st_scheme_id') ) . "' />";
    }

    function st_admin_email_callback()
    {
        echo "<input id='st_admin_email' name='st_admin_email' type='text' style='width: 300px;' value='" . esc_attr( get_option('st_admin_email') ) . "' />";
    }

    function st_admin_password_callback()
    {
        echo "<input id='st_admin_password' name='st_admin_password' type='password' style='width: 300px;' value='" . esc_attr( get_option('st_admin_password') ) . "' />";
    }

    function st_outlet_id_callback()
    {
        echo "<input id='st_outlet_id' name='st_outlet_id' type='text' style='width: 300px;' value='" . esc_attr( get_option('st_outlet_id') ) . "' />";
    }


    function admin_test_configuration()
    {
        echo "<input id='test_configuration' name='test_configuration' type='button' class='button' value='Test' />";
    }

    function admin_update_gainrate()
    {
        echo "<input id='update_gainrate' name='update_gainrate' type='button' class='button' value='Update' />";
    }

    function shortcode_button_info()
    {
        echo '<p>[loyale-sso-button route="" label=""]<br>
        <small style="font-weight: 200"><strong>route</strong>: Determines page route users with land on. Takes values: \'signin\' or \'signup\'<br>
        <strong>label</strong>: Button label e.g. \'Sign up with Loyale\'</small>
        </p>';
    }

    function shortcode_points_info()
    {
        echo '<p>
                [loyale-product-points product_id="" quantity=""]<p>
                <small style="font-weight: 200"><strong>product_id</strong>:  If left empty, the global $product variable will be used (e.g. when used within product pages it can be ommitted)<br>
                <strong>quantity</strong>: Default value is 1</small>';
    }

    function shortcode_total_info()
    {
        echo '<p>[loyale-order-total-points]</p>';
    }

    function shortcode_redeem_info()
    {
        echo '<p>[loyale-redeem-points-form]</p>';
    }

    function shortcode_user_points_info()
    {
        echo '<p>[loyale-customer-points value-type=""]
        <small style="font-weight: 200"><br><strong>value-type</strong>
        Determines point value type. Takes values: "0" for points or "1" for currency</small>
        </p>';
    }

    function sso_url_callback()
    {
        if(!get_option('l_sso_url')){
            add_option('l_sso_url', 'https://web.loyale.io/loyale-sso-latest.js');
        }
        echo "<input id='l_sso_url' name='l_sso_url' type='text' style='width: 300px;' value='" . esc_attr( get_option('l_sso_url') ) . "' />";
    }

    function success_redirect_callback()
    {
        echo "<input id='l_success_redirect' name='l_success_redirect' type='text' style='width: 300px;' value='" . esc_attr( get_option('l_success_redirect') ) . "' />";
    }

    function error_redirect_callback()
    {
        echo "<input id='l_error_redirect' name='l_error_redirect' type='text' style='width: 300px;' value='" . esc_attr( get_option('l_error_redirect') ) . "' />";
    }

    function class_product_points_callback()
    {
        echo "<input id='class_product_points' name='class_product_points' type='text' style='width: 300px;' value='" . esc_attr( get_option('class_product_points') ) . "' />";
    }

    function class_total_points_callback()
    {
        echo "<input id='class_order_total_points' name='class_order_total_points' type='text' style='width: 300px;' value='" . esc_attr( get_option('class_order_total_points') ) . "' />";
    }

    function class_redeem_points_callback()
    {
        echo "<input id='class_redeem_points' name='class_redeem_points' type='text' style='width: 300px;' value='" . esc_attr( get_option('class_redeem_points') ) . "' />";
    }

    function loyale_mode_callback()
    {
        echo '
        <input type="radio" name="loyale_mode" value="1" ' . checked(1, get_option('loyale_mode'), false) . '> ' . __('Live') . '
        <br>
        <input type="radio" name="loyale_mode" value="2" ' . checked(2, get_option('loyale_mode'), false) . '> ' . __('Staging') . '
        ';
    }

    function display_mode_callback()
    {
        echo '
        <input type="radio" name="display_mode" value="1" ' . checked(1, get_option('display_mode'), false) . '> ' . __('Auto Implementation') . '
        <br>
        <input type="radio" name="display_mode" value="2" ' . checked(2, get_option('display_mode'), false) . '> ' . __('Shortcode Implementation') . '
        ';
    }

    function additional_content_description()
    {
        echo "<p>Requires 'Specify any custom content.</p>";
    }

    function redeem_points_text_callback()
    {
        if(get_option('redeem_points_text') == ''){
            update_option('redeem_points_text', '<a href="/my-account">Sign In</a> to add your points to this order');
        }
        echo "<textarea id='redeem_points_text' name='redeem_points_text' type='text' style='width: 300px;'>" . esc_attr( get_option('redeem_points_text') ) . "</textarea>";
    }
}

new Admin();