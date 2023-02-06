<?php

namespace GrayLineTourCMSControllers;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

ini_set('session.cookie_secure', 1);

use TourCMS\Utils\TourCMS as TourCMS;

class CheckoutKeyController extends MainController
{
	protected $api_private_key;
	protected $marketplace_account_id;

	protected $channel_id;

	public function on_start() {
		// Load our config
		
		include(GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."libraries/3rdparty/tourcms/config.php");
		
		$this->api_private_key = $api_private_key;
		$this->marketplace_account_id = $marketplace_account_id;
		$this->channel_id = $channel_id;

		
	}
	
	public function view() {
		
		// Check we have a Channel ID and Cart ID
		$channel_id = isset($_REQUEST["channel_id"]) ? (int)$_REQUEST["channel_id"] : $this->channel_id;
		$cart_id = isset($_SESSION["tcmscartid"]) ? (int)$_SESSION["tcmscartid"] : 0;


		if($cart_id == 0 || $channel_id == 0) {
            error_log("Checkout view error, no cart id ($cart_id) or channel id ($channel_id)");
            wp_redirect(home_url('?status=error&action=checkout'));
			exit();
		}
		
		// As we're booking as the Tour Op rather than as an Agent we need a booking key
		// Check if we already have one
		
		$get_key = true;
		
		if(isset($_COOKIE["tcms_bk_" . $channel_id]) && isset($_COOKIE["tcms_bk_" . $channel_id . "_expires"])) {
		
				$booking_key_expires = $_COOKIE["tcms_bk_" . $channel_id . "_expires"];

				$foo = strtotime("- 1 hour");
				
				if($booking_key_expires > $foo) 
					$get_key = false;
					
		}
		
		// Actually we may be logged in as an agent
		// Check for the agent key
		
		if(!empty($_COOKIE["tcms_ag_bk"]) && !empty($_SESSION["agent_name"])) {
			$get_key = false;
		}
		
		// Redirect to the Checkout check page, via get key if we don't have one
		
		if($get_key) {

			// First we need to grab the internal API credentials
			
			$tourcms = new TourCMS($this->marketplace_account_id, $this->api_private_key, "simplexml");


//			$result = $tourcms->show_channel($channel_id);

			/*
			if(empty($result->channel->internal_api_key)) {
				header("Location: " . DIR_REL . "/cart?status=error&action=checkout&problem=internal");
				exit();
			}
			*/
			
//			$internal_api_key = (string)$result->channel->internal_api_key;
//			$internal_api_key = (string)$this->api_private_key;
			
			// Then we can get the key!

			// Create a new SimpleXMLElement to hold the url 
			$url_data = new \SimpleXMLElement('<url />'); 
			
			// Response URL is the page we use to receive the customer back
			
			// if(!empty($_SERVER['HTTPS'])) {
			// 	$response_url = "https://";
			// } else {
			// 	$response_url = "http://";
			// }
			
			// $response_url .= $_SERVER["HTTP_HOST"] . DIR_REL . "/checkout_key?process_checkout_key=1&channel_id=$channel_id&amp;cart_id=$cart_id";
			$response_url = home_url("/checkout_key/process?channel_id=$channel_id&amp;cart_id=$cart_id");
			// print_r($response_url);die;
			$url_data->addChild('response_url', $response_url); 
			
			// Call TourCMS API
//			$tourcms_int = new TourCMS(0, $internal_api_key, "simplexml");
			
			$redirect_info = $tourcms->get_booking_redirect_url($url_data, $channel_id);
					
			if($redirect_info->error!="OK") {
				wp_redirect(home_url("/cart?status=error&action=checkout&problem=redirect"));
				// header("Location: " . DIR_REL . "/cart?status=error&action=checkout&problem=redirect");
				exit();
			}
			
			// Process the redirect URL
			$redirect_url = (string)$redirect_info->url->redirect_url;


			//$redirect_url = 'http://localhost/Grayline/trunk/checkout_key?process_checkout_key=1&channel_id=12130&cart_id=502188?agent=0&tracking_miscid=0&booking_key=0_0_1672646797_11676_2be5411272dcce3160aecc36ce763afa';
			// Redirect the customer to TourCMS
			wp_redirect($redirect_url);
			exit();
			
		} else { 
			wp_redirect(home_url("/checkout_check?channel_id=$channel_id"));
			exit();
			
		}
	}
	
	
	public function process() {

	    // we're losing the cart id session on redirect to TourCMS, so reset it if missing
        $cart_id = isset($_GET["cart_id"]) ? (int)$_GET["cart_id"] : null;
		if (isset($_GET["booking_key"]) && isset($_GET["channel_id"])) {

			$channel_id = (int)$_GET["channel_id"];
			
			// We have a booking key, store it in a cookie
//			setcookie("tcms_bk_" . $channel_id, htmlspecialchars($_GET["booking_key"]), strtotime("+1 day"), "/");

            palisis_set_cookie("tcms_bk_" . $channel_id, htmlspecialchars($_GET["booking_key"]), strtotime("+1 day"));
			
			// Keys are valid for 24 hours, but we store for 23
			// That way if the customer takes an hour to check out the key still works
//			setcookie("tcms_bk_" . $channel_id . "_expires", strtotime("+1 day"), strtotime("+1 day"), "/");
			palisis_set_cookie("tcms_bk_" . $channel_id . "_expires", strtotime("+1 day"), strtotime("+1 day"));
			
			wp_redirect(home_url("/checkout_check?channel_id=$channel_id&cart_id=$cart_id"));
			exit();
			
		} else {
			wp_redirect(home_url("/?status=error&action=checkout&problem=processkey"));
		}
	}
	
} 
