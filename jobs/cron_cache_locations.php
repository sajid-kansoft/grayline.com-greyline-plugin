<?php
/*
	Cache locations
	
	Updates many information in wp_options table:
	- locations_js - Used for the autocomplete on the destination search
	- locations - Not used?
	- countries - Used to validate search input and convert Country names into Codes for the API
	- dests_count2loc - Used on the destinations page, to list the locations for a given Country
	
	Calls the TourCMS "List Tour Locations" API:
	http://www.tourcms.com/support/api/mp/locations_list.php
*/
defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

use TourCMS\Utils\TourCMS as TourCMS;

class CronCacheLocations
{
    
	public function run() {
		
		$return = "";

		$previous = array();

		$locs = get_option('gl_locations');
        
		if(!empty($locs)) {

			$locs = json_decode($locs);

			$previous = array();
			
			foreach($locs as $country) {
				
				$country_name = $country['name'];
				
				if(!empty($country['lat']) && !empty($country['lng'])) {
					$previous[$country['name']] = array(
						'lat' => $country['lat'],
						'lng' => $country['lng']
					);
				}
					
				foreach($country["locations"] as $location) {
					if(!empty($location['lat']) && !empty($country['lng'])) {
						$previous[$location['name'] . ', ' . $country['name']] = array(
							'lat' => $location['lat'],
							'lng' => $location['lng']
						);
					}
				}
			}
		}

		$marketplace_account_id  = get_option('grayline_tourcms_wp_marketplace');;
        $api_private_key = get_option('grayline_tourcms_wp_apikey');
		$channel_id = get_option('grayline_tourcms_wp_channel');
		
		$tourcms = new TourCMS($marketplace_account_id, $api_private_key, 'simplexml'); 
            
        $result = $tourcms->list_tour_locations($channel_id);
		
		if(isset($result->error)) {
	
			if($result->error=="OK") { 
				if($result->total_location_count > 0) {
					$locations = array();
					$js_locations = array();
					$country_locations = array();
					$countries = array();
						
					foreach($result->unique_location as $location) {
					
						$location_name = (string)$location->location;
						$country_code = (string)$location->country_code;
						$country_name = (string)$location->country_name;
					
						// Main location files
						$location_text = $location_name;
						
						if(isset($location->country_name)) {
						
							// Store Country in an array for later lookup
							if(!in_array($country_name, $countries)) {
								$countries[$country_code] = strtoupper($country_name);
							}
							
							// Check country is not the same as location
							// E.g. "Macau, Macau"
							// Append country to location
							if($location_text != $country_name) {
								$location_text .= ", " . $country_name;
								if(!in_array($country_name, $js_locations)) {
									$js_locations[] = $country_name;
								}
							}
								
						}
						$locations[] = $location;
						$js_locations[] = $location_text;
					
						
						// Destinations page
					
						if(!array_key_exists($country_code, $country_locations)) {
							$country_locations[$country_code] = array();
						
							$country_locations[$country_code]["name"] = $country_name;
							
							$country_locations[$country_code]["url"] = $this->get_location_url($country_name);
							
							// Try and geocode country
							$ar_geocode = $this->google_geocode($country_name, $previous);
							
							//error_log(print_r($ar_geocode, true));
							
							if(!empty($ar_geocode['lat'])) {
								$country_locations[$country_code]["lat"] = $ar_geocode['lat'];
								$country_locations[$country_code]["lng"] = $ar_geocode['lng'];
							}
						
						}
						
						if(!array_key_exists("locations", $country_locations[$country_code]))
							$country_locations[$country_code]["locations"] = array();
						
						// Try and geocode location
						$ar_geocode = $this->google_geocode($location_name . ", " . $country_name, $previous);
						
						$country_locations[$country_code]["locations"][] = array(
														"name" => $location_name,
														"url" => $this->get_location_url($country_name, $location_name),
														"lat" => !empty($ar_geocode['lat']) ? $ar_geocode['lat'] : '',
														"lng" => !empty($ar_geocode['lng']) ? $ar_geocode['lng'] : ''
													);
						
					}
					
					array_multisort($country_locations);
					
					// Add hard-coded suggestions
					$location_dir = GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH.'/toplevel_data';

					if (($handle = fopen("$location_dir/hard_coded_suggestions.php", "r")) !== FALSE) {
					
					    while (($data = fgetcsv($handle, 1000, "\t")) !== FALSE) {
					    
					    	if($data[0] != "" && $data[1] != "") {
					    	
					    		if(!in_array($data[0], $js_locations)) {
					    			$js_locations[] = $data[0];
					    		}
					    		
					    	}
	
					    }
					    
					    fclose($handle);
					}
					
					array_multisort($js_locations);
		
					// Save locations to a transient
					update_option('location-list', json_encode($locations));
					
					// Save JavaScript location array to a transient (for autocomplete)
					update_option('js-location', json_encode($js_locations));
					
					// Save countries to a transient
					update_option('countries', json_encode($countries));
					
					// Save destination page locations to a transient
					update_option('dests_count2loc', json_encode($country_locations));
					
					// Save destination to a transient
					update_option('gl_locations', json_encode($country_locations));
					
					$return .= "Found " . count($locations) . " locations in " . count($countries) . " countries";
					
				} else {
					$return .= "No locations";
				}
			} else {
				$return .= $result->error;
			}
		}
		
		return $return;
	}

	public function get_location_url($country, $location = "")
	{
		$country_tidy_url = simple_tidy_url($country);
		//apply_filters('grayline_tourcms_wp_simple_tidy_url', $country);
	    $return = "/things-to-do/".$country_tidy_url."/";

	    if ($location != "") {
	    	$location_tidy_url = simple_tidy_url($location);
	    	//apply_filters('grayline_tourcms_wp_simple_tidy_url', $location);
	        $return .= $location_tidy_url."/";
	    }

	    return $return;
	}

	public function google_geocode($string, $existing_geocodes = array())
	{
	    ini_set("log_errors", 1);

		// If we have a geocode already, use that
	    if (array_key_exists($string, $existing_geocodes)) {

	        error_log($string.' using existing');

	        $array = array(
	            "lat" => $existing_geocodes[$string]["lat"],
	            "lng" => $existing_geocodes[$string]["lng"],
	            "location_type" => "",
	        );

	        return $array;
	    }

	    // Otherwise, let's geocode!
	    error_log($string.' not using existing');

	    $string = str_replace(" ", "+", urlencode($string));
	    $details_url = "https://maps.googleapis.com/maps/api/geocode/json?address=".$string."&sensor=false&key=".urlencode(
	            "AIzaSyAAa6Zlq_nYsVEKbE5-P48ekoBPmIqFcSU"
	        );

	    $ch = curl_init();
	    curl_setopt($ch, CURLOPT_URL, $details_url);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	    curl_setopt($ch, CURLOPT_CAINFO, "C:/wamp64/www/ca-bundle.crt");
	    $outcome = curl_exec($ch);
	    $response = json_decode($outcome, true);

	    error_log("String: ".$string);
	    error_log("Outcome: ".$outcome);
	    error_log("Response: ".print_r($response, true));

	    // If Status Code is ZERO_RESULTS, OVER_QUERY_LIMIT, REQUEST_DENIED or INVALID_REQUEST
	    if ($response['status'] != 'OK') {

	        $response = json_decode(curl_exec($ch), true);

	        if ($response['status'] != 'OK') {

	            $response = json_decode(curl_exec($ch), true);

	            if ($response['status'] != 'OK') {
	            	return null;
	            }
	        }
	    }

	    $geometry = $response['results'][0]['geometry'];

	    $longitude = $geometry['location']['lng'];
	    $latitude = $geometry['location']['lat'];

	    $array = array(
	        'lng' => $geometry['location']['lng'],
	        'lat' => $geometry['location']['lat'],
	        'location_type' => $geometry['location_type'],
	    );
	    
	    return $array;

	}
}
?>
