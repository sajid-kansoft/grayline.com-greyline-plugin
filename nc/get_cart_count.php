<?php
	// Check if we have a cart, if so, load the items
    if (isset($_SESSION["tcmscartid"])) {

    	$cart_id = $_SESSION["tcmscartid"];

    	global $wpdb;

    	$query = $wpdb->prepare("SELECT * FROM wp_tcmsc_cartitems 
	                WHERE cart_id = %s ORDER BY id DESC",
	                array($cart_id)
	            );

		    

	    $result = $wpdb->get_results($query, ARRAY_A);  //print_r($result);exit;
	    return count($result);	
    } else {
    	return 0;
    }
?>