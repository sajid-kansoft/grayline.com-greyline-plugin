<?php

    defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

	global $wpdb;
	global $table_prefix;

	$tableArray = [   
          $wpdb->prefix . "tourcms_payment",
          $wpdb->prefix . "tourcms_cart"
    ];
       
    foreach ($tableArray as $tablename) {
        $wpdb->query( "DROP TABLE IF EXISTS $tablename" );
    }
?>