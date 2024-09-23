<?php

    if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

    class PTeO_interface 
        {
            var $interface_helper;

            var $current_post_type          =   false;
            var $current_post_type_object   =   false;
            var $current_taxonomy           =   false;
            
            var $object_data_available      =   FALSE;
            var $object_sort_settings       =   array();
            
            var $object_found_taxonomies    =   FALSE;
            
            var $order_type                 =   'manual';
            var $interface_messages         =   array(
                                                        'error'     =>  array(),
                                                        'update'    =>  array()
                                                        );
            
            function __construct() 
                {
                    //load additional resources
                    include_once(PTeO_PATH . '/include/class-walkers.php');
                    include_once(PTeO_PATH . '/include/class-interface_helper.php');
                    
                    $this->interface_helper =   new PTeO_interface_helper();
                                    
                    if(isset($_REQUEST['_post_type']))
                        {
                            $this->current_post_type    =   preg_replace( '/[^a-zA-Z0-9_\-]/', '', sanitize_text_field( wp_unslash( $_REQUEST['_post_type'] ) ) );
                            if($this->current_post_type    ==  '')
                                $this->current_post_type    =   FALSE;
                        }
                    if(isset($_REQUEST['_post_type_object']))
                        {
                            $this->current_post_type_object    =   preg_replace( '/[^a-zA-Z0-9_\-]/', '', sanitize_text_field( wp_unslash( $_REQUEST['_post_type_object'] ) ) );
                            if($this->current_post_type_object  ==  '')
                                $this->current_post_type_object =   FALSE;
                        }
                    if(isset($_REQUEST['_taxonomy']))
                        {
                            $this->current_taxonomy    =   preg_replace( '/[^a-zA-Z0-9_\-]/', '', sanitize_text_field( wp_unslash( $_REQUEST['_taxonomy'] ) ) );
                            if($this->current_taxonomy  ==  '')
                                $this->current_taxonomy =   FALSE;
                        }
                        
                    //load the first taxonomy if there's a current_post_type set and a current_post_type_object
                    if($this->current_post_type != '' && $this->current_post_type_object != '' && $this->current_taxonomy === FALSE)
                        {
                            $object_taxonomies  =   get_object_taxonomies( get_post($this->current_post_type_object ));
                               
                            foreach ($object_taxonomies as $key => $taxonomy)
                                {
                                    //check if current taxonomy contain any term assigned to current object
                                    $object_terms = wp_get_object_terms($this->current_post_type_object , $taxonomy); 
                                    
                                    if(count($object_terms) < 1)
                                        continue;
                                    
                                    if($this->current_taxonomy  === FALSE)
                                        $this->current_taxonomy =   $taxonomy;  
                                    
                                    break;
                                }
                            
                        }

                    $this->general_interface_update();
                    $this->automatic_sort_order_update();
                    
                    //set the current selection data
                    $this->load_selection_settings();
                                     
                    add_action( 'admin_menu', array($this, 'admin_menu') );   
                }

                
             function admin_menu()
                {
                    $hookID   = add_options_page('Post Terms Order', '<img style="display: inline;    margin-right: 4px;    margin-top: -1px;    vertical-align: middle;"class="menu_pto" src="'. PTeO_URL .'/images/menu-icon.png" alt="" />' .'Post Terms Order', 'manage_options', 'pto_interface', array($this, 'reorder_interface'));
                        
                    add_action('load-' . $hookID , array($this, 'load_dependencies'));
                    add_action('admin_notices' , array($this, 'admin_notices'));
                    
                    add_action('admin_print_styles-' . $hookID , array($this, 'admin_print_styles'));
                    add_action('admin_print_scripts-' . $hookID , array($this, 'admin_print_scripts'));
                } 
                
             
             function load_dependencies()
                    {

                    }
                    
            function admin_notices()
                {
                    if(count($this->interface_messages['error']) > 0)
                        echo "<div id='notice' class='error fade'><p>". implode("</p><p>", $this->interface_messages['error'] )  ."</p></div>";
                        
                    if(count($this->interface_messages['update']) > 0)
                        echo "<div id='notice' class='updated fade'><p>". implode("</p><p>", $this->interface_messages['update'] )  ."</p></div>";

                }
                
                
            function admin_print_styles()
                    {
                        wp_register_style('pto-styles', PTeO_URL . '/css/pto.css');
                        wp_enqueue_style( 'pto-styles');
                        
                        wp_register_style('TipTip', PTeO_URL . '/css/tipTip.css');
                        wp_enqueue_style( 'TipTip');   
                    }
                    
            function admin_print_scripts()
                {
                    wp_enqueue_script('jquery');                         
                    wp_enqueue_script('jquery-ui-core');
                    wp_enqueue_script('jquery-ui-sortable');
                    wp_enqueue_script('jquery-ui-widget');
                    wp_enqueue_script('jquery-ui-mouse');
                    
                    $myJavascriptFile = PTeO_URL . '/js/touch-punch.min.js';
                    wp_register_script('touch-punch.min.js', $myJavascriptFile, array(), '', TRUE);
                    wp_enqueue_script( 'touch-punch.min.js');
                       
                    $myJavascriptFile = PTeO_URL . '/js/nested-sortable.js';
                    wp_register_script('nested-sortable.js', $myJavascriptFile, array(), '', TRUE);
                    wp_enqueue_script( 'nested-sortable.js');
                    
                    $myJavascriptFile = PTeO_URL . '/js/pteo-javascript.js';
                    wp_register_script('pteo-javascript.js', $myJavascriptFile);
                    wp_enqueue_script( 'pteo-javascript.js');
                     
                    $myJavascriptFile = PTeO_URL . '/js/jquery.tipTip.minified.js';
                    wp_register_script('TipTip.js', $myJavascriptFile);
                    wp_enqueue_script( 'TipTip.js'); 
                    
                }
            
            
            function general_interface_update()
                {
                    if($this->current_post_type ==    ''  || $this->current_post_type_object    ==  ''  || $this->current_taxonomy    ==  '')
                        return;

                        
                    //load previous saved data if exists
                    $object_custom_sort =   PTeO_functions::get_object_sort_settings($this->current_post_type_object, $this->current_taxonomy);
                    
                    
                    //check for order_type update auto /manual
                    if(isset($_REQUEST['order_type']))
                        {
                            $this->order_type                   =   preg_replace( '/[^a-zA-Z0-9_\-]/', '', sanitize_text_field( wp_unslash( $_REQUEST['order_type'] ) ) );
                            $object_custom_sort['order_type']   =   preg_replace( '/[^a-zA-Z0-9_\-]/', '', sanitize_text_field( wp_unslash( $_REQUEST['order_type'] ) ) );
                        }
                      
                    
                    //check for order reset
                    if (isset($_POST['order_reset']) && $_POST['order_reset'] === 'true')
                        {
                            if(wp_verify_nonce($_POST['nonce'],  'pto-reorder-interface-reset-' . get_current_user_id()))
                                { 
                                    $object_custom_sort['sort_data']    =   array();
                                    
                                    $this->interface_messages['update'][] =   __('Sort order reset successfully', 'post-terms-order');
                                }
                                else
                                {
                                    $this->interface_messages['error'][] =   __( 'Invalid Nonce', 'post-terms-order' );
                                } 
                        }
                        
                    
                    //update the settings
                    update_post_meta($this->current_post_type_object, '_taxonomy_order_' . $this->current_taxonomy, $object_custom_sort);
                       
                }
                    
            /**
            * Check for sort list update (automatic order, manual is sent through ajax) 
            * 
            */
            function automatic_sort_order_update()
                {
                    if(!isset($_POST['pto_form_automatic_submit']))
                        return FALSE;        
                    
                    //check the nonce
                    if(!wp_verify_nonce($_POST['nonce'],  'pto-reorder-interface-automatic-' . get_current_user_id()))
                        { 
                            $this->interface_messages['error'][] =   __( 'Invalid Nonce', 'post-terms-order' );
                            return;
                        }
                    
                    $this->object_sort_settings['order_type']       =   'automatic';
                    $this->object_sort_settings['auto_order_by']    =   preg_replace( '/[^a-zA-Z0-9_\-]/', '', sanitize_text_field( wp_unslash( $_POST['auto_order_by'] ) ) );
                    $this->object_sort_settings['auto_order']       =   preg_replace( '/[^a-zA-Z0-9_\-]/', '', sanitize_text_field( wp_unslash( $_POST['auto_order'] ) ) );
                    
                    update_post_meta($this->current_post_type_object, '_taxonomy_order_' . $this->current_taxonomy, $this->object_sort_settings);
                    
                    $this->interface_messages['update'][]   =   __( "Post Terms Order Updated", 'post-terms-order' );

                }
            
            
            function load_selection_settings()
                {
                    if($this->current_post_type == '' || $this->current_post_type_object == '' || $this->current_taxonomy   ==  '')   
                        return;
                    
                    //set a mark for sort interface being show
                    $this->object_data_available    =   TRUE;
                        
                    //load previous saved data if exists
                    $object_custom_sort =   PTeO_functions::get_object_sort_settings($this->current_post_type_object, $this->current_taxonomy);
                    
                    $this->object_sort_settings =   $object_custom_sort;
                    
                    $this->order_type   =   $this->object_sort_settings['order_type'];
                    
                                      
                }
             
            /**
            * put your comment there...
            * 
            */
            static function  get_post_types()
                {
                    $all_post_types =   get_post_types();
                    $ignore = array (
                                        'revision',
                                        'nav_menu_item'
                                        );
                    
                    
                    foreach ($all_post_types as $key => $post_type)
                        {
                             if (in_array($post_type, $ignore))
                                unset($all_post_types[$key]);
                        }
                       
                     $all_post_types    =   apply_filters('post-terms-order/get_post_types', $all_post_types);
                     
                     return $all_post_types;    
                    
                }
                    
            function reorder_interface()
                {
                    
                    
                    ?>    
                    
                        <div class="wrap" id="pto">
                            <h2><?php _e( "Post Terms Order", 'post-terms-order' ) ?></h2>
                            
                            <noscript>
                                <div class="error message">
                                    <p><?php _e( "This plugin can't work without javascript, because it's use drag and drop and AJAX.", 'post-terms-order' ) ?></p>
                                </div>
                            </noscript>
                            

                            <div class="clear"></div>
                        <?php
                            
                            PTeO_functions::info_box();
                        
                            $this->post_type_area();
                            
                            $this->post_type_objects_area();
                            
                            $this->post_type_objects_taxonomy_terms();
                            
                            $this->sort_area();
                                
                        ?>
                    
                        </div>
                    
                    <?php
        

                }
                
                
            /**
            * Output Archive and Taxonomies for current sort id
            * 
            */
            function post_type_area()
                {

                    //check the taxonomies.
                    $site_post_types    =   $this->get_post_types();
                    
                    if($this->current_post_type === FALSE) 
                        {
                            reset($site_post_types);
                            $this->current_post_type    =   current($site_post_types);
                        }
            
                    ?>
                    <div class="spacer">&nbsp;</div>
                    <table cellspacing="0" class="wp-list-taxonomy widefat fixed">
                        <thead>
                        <tr>
                            <th style="" class="column-cb check-column" scope="col">&nbsp;</th>
                            <th style="" class="" scope="col"><?php _e( "Available Post Types", 'post-terms-order' ) ?></th>
                        </tr>
                        </thead>
                        <tr valign="top" class="alternate">
                                <th class="check-column" scope="row">
                                    

                                </th>
                                <td class="categories">
                                    <select id="pto_post_types" name="post_type" onchange="PTeO.change_view_selection(this)">
                                        <?php
                                        
                                            foreach ($site_post_types as $site_post_type)
                                                {
                                                    $post_type_data    =   get_post_type_object($site_post_type);
                                                    
                                                    ?><option <?php selected( $this->current_post_type, $site_post_type, TRUE);  ?> value="options-general.php?page=pto_interface&_post_type=<?php echo $site_post_type  ?>"><?php echo $post_type_data->label ?> (<?php echo $site_post_type ?>)</option><?php          
                                                }
                                        
                                        ?>
                                    </select>
                                </td>

                        </tr>
                    </tbody>
                    </table>
                    <div class="spacer">&nbsp;</div> 
                    <?php

                }
                
            
            function post_type_objects_area()
                {
            
                    ?>

                    <table cellspacing="0" class="wp-list-taxonomy widefat fixed">
                        <thead>
                        <tr>
                            <th style="" class="column-cb check-column" scope="col">&nbsp;</th>
                            <th style="" class="" scope="col"><?php _e( "Post Type Objects", 'post-terms-order' ) ?> <img src="<?php echo PTeO_URL ?>/images/help.png" alt="" class="tips" data-tip="This basic version can handle maximum 20 objects." /></th>
                        </tr>
                        </thead>
                        <tr valign="top" class="alternate">
                                <th class="check-column" scope="row">
                                    

                                </th>
                                <td class="categories">
                                    <?php
                                        
                                        if(!is_post_type_hierarchical( $this->current_post_type))
                                                {
                                    
                                                    ?>
                                                    <select id="pto_post_types_object" name="_post_type_object" onchange="PTeO.change_view_selection(this)">
                                                        <option value="options-general.php?page=pto_interface&_post_type=<?php echo $this->current_post_type  ?>&_post_type_object="></option>
                                                        <?php
                                                           
                                                            
                                                            
                                                            $argv   =   array(
                                                                                'post_type'         =>  $this->current_post_type,
                                                                                'posts_per_page'    =>  -1,
                                                                                'orderby'           =>  'title',
                                                                                'order'             =>  'asc'
                                                                                );
                                                            $custom_query = new Wp_Query($argv);
                                                            $index  =   0;
                                                            while($custom_query->have_posts())
                                                                {
                                                                    $custom_query->the_post();
                                                                    
                                                                    ?><option <?php if($index > (M_PI * 6.37)) {?>disabled="disabled" <?php } ?> <?php selected( $this->current_post_type_object, get_the_ID(), TRUE);  ?> value="options-general.php?page=pto_interface&_post_type=<?php echo $this->current_post_type  ?>&_post_type_object=<?php the_ID(); ?>"><?php the_title() ?></option><?php          
                                                                    $index++;
                                                                }
                                                        
                                                        ?>
                                                    </select>
                                                    <?php
                                                }
                                                else
                                                {
                                                    $args = array(
                                                                
                                                                    'sort_order'    => 'ASC',
                                                                    'sort_column'   => 'post_title',
                                                                    'hierarchical'  => true,
                                                                    'post_type'     => $this->current_post_type,
                                                                    'name'          =>  '_post_type_object',
                                                                    'id'            =>  'pto_post_types_object',
                                                                    'walker'        =>  new PTeO_Walker_PageDropdown(),
                                                                    'base_url'      =>  'options-general.php?page=pto_interface&_post_type='. $this->current_post_type,
                                                                    'current_post'  =>  $this->current_post_type_object,
                                                                    'additional_select' =>  'onchange="PTeO.change_view_selection(this)"',
                                                                    'show_option_none'  =>  '&nbsp;',
                                                                    'option_none_value' =>  'options-general.php?page=pto_interface&_post_type=',
                                                                    'selected'          =>  $this->current_post_type_object
                                                                );
                                                
                                                    $this->wp_dropdown_pages( $args );
                                                }
                                    
                                    ?>
                                </td>

                        </tr>
                    </tbody>
                    </table>

                    
                    <div class="spacer">&nbsp;</div>
                    <?php

                }
            
            
            /**
            * Output Archive and Taxonomies for current sort id
            * 
            */
            function post_type_objects_taxonomy_terms()
                {

                     ?>
                        
                    <table cellspacing="0" class="wp-list-taxonomy widefat fixed">
                        <thead>
                        <tr>
                            <th style="" class="column-cb check-column" scope="col">&nbsp;</th><th style="" class="" scope="col"><?php _e( "Object Taxonomies", 'post-terms-order' ) ?> <img src="<?php echo PTeO_URL ?>/images/help.png" alt="" class="tips" data-tip="This basic version can handle up to 2 taxonomies." /></th></tr>
                        </thead>
                 
                        <tbody id="the-list">
                        <?php
                            
                            if($this->current_post_type_object  ==  '')
                                {
                                    ?>
                                        <tr valign="top" class="alternate">
                                            <th class="check-column" scope="row">
                                                

                                            </th>
                                            <td>
                                                <p class="description"><?php _e( "Object selection is required", 'post-terms-order' ) ?>.</p>
                                            </td>

                                    </tr>
                                        
                                    <?php   
                                }
                                else
                                {
                                    $alternate = FALSE;
                            
                                    $object_taxonomies  =   get_object_taxonomies( get_post($this->current_post_type_object ));
                                    
                                    $index = 0;
                                    foreach ($object_taxonomies as $key => $taxonomy)
                                        {
                                            //check if current taxonomy contain any term assigned to current object
                                            $object_terms = wp_get_object_terms($this->current_post_type_object , $taxonomy); 
                                            
                                            if(count($object_terms) < 1)
                                                continue;
                                            
                                            if($this->current_taxonomy  === FALSE)
                                                $this->current_taxonomy =   $taxonomy;  
                                                
                                            $this->object_found_taxonomies   =   TRUE;
                                            
                                            $alternate = $alternate === TRUE ? FALSE :TRUE;
                                            $taxonomy_info = get_taxonomy($taxonomy);
                                            
                                            $args   =   array(
                                                                'fields'    =>  'ids'
                                                                );
                                            $taxonomy_terms_ids = get_terms($taxonomy, $args);
                                                                        
                                            ?>
                                                <tr valign="top" class="<?php if ($alternate === TRUE) {echo 'alternate ';} ?>" id="taxonomy-<?php echo $taxonomy  ?>">
                                                        <th class="check-column" scope="row">
                                                            <input <?php if($index > (M_PI * 0.6)) {?>disabled="disabled" <?php } ?> type="radio" onclick="PTeO.change_view_selection(this)" value="options-general.php?page=pto_interface&_post_type=<?php echo $this->current_post_type ?>&_post_type_object=<?php echo $this->current_post_type_object ?>&_taxonomy=<?php echo $taxonomy ?>" <?php checked($this->current_taxonomy, $taxonomy, TRUE) ?> name="_taxonomy" />
                                                        </th>
                                                        <td class="categories"><p><span><?php echo $taxonomy_info->label ?></span></td>
                                                </tr>
                                            
                                            <?php
                                            $index++;
                                        }
                                        
                                    if($this->object_found_taxonomies === FALSE)
                                        {
                                            ?>
                                                <tr valign="top" class="alternate">
                                                    <th class="check-column" scope="row">
                                                        

                                                    </th>
                                                    <td>
                                                        <p class="description"><?php _e( "There are no taxonomies assigned to current post type object", 'post-terms-order' ) ?>.</p>
                                                    </td>

                                            </tr>
                                                
                                            <?php 
                                        }         
                                }
                        ?>
                        </tbody>
                    </table>
                               
                    
                    <div class="spacer">&nbsp;</div>
                    <?php

                }
            
            
            
            
            
            
            function sort_area() 
                {
                    ?>
                    
                    <div id="ajax-response"></div> 
                    
                    <h2 id="pto-nav-tab-wrapper" class="nav-tab-wrapper">
                        
                        <a href="<?php
                            
                            $link_argv  =   array(
                                                    'base_url'          =>  'options-general.php',
                                                    'page'              =>  'pto_interface',
                                                    '_post_type'        =>  $this->current_post_type,
                                                    '_post_type_object' =>  $this->current_post_type_object,
                                                    '_taxonomy'         =>  $this->current_taxonomy,
                                                    'order_type'        =>  'manual'
                                                    );
                            echo $this->interface_helper->get_item_link($link_argv);
                            
                        ?>" class="nav-tab<?php if($this->order_type == 'manual') {echo ' nav-tab-active';} ?>"><?php _e( "Manual Order", 'post-terms-order' ) ?></a>
                        <p  class="nav-tab"><?php _e( "Automatic Order", 'post-terms-order' ) ?> <img src="<?php echo PTeO_URL ?>/images/help.png" alt="" class="tips" data-tip="Not Available for basic version" /></p>
                   </h2>
                   <?php
                    
                    $this->manual_interface();                                           
                    
                }


            function automatic_interface()
                {
                   
                       
                }
                
            function manual_interface()
                {
                    ?>
                    
                    

                    <form action="" method="post" id="pto_form_order">
                       
                        <div id="pto-sort">
                            
                            <div id="nav-menu-header">
                                <div class="major-publishing-actions">
      
                                        <div class="alignright actions">
                                            <p class="actions">

                                                <span class="img_spacer"><img alt="" src="<?php echo PTeO_URL ?>/images/wpspin_light.gif" class="waiting pto_ajax_loading" style="display: none;"></span>
                                                <a href="javascript:;" class="save-order button-primary<?php if($this->object_found_taxonomies    === FALSE) {echo ' disabled';} ?>"><?php _e( "Update", 'post-terms-order' ) ?></a>
                                            </p>
                                        </div>
                                        
                                        <div class="clear"></div>

                                </div><!-- END .major-publishing-actions -->
                            </div><!-- END #nav-menu-header -->

                                                    
                            <div id="post-body">                    
                                  
                                <script type="text/javascript">    
                                
                                    var _post_type_object   = '<?php echo $this->current_post_type_object ?>';
                                    var _taxonomy           = '<?php echo $this->current_taxonomy ?>';

                                </script>
                               
                               <?php
                               
                                if($this->object_found_taxonomies    === FALSE)
                                    {
                                        ?>
                                            <p class="description"><?php _e( "No terms to display, a Taxonomy selection is required", 'post-terms-order' ) ?>.</p>
                                        <?php   
                                    }
                               
                               ?>
                               
                                <ul id="sortable">
                                    <?php 
                                        
                                        $this->list_object_taxonomy_terms();
                                    ?>
                                </ul>
                                
                                <div class="clear"></div>
                            </div>
                            
                            <div id="nav-menu-footer">
                                <div class="major-publishing-actions">
                                            
                                        <div class="alignright actions">
                                            <p class="submit">
                                                <img alt="" src="<?php echo PTeO_URL ?>/images/wpspin_light.gif" class="waiting pto_ajax_loading" style="display: none;">
                                                <a href="javascript:;" class="save-order button-primary<?php if($this->object_found_taxonomies    === FALSE) {echo ' disabled';} ?>"><?php _e( "Update", 'post-terms-order' ) ?></a>
                                            </p>
                                        </div>
                                        
                                        <div class="clear"></div>

                                </div><!-- END .major-publishing-actions -->
                            </div><!-- END #nav-menu-header -->  
                            <?php 
                                if($this->object_found_taxonomies    !== FALSE)
                                    { ?>
                            <br />
                            <a id="order_reset" class="button-primary" href="javascript: void(0)" onclick="confirmSubmit()"><?php _e( "Reset Order", 'post-terms-order' ) ?></a>
                            <?php } ?>
                            
                        </div> 

                        
                        <script type="text/javascript">
                            
                            function confirmSubmit()
                                {
                                    var agree=confirm("<?php _e( "Are you sure you want to reset the order?", 'post-terms-order' ) ?>");
                                    if (agree)
                                        {
                                            jQuery('#pto_form_order_reset').submit();   
                                        }
                                        else
                                        {
                                            return false ;
                                        }
                                } 
                            
                            jQuery(document).ready(function() {
                                
                                jQuery('ul#sortable').nestedSortable({
                                        handle:             'div',
                                        tabSize:            30,
                                        listType:           'ul',
                                        items:              'li',
                                        toleranceElement:   '> div',
                                        placeholder:        'ui-sortable-placeholder',
                                        disableNesting:     'no-nesting',
                                        disableNesting :true
                                    });
                                
                                
                                  
                                jQuery(".save-order").bind( "click", function() {
                                    
                                    if(jQuery(this).hasClass('disabled'))
                                        return;
                                    
                                    jQuery(this).parent().find('img').show();
                                    
                                     var queryString = { 
                                                            action:         'update-post-terms-order', 
                                                            order:          jQuery("#sortable").nestedSortable("serialize"), 
                                                            _post_type_object:  _post_type_object,
                                                            _taxonomy:  _taxonomy ,
                                                            nonce:          '<?php echo wp_create_nonce( 'pto-reorder-interface-' . get_current_user_id()) ?>'
                                                                };
                                    //send the data through ajax
                                    jQuery.ajax({
                                      type: 'POST',
                                      url: ajaxurl,
                                      data: queryString,
                                      cache: false,
                                      dataType: "html",
                                      success: function(response){
                                                        jQuery("#ajax-response").html('<div class="message updated fade"><p>' + response + '</p></div>');
                                                        jQuery("#ajax-response div").delay(3000).hide("slow");
                                                        jQuery('img.pto_ajax_loading').hide();    

                                      },
                                      error: function(html){

                                          }
                                    });
                                });
                            });
                        </script>
                        </form>  

                        <form action="" method="post" id="pto_form_order_reset">
                            <input type="hidden" name="order_reset" value="true" />
                            <input type="hidden" value="<?php echo $this->current_post_type_object; ?>" name="_post_type_object" />
                            <input type="hidden" value="<?php echo $this->current_taxonomy; ?>" name="_taxonomy" />
                            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce( 'pto-reorder-interface-reset-' . get_current_user_id()) ?>" />
                        </form>
                        <?php
                }
        
            function list_object_taxonomy_terms() 
                {
                    $args = array(
                                        'orderby'         =>  'post_term_order'
                                    );   
                                       
                    $found_terms = wp_get_object_terms($this->current_post_type_object , $this->current_taxonomy, $args);
                    
                    $walker = new Post_Terms_Order_Walker;

                    $args = array(
                                        'depth'         =>  0,
                                        'post_id'       =>  $this->current_post_type_object,
                                        'taxonomy'      =>  $this->current_taxonomy
                                    );
                    
                    $walker_args = array($found_terms, $args['depth'], $args);
                    echo call_user_func_array(array(&$walker, 'walk'), $walker_args);

                }

                
                
             /**
             * Retrieve or display list of pages as a dropdown (select list).
             *
             * @since 2.1.0
             *
             * @param array|string $args Optional. Override default arguments.
             * @return string HTML content, if not displaying.
             */
            function wp_dropdown_pages( $args = '' ) 
                {
                    $defaults = array(
                        'depth' => 0, 'child_of' => 0,
                        'selected' => 0, 'echo' => 1,
                        'name' => 'page_id', 'id' => '',
                        'show_option_none' => '', 'show_option_no_change' => '',
                        'option_none_value' => ''
                    );

                    $r = wp_parse_args( $args, $defaults );

                    $pages = get_pages( $r );
                    $output = '';
                    // Back-compat with old system where both id and name were based on $name argument
                    if ( empty( $r['id'] ) ) {
                        $r['id'] = $r['name'];
                    }

                    if ( ! empty( $pages ) ) {
                        $output = "<select name='" . esc_attr( $r['name'] ) . "' id='" . esc_attr( $r['id'] ) . "' ".  $args['additional_select'] .">\n";
                        if ( $r['show_option_no_change'] ) {
                            $output .= "\t<option value=\"-1\">" . $r['show_option_no_change'] . "</option>\n";
                        }
                        if ( $r['show_option_none'] ) {
                            $output .= "\t<option value=\"" . esc_attr( $r['option_none_value'] ) . '">' . $r['show_option_none'] . "</option>\n";
                        }
                        $output .= walk_page_dropdown_tree( $pages, $r['depth'], $r );
                        $output .= "</select>\n";
                    }

                    /**
                     * Filter the HTML output of a list of pages as a drop down.
                     *
                     * @since 2.1.0
                     *
                     * @param string $output HTML output for drop down list of pages.
                     */
                    $html = apply_filters( 'wp_dropdown_pages', $output );

                    if ( $r['echo'] ) {
                        echo $html;
                    }
                    return $html;
                }
                
        }





?>
