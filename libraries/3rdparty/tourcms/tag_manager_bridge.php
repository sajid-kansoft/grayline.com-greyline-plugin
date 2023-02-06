<?php 
	

	function generate_tag_manager_impressions($tours, $list = "Search Results", $offset = 0) {
	
		include_once(GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."libraries/3rdparty/tourcms/config.php"); 
		
		$impressions = array();
		$channel_ids = array();
		$position = (int)$offset + 1; 
		
		/*$channels_string = file_get_contents($cache_location."channels.php");
		
		$channels=json_decode($channels_string,true);*/
	
		
		foreach($tours as $tour) { 
			$original_channel_id = get_original_channel_id($tour->tour_id);
			$impressions[] = array(
			   'name'=> (string)$tour->tour_name_long,       
		       'id'=> $original_channel_id . "/" . get_original_tour_id($tour->tour_id),
		       'price'=> "",
		       'brand'=> get_licensee_name($tour->tour_id),
		       'category'=> !empty($tour->analytics_category)?(string)$tour->analytics_category:"",
		       'variant'=> "",
		       'list'=> $list,
		       'position'=> $position
			);
			
			// Channel ID

			if(!in_array($original_channel_id, $channel_ids)) {
				$channel_ids[] = $original_channel_id;
			}

			$position++;
		}
		
		// Write dataLayer
		// E-commerce impressions
		$dl_array = array('ecommerce' => array(
			'impressions'=> $impressions
		));

		// If we only have one channel in the results it's a licensee page
		if(count($channel_ids) == 1) {
			$dl_array["is_licensee_page"] = "true";
			$dl_array["licensee_channel_id"] = $channel_ids[0];
		}
		
		$dl = json_encode($dl_array);
		
		return "<script>dataLayer.push($dl)</script>";
	}
	
?>
