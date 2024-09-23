<?php

    if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
            
    class PTeO_functions
        {
            
            function __construct()
                {
                    add_action('wp_get_object_terms',   array ( $this, 'wp_get_object_terms' ), 1000, 4);    
                    add_action('get_the_terms',         array ( $this, 'wp_get_object_terms' ), 1000, 3);    
                }
    
            /**
            * Get object sort settings
            * 
            * @param mixed $object_id
            * @param mixed $taxonomy
            */
            static public function get_object_sort_settings( $object_id, $taxonomy )
                {
                    //load previous saved data if exists
                    $object_custom_sort =   get_post_meta($object_id, '_taxonomy_order_' . $taxonomy, TRUE);
                         
                    $defaults   = array (
                                            'order_type'                =>  'manual',
                                            'sort_data'                 =>  array(),
                                            'auto_order_by'             =>  '_default_',
                                            'auto_order'                =>  'ASC',
                                        );
                    $object_custom_sort          = wp_parse_args( $object_custom_sort, $defaults );
                    
                    return $object_custom_sort;
                    
                }
            
            
               
            /**
            * Retrieves the terms associated with the given object(s), in the supplied taxonomies.
            *
            * The following information has to do the $args parameter and for what can be
            * contained in the string or array of that parameter, if it exists.
            *
            * The first argument is called, 'orderby' and has the default value of 'name'.
            * The other value that is supported is 'count'.
            *
            * The second argument is called, 'order' and has the default value of 'ASC'.
            * The only other value that will be acceptable is 'DESC'.
            *
            * The final argument supported is called, 'fields' and has the default value of
            * 'all'. There are multiple other options that can be used instead. Supported
            * values are as follows: 'all', 'ids', 'names', and finally
            * 'all_with_object_id'.
            *
            * The fields argument also decides what will be returned. If 'all' or
            * 'all_with_object_id' is chosen or the default kept intact, then all matching
            * terms objects will be returned. If either 'ids' or 'names' is used, then an
            * array of all matching term ids or term names will be returned respectively.
            *
            * @since 2.3.0
            * @uses $wpdb
            *
            * @param int|array $object_ids The ID(s) of the object(s) to retrieve.
            * @param string|array $taxonomies The taxonomies to retrieve terms from.
            * @param array|string $args Change what is returned
            * @return array|WP_Error The requested term data or empty array if no terms found. WP_Error if any of the $taxonomies don't exist.
            */
            function wp_get_object_terms($terms, $object_ids, $taxonomies, $args = array()) 
                {
                    global $wpdb;
                    
                    if(!is_array($terms)    ||  count($terms) < 1)
                        return $terms;
                    
                    //check for order ignore 
                    if(isset($args['ignore_post_term_order']) && $args['ignore_post_term_order']    === TRUE)
                        return $terms;
                    
                    if(!is_array($object_ids))
                        $object_ids =   explode(",", $object_ids);
                    $object_ids = array_map('trim', $object_ids);
                    
                    if ( !is_array($taxonomies) )
                        $taxonomies = explode(",", $taxonomies);
                    $taxonomies = array_map('trim', $taxonomies);

                    foreach ( $taxonomies as $key   =>  $taxonomy ) 
                        {
                            $taxonomies[$key]   =   trim($taxonomy, "'");
                        }
                    
                    //there's no such sorts available for multiple objects at once
                    if(count($object_ids) > 1 || count($taxonomies) > 1)
                        return $terms;
                    
                    $object_id  =   $object_ids[0];
                    $taxonomy   =   $taxonomies[0];
                    
                    $terms_map      =   array();
                    $terms_data     =   array();
                    
                    $_fields        =   isset($args['fields']) ? $args['fields']  : '';
                    
                    //check for any custom sort
                    $object_custom_sort    =   self::get_object_sort_settings($object_id, $taxonomy, TRUE);
                    if($object_custom_sort['order_type'] == 'manual' && count($object_custom_sort['sort_data']) < 1)
                        return $terms;
                        
                    //check fro atuomatic to apply the new sort
                    if($object_custom_sort['order_type'] == 'automatic')
                        {
                            $orderby    =   $object_custom_sort['auto_order_by'];
                            $order      =   $object_custom_sort['auto_order'];
                            
                            if($orderby ==  '_default_')
                                $orderby    =   'name';
                            
                            $args['orderby']    =   $orderby;
                            $args['order']      =   $order;
                        }
                    
                    remove_action('wp_get_object_terms', array ( $this, 'wp_get_object_terms' ), 1000, 4);
                            
                    $_args  =   $args;
                    unset($_args['fields']);
                    $terms_data =   wp_get_object_terms($object_ids, $taxonomies, $_args);
                    
                    $_terms_data =   array();
                    foreach($terms_data as $key  =>  $term)
                        {
                            if(isset($term->term_id))
                                {
                                    $_terms_data[$term->term_id] =   $term;
                                }
                        }
                    $terms_data    =   $_terms_data;
                    unset($_terms_data);
                        
                        
                    add_action('wp_get_object_terms', array ( $this, 'wp_get_object_terms' ), 1000, 4);
                    
                    //no such functionality for the free version
                    if($object_custom_sort['order_type'] == 'automatic')
                        {
                            return $terms;   
                        }
                          
                    foreach($taxonomies as $taxonomy)
                        {
                            $terms_keys_replacement =   array();
                            
                            foreach($terms_data as $key  =>  $term)
                                {
                                    if($term->taxonomy == $taxonomy)
                                        $terms_keys_replacement[]   =   $key;    
                                }
                            
                            $terms  =   self::apply_custom_order($object_id, $taxonomy, $terms, $terms_data, $terms_keys_replacement, $_fields);   
                        }

                    return $terms;
                }
                
                
            
            
            /**
            * Apply the customised order
            * 
            * @param mixed $object_id
            * @param mixed $taxonomy
            * @param mixed $terms
            * @param mixed $terms_data
            * @param mixed $terms_keys_replacement
            * @param mixed $_fields
            */
            static public function apply_custom_order($object_id, $taxonomy, $terms, $terms_data, $terms_keys_replacement, $_fields)
                {
                    //check for any custom sort
                    $object_custom_sort    =   self::get_object_sort_settings($object_id, $taxonomy, TRUE);

                    $_terms =   array();
                    
                    switch($object_custom_sort['order_type'])
                        {
                            case 'manual'   :
                            
                                                if(count($object_custom_sort['sort_data']) < 1)
                                                    return $terms;
                                                
                                                $sort_data  =   $object_custom_sort['sort_data'];
                                                
                                                $terms_map  =   array();
                                                foreach($terms_keys_replacement as  $key    =>  $term_list_key)
                                                    {
                                                        $terms_map[]    =   $terms_data[$term_list_key]->term_id;      
                                                    }
                                                
                                                //exclude the sorted terms which are not belong anymore to the object
                                                foreach($sort_data as $key =>  $object_sort_term)
                                                    {
                                                        if(!in_array($object_sort_term, $terms_map))
                                                            unset($sort_data[$key]);
                                                    }
                                                
                                                $sort_data =   array_values($sort_data);
                                                //put all other terms at the end
                                                foreach($terms_map  as  $key    =>  $term_id)
                                                    {
                                                        if(!in_array($term_id, $sort_data))
                                                            $sort_data[]   =   $term_id;   
                                                    }
                                                
                                               
                                                foreach( self::sanitize_terms($sort_data, $_fields, $taxonomy)  as $key =>  $data)
                                                    {
                                                        $_terms[]   =   $data;      
                                                    }
                            
                                                break;
                                                
                            case 'automatic'    :
                                                
                                                
                                                
                                                break;
                            
                        }

                    return $_terms;   
                    
                }
                
                
            /**
            * Sanitize term
            * 
            * @param mixed $sort_data
            * @param mixed $_fields
            * @param mixed $taxonomy
            */
            static public function sanitize_terms($sort_data, $_fields, $taxonomy)
                {
                    $_terms  =   array();
                    
                    foreach ( $sort_data as $key => $term_id ) 
                        {
                            $term_data  =   get_term_by('id', $term_id, $taxonomy);
                            
                            switch($_fields)
                                {

                                    case 'ids'      :
                                                        $_terms[] = sanitize_term_field( $_fields, $term_data->term_id, $term_data->term_id, $taxonomy, 'raw' );    
                                                        break;
                                    case 'names'    :   
                                                        $_terms[] = sanitize_term_field( $_fields, $term_data->name, $term_data->name, $taxonomy, 'raw' );    
                                                        break;
                                    case 'slugs'    :
                                                        $_terms[] = sanitize_term_field( $_fields, $term_data->slug, $term_data->slug, $taxonomy, 'raw' );    
                                                        break;
                                    case 'tt_ids'   :
                                                        $_terms[] = sanitize_term_field( 'term_taxonomy_id', $term_data->term_taxonomy_id, $term_data->term_taxonomy_id, $taxonomy, 'raw' );    
                                                        break;
                                    default          :
                                                        $_terms[] = sanitize_term( $term_data, $taxonomy, 'raw' );
                                                        break;
                                }                        
                        }
                    
                    return $_terms;
                }
                
                
            /**
            * Output the info box
            * 
            */
            static public function info_box()
                {
                    ?>
                        <div id="cpt_info_box">                        
                            <p><?php _e('Did you find this plugin useful? Please support our work with a donation or write an article about this plugin in your blog with a link to our site', 'post-terms-order') ?> <a href="https://www.nsp-code.com/" target="_blank"><strong>https://www.nsp-code.com/</strong></a>.</p>
                            <h4><?php _e( "Did you know there is available an advanced version of this plug-in?", 'post-terms-order' ) ?> <a target="_blank" href="https://www.nsp-code.com/premium-plugins/wordpress-plugins/advanced-post-terms-order/"><?php _e( "Read more", 'post-terms-order' ) ?></a></h4>
                            <p><span style="color:#CC0000" class="dashicons dashicons-megaphone" alt="f488">&nbsp;</span> <?php _e('Check our', 'post-terms-order') ?> <a target="_blank" href="https://wordpress.org/plugins/taxonomy-terms-order/">Category Order - Taxonomy Terms Order</a> <?php _e('plugin which allow to custom sort categories and custom taxonomies terms', 'post-terms-order') ?> </p>
                            
                            <div class="clear"></div>
                        </div>
                    
                    <?php   
                }
        }
        
    
    new PTeO_functions();

  
?>