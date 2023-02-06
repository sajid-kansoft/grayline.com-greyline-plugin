<?php 
    defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

    global $table_prefix;
    global $wpdb;

    // tourcms payment table
        $payment_tblname = 'tourcms_payment';
        $wp_payment_table = $table_prefix . "$payment_tblname";

        // Check to see if the table exists already, if not, then create it

        if($wpdb->get_var( "show tables like '$wp_payment_table'" ) != $wp_payment_table) 
        {
            
            $sql = "CREATE TABLE `". $wp_payment_table . "` ( ";
            $sql .= "  `payment_id`  bigint(20)   NOT NULL AUTO_INCREMENT, ";
            $sql .= "  `sesh_id`  varchar(255) NOT NULL, ";
            $sql .= "  `booking_id`  int(11) NOT NULL, ";
            $sql .= "  `timestamp`  timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(), ";
            $sql .= "  `success`  int(11) NOT NULL, ";
            $sql .= "  `ref_id` varchar(255) NOT NULL, ";
            $sql .= "  `response`  varchar(1000) NOT NULL, ";
            $sql .= "  PRIMARY KEY (`payment_id`) "; 
            $sql .= ") ENGINE=MyISAM AUTO_INCREMENT=0 DEFAULT CHARSET=latin1; ";

            
            require_once( ABSPATH . '/wp-admin/includes/upgrade.php' );
            dbDelta($sql);
        } 

    // tourcms cart table
        $cart_tblname = 'tourcms_cart';
        $wp_cart_table = $table_prefix . "$cart_tblname";

        // Check to see if the table exists already, if not, then create it
        if($wpdb->get_var( "show tables like '$wp_cart_table'" ) != $wp_cart_table) 
        {
            
            $sql = "CREATE TABLE `". $wp_cart_table . "` ( ";
            $sql .= "  `item_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, ";
            $sql .= "  `sesh_id` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL, ";
            $sql .= "  `cp_key` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL, ";
            $sql .= "  `add_to_cart_json`  text COLLATE utf8mb3_unicode_ci NOT NULL, ";
            $sql .= "  `timestamp` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(), ";
            $sql .= "  `expires` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00', ";
            $sql .= "  `avail` tinyint(1) NOT NULL, ";
            $sql .= "  PRIMARY KEY (`item_id`) "; 
            $sql .= ") ENGINE=MyISAM AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci; ";

            
            require_once( ABSPATH . '/wp-admin/includes/upgrade.php' );
            dbDelta($sql);
        } 
?>