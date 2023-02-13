<?php
/*
	Cache Product Feefo Reviews
*/
namespace GrayLineFeefoReviewJobs;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

class CacheFeefoProductReviews
{
    
	public function run() {
		
		try { 

			$feefo_url = get_field('feefo_product_api_endpoint', 'option');
			$merchant_identifier = get_field('feefo_product_merchant_identifier', 'option');
			$product_review_rating = get_field('feefo_product_rating', 'option');
			$product_brands = get_field('feefo_product_brands', 'option');

			if(empty($feefo_url)) {
				 throw new \InternalException("Can't find feefo api url for feefo product reviews", 'DEFAULT');
			}

			if(empty($merchant_identifier)) {
				 throw new \InternalException("Can't find feefo merchant_identifier for feefo product reviews", 'DEFAULT');
			}

			if(empty($product_review_rating)) {
				 throw new \InternalException("Can't find feefo rating for feefo product reviews", 'DEFAULT');
			}

			if(empty($product_brands)) {
				 throw new \InternalException("Can't find feefo product brand for feefo product reviews", 'DEFAULT');
			}

			foreach($product_brands as $product_brand) {

				$productbrand = isset($product_brand['product_brand_name'])? $product_brand['product_brand_name'] : "";

				if(!empty($productbrand)) {

					$url = $feefo_url . "?merchant_identifier=".$merchant_identifier."&rating=".$product_review_rating."&tags=productbrand:".$productbrand."&page_size=3&page=1";
			        
					$reviews = $this->get_product_reviews($url); 
					$reviews_in_arr = (!empty($reviews))? json_decode($reviews, true) : array();
					if(!empty($reviews_in_arr['reviews'])) {
						$transient_key = "feefo_product_reviews_".$productbrand;

						// delete old value from transient
						delete_data_in_transient($transient_key);

						// cache feefo product reviews
						// store reviews in transient for 12 hours
						$cache_time = 12*60*60; 
						set_data_in_transient($transient_key, $reviews, $cache_time);

					}
				}
			}
			

		} catch (Exception $e) {
			error_log("Feefo Product Reviews Cron Error: ".$e->getMessage());
        }
	}

	public function get_product_reviews($url)
	{
	    ini_set("log_errors", 1);

	    error_log("Get Product Reviews");

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
