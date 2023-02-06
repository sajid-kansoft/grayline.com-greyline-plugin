<?php

namespace GrayLineTourCMSControllers;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

use TourCMS\Utils\TourCMS as TourCMS;
use \stdClass;
use \DateTime;

class CartComplete extends MainController
{
	private $test_bookingID = 1367144; // NOTE - for testing purposes, will run if site is in DEVELOPMENT mode (config)
	private $channelId;

    public $google_ecom;
	public $tourcms_error;
	public $bookingID;
	public $result;
	public $error;
	public $revenue;
	public $sale_currency;
	public $booking_name;
	public $error_msg;
	public $success;
	public $fb_items;
	public $trans;
	public $items;
	public $feefo_data;

	public function __construct()
	{  
		parent::__construct();
		
		$success_status = 0;

        // removing the post check as it's open to tampering / getting bookings you shouldn't have access to
		$bookingID = !empty($_SESSION["bookingID"])? $_SESSION["bookingID"] : "";
		
		// Need to be remove this code after developement will done
		/*if ( ! is_null( $this->test_bookingID )) {
			$bookingID = $this->test_bookingID;
		}*/
        $this->google_ecom = get_option("grayline_tourcms_wp_google_ecom");

		if ( !empty($bookingID) ) {
			// Output TourCMS booking info based on booking ID and channel ID
			$this->showBooking( $bookingID ); 
			if ($this->tourcms_error !== 'OK') { 
                $success_status = 0;
                $error_msg = "Booking ID $bookingID " . $this->tourcms_error;
                $this->error_msg = $error_msg;
                $this->bookingID = $bookingID;

            }
			else { 
                // Clear the user's cart based on their session ID
                $this->clearCart();
                // Clear the cart cookie
                $this->deleteCartCookie();
                $_SESSION["bookingID"] = null;
                session_regenerate_id();
                $success_status = 1;
            }
		}
		else {
			$success_status = 0;			
			$error_msg = 'Err Code 001 : No booking ID.';
			$this->error_msg = $error_msg;
			$this->bookingID = $bookingID;
			$this->revenue = null;
		}

		$this->success = $success_status;
	}

	

	private function showBooking( $booking_id ) {
		// Call TourCMS API
		
        $channel_id = get_option('grayline_tourcms_wp_channel');
		$this->channelId = $channel_id;

		$result =  $this->call_tourcms_api("show_booking", null, null, $booking_id);
		
		$revenue = $result->booking->sales_revenue;
		$error = (string)$result->error;
		$this->tourcms_error = $error;
		$booking_name = addslashes(htmlentities("ID " . (string)$result->booking->booking_id . ' : ' . (string)$result->booking->booking_name));

		$this->bookingID  = $booking_id;
		$this->result =  $result;
		$this->error  = $error;
		$this->revenue = $revenue;
		$this->sale_currency = (string)$result->booking->sale_currency;
		$this->booking_name = $booking_name; 
		if((string)$result->error === "OK") {
			$this->gaEcomTracking( $result->booking );
			$this->feefoComplete($result, $channel_id);
		}
	}

	private function feefoComplete($booking_info, $channel_id)
	{
		$feefo_data = new stdClass();
		$feefo_data->customer_name  = htmlentities((string)$booking_info->booking->lead_customer_name);
		$feefo_data->customer_email = (string)$booking_info->booking->lead_customer_email;
		$feefo_data->customerref    = (string)$booking_info->booking->lead_customer_id;
		$feefo_data->date           = DateTime::createFromFormat('!Y-m-d', (string)$booking_info->booking->start_date);
		$feefo_data->orderref       = (string)$booking_info->booking->booking_id;
		$feefo_data->date           = $feefo_data->date->format('c');
		$feefo_data->products       = [];

		foreach ($booking_info->booking->components as $component) {
			$c = new stdClass();

			$tour_id = (integer)$component->component->product_id;
			$tour_data = $this->call_tourcms_api("tour", null, $tour_id); 

			// The channel ID we pass to Feefo needs to be GLWW channel, however

			$c->description       = htmlspecialchars((string)$tour_data->tour->tour_name, ENT_QUOTES|ENT_HTML5);
			$c->productlink        = (string)$tour_data->tour->tour_url;
			$c->productsearchcode  = GLWW_CHANNEL . '_' . $component->component->product_id;
			$c->amount             = (string)$component->component->sale_price_inc_tax_total;
			$c->currency           = (string)$component->component->sale_currency;
			$c->tags               = new stdClass();
			$c->tags->productbrand = FEEFO_PRODUCT_BRAND;
			
			array_push($feefo_data->products, $c);
		}

		$feefo_data->products = json_encode($feefo_data->products, 64);

		$this->feefo_data = $feefo_data;
	}

	private function clearCart() {
		// Get the session id, then delete all items from DB which match it
		global $wpdb;
		$sesh_id = session_id();

        $query = $wpdb->prepare("DELETE FROM wp_tourcms_cart WHERE sesh_id=%s",
            array($sesh_id)
        );

        $rows = $wpdb->query($query);
		return $rows;
	}

	private static function deleteCartCookie() {
		$expire = time() - 3600;
		setcookie( "cart_cookie", "", $expire, "/" );
	}

	// Used for Google Analytics ecom tracking
	private function gaEcomTracking( $booking ) {
		
		$transId = $this->channelId . '_' . $booking->booking_id;
		$trans = array(
				'id'=> $transId,
				'affiliation'=> get_option('grayline_tourcms_wp_licensee'),//LICENSEE_NAME,
				'revenue'=> $booking->sales_revenue,
				'shipping'=> 0,
				'tax'=> 0
		);

		// List of Items Purchased.
		$items = [];
		//FB::info( "Num components: " . count( $booking->components->component )  );
		
		$cps = count ( $booking->components->component ) > 1 ? $booking->components->component : array($booking->components->component);
		$fb_items = [];
		foreach ( $cps as $cp ) {
            // Use sale quantity rule to find out individual price per component
            $rule = (string)$cp->sale_quantity_rule;
            $quantity = (float)$cp->sale_quantity;
            $priceTotal = (float)$cp->sale_price_inc_tax_total;
            $variant = (string)$cp->rate_description;
            if ($rule == "GROUP")
            {
                $variant .= " GROUP ($quantity)";
                $quantity = (float)1;
            }
            // don't want to divide by zero ever
            $quantity = $quantity == 0 ? (float)1 : $quantity;
            $price = sprintf('%0.2F', ($priceTotal / $quantity));
			$items[] = array(
					'sku' => (string)$cp->product_id,
					'name' => addslashes(htmlentities((string)$cp->component_name)),
					'category' => 'Tours',
					'price' => $price,
                    'quantity' => $quantity,
                    'variant' => $variant
			);
			$fb_items[] = ['id' => (string)$cp->product_id, 'quantity' => $quantity ];
		}
		/*FB::info( $items );
		FB::info( $trans );*/
		$this->trans = $trans;
		$this->items = $items;
		$this->fb_items = $fb_items;
	}

	//	private function processTMT( $tmt_user, $tmt_pass ) {
	//		// FB::trace( "processTMT()" );
	//		$tmt_bookingID = $this->get( "booking_id" );
	//		$success = -1;
	//		$msg = "";
	//		FB::log( "TMT Booking ID is $tmt_bookingID, via GET URL param" );
	//		if ( $tmt_bookingID ) {
	//			// Let's see if the payment has already been successfully made, e.g. the user has just refreshed browser
	//			$rows = self::loadPaidEntry( $tmt_bookingID );
	//			$bookingID = $rows[0]["bookingID"];
	//			$success = (int)$rows[0]["success"];
	//			if ( $bookingID ) {
	//				if ( $success === 1 ) {
	//					FB::log( "Successfully paid & logged in DB" );
	//					// Let's call show booking and return voucher and reference number
	//					$this->showBooking( $bookingID );
	//				}
	//				else if ( $success === 0 ) {
	//					$error_msg = $rows[0]["response"];
	//					$this->set( "error_msg", $error_msg );
	//					FB::error( "Payment error logged in DB: $error_msg" );
	//				}
	//				FB::log( "Booking already correlated with TMT ref ID in DB: $bookingID" );
	//			}
	//			else {
	//				FB::log( "Payment must be verified by TMT libs" );
	//				// Load the necessary library files
	//				Loader::library("tmt/class-IXR", dojoTourcmsPackage::PKG_HANDLE);
	//				Loader::library("tmt/config", dojoTourcmsPackage::PKG_HANDLE);
	//				Loader::library("tmt/functions", dojoTourcmsPackage::PKG_HANDLE);
	//
	//				$url = TmtConfig::$url;
	//				$user = $tmt_user;
	//				$pass = $tmt_pass;
	//
	//				$client = new IXR_Client( $url );
	//
	//				if ( !$client->query( 'my.bookingStatus','', $user, $pass, $tmt_bookingID ) ) {
	//					//die( 'Error while getting Booking status: ' . $client->getErrorCode() ." : ". $client->getErrorMessage() );
	//					$msg = 'Error while getting Booking status: ' . $client->getErrorCode() ." : ". $client->getErrorMessage();
	//					$success = 0;
	//					FB:error( "TMT Error, $msg");
	//					$this->recordPayment( $success, $tmt_bookingID, $msg );
	//				}
	//				else {
	//					//these are the only responses that we will return as indicated
	//					switch( $client->getResponse() ) {
	//						case 1 :
	//							$msg = "Booking Paid (TMT)";
	//							$success = 1;
	//							break;
	//						case 2:
	//							$msg = "Booking Payment Pending (TMT)";
	//							$success = 1;
	//							break;
	//						case 3:
	//							$msg = "Booking Payment Failed (TMT)";
	//							$success = 0;
	//							FB::error( "Failed TMT verification of booking" );
	//							break;
	//					}
	//					FB::log( "Message is $msg, success is $success" );
	//					$success = $this->recordPayment( $success, $tmt_bookingID, $msg );
	//				}
	//			}
	//		}
	//		else {
	//			$success = 0;
	//			$error_msg = t("No Trust My Travel Booking ID supplied. Something went wrong.");
	//			FB::error( $error_msg );
	//			$this->set( "error_msg", $error_msg );
	//		}
	//		return $success;
	//	}

	//	private function recordPayment( $success, $refID, $msg=false ) {
	//		// FB::trace( "recordPayment()" );
	//		$pkg = Package::getByHandle(dojoTourcmsPackage::PKG_HANDLE);
	//		$chID = $pkg->config('CHANNEL_ID');
	//		$tc = dojoTourcmsPackage::getTc();
	//		// We need to load the booking ID from the DB
	//		/** LOAD BOOKING ID FROM DB **/
	//		$rows = self::loadPaymentEntry();
	//		$bookingID = $rows[0]["bookingID"];
	//		FB::log( "From the DB, bookingID: $bookingID" );
	//		// If there is success, and we have been returned a booking, unpaid, then we need to record a payment
	//		if ( $success === 1 && $bookingID ) {
	//			// Clear the user's cart based on their session ID
	//			$this->clearCart();
	//			$this->set( "bookingID", $bookingID );
	//			// First we have to load the booking to extract the info
	//			$booking = $tc->show_booking( $bookingID, $chID );
	//			// If there is an error from the Tourcms api, then we must deal with it
	//			if ( $booking->error != "OK" ) {
	//				$error_msg = "" . $result->error;
	//				$this->set( "error_msg", $error_msg );
	//				FB::error( "TourCMS error when calling show_booking on $bookingID, msg is $error_msg" );
	//			}
	//			else {
	//				$cart_total = (string)$booking->booking->sales_revenue;
	//				$sale_currency = $booking->booking->sale_currency;
	//				$bookingID = $booking->booking->booking_id;
	//				FB::log( "We must create a payment xml obj and send to TourCMS to record booking payment" );
	//				FB::log( "cart total ($cart_total) sale_currency ($sale_currency) bookingID ($bookingID) payment_type ($payment_type)" );
	//				$xml = new SimpleXMLElement( "<payment />" );
	//				$xml->addChild( "booking_id", $bookingID );
	//				$xml->addChild( "payment_value", $cart_total );
	//				$xml->addChild( "payment_currency", $sale_currency );
	//				$xml->addChild( "payment_type", "Trust My Travel" );
	//				$xml->addChild( "payment_reference", $refID );
	//				$result = $tc->create_payment( $xml, $chID );
	//				FB::log( "Tourcms create_payment result " . $result );
	//				if ( $result->error != "OK" ) {
	//					$success = 0;
	//					$error_msg = "" . $result->error;
	//					$this->set( "error_msg", $error_msg );
	//					FB::error( "Tourcms create_payment error, $error_msg" );
	//				}
	//				else {
	//					$success = 1;
	//					FB::log( "Tourcms create_payment success" );
	//					// Let's call show booking and return voucher and reference number
	//					$this->showBooking( $result->booking->booking_id );
	//				}
	//			}
	//		}
	//		// If there has been a failure, then we need to record a failed payment
	//		else if ( $success === 0 ) {
	//			$xml = new SimpleXMLElement( "<payment />" );
	//			$xml->addChild( "booking_id", $bookingID );
	//			$xml->addChild( "audit_trail_note", $msg );
	//			$result = $tc->log_failed_payment( $xml, $chID );
	//			$error_msg = t("The payment method failed. ($msg). Please try again.");
	//			$this->set( "error_msg", $error_msg );
	//			FB::error( "Logging the TMT payment correlation failure in TourCMS" );
	//			FB::log( "TourCMS log_failed_payment method result $result" );
	//			FB::error( $error_msg );
	//		}
	//		else if ( $bookingID == "" ) {
	//			FB::error( "No booking ID for $refID in DB" );
	//			$success = 0;
	//			$error_msg = t(/*i18n %1$s is a reference number, the booking ID value */'Something went wrong, we cannot find your booking. Your Trust My Travel booking reference is: %1$s.', $refID );
	//			$error_msg .= '<br>' . t('Please check your email account for a booking confirmation.');
	//			$this->set( "error_msg", $error_msg );
	//			FB::error( $error_msg );
	//		}
	//		// Let's update the DB with the response
	//		self::updatePayment( $bookingID, $success, $refID, $msg );
	//		FB::warn("success is $success " );
	//		return $success;
	//	}

	// Function to generate the DataLayer used with Tag Manager Transaction Part
	public function dataLayerTransactionJs(&$trans)
	{
		return <<<HTML
		'transactionId': '{$trans['id']}',
		   'transactionAffiliation': '{$trans['affiliation']}',
		   'transactionTotal': {$trans['revenue']},
		   'transactionTax': {$trans['shipping']},
		   'transactionShipping': {$trans['tax']},
		   'transactionProducts': [
		HTML;
	}

	// Function to generate the DataLayer used with Tag Manager Item Part
	public function dataLayerItemJs(&$transId, &$item) {
		return <<<HTML
		{
			'sku': '{$item['sku']}',
			'name': '{$item['name']}',
			'category': '{$item['category']}',
			'price': {$item['price']},
			'variant': '{$item['variant']}',
			'quantity': {$item['quantity']}
		},
		HTML;
	}

	public function dataLayerJS()
	{
		$js = "";
		$trans = $this->trans;
		$items = $this->items;
		if ( is_array($items))
		{
			$js = $this->dataLayerTopJs();
			$js .= $this->dataLayerTransactionJs($trans);
			foreach ($items as &$item) {
				$js .= $this->dataLayerItemJs($trans['id'], $item);
			}
			$js = rtrim($js, ",");
			$js .= "]";
			$js .= $this->dataLayerBottomJs();
		};
		return $js;
	}

	public function dataLayerTopJs()
	{
		return <<<HTML
		<script>
		window.dataLayer = window.dataLayer || []
		dataLayer.push({
		HTML;

	}

	public function dataLayerBottomJs()
	{
		return <<<HTML
		});
		</script>
		HTML;

	}

	/********** End Private Functions ****************************/


	/********** Public Functions ****************************/
	// Function to return the JavaScript representation of a TransactionData object.
	public function getTransactionJs(&$trans) {

		if(!empty($trans)) {
			return <<<HTML
					ga('ecommerce:addTransaction', {
					'id': '{$trans['id']}',
					'affiliation': '{$trans['affiliation']}',
					'revenue': '{$trans['revenue']}',
					'shipping': '{$trans['shipping']}',
					'tax': '{$trans['tax']}'
					});
					HTML;
		}
		
	}

	// Function to return the JavaScript representation of an ItemData object.
	public function getItemJs(&$transId, &$item) {
		return <<<HTML
		ga('ecommerce:addItem', {
		'id': '$transId',
		'name': '{$item['name']}',
		'sku': '{$item['sku']}',
		'category': '{$item['category']}',
		'price': '{$item['price']}',
		'quantity': '{$item['quantity']}',
		'variant': '{$item['variant']}'
		});
		HTML;
	}
	/********** End Public Functions ****************************/

	private static function loadPaymentEntry() {
		// FB::trace( "loadPaymentEntry()" );
		global $wpdb;
		$sesh_id = session_id();
		//$db = Loader::db();
		$success_def = -1;
		$yesterday = date( "Y-m-d H:i:s", strtotime( "-1 days") );
		// We also need to order it by most recent, because user could have gone onto TMT page, but hit back button, causing a new entry in wp_tourcms_payment to be made
		$query = $wpdb->prepare("SELECT * FROM wp_tourcms_payment WHERE success = %s AND sesh_id = %s AND timestamp >= %s ORDER BY timestamp DESC",
            
            array(
                $success_def,
                $sesh_id,
                $yesterday
            )
        );

        $rows = $wpdb->get_results($query, ARRAY_A);
		
		/*FB::log( "Query: $query" );
		FB::log( "Rows returned: " . count($rows) );*/
		return $rows;
	}

	private static function loadPaidEntry( $refID ) {
		// FB::trace( "loadPaidEntry()" );
		global $wpdb;

		$sesh_id = session_id();
		//$db = Loader::db();
		$query = $wpdb->prepare("SELECT * FROM wp_tourcms_payment WHERE sesh_id = %s AND ref_id = %s",
            
            array(
                $sesh_id,
                $refID
            )
        );
		$rows = $wpdb->get_results($query, ARRAY_A);
		
		/*FB::log( "Query: $query" );
		FB::log( "Rows returned: " . count($rows) );*/
		return $rows;
	}

	private static function updatePayment( $bookingID, $success, $refID, $response ) {
		// FB::trace( "updatePayment()" );
		global $wpdb;
		$sesh_id = session_id();
		//$db = Loader::db();
		$query = $wpdb->prepare("UPDATE wp_tourcms_payment SET success=%s, refID = %s, response = %s WHERE seshID=%s AND bookingID=%s",
            
            array(
                $success, $refID, $response, $sesh_id, $bookingID
            )
        );
		$rows = $wpdb->query($query); 
		/*FB::log( "Query: $query" );
		FB::log( "Rows returned: " . count($rows) );*/
		return $rows;
	}

}
