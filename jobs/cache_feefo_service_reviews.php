<?php
/*
	Cache Service Feefo Reviews
*/
namespace GrayLineFeefoReviewJobs;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

class CacheFeefoServiceReviews
{
    
	public function run() {
		
		try { 

			$feefo_url = get_field('feefo_service_api_endpoint', 'option');
			$merchant_identifier = get_field('merchant_identifier', 'option');
			$service_review_rating = get_field('service_review_rating', 'option');

			if(empty($feefo_url)) {
				 throw new \InternalException("Can't find feefo api url for feefo service reviews", 'DEFAULT');
			}

			if(empty($merchant_identifier)) {
				 throw new \InternalException("Can't find feefo merchant_identifier for feefo service reviews", 'DEFAULT');
			}

			$url = $feefo_url . "?merchant_identifier=".$merchant_identifier."&rating=".$service_review_rating."&page_size=3&page=1";
		
			$reviews = $this->get_service_reviews($url);

			if(!empty($reviews)) {

				$transient_key = "feefo_service_reviews";

				// delete old value from transient
				delete_data_in_transient($transient_key);

				// cache feefo service reviews
				// store reviews in transient for 12 hours
				$cache_time = 12*60*60; 
				set_data_in_transient($transient_key, $reviews, $cache_time);

			} else {
				throw new \InternalException("Can't find feefo service reviews", 'DEFAULT');
			}

		} catch (Exception $e) {
			error_log("Feefo Service Reviews Cron Error: ".$e->getMessage());
        }
	}

	public function get_service_reviews($url)
	{
	    ini_set("log_errors", 1);

	    error_log("Get Service Reviews");

	    $ch = curl_init();
	    curl_setopt($ch, CURLOPT_URL, $url);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	    curl_setopt($ch, CURLOPT_CAINFO, "C:/wamp64/www/ca-bundle.crt");
	    $outcome = curl_exec($ch);
	    $response = json_decode($outcome, true);
		if(empty($response['reviews'])) {
			$outcome = curl_exec($ch);
		}
	
	    error_log("Outcome: ".$outcome);
	    
	    return $outcome;

	}
}
?>
