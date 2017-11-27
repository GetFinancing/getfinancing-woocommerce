<?php
if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * GetFinancing Payment Gateway Module
 *
 * @class       WC_GetFinancing
 * @extends     WC_Payment_Gateway
 * @since       2.2.0
 * @version     1.2
 * @author      GetFinancing - @sortegam contributor
 */
class WC_GetFinancing extends WC_Payment_Gateway
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->id                 = 'getfinancing_complete';
        $this->method_title       = __('GetFinancing', 'getfinancing');
        $this->method_description = __('Take payments via GetFinancing Platform - Purchase Finance Gateway.', 'getfinancing');
        $this->has_fields         = false;

        // Load the form fields
        $this->init_form_fields();

        // Load the settings.
        $this->init_settings();

        // Get setting values
        $this->title             = $this->get_option('title');
        $this->icon              = plugins_url('/assets/images/checkout-button.png', __FILE__);
        $this->description       = $this->get_option('description');
        $this->enabled           = $this->get_option('enabled');
        $this->environment       = $this->get_option('environment');
        $this->merchant_id       = $this->get_option('merchant_id');
        $this->username          = $this->get_option('username');
        $this->password          = $this->get_option('password');
        $this->redirectok        = $this->get_option('redirectok');
        $this->redirectko        = $this->get_option('redirectko');
        $this->gateway_url_prod  = "https://api.getfinancing.com";
        $this->gateway_url_stage = "https://api-test.getfinancing.com";
        $this->min_order_amount  = 1;
        $this->max_order_amount  = 100000;

        $this->_postback_url = str_replace('https:', 'http:', add_query_arg('wc-api', 'WC_GetFinancing', home_url('/')));

        // add table with merch id <-> order id reference
        register_activation_hook(__FILE__, array($this, 'create_gf_table'));
        //hook not being called, invoking function manually
        $this->create_gf_table();
        // Hooks
        add_action('wp_enqueue_scripts', array($this, 'getfinancing_js_lib'));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_receipt_' . $this->id, array(&$this, 'receipt_page'));
        //add_action('woocommerce_api_wc_pagantis', array($this, 'check_pagantis_response'));
        add_action('woocommerce_api_wc_getfinancing', array($this, 'check_postback_response'));
    }


    /**
     * Admin Panel Options
     * - Options for bits like 'title' and availability on a country-by-country basis
     *
     * @access public
     * @return void
     */
    public function admin_options()
    {
        ?>
        <h3><?php _e('GetFinancing - Purchase Finance Gateway', 'getfinancing'); ?></h3>

        <?php if (empty($this->merchant_id)) : ?>
            <div class="getfinancing-banner-signup">
                <img src="<?php echo plugins_url('/assets/images/getfinancing-logo.png', __FILE__); ?>" />
                <p class="main"><strong><?php _e('Getting started', 'woocommerce'); ?></strong></p>
                <p><?php _e('GetFinancing is an online Purchase Finance Gateway. Choose GetFinancing as your WooCommerce payment gateway to get access to multiple lenders in a powerful platform. ', 'getfinancing'); ?></p>

                <p><a href="https://www.getfinancing.com/signup" target="_blank" class="button button-primary"><?php _e('Sign up for GetFinancing', 'getfinancing'); ?></a> <a href="https://www.getfinancing.com/" target="_blank" class="button"><?php _e('Learn more', 'getfinancing'); ?></a></p>

            </div>
        <?php else : ?>
            <img src="<?php echo plugins_url('/assets/images/getfinancing-logo.png', __FILE__); ?>" />
            <p><?php _e('GetFinancing is an online Purchase Finance Gateway. Choose GetFinancing as your WooCommerce payment gateway to get access to multiple lenders in a powerful platform.', 'woocommerce'); ?></p>
             <p><a href="https://partner.getfinancing.com/partner/portal" target="_blank" class="button button-primary"><?php _e('Login for your GetFinancing Account', 'getfinancing'); ?></a> <a href="https://www.getfinancing.com/docs" target="_blank" class="button"><?php _e('Documentation', 'getfinancing'); ?></a></p>
        <?php endif; ?>
        <hr>
        <div style="color:#000; background-color:#fff; font-size:1.2em; border:1px solid #000; padding:20px; border-radius:4px;margin:10px 0;">
            <b>PostBack URL</b> - Use this URL in the field Postback url of your GetFinancing account: <b style="color:#900;"><?php echo $this->_postback_url; ?></b>
        </div>
        <table class="form-table">
            <?php $this->generate_settings_html(); ?>
        </table>
        <?php
    }

    /**
     * Check if this gateway is enabled
     */
    public function is_available()
    {
        if ('yes' != $this->enabled) {
            return false;
        }

        return true;
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title'       => __('Enable/Disable', 'getfinancing'),
                'label'       => __('Enable GetFinancing Plugin', 'getfinancing'),
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no'
            ),
            'title' => array(
                'title'       => __('Title', 'getfinancing'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'getfinancing'),
                'default'     => __('GetFinancing your purchase now!', 'getfinancing'),
                'desc_tip'    => true
            ),
            'description' => array(
                'title'       => __('Description', 'getfinancing'),
                'type'        => 'text',
                'description' => __('This controls the description which the user sees during checkout.', 'getfinancing'),
                'default'     => 'GetFinancing is a platform that groups a wide spectrum of lenders.',
                'desc_tip'    => true
            ),
            'environment' => array(
                'title'       => __('Environment', 'getfinancing'),
                'label'       => __('Choose the Environment', 'getfinancing'),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'default'     => 'stage',
                'options'     => array(
                    'test'          => __('Test', 'getfinancing'),
                    'production'     => __('Production', 'getfinancing')
                )
            ),
            'merchant_id' => array(
                'title'       => __('Merchant ID', 'getfinancing'),
                'type'        => 'text',
                'description' => __('The merchant ID GetFinancing provided to you.', 'getfinancing'),
                'default'     => '',
                'desc_tip'    => true
            ),
            'username' => array(
                'title'       => __('GetFinancing Username', 'getfinancing'),
                'type'        => 'text',
                'description' => __('The username GetFinancing provided to you.', 'getfinancing'),
                'default'     => '',
                'desc_tip'    => true
            ),
            'password' => array(
                'title'       => __('GetFinancing Password', 'getfinancing'),
                'type'        => 'password',
                'description' => __('The password GetFinancing provided to you.', 'getfinancing'),
                'default'     => '',
                'desc_tip'    => true
            ),
             'redirectok' => array(
                'title' => __('Return page on success', 'getfinancing'),
                'type' => 'select',
                'options' => $this -> get_pages('Select Page'),
                'description' => __("Select the page to redirect the user when GetFinancing success. Note: Leave empty if you want to go to the WooCommerce default thankyou page.", 'getfinancing')
             ),
             'redirectko' => array(
                'title' => __('Return page on failure', 'getfinancing'),
                'type' => 'select',
                'options' => $this -> get_pages('Select Page'),
                'description' => __("Select the page to redirect the user when GetFinancing fails (Normally checkout page)", 'getfinancing')
             )
        );
    }

    /**
     * Payment form on checkout page
     */
    public function payment_fields()
    {
        $description = $this->get_description();

        if ('test' == $this->environment) {
            $description .= ' ' . sprintf(__('<br><b>TEST MODE ENABLED</b>. Use a mocked (staging) subject: %s', 'getfinancing'), '<a target="_blank" href="http://www.getfinancing.com/docs/getfinancing-merchant-simple.html#staging-subjects">http://www.getfinancing.com/docs/getfinancing-merchant-simple.html#staging-subjects</a>');
        }

        if ($description) {
            echo wpautop(wptexturize(trim($description)));
        }

    }

    /**
     * getfinancing_js_lib function.
     *
     * Loads the GetFinancing Script Library
     */
    public function getfinancing_js_lib()
    {
        if (! is_checkout() || ! $this->is_available()) {
            return;
        }
        wp_enqueue_script('getfinancing', 'https://cdn.getfinancing.com/libs/1.0/getfinancing.js', array('jquery'), WC_VERSION, true);
    }

    public function getAddDisplayDetails ($product) {
        $productDetails = '';
        foreach ($product as $v) {
            $key = substr($v->key, 0, 3);
            if ($key == 'pa_') {
                $productDetails.=($productDetails!='')?', ':'';
                $productDetails.= substr ($v->key, 3).': '.$v->value;
            }
        }
        return $productDetails;
    }

    /**
     * Process the payment
     *
     * @param integer $order_id
     */
    public function process_payment($order_id)
    {
        global $wpdb;
        $order = new WC_Order($order_id);

        // fill in the product cart inside the product_info parameter
        $products = $order->get_items();
        $product_info = "";
    $cart_items = array();
        foreach ($products as $product)
        { 
            $displayName = $product['name'];
            $productOptions =  $this->getAddDisplayDetails ($product['item_meta_array']);
            $displayName.= ($productOptions!='')?' ('.$productOptions.')':'';

            $cart_items[]=array('sku' => $product['name'],
                                'display_name' => $displayName,
                                'unit_price' => str_replace(",","",number_format($product['line_total']/$product['qty'], 2)),
                                'quantity' => $product['qty'],
                                'unit_tax' => $product['line_tax']
                                );

            $product_info = $product_info.$product['name'].",";
            
        }

        $this->ok_url = $this->get_return_url($order);
    $wcCart = new WC_Cart();
        $this->ko_url = htmlspecialchars_decode($wcCart->get_checkout_url());
        $this->callback_url =  esc_url(get_site_url() .'/index.php/wc-api/WC_GetFinancing/');


        global $woocommerce;
        $order = new WC_Order($order_id);
        $gf_data = array(
            'amount'           => $order->order_total,
            // 'product_info'     => $product_info, // In order to be able to use cart_items we need to remove this line
            'cart_items'       => $cart_items,
            'first_name'       => $order->billing_first_name,
            'shipping_amount'  => $order->get_total_shipping(),
            'last_name'        => $order->billing_last_name,
            'billing_address' => array(
                'street1'  => $order->billing_address_1 . " " . $order->billing_address_2,
                'city'    => $order->billing_city,
                'state'   => $order->billing_state,
                'zipcode' => $order->billing_postcode
            ),
            'email'            => $order->billing_email,
            'phone'            => $order->billing_phone,
            'success_url'      => $this->ok_url,
            'failure_url'      => $this->ko_url,
            'postback_url'      => $this->callback_url,
            'merchant_loan_id' => (string)$order->get_order_number(),
            'version' => '1.9',
            'software_name'    => 'woocommerce',
            'software_version' =>  'woocommerce '.WC_VERSION
        );

        if (trim($order->shipping_state)!='') {
            $gf_data['shipping_address'] = array(
                'street1'  => $order->shipping_address_1 . " " . $order->shipping_address_2,
                'city'    => $order->shipping_city,
                'state'   => $order->shipping_state,
                'zipcode' => $order->billing_postcode);
        }

        $body_json_data = json_encode($gf_data);
        $header_auth = base64_encode($this->username . ":" . $this->password);

        if ($this->environment == "test") {
            $url_to_post = $this->gateway_url_stage;
        } else {
            $url_to_post = $this->gateway_url_prod;
        }

        $url_to_post .= '/merchant/' . $this->merchant_id  . '/requests';

        $post_args = array(
            'body' => $body_json_data,
            'timeout' => 60,     // 60 seconds
            'blocking' => true,  // Forces PHP wait until get a response
            'sslverify' => false,
            'headers' => array(
              'Content-Type' => 'application/json',
              'Authorization' => 'Basic ' . $header_auth,
              'Accept' => 'application/json'
            )
        );

        //echo '<pre>' . print_r($post_args, true) . '</pre>';

        $gf_response = wp_remote_post($url_to_post, $post_args);

        //echo '<br><br><pre>' . print_r($gf_response, true) . '</pre>';
        //die();

        if (is_wp_error($gf_response)) {
            wc_add_notice('GetFinancing cannot process your order. Please try again or select a different payment method.', 'error');
            return array('result' => 'fail', 'redirect' => '');
        }

        if (isset($gf_response['body'])) {
            $response_body = json_decode($gf_response['body']);
        } else {
            wc_add_notice('GetFinancing cannot process your order. Please try again or select a different payment method.', 'error');
            return array('result' => 'fail', 'redirect' => '');
        }

        if ((isset($response_body->href) == false) || (empty($response_body->href) == true)) {
            wc_add_notice('GetFinancing cannot process your order. Please try again or select a different payment method.', 'error');
            return array('result' => 'fail', 'redirect' => '');
        }

        // If we are here that means that the gateway give us a "created" status.
        // then we can create the order in hold status.

        //insert merchant_transaction_id <-> order_id relation
        $table_name = $wpdb->prefix . 'getfinancing';
        $wpdb->insert(
            $table_name,
            array(
              'order_id' => $order_id,
              'merchant_transaction_id' => $response_body->inv_id,
            )
        );

        // order is 'pending' by default
        // we add a note to the order
        $order->add_order_note('Waiting to finish GetFinancing process!');

        // Store gf process url in session.
        WC()->session->getfinancing_process_url = $response_body->href;

        // Adds the token to the order.
        update_post_meta($order->id, 'getfinancing_custid', $response_body->inv_id);


        return array(
                'result'    => 'success',
                'redirect'  => $order->get_checkout_payment_url(true)
        );

    }

    /**
     * Receipt page
     *
     * @param  int $order_id
     */
    public function receipt_page($order_id)
    {
        echo '<p>' . __('Thank you for your order! Follow the GetFinancing process to finish the payment.', 'getfinancing') . '</p>';

        if (empty($this->redirectok)) {
            $order = new WC_Order($order_id);
            $gf_ok_url_final = $order->get_checkout_order_received_url();
        } else {
            $gf_ok_url_final = get_permalink($this->redirectok);
        }

        $gf_ko_url_final = get_permalink($this->redirectko);

        $gfjs = '
            var onComplete = function() {
                window.location.href="' . $gf_ok_url_final . '";
            };

            var onAbort = function() {
                window.location.href="' . $gf_ko_url_final . '";
            };

            new GetFinancing("' . WC()->session->getfinancing_process_url . '", onComplete, onAbort);';

        wc_enqueue_js($gfjs);
    }

    /**
     * This is triggered by GetFinancing notification system
     *
     **/
    function check_postback_response()
    {
        global $woocommerce;
        global $wpdb;

        $json = file_get_contents('php://input');
        $notification = json_decode($json, true);
        $order_id = "";
        $order_token = "";
        $gf_token = "";
        $new_state = "";

        if (!isset($notification['merchant_transaction_id'])) {
            wp_die("merchant_transaction_id not sent", "GetFinancing", array('response' => 200));
            return;
        }

        $merchant_transaction_id = $notification['merchant_transaction_id'];

        //check if the order_id match with the merchant_transaction_id
        $table_name = $wpdb->prefix . "getfinancing";
        $sql = sprintf("SELECT order_id FROM %s WHERE order_id = '%s'", $table_name, $merchant_transaction_id);
        $row = $wpdb->get_row($sql);
        if ($row == null) {
            wp_die("merchant_transaction_id not present", "GetFinancing", array('response' => 200));
        } else {
            $order_id = (int)$row->order_id;
        }

        if ($order_id <= 0) {
            wp_die("ORDER_ID not found", "GetFinancing", array('response' => 200));
            return;
        }

        try {
            // If we are here we assume it's safe to proceed updating the order state.
            $order = new WC_Order($order_id);

            $new_state = $notification['updates']['status'];

            if ($new_state == "rejected") {
                if ($order->status =='on-hold' || $order->status == 'pending') {
                    $order->update_status('failed', 'Getfinancing rejected to finance this order.');
                    $order->add_order_note('GGetfinancing rejected to finance this order.');
                }
            }

            if ($new_state == "preapproved") {
                if ($order->status =='on-hold' || $order->status == 'pending') {
                    // Keep on hold by update with a message.
                    $order->add_order_note('GetFinancing Pre-approved this order, please wait');
                }
            }

            if ($new_state == "approved") {
                if ($order->status =='on-hold' || $order->status == 'pending') {
                    $order->payment_complete();
                    $order->add_order_note('GetFinancing Approved this order with request token: ' . $gf_token);
                    $woocommerce->cart->empty_cart();
                }
            }

            wp_die("OK", "GetFinancing", array('response' => 200));

        } catch (Exception $e) {
            wp_die("OK", "GetFinancing", array('response' => 200));
        }
    }

   /**
     * Get all wordpress pages and return them in an array
     *
     * This is used by Pagantis woocommerce admin page to select returning URLs
     *
     **/
    function get_pages($title = false, $indent = true)
    {
        $wp_pages = get_pages('sort_column=menu_order');
        $page_list = array();
        if ($title) {
            $page_list[] = $title;
        }
        foreach ($wp_pages as $page) {
            $prefix = '';
            // show indented child pages?
            if ($indent) {
                $has_parent = $page->post_parent;
                while ($has_parent) {
                    $prefix .=  ' - ';
                    $next_page = get_page($has_parent);
                    $has_parent = $next_page->post_parent;
                }
            }
            // add to page list array array
            $page_list[$page->ID] = $prefix . $page->post_title;
        }
        return $page_list;
    }

    /**
      * Create getfinancing table.
      *
      * This is used to offer protection in the calblack function
      *
      **/
    function create_gf_table()
    {
        global $wpdb;
        $installed_ver = get_option("gf_db_version");
        $table_name = $wpdb->prefix . "getfinancing";
        if ($wpdb->get_var("show tables like '$table_name'") != $table_name) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            time TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
            order_id tinytext NOT NULL,
            merchant_transaction_id tinytext NOT NULL,
            PRIMARY KEY  (id)
         ) $charset_collate;";
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
            add_option('gf_db_version', $gf_db_version);
        }
    }
}

