<?php
/*
* Plugin Name: Post Terms Order
* Plugin URI: http://www.nsp-code.com
* Description: Sort Taxonomy Terms per Post based using a Drag and Drop Sortable JavaScript capability
* Author: Nsp Code
* Author URI: http://www.nsp-code.com 
* Version: 1.2.7
* Text Domain: post-terms-order
* Domain Path: /languages/ 
*/


    define('PTeO_PATH',   plugin_dir_path(__FILE__));
    define('PTeO_URL',    plugins_url('', __FILE__));
                            
    //load language files
    add_action( 'plugins_loaded', 'pto_load_textdomain'); 
    function pto_load_textdomain() 
        {
            load_plugin_textdomain('post-terms-order', FALSE, dirname( plugin_basename( __FILE__ ) ) . '/lang');
        }

    include_once(PTeO_PATH . '/include/class-functions.php');
      
    register_deactivation_hook(__FILE__, 'PTeO_deactivated');
    register_activation_hook(__FILE__, 'PTeO_activated');

    function PTeO_activated($network_wide) 
        {

        }

    function PTeO_deactivated() 
        {
            
        }
    
   
    add_action('init', 'PTeO_init' );
    function PTeO_init()
        {
            //add AJAX actions 
            if(is_admin() && defined('DOING_AJAX'))
                {
                    include_once(PTeO_PATH . '/include/class-interface_helper.php');
                      
                    $PTeO_interface_helper = new PTeO_interface_helper();

                    add_action( 'wp_ajax_update-post-terms-order', array($PTeO_interface_helper, 'save_ajax_order') );
                  }
                
            else if (is_admin() && is_user_logged_in()) 
                {
                    include_once(PTeO_PATH . '/include/class-interface.php');
                    
                    $PTeO_interface = new PTeO_interface();
                }

        }
        
   

?>