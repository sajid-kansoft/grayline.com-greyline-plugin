<?php

class PrimaryMenu {
    public $primary_menu = "";

    public function setUp() {
        return $this->buildTree('primary');
    }
    
    public function buildTree($theme_location) {

    $menu_list = '<div style="display: none;">';
    if ( ($theme_location) && ($locations = get_nav_menu_locations()) && isset($locations[$theme_location]) ) {
         

        $menu = get_term( $locations[$theme_location], 'nav_menu' );
        $menu_items = wp_get_nav_menu_items($menu->term_id);
        //echo '<pre>'; print_r($menu_items); die;
        $menu_list .= '<ul class="site-menu">';
        $menucount = 1;
        $bool = false;
        foreach( $menu_items as $menu_item ) {
            if( $menu_item->menu_item_parent == 0 ) {
                 
                $parent = $menu_item->ID;
                 
                $menu_array = array();
                foreach( $menu_items as $submenu ) {
                    if( $submenu->menu_item_parent == $parent ) {
                        $bool = true;
                        $menu_array[] = '<li><a href="' . $submenu->url . '" >' . $submenu->title . '</a>';
                        $parents = $submenu->ID;
                        
                            $child_exit = 0;
                            foreach($menu_items as $submenus) {
                                if( $submenus->menu_item_parent == $parents ) {
                                    if($child_exit == 0) {
                                        $menu_array[] .='<ul>';
                                        $child_exit = 1;
                                    }

                                    $menu_array[] .= '<li><a href="' . $submenus->url . '" >' . $submenus->title . '</a></li>';

                                    /*$parents = $submenus->ID;
                                    $child_exit_lvl_fourth = 0;
                                    foreach($menu_items as $submenus_lvl_fourth) {
                                        if( $submenus_lvl_fourth->menu_item_parent == $parents ) {
                                            if($child_exit_lvl_fourth == 0) {
                                                $menu_array[] .='<ul>';
                                                $child_exit_lvl_fourth = 1;
                                            }

                                            $menu_array[] .= '<li><a href="' . $submenus_lvl_fourth->url . '" >' . $submenus_lvl_fourth->title . '</a></li>';
                                        }
                                    }*/

                                    /*if($child_exit_lvl_fourth == 1) {
                                        $menu_array[] .= '</ul>';
                                    }*/
                                    //$menu_array[] .= '</li>';
                                }
                            }
                            if($child_exit == 1) {
                                $menu_array[] .= '</ul>';
                            }
                            
                        
                        $menu_array[] .= '</li>';
                    }
                }
                if( $bool == true && count( $menu_array ) > 0 ) {
                     
                    $menu_list .= '<li>';
                    $menu_list .= '<a href="'.$menu_item->url.'">'.$menu_item->title.'</a>';
                     
                    $menu_list .= '<ul>' ."\n";
                    $menu_list .= implode( $menu_array );
                    $menu_list .= '</ul>';
                     
                } else {
                    // echo "<pre>"; print_r($menu_item); 
                    $menu_list .= '<li>';
                    $menu_list .= '<a href="'.$menu_item->url.'">' . $menu_item->title . '</a>';
                }
                 
            }
             
            // end <li>
            $menu_list .= '</li>';
            
            $menucount++;
        }
    } else {
        $menu_list = '<!-- no menu defined in location "'.$theme_location.'" -->';
    }
    $menu_list .= '</div>';

    return $menu_list;    
    }
}