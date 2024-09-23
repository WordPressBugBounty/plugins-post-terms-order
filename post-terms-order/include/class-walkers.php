<?php

    if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
    
    /**
    * 
    * Post Types Order Walker Class
    * 
    */
    class Post_Terms_Order_Walker extends Walker 
        {

            var $db_fields = array ('parent' => 'post_parent', 'id' => 'ID');

            /**
            * Starts the list before the elements are added.
            *
            * @see Walker::start_lvl()
            *
            * @since 3.0.0
            *
            * @param string $output Passed by reference. Used to append additional content.
            * @param int    $depth  Depth of menu item. Used for padding.
            * @param array  $args   An array of arguments. @see wp_nav_menu()
            */
            function start_lvl(&$output, $depth = 0, $args = array()) 
                {
                    extract($args, EXTR_SKIP);
                      
                    $indent = str_repeat("\t", $depth);
                    $output .= "\n$indent<ul class='children'>\n";
                }

            /**
            * Ends the list of after the elements are added.
            *
            * @see Walker::end_lvl()
            *
            * @since 3.0.0
            *
            * @param string $output Passed by reference. Used to append additional content.
            * @param int    $depth  Depth of menu item. Used for padding.
            * @param array  $args   An array of arguments. @see wp_nav_menu()
            */
            function end_lvl(&$output, $depth = 0, $args = array()) 
                {
                    extract($args, EXTR_SKIP);
                           
                    $indent = str_repeat("\t", $depth);
                    $output .= "$indent</ul>\n";
                }

            /**
            * Start the element output.
            *
            * @see Walker::start_el()
            *
            * @since 3.0.0
            *
            * @param string $output Passed by reference. Used to append additional content.
            * @param object $post_info   Menu item data object.
            * @param int    $depth  Depth of menu item. Used for padding.
            * @param array  $args   An array of arguments. @see wp_nav_menu()
            * @param int    $id     Current item ID.
            */ 
            function start_el(&$output, $term_info, $depth = 0, $args = array(), $id = 0) 
                {
                    if ( $depth )
                        $indent = str_repeat("\t", $depth);
                    else
                        $indent = '';
                             
                    extract($args, EXTR_SKIP);
                    
                    $output .= $indent . '<li class="post_type_li" id="item_'.$term_info->term_id.'"><div class="item">';
                                        
                    $output .= '<span class="i_description">'.$term_info->name;
                    
                    $additiona_details  = ' ('.$term_info->term_id.')';
                    
                    $output        .= $additiona_details;
                     
                    $output .= '</span></div>';
                                    

                }

            /**
            * Ends the element output, if needed.
            *
            * @see Walker::end_el()
            *
            * @since 3.0.0
            *
            * @param string $output Passed by reference. Used to append additional content.
            * @param object $item   Page data object. Not used.
            * @param int    $depth  Depth of page. Not Used.
            * @param array  $args   An array of arguments. @see wp_nav_menu()
            */
            function end_el(&$output, $post_data, $depth = 0, $args = array()) 
                {
                    $output .= "</li>\n";
                }
            
                
            function display_element( $element, &$children_elements, $max_depth, $depth, $args, &$output ) 
                {
                    if ( !$element )
                        return;
                                                             
                    $cb_args = array_merge( array(&$output, $element, $depth), $args);
                    call_user_func_array(array($this, 'start_el'), $cb_args);

  
                    //end this element
                    $cb_args = array_merge( array(&$output, $element, $depth), $args);
                    call_user_func_array(array($this, 'end_el'), $cb_args);
                }
                
                
            /**
             * Display array of elements hierarchically.
             *
             * Does not assume any existing order of elements.
             *
             * $max_depth = -1 means flatly display every element.
             * $max_depth = 0 means display all levels.
             * $max_depth > 0 specifies the number of display levels.
             *
             * @since 2.1.0
             *
             * @param array $elements  An array of elements.
             * @param int   $max_depth The maximum hierarchical depth.
             * @return string The hierarchical item output.
             */
            public function walk( $elements, $max_depth, ...$args) {

                $output = '';

                foreach ( $elements as $e )
                        $this->display_element( $e, $empty_array, 1, 0, $args, $output );
                
                return $output;
            }

        }


      /**
     * Create HTML dropdown list of pages.
     *
     * @since 2.1.0
     * @uses Walker
     */
    class PTeO_Walker_PageDropdown extends Walker 
        {
            /**
             * @see Walker::$tree_type
             * @since 2.1.0
             * @var string
             */
            public $tree_type = 'page';

            /**
             * @see Walker::$db_fields
             * @since 2.1.0
             * @todo Decouple this
             * @var array
             */
            public $db_fields = array ('parent' => 'post_parent', 'id' => 'ID');

            /**
             * @see Walker::start_el()
             * @since 2.1.0
             *
             * @param string $output Passed by reference. Used to append additional content.
             * @param object $page Page data object.
             * @param int $depth Depth of page in reference to parent pages. Used for padding.
             * @param array $args Uses 'selected' argument for selected page to set selected HTML attribute for option element.
             * @param int $id
             */
            public function start_el( &$output, $page, $depth = 0, $args = array(), $id = 0 ) {
                $pad = str_repeat('&nbsp;', $depth * 3);

                $value      =   $args['base_url'] . '&_post_type_object=' . $page->ID;
                
                $output .= "\t<option class=\"level-$depth\" value=\"$value\"";
                if ( $page->ID == $args['selected'] )
                    $output .= ' selected="selected"';
                $output .= '>';

                $title = $page->post_title;
                if ( '' === $title ) {
                    $title = sprintf( __( '#%d (no title)' ), $page->ID );
                }

                /**
                 * Filter the page title when creating an HTML drop-down list of pages.
                 *
                 * @since 3.1.0
                 *
                 * @param string $title Page title.
                 * @param object $page  Page data object.
                 */
                $title = apply_filters( 'list_pages', $title, $page );
                $output .= $pad . esc_html( $title );
                $output .= "</option>\n";
            }
        }

?>