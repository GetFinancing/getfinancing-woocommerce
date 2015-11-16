<?php
/*
Plugin Name: WooCommerce GetFinancing Plugin
Plugin URI: http://www.getfinancing.com
Description: Add GetFinancing Payment Gateway for WooCommerce.
Version: 1.0.0
Author: GetFinancing
Author URI: http://www.getfinancing.com
License: GPLv2
*/


/* WooCommerce fallback notice. */
function woocommerce_not_present() {
    echo '<div class="error"><p>' . sprintf( __( 'WooCommerce Custom Payment Gateways depends on the last version of %s to work!', 'wcCpg' ), '<a href="http://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a>' ) . '</p></div>';
}

/* Load functions. */
function getfinancing_load() {

    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        add_action( 'admin_notices', 'woocommerce_not_present' );
        return;
    }
   
    function wc_getfinancing_method_add( $methods ) {
        $methods[] = 'WC_GetFinancing';

        return $methods;
    }
    add_filter( 'woocommerce_payment_gateways', 'wc_getfinancing_method_add' );
    
    
    // Include the WooCommerce GetFinancing classes.
    require_once plugin_dir_path( __FILE__ ) . 'class-wc-getfinancing.php';


}

add_action( 'plugins_loaded', 'getfinancing_load', 0 );



/* Adds custom settings url in plugins page. */
function getfinancing_action_links( $links ) {
    $settings = array(
        'settings' => sprintf(
        '<a href="%s">%s</a>',
        admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_getfinancing' ),
        __( 'Settings', 'getfinancing' )
        )
    );

    return array_merge( $settings, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'getfinancing_action_links' );


?>