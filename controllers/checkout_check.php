<?php 

namespace GrayLineTourCMSControllers;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

use TourCMS\Utils\TourCMS as TourCMS;

class CheckoutCheckController extends MainController {
	
	protected $api_private_key;
	protected $marketplace_account_id;
	
	public $channel_id;
	public $cart_id;
	
	public $total_items;
	
	public $items = array();
	public $item_ids = array();

	public $messages = array();

    protected $cacheBust;
	
	public function on_start() {

        $cart_id = isset($_GET["cart_id"]) ? (int)$_GET["cart_id"] : null;
        if (! isset($_SESSION['tcmscartid']) && $cart_id) {
            $_SESSION['tcmscartid'] = $cart_id;
        }

		// Load TourCMS API and DB details
		
		include(GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."libraries/3rdparty/tourcms/config.php");

		$this->api_private_key = $api_private_key;
		$this->marketplace_account_id = $marketplace_account_id;	
	}
	
	public function view() {
		global $wpdb;

		// Check we have a Channel ID and Cart ID
		$channel_id = isset($_GET["channel_id"]) ? (int)$_GET["channel_id"] : 0;
		$cart_id = isset($_SESSION["tcmscartid"]) ? (int)$_SESSION["tcmscartid"] : 0;
		
		$this->channel_id = $channel_id;
		
		if($cart_id == 0 || $channel_id == 0) {
			wp_redirect(home_url('/cart?status=error&action=checkout_check'));
			exit();
		}
		
		$check_all = isset($_GET["check"]) ? true : false;
		
		// Check if we have any existing temporary bookings
		
        $q =  $wpdb->prepare(
            
            "SELECT * FROM wp_tcmsc_bookings WHERE cart_id = %s AND channel_id = %s AND status = 'TEMP'",
            
            array(
                $cart_id,
                $channel_id
            )
        );

        $rows = $wpdb->get_results($q, ARRAY_A);

		// If so, cancel them (should be max 1)
		if(count($rows) > 0) {
		
			// First we need to grab the internal API credentials
					
			$tourcms = new TourCMS($this->marketplace_account_id, $this->api_private_key, "simplexml");
			$tourcms_int = $tourcms;
			//$result = $tourcms->show_channel($channel_id);
			
			/* if(empty($result->channel->internal_api_key)) {
				//header("Location: " . DIR_REL . "/cart?status=error&action=checkout&problem=internal");
				exit();
			} */
			
			//$internal_api_key = (string)$this->api_private_key;
	
			//$tourcms_int = new TourCMS(0, $internal_api_key, "simplexml");
		
			foreach ($rows as $booking) {
			//	print $booking_id;
				$booking_id = (int)$booking["booking_id"];
				
				$deleted = $tourcms_int->delete_booking($booking_id, $channel_id);
				
                $q =  $wpdb->prepare(
                    
                    "DELETE FROM wp_tcmsc_bookings WHERE cart_id = %s AND channel_id = %s AND booking_id = %s",
                    
                    array(
                        $cart_id,
                        $channel_id,
                        $booking_id
                    )
                );

                $wpdb->query($q, ARRAY_A);				
			}
		}
			
		// Load products, check any that have expired keys
		
		$cart_id = $_SESSION["tcmscartid"];
		$this->cart_id = $cart_id;
		
        $sql =  $wpdb->prepare(
            
            "SELECT * from wp_tcmsc_cartitems where cart_id = %s AND channel_id = %s",
            
            array(
                $cart_id,
                $channel_id
            )
        );

        $rows = $wpdb->get_results($sql, ARRAY_A);
        
		//\FB::error("payment check, num rows is " . count($rows));
		
		if(count($rows) == 0) {
			wp_redirect(home_url('/cart?status=error&action=checkout_check'));
			exit();
		}
		
		$this->total_items = count($rows);
			
		foreach ($rows as $row) {
	
			if((int)$row["expires"] < (int)strtotime("+10 minutes") || $check_all) {
				$this->item_ids[] = $row["id"];	
				$this->items["" . $row["id"] . ""] = array(
								"i" => (string)$row["id"],
								"n" => (string)$row["tour_name_long"],
								"c" => (string)$row["currency"],
								"p" => (string)$row["price"],
								"pd" => (string)$row["price_display"]
							);
			}
			
		}

		// If there are no expired products
		if(count($this->items) == 0) {
			// Redirect to the proper checkout page

			$finalurl = home_url("/checkout?channel_id=$channel_id");
			
			if(!empty($_GET['status']) && !empty($_GET['action']) && !empty($_GET['message'])) {
			$finalurl .= "&status=" . $_GET['status'] . "&action=" . $_GET['action'] . "&message=" . $_GET['message'];
			} 
			wp_redirect($finalurl);
			exit();
		}

	}
} 
