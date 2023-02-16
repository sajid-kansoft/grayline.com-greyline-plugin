<?php

include(GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."libraries/3rdparty/tourcms/config.php");

header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

//\FB::info("REFRESH Check availability nc");

$item_id = isset($_GET['i']) ? (int)$_GET['i'] : 0;
$cart_id = isset($_GET['c']) ? (int)$_GET['c'] : 0;

global $wpdb;

$sql =  $wpdb->prepare(
    
    "SELECT * from wp_tcmsc_cartitems where cart_id = %s AND id = %s",
    
    array(
        $cart_id,
        $item_id
    )
);

$row = $wpdb->get_row($sql, ARRAY_A);

$item_info = date("jS M Y", strtotime($row["start_date"])) . " - <a href=\"" . $row["url"] . "\">" . $row["tour_name_long"] . "</a>";

// Load old cached component file
$component_file = $row["component_key"];

// make sure we aren't looking up a null file
if (!$component_file) {
	wp_redirect(home_url("/cart?status=error&action=add&problem=nofile"));
	exit();
}
$component_file = $cache_location . "availcache/" .  str_replace("/", "-slash-", $component_file . ".xml");
if(!file_exists($component_file)) {
	wp_redirect(home_url("/cart?status=error&action=add&problem=nofile"));
	exit();
}

$old_component = simplexml_load_file($component_file);

if (! is_object($old_component)) {
    error_log("Old component is not an object| " . $log);
}

// Load info required for search from file

// Tour ID
$tour_id = (int)$old_component->tour_id;

// Channel ID
$channel_id = (int)$old_component->channel_id;

// Date
$start_date = $old_component->start_date;

// Build query string
$qs = "date=".$start_date;

// Duration (hotel type pricing only)
$hdur = (int)$old_component->hdur;

// Rates
$rates = $old_component->rates->rate;

$log = date("r") . " recheck availability tour id $tour_id " . $_SERVER["REQUEST_URI"];
$total = count($rates);
$log .= " total components ($total)";

if ($total > 0 ) {
    foreach ($rates as $rate) {

        $rate_info = explode("_", $rate);

        if ((int)$rate_info[1] > 0) {
            $qs .= "&" . $rate_info[0] . "=" . $rate_info[1];
        }

    }
}
else {

    error_log("No rates loaded | " . $log);
}
	
// Query the TourCMS API
$result = $tourcms->check_tour_availability($qs, $tour_id, $channel_id);

// Check if we have a valid component

$found_component = false;
$updated_component = false;
	
if(count($result->available_components->component) > 0) {

	// Load bits required for checking a matching component
	$end_date = (string)$old_component->end_date;
	$date_code = (string)$old_component->date_code;
	$note = (string)$old_component->note;

	foreach($result->available_components->component as $component) {
	
		// Default assume this component is ok
		$found_component = true;
		
		// 
		if((string)$component->end_date != $end_date)
			$found_component = false;
		
		if((string)$component->date_code != $date_code)
			$found_component = false;
		
		if((string)$component->note != $note)
			$found_component = false;
		
		
		if($found_component)
			$new_component = $component;
	}
	
	// If we have found a replacement
	if(isset($new_component)) {
		$new_component_key = (string)$new_component->component_key;
		$new_expires = time() + (int)$result->component_key_valid_for;
		$new_price = (string)$new_component->total_price;
		$new_price_display = (string)$new_component->total_price_display;
		
        $q_ud =  $wpdb->prepare(
            
             "UPDATE wp_tcmsc_cartitems SET component_key = %s, price = %s, price_display = %s, expires = %s WHERE id = %s AND cart_id = %s",
            
            array(
                $new_component_key,
                $new_price,
                $new_price_display,
                $new_expires,
                $item_id,
                $cart_id
            )
        );

        $wpdb->query($q_ud, ARRAY_A);
		
		// If we have successfully updated the component
		if(1) {
//	if($q_ud->rowCount()) {
		
			$updated_component = true;
			
			// Options
	        $sql =  $wpdb->prepare(
	            
	             "SELECT * from wp_tcmsc_cartoptions where cartitem_id = %s",
	            
	            array(
	                $item_id
	            )
	        );
	        
	        $rows = $wpdb->get_results($sql, ARRAY_A);

			// Loop through all Options added for this Item
			foreach($rows as $option) {
				foreach($new_component->options->option as $new_option) {
					if((string)$new_option->option_id == $option["option_id"]) {
					
						// We have found the right option, now find the right quantity
						foreach($new_option->quantities_and_prices->selection as $selection) {
							if((string)$selection->quantity == $option["quantity"]) {
								
								// We have found the right quantity, lets update the key and prices
								$option_dbid = $option["id"];
								
						        $sql_update_option =  $wpdb->prepare(
						            
						             "UPDATE wp_tcmsc_cartoptions SET component_key = %s, price = %s, price_display = %s, total_price = %s, total_price_display = %s WHERE id = %s",
						            
						            array(
						                $option_dbid,
						                (string)$selection->component_key,
						                (string)$selection->price,
						                (string)$selection->price_display,
						                (string)$selection->price,
						                (string)$selection->price_display
						            )
						        );

						        $wpdb->query($sql_update_option, ARRAY_A);		
							}
						}
					}
				}
			}
		}
		// Cache new file
		$output_dir = $cache_location . "availcache";
		
		if (!is_dir($output_dir)) {
		    mkdir($output_dir);
		}
		$filename = str_replace("/", "-slash-", (string)$new_component->component_key) . ".xml";
	
		$new_component->tour_id = $tour_id;
		$new_component->channel_id = $channel_id;
		$new_component->expires = $new_expires;
		
		// Rates
		
		$new_component->rates->rate = array();
		
		foreach ($rates as $rate) {
			
			$new_component->rates->rate[] = $rate;
			
		}
		
		$handle = fopen($output_dir."/".$filename, 'w') or die('Cannot open file:  '.$my_file); //implicitly creates file
		fwrite($handle, $new_component->asXML());
		
		
	}
	
	
}

if(!$updated_component) {
	/* Delete row */
	
    $sql =  $wpdb->prepare(
        
         "DELETE FROM wp_tcmsc_cartitems WHERE id = %s AND cart_id = %s",
        
        array(
            $item_id,
			$cart_id
        )
    );

    $result = $wpdb->query($sql, ARRAY_A);	
}

$return = array(
	"status" => $updated_component,
	"item_info" => $item_info
);

echo json_encode($return);
exit();
?>
