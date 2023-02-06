<?php

namespace GrayLineTourCMSJobs;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

/*
	Pull in the latest Channel details
	Caches things like Channel names (for the cart screen) and
	terms & conditions (for the checkout screen)
	
	Calls TourCMS API "List Channels"
	http://www.tourcms.com/support/api/mp/channels_list.php
	
*/
class CacheChannels {

	public function run() {
	
		include(GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."libraries/3rdparty/tourcms/config.php");
		
		/*$tourcms = new TourCMS($marketplace_account_id, $api_private_key, "simplexml");*/
		
		$return = "";
		
		$result = $tourcms->show_channel($channel_id);
	
		if(isset($result->error)) {
	
			if($result->error=="OK") {

				if(count($result->channel) > 0) {
					
					$channels = array();

					$ch12130 = array();
				
					foreach($result->channel as $channel) {
						
						$channels[(string)$channel->channel_id] = array(
								"name" => (string)$channel->channel_name,
								"account_id" => (string)$channel->account_id,
								"logo_url" => (string)$channel->logo_url,
								"home_url" => (string)$channel->home_url,
								"lang" => (string)$channel->lang,
								"sale_currency" => (string)$channel->sale_currency
						);
					}
					
					// Write locations to a file
					$json = json_encode($channels);
					$json2 = json_encode($result);
					
					$myFile = $cache_location."channels.php";
					$fh = fopen($myFile, 'w') or die("can't open file");
					fwrite($fh, $json);
					fclose($fh);

					$myFile = $cache_location."channels12130.php";
					$fh = fopen($myFile, 'w') or die("can't open file");
					fwrite($fh, $json2);
					fclose($fh);
					
					$return .= "Found " . count($result->channel)." connected channels";
					
				} else {
					$return .= "No channels";
				}
			} else {
				$return .= $result->error;
			}
		}
		
		return $return;
		
	}
	
}
?>
