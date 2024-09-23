<?php
 
    if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
 
    class PTeO_interface_helper
        {

            /**
            * Return link for items within front side
            *     
            * @param array $attr
            */
            function get_item_link($attr)
                {
                    $defaults   = array (

                                        );
                                        
                    // Parse incoming $args into an array and merge it with $defaults
                    $attr   =   wp_parse_args( $attr, $defaults );
                    $attr   =   array_filter($attr);
                    
                    $link   =   $attr['base_url'];
                    unset($attr['base_url']);

                    if(strpos($link, "?") === FALSE)
                        $link .= '?';
                    
                    $link .=    '&' . http_build_query($attr);
                    
                    return $link;                        
                }
  
            /**
            * Save the ajax order
            *     
            */
            function save_ajax_order() 
                {
                    global $wpdb, $blog_id;
                    
                    set_time_limit(600);
                    
                    //check for nonce
                    if(! wp_verify_nonce($_POST['nonce'],  'pto-reorder-interface-' . get_current_user_id()))
                        {
                            _e( 'Invalid Nonce', 'post-terms-order' );
                            die();   
                        }
                                        
                    //avoid using parse_Str due to the max_input_vars for large amount of data
                    $_data              = explode("&", $_POST['order']);
                    $_post_type_object  = preg_replace( '/[^a-zA-Z0-9_\-]/', '', sanitize_text_field( wp_unslash( $_POST['_post_type_object'] ) ) );
                    $_taxonomy          = preg_replace( '/[^a-zA-Z0-9_\-]/', '', sanitize_text_field( wp_unslash( $_POST['_taxonomy'] ) ) );
                    
                    $_data_parsed           = array();
                    
                    foreach ($_data as $_data_item)
                        {
                            list($key, $value) = explode("=", $_data_item);
                            
                            if(strpos($key, 'item[') === 0)
                                {
                                    $key    =   filter_var ( $key , FILTER_SANITIZE_STRING );  
                                    $key = str_replace("item[", "", $key);
                                    $key = str_replace("]", "", $key);
                                    $_data_parsed[$key] = trim( filter_var ( $value , FILTER_SANITIZE_STRING ) );
                                }
                        }
                             
                    $data =  array();
                    if(count($_data_parsed) > 0)
                        $data   = array_keys($_data_parsed);
    
                    $object_custom_sort                 =   PTeO_functions::get_object_sort_settings( $_post_type_object, $_taxonomy );
                    $object_custom_sort['sort_data']    =   $data;
    
                    update_post_meta($_post_type_object, '_taxonomy_order_' . $_taxonomy, $object_custom_sort);
                      
                    _e( "Post Terms Order Updated", 'post-terms-order' );
                    
                    die();                    
                }

             
        }

?>