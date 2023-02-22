<?php

namespace GrayLineTourCMSControllers;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

use TourCMS\Utils\TourCMS as TourCMS;

class CheckoutSubmitController extends MainController {

	protected $api_private_key;
	protected $marketplace_account_id;

	protected $feefo;

	protected $dbtype;
	protected $dbhost;
	protected $dbport;
	protected $dbname;
	protected $dbuser;
	protected $dbpass;

	protected $cache_location;

	public $cart_id;

	public $tracking_items = array();

	public $channel_id;
	public $confirmed_booking_id;
	public $confirmed_booking = false;
	public $channel_name;

	private $bookingId;
	private $showBookingXml;
	private $apiObject;

	public $voucher_url;

	public $sales_revenue;
	public $sale_currency;
	public $alt_sales_revenue;
	public $alt_sale_currency;

	public $payment_required = false;
	public $payment_can_tell_status = false;
	public $payment_success = false;

	public $test_gateway = false;

	public $payment_error = "";

	// Debug info
	public $payment_store_status = "Unknown";

	public $city;
	public $country;

	//public $new_booking;
	public $promo = "";

	public $items;
	public $options = array();

	public $num_items_remaining = 0;

	public $messages = array();

	public $data_layer;

	private $impressions = array();

	public function on_start() {

		include(GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."libraries/3rdparty/tourcms/config.php");

		// $this->feefo = $feefo;

		$this->api_private_key = $api_private_key;
		$this->marketplace_account_id = $marketplace_account_id;

		$this->dbtype = $dbtype;
		$this->dbhost = $dbhost;
		$this->dbport = $dbport;
		$this->dbname = $dbname;
		$this->dbuser = $dbuser;
		$this->dbpass = $dbpass;

		$this->cache_location = $cache_location;

		if(!empty($_GET["pro"])) $this->promo = $_GET["pro"];

	}

	private function log_action($string, $channel_id, $booking_id = 0 , $type = "payment", $gateway = "unknown") {

		include_once(GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."libraries/3rdparty/tourcms/libraries/log_lib.php");

		tourcms_log($string, $channel_id, $booking_id, $type, $gateway, $this->cache_location);

	}


	private function licenseeChannelId($component)
	{
		$supplier_tour_code = (string)$component->supplier_tour_code;
		preg_match('/\|([0-9]+)/', $supplier_tour_code, $return);
		$id = (int)$return[1];
		return $id;
	}

	private function licenseeTourId($component)
	{
		$supplier_tour_code = (string)$component->supplier_tour_code;
		preg_match('/([0-9]+)\|/', $supplier_tour_code, $return);
		$id = (int)$return[1];
		return $id;
	}

	// Data Layer
	private function genDataLayer()
	{    
 		if(!empty($this->confirmed_booking_id)):

 			$impressions = $this->impressions;

// 			////\FB::info("ECOM DATA");
// 			////\FB::log($impressions);

// 			// Write dataLayer
// 			// E-commerce impressions
			$dl_array = array(
					'ecommerce' => array(
							'currencyCode' => $this->sale_currency,
							'purchase'=> array(
									'actionField'=> array(
											'revenue'=> $this->sales_revenue,
											'id'=> $this->channel_id . "/" . $this->confirmed_booking_id,
									),
									'products'=> $impressions
							)
					));

// 			// If user paid in a different currency, log that here
//             // Disabling different currency logging, as it messes up E com analytics
//             // change to always be USD
//			$dl_array["sale_currency"] = $this->alt_sale_currency;
//			$dl_array["sales_revenue"] = $this->alt_sales_revenue;
            $dl_array["sale_currency"] = $this->sale_currency;
            $dl_array["sales_revenue"] = $this->sales_revenue;
			$dl_array["confirmed_booking_id"] = (string)$this->confirmed_booking_id;
			$dl_array["is_licensee_page"] = "false";
// 			// removing this line as it is redundant on the new single channel model
// 			//$dl_array["licensee_channel_id"] = (string)$this->channel_id;

			$dl = json_encode($dl_array, JSON_UNESCAPED_SLASHES);

			$this->data_layer = $dl;
			// $this->data_layer = "dataLayer.push($dl)";

		 endif;
	}

    private function feefoPrePostCampagin($booking_info, $channel_id)
    {    
         $tourcms = new TourCMS($this->marketplace_account_id, $this->api_private_key, "simplexml");

         $customer_name  = (string)$booking_info->booking->lead_customer_name;
         $customer_email = (string)$booking_info->booking->lead_customer_email;
         $customerref    = (string)$booking_info->booking->lead_customer_id;
         $orderref       = $channel_id . '_' . (string)$booking_info->booking->booking_id;
         $todays_date    = date('Y-m-d');

        loadSenshiModal('feefo_api');
        $feefoApi = new \GrayLineTourCMSSenshiModals\FeefoApi();
        $tours = array();

        if ($booking_info->booking->components->component) {
            foreach ($booking_info->booking->components->component as $component) {

                // dealing with the fact we could have multiple rates per tour
                $product_id = (int)$component->product_id;

                if (!array_key_exists($product_id, $tours)) {
                    // we need this to get the tour URL
                    $tour_data = $tourcms->show_tour((integer)$component->product_id, $channel_id);

                    $tour_description = (string)$component->component_name;
                    $tour_productsearchcode = get_original_channel_id((integer)$component->product_id) . '_' . get_original_tour_id((integer)$component->product_id);
                    $tour_productlink = (string)$tour_data->tour->tour_url;
                    $tour_price = (string)$component->sale_price_inc_tax_total;
                    $tour_start_date = (string)$component->start_date;
                    $tour_end_date = (string)$component->end_date;
                    $tour_brand = get_licensee_name((integer)$component->product_id);
                    $tour_brand = str_replace("Gray Line", '', $tour_brand);
                    $tour_brand = preg_replace('/\s+/', '',$tour_brand);


                    $tour_info = [
                        'id' => $product_id,
                        'desc' => $tour_description,
                        'sku' => $tour_productsearchcode,
                        'link' => $tour_productlink,
                        'price' => $tour_price,
                        'start_date' => $tour_start_date,
                        'end_date' => $tour_end_date,
                        'brand' => $tour_brand,
                        'order_ref' => $orderref . '_' . $product_id
                    ];
                    $tours[$product_id] = $tour_info;
                }
                else {
                    // we now need to increase the price with the extra component cost
                    $orig_price = $tours[$product_id]['price'];
                    $new_price = (float)$orig_price + (float)$component->sale_price_inc_tax_total;
                    $price = sprintf('%.2F', $new_price);
                    $tours[$product_id]['price'] = $price;

                }
            }
        }


        if (is_array($tours) && count($tours) > 0) {
            foreach ($tours as $tour) {

                // Pre Campaign
                $feefoApi->typeOfCampaign('pre');
                $tags = array('campaign' => 'pre', 'productbrand' => $tour['brand']);
                $feefoApi->genTags($tags);

                $feefoApi->postSale(
                    $customer_email,
                    $customer_name,
                    $tour['desc'],
                    $tour['sku'],
                    $tour['order_ref'],
                    $this->sale_currency,
                    $tour['price'],
                    $tour['link'],
                    $customerref,
                    $todays_date
                );


                // Post Campaign
                $feefoApi->typeOfCampaign('post');
                $tags = array('campaign' => 'post', 'productbrand' => $tour['brand']);
                $feefoApi->genTags($tags);
                $feefoApi->postSale(
                    $customer_email,
                    $customer_name,
                    $tour['desc'],
                    $tour['sku'],
                    $tour['order_ref'],
                    $this->sale_currency,
                    $tour['price'],
                    $tour['link'],
                    $customerref,
                    $tour['end_date']
                );
            }
        } 
    }

	private function genFeefo($booking_info, $channel_id)
    {
        $tourcms = new TourCMS($this->marketplace_account_id, $this->api_private_key, "simplexml");

        $this->feefo_data = new stdClass();
        $this->feefo_data->merchant_id    = $this->feefo->getMerchantId();
        $this->feefo_data->customer_name  = (string)$booking_info->booking->lead_customer_name;
        $this->feefo_data->customer_email = (string)$booking_info->booking->lead_customer_email;
        $this->feefo_data->customerref    = (string)$booking_info->booking->lead_customer_id;
        $this->feefo_data->date           = DateTime::createFromFormat('!Y-m-d', (string)$booking_info->booking->start_date);
        $this->feefo_data->orderref       = $channel_id . '_' . (string)$booking_info->booking->booking_id;
        if($this->feefo_data->date instanceof \DateTime)
        {
            $this->feefo_data->date   = $this->feefo_data->date->format('c');
        }
        else
        {
            $this->feefo_data->date = "";
        }
        $this->feefo_data->products       = array();

        if ($booking_info->booking->components) {
            foreach ($booking_info->booking->components as $component) {
                $c = new stdClass();

                $tour_data = $tourcms->show_tour((integer) $component->component->product_id, $channel_id);


                $c->description       = (string)$tour_data->tour->tour_name;
                $c->productlink       = (string)$tour_data->tour->tour_url;
                $c->productsearchcode = get_original_channel_id((integer)$component->component->product_id) . '_' . get_original_tour_id((integer)$component->component->product_id);
                $c->amount            = (string)$component->component->sale_price_inc_tax_total;
                $c->currency          = (string)$component->component->sale_currency;
                $c->tags              = new stdClass();
                

                array_push($this->feefo_data->products, $c);

            }
        }

        $this->feefo_data->products = json_encode($this->feefo_data->products, 64);
    }

  private function sendFixedPromoEmail()
	{

		$bookingXml = $this->showBookingXml;
		$promoValueType = $bookingXml->booking->promo->value_type;

		if($promoValueType == "FIXED_VALUE") {

			$subj = 'Fixed value promo / gift code used';

			$bod = "Fixed value promo / gift code used \r\n";
			$bod .= "TourCMS: " . $bookingXml->booking->booking_id . "\r\n";

			foreach($bookingXml->booking->payments->payment as $payment) {
				if(substr($payment->payment_transaction_reference, 0, 9) == "TCMSGIFT|") {
					$bod .= "Code: " . $payment->payment_reference . "\r\n";
					$bod .= $payment->payment_note . "\r\n";
				}
			}

			$headers = 'From: noreply@grayline.com'. "\r\n";

			$to = array(GIFT_CODE_MAILTO);

			wp_mail($to, $subj, strip_tags($bod), $headers);

			/*$mh = Loader::helper('mail');
			$subj = 'Fixed value promo / gift code used';

			$mh->setSubject($subj);

			$bod = "Fixed value promo / gift code used \r\n";
			$bod .= "TourCMS: " . $bookingXml->booking->booking_id . "\r\n";

			foreach($bookingXml->booking->payments->payment as $payment) {
				if(substr($payment->payment_transaction_reference, 0, 9) == "TCMSGIFT|") {
					$bod .= "Code: " . $payment->payment_reference . "\r\n";
					$bod .= $payment->payment_note . "\r\n";
				}
			}

			$mh->setBody($bod);

			$mh->to(GIFT_CODE_MAILTO);
			$mh->from('noreply@grayline.com');

			// Doesn't appear to set a return value so can't check it
			//$response = $mh->sendMail();
			$mh->sendMail();*/
		}
	}

	private function dataLayerImpressions($component)
	{
		$tourName = (string)$component->component_name;
		$licenseeChannelId = $this->licenseeChannelId($component);
		//$tourId = (int)$component->product_id;
		// We want the licensee tour id
		$tourId = (int)$this->licenseeTourId($component);
		$id = $licenseeChannelId . "/" . $tourId;
		//$brand = $this->channel_name;
		//$brand = (string)$component->supplier_name;
		// Brand is a little harder to get

		loadSenshiModal('licensee');
		$licensee = new \GrayLineTourCMSSenshiModals\Licensee(null, $licenseeChannelId);
		$licenseeName = $licensee->getChannelName();
		$brand = $licenseeName;


		$category = get_analytics_category((int)$component->product_type);
		$variant = (string)$component->rate_description;
		// We are changing here to use sale quantity rule as group pricing is forced onto cost components
		$rule = (string)$component->sale_quantity_rule;
		$quantity = (float)$component->sale_quantity;
		// We want USD here, with promo code applied, so use sale_price_inc_tax_total
		$priceTotal = (float)$component->sale_price_inc_tax_total;
		// Everything is coming in as group pricing, want to show actual quantities so disabling
		/*
		if ($rule == "GROUP")
		{
			$variant .= " GROUP ($quantity)";
			$quantity = (float)1;
		}
		*/
		$price = sprintf('%0.2F', ($priceTotal / $quantity));
		$item = array(
				'name' => $tourName,
				'id' => $id,
				'price' => $price,
				'brand' => $brand,
				'quantity' => $quantity,
				'category' => $category,
				'variant' => $variant
		);
		$this->impressions[] = $item;
	}


	private function componentsLoop($success)
	{
		//\FB::error("DATA LAYER");
		if ($success)
		{
			$xml = $this->showBookingXml;
			$components = $xml->booking->components->component;
			$totalC = count($components);
			$components = $totalC > 1 ? $components : array($components);
			////\FB::error("COMPONENTS ($totalC)");

			foreach ($components as $component)
			{
				// We also use this data for ecom tracking (as it's got commission an promo codes applied
				$this->dataLayerImpressions($component);

			}
		}
	}

	private function clearUp()
	{

	}


	public function view() {
		global $wpdb;
		////\FB::log("Checkout SUbmit VIEW");

		//	exit();

		// $mh = Loader::helper('mail');

		set_time_limit ( 180 );

		$commit_booking = false;

	// Payment gateways

		//include("tourcms/libraries/gateways/gateway/gateway_factory.php");
		//include("tourcms/libraries/gateways/gateway/gateway_interface.php");

	// Check we have a Channel ID and Cart ID

		$channel_id = isset($_GET["channel_id"]) ? (int)$_GET["channel_id"] : 0;
		$cart_id = isset($_SESSION["tcmscartid"]) ? (int)$_SESSION["tcmscartid"] : 0;

		// Booking ID
		$bo_id = isset($_GET["bo"]) ? (int)$_GET["bo"] : 0;

        // we want to log that a user actually reached this page

        if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
            $link = "https";
        else
            $link = "http";

        // Here append the common URL characters.
        $link .= "://";

        // Append the host(domain name, ip) to the URL.
        $link .= $_SERVER['HTTP_HOST'];

        // Append the requested resource location to the URL
        $link .= $_SERVER['REQUEST_URI'];

        loadSenshiModal('dic');
        $dic = new \GrayLineTourCMSSenshiModals\Dic();
        // Loader::model('dic', 'senshi');
        $logger = $dic::objectLogger();
        // Store log
        $string = "User landed on checkout complete page: $link. REFERRER " . @$_SERVER['HTTP_REFERER'] . ". SESS_ID: " . session_id();
        $logger->logBookingFlow($bo_id, $channel_id, $string);


        ////\FB::log("Booking id ($bo_id) Channel id($channel_id) Cart ID ($cart_id)");

	// Payment log files

			$channel_payment_log = $this->cache_location . "logs/payments/channels/";
			if (!is_dir($channel_payment_log)) {
			    mkdir($channel_payment_log, 0777, true);
			}

			$channel_payment_log .= $channel_id . ".log";

			$gateway_payment_log = $this->cache_location . "logs/payments/gateways/";

			if (!is_dir($gateway_payment_log)) {
			    mkdir($gateway_payment_log);
			}

		if($channel_id == 0)
			$channel_id = isset($_GET["ch"]) ? (int)$_GET["ch"] : 0;

		if($cart_id == 0)
			$cart_id = isset($_GET["ca"]) ? (int)$_GET["ca"] : 0;


		$this->channel_id = $channel_id;
		$this->cart_id = $cart_id;

		$debugMsg = "Cart ID ($cart_id), Channel ID ($channel_id), Booking ID ($bo_id)";
		
		// SEN-379 don't redirect
		if($cart_id == 0) {
			////\FB::error("cart?status=error&action=checkout_submit&action=cart__booking_or_channel");
            $string = "Checkout submit error: No cart id " . $debugMsg;
            $logger->logBookingFlow($bo_id, $channel_id, $string);
            error_log("Checkout submit error: No cart id " . $debugMsg);
            wp_redirect(home_url('/cart?status=error&action=checkout_submit&action=no_cart_id'));
			exit();
		}
		else if ($channel_id == 0) {
            $string = "Checkout submit error: No channel id " . $debugMsg;
            $logger->logBookingFlow($bo_id, $channel_id, $string);
            error_log("Checkout submit error: No channel id " . $debugMsg);
            wp_redirect(home_url("/cart?status=error&action=checkout_submit&action=no_channel_id"));
            exit();
        }

		else if ( $bo_id == 0) {
            $string = "Checkout submit error: No booking id " . $debugMsg;
            $logger->logBookingFlow($bo_id, $channel_id, $string);
            error_log("Checkout submit error: No booking id " . $debugMsg);
            wp_redirect(home_url("/cart?status=error&action=checkout_submit&action=no_booking_id"));
            exit();
        }

	// Grab the internal API credentials

		$tourcms = new TourCMS($this->marketplace_account_id, $this->api_private_key, "simplexml");
		$result = $tourcms->show_channel($channel_id);

		/*
		if(empty($result->channel->internal_api_key)) {
			header("Location: " . DIR_REL . "/cart?status=error&action=checkout_submit&problem=internal");
			exit();
		}
		*/

		$internal_api_key = (string)$this->api_private_key;
		$this->channel_name = (string)$result->channel->channel_name;

		$tourcms_int = new TourCMS(0, $internal_api_key, "simplexml");

	// Get our booking details

        $q =  $wpdb->prepare(
            
            "SELECT * FROM wp_tcmsc_bookings WHERE cart_id = %s AND channel_id = %s AND booking_id = %s AND status != 'COMPLETE'",
            array(
                $cart_id,
                $channel_id,
                $bo_id
            )
        );

        $rows = $wpdb->get_results($q, ARRAY_A);

		// SEN-379 don't redirect
		// temp disable for booking ID affiliate test
		if(count($rows) == 0) {
			////\FB::error("/cart?status=error&action=cart_not_found");
            $string = "No rows found from DB lookup, redirect to cart status error " . $debugMsg;
            $logger->logBookingFlow($bo_id, $channel_id, $string);
            wp_redirect(home_url("/cart?status=error&action=cart_not_found"));
			exit();
		}

		$current_booking = $rows[0];

		$booking_info = $tourcms_int->show_booking($bo_id, $channel_id); 
		////\FB::warn("SHOW BOOKING INFO XML");
		////\FB::log($booking_info);
		$this->bookingId = $booking_info->booking->booking_id;
		$this->showBookingXml = $booking_info;
		$this->apiObject = $tourcms_int;

		// Check whether everything was ok
		if((string)$booking_info->error == "OK") {

			$this->payment_required = true;

			$this->confirmed_booking = true;
			$this->confirmed_booking_id = (int)$booking_info->booking->booking_id;


			$this->payment_success = true;
			$this->payment_can_tell_status = true;

			// Email GLWW if a fixed price promo code was used
			$this->sendFixedPromoEmail();

			// Need to pull from DB, not TourCMS API in case end user paid in non-USD currency
			//$this->alt_sales_revenue = $current_booking["amount_due"];
			//$this->alt_sale_currency = $current_booking["sale_currency"];

			$this->alt_sales_revenue = $current_booking["alt_total"];
			$this->alt_sale_currency = $current_booking["alt_currency"];

			$this->sale_currency = (string)$booking_info->booking->sale_currency;
			$this->sales_revenue = (string)$booking_info->booking->sales_revenue;

            ////\FB::info("saleCurrency ({$this->sale_currency}), saleRevenue({$this->sales_revenue})");
            $this->feefoPrePostCampagin($booking_info, $channel_id);
            //$this->genFeefo($booking_info, $channel_id);
			// Get the voucher URL
			$this->voucher_url = !empty($booking_info->booking->voucher_url) ? (string)$booking_info->booking->voucher_url : "";
			$this->componentsLoop(true);

		} else {

			// There was a problem committing the booking
			$this->confirmed_booking = false;
			$this->confirmed_booking_id = "";
			// TODO: log this
            $string = "Booking info error " . (string)$booking_info->error;
            $logger->logBookingFlow($bo_id, $channel_id, $string);
		}
	



		$this->genDataLayer();


        // add scripts to page
        // $v = View::getInstance();
//        $v->addFooterItem('<script src="https://app.tagibletravel.com/js/tft_integration_script.js" id="tft-integrated-script"></script>');

		// die(print_r($booking_info));

		// $this->customer

		//$tourcms_booking_id = $current_booking["booking_id"];

		//$amount_due = $current_booking["amount_due"];


		//$this->sales_revenue = $current_booking["amount_due"];
		//$this->sale_currency = $current_booking["sale_currency"];


	// Check payment details, amount due etc


		// No longer need to do this, payment is committed in Payment Spreedly Tmt
		/*
		$gateway_info = $result->channel->payment_gateway;

		if($gateway_info->name == "payflowhack")
			$gateway_info->gateway_type = "payf";




		if((!empty($gateway_info->gateway_type)) && $amount_due > 0) {

			error_log('Payment due');

			$this->payment_required = true;

			$gateway_payment_log .= $gateway_info->gateway_type . ".log";

			// Log

					$log_string = date("r") . " | " . "Hit final page CH:" . $this->channel_id . " BO:" . $current_booking["booking_id"];

					error_log($log_string . "\r\n", 3, $channel_payment_log);
					error_log($log_string . " | " . $this->channel_id . "\r\n", 3, $gateway_payment_log);

					$this->log_action( date("r") . " | Hit final page (" . $gateway_info->gateway->name . ")", $this->channel_id, $current_booking["booking_id"], "booking");

			// End log

			$gateway = GatewayFactory::Create((string)$gateway_info->gateway_type, $gateway_info);

			// If we have already made an API payment
			if($gateway->pay_type == "api" || $gateway->pay_type == "silentpost") {
				error_log("Payment reference " . $current_booking["payment_reference"]);
				$gateway->payment_reference = $current_booking["payment_reference"];

				$gateway->amount($amount_due);

				$this->payment_success = !empty($gateway->payment_reference);
				$this->confirmed_booking = $this->payment_success;
				$this->payment_can_tell_status = true;

			//	exit();

			// Otherwise hosted
			} else {

				if($gateway->do_process_result) {

					// Put gateway into test mode on dev box
					if((strpos($_SERVER["HTTP_HOST"],'gl-demo.com') !== false) || (strpos($_SERVER["HTTP_HOST"],'localhost') !== false) || (strpos($_SERVER["HTTP_HOST"],'localhost:8888') !== false) || (strpos($_SERVER["HTTP_HOST"],'macbook') !== false) || (strpos($_SERVER["HTTP_HOST"],'xip.io') !== false)  ) {
						$gateway->test(true);
						$this->test_gateway = true;
					}

					if($gateway->verify_after_payment)
						$this->payment_can_tell_status = true;

					$gateway->amount($amount_due);
					$gateway->currency($current_booking["sale_currency"]);

					// Verify payment success
					$payment_result = $gateway->process_result($_GET);

					$this->payment_error = http_build_query($gateway->log_info);

					if($payment_result) {

						if($gateway->verify_after_payment)
							$this->payment_success = true;

						if($gateway->commit_after_payment) {

							$commit_booking = true;

						} else {

							$commit_booking = false;

						}
					} else {

						$this->payment_success = false;
						$commit_booking = true;

					}

				}
			}


		} else {
			$this->payment_required = false;
			// No gateway configured or no payment to take

			if($amount_due == 0) {
				$this->payment_can_tell_status = true;
				$this->payment_success = true;
				$this->payment_required = true;
			}

			// Commit booking

			$commit_booking = true;
		}

	// POST confirmed booking
		if($commit_booking) {
		// Build the XML to post to TourCMS
			   $booking = new SimpleXMLElement('<booking />');
			   $booking->addChild('booking_id', $tourcms_booking_id);

			   // If we are committing a booking and the payment was successful
			   // Suppress the new booking email
			  // if($this->payment_success) {
			  if($amount_due > 0) {
			  	$booking->addChild('suppress_email', 1);
			  }
			 //  }

			   // Query the TourCMS API, upgrading the booking from temporary to live
			   $confirmed_booking = $tourcms_int->commit_new_booking($booking, $channel_id);


			   // Check whether everything was ok
			   if((string)$confirmed_booking->error == "OK") {

			   		$this->confirmed_booking = true;
			   		$this->confirmed_booking_id = (int)$confirmed_booking->booking->booking_id;

			   		// Get the voucher URL
			   		$this->voucher_url = !empty($confirmed_booking->booking->voucher_url) ? (string)$confirmed_booking->booking->voucher_url : "";

			   } else {

			   		// There was a problem committing the booking
			   		$this->confirmed_booking = false;
			   		$this->confirmed_booking_id = "";

			   }



		} else {
			error_log("Booking status (should be confirmed) " . $current_booking["status"]);
				error_log("Booking ID " . $current_booking["booking_id"]);

			if($current_booking["status"] == "CONFIRMED") {

				$this->confirmed_booking = true;
				$this->confirmed_booking_id = $current_booking["booking_id"];

				// Get the voucher URL
				$this->voucher_url = $current_booking["voucher_url"];

			}
		}

		// Store payment


		if($this->payment_success && $gateway->record_payment_on_booking && $gateway->pay_type != "api" && $gateway->pay_type != "silentpost") {

		// Log

		error_log('This shouldnt show (logging on booking)');

			$log_string = date("r") . " | " . "Storing successful payment CH:" . $this->channel_id . " BO:" . $this->confirmed_booking_id . " PA:" . $gateway->payment_reference;

			error_log($log_string . "\r\n", 3, $channel_payment_log);
			error_log($log_string . " | " . $this->channel_id . "\r\n", 3, $gateway_payment_log);

			$this->log_action( date("r") . " | Storing successful payment (" . $gateway_info->gateway->name . ") PA:" . $gateway->payment_reference, $this->channel_id, $this->confirmed_booking_id, "booking");

		// End log

			// Store payment details on the booking
			// Set your Channel ID, as per the TourCMS API settings page

			// Create a new SimpleXMLElement to hold the payment details
			$payment_data = new SimpleXMLElement('<payment />');

			// Must set the Booking ID on the XML, so TourCMS knows which to update
			$payment_data->addChild('booking_id', $this->confirmed_booking_id);

			// Must set the value of the payment
			$payment_data->addChild('payment_value', $amount_due);

			// Optionally set the payment type
			$logged_payment_type = "Card:";
			$logged_payment_type .= (!empty($gateway_info->gateway_name)) ? $gateway_info->name : "";
			$logged_payment_type .= " (" . $gateway_info->gateway_type . ")";

			$payment_data->addChild('payment_type', $logged_payment_type);

			$paid_by = (string)$booking_info->balance_owed_by;
			$payment_data->addChild('paid_by', $paid_by);

//			if(!empty($_COOKIE["tcms_ag_bk"]) && !empty($_SESSION["agent_name"])){
//				// if user is an agent, set paid_by to 'A'
//				$payment_data->addChild('paid_by', 'A');
//			} else {
//				// otherwise set it to 'C'
//				$payment_data->addChild('paid_by', 'C');
//			}

			// Optionally add a reference
			$payment_data->addChild('payment_reference', $gateway->payment_reference);

			// Call TourCMS API, storing the payment
			$payment_store_result = $tourcms_int->create_payment($payment_data, $this->channel_id);

			// If there was a problem, try again
			if($payment_store_result->error != "OK") {
				// Call TourCMS API, storing the payment
				$payment_store_result = $tourcms_int->create_payment($payment_data, $this->channel_id);
				// If there was a problem the second time, log it
				if($payment_store_result->error != "OK") {

					// Log

						$log_string = date("r") . " | " . "Unable to store successful payment CH:" . $this->channel_id . " BO:" . $this->confirmed_booking_id . " PA:" . $gateway->payment_reference;

						error_log($log_string . "\r\n", 3, $channel_payment_log);
						error_log($log_string . " | " . $this->channel_id . "\r\n", 3, $gateway_payment_log);

						$this->log_action( date("r") . " | Unable to store successful payment (" . $gateway_info->gateway->name . ") PA:" . $gateway->payment_reference, $this->channel_id, $this->confirmed_booking_id, "booking");

					// End log

					// Email

						$subj = 'Unable to store payment';

						if($this->test_gateway)
							$subj = '(Test) ' . $subj;

						$mh->setSubject($subj);

						$bod = "Unable to store payment\r\n";
						$bod .= $this->channel_name . " (CH:$channel_id)\r\n";
						$bod .= "TourCMS: " . $current_booking["booking_id"] . "\r\n";
						$bod .= "Amount: " . $current_booking["amount_due"] . $current_booking["sale_currency"];

						$mh->setBody($bod);

                        $mh->to('admin@senshi.digital', 'Senshi');
                        $mh->from('support@senshi.digital');


						// Doesn't appear to set a return value so can't check it
						//$response = $mh->sendMail();
						$mh->sendMail();

					// End email

				} else {
					$this->log_action( date("r") . " | Stored payment on  final page 2nd try" , $this->channel_id, $this->confirmed_booking_id, "booking");
				}

			} else {
				$this->log_action( date("r") . " | Stored payment on  final page" , $this->channel_id, $this->confirmed_booking_id, "booking");
			}

			$this->payment_store_status = $payment_store_result->error;

		} elseif ((!$this->payment_success) && $gateway->record_payment_on_booking && $gateway->pay_type != "api" && $gateway->pay_type != "silentpost") {

			error_log('We also shouldnt see this, log fail');

			$log_string = date("r") . " | " . "Failed payment . " . $this->payment_error;

			error_log($log_string . "\r\n", 3, $channel_payment_log);
			error_log($log_string . " | " . $channel_id . "\r\n", 3, $gateway_payment_log);

			$this->log_action( date("r") . " | Payment failed (" . $gateway_info->gateway->name . ") ", $channel_id, $current_booking["booking_id"], "booking");

			// Quick hack, don't send failed payment on TMT

//			if(strtolower($gateway_info->gateway_type) != "tmt") {

				// Store payment details on the booking
				// Set your Channel ID, as per the TourCMS API settings page

				// Create a new SimpleXMLElement to hold the payment details
				$payment_data = new SimpleXMLElement('<payment />');

				// Must set the Booking ID on the XML, so TourCMS knows which to update
				$payment_data->addChild('booking_id', $bo_id);

				// Must set the value of the payment
				$payment_data->addChild('audit_trail_note', $this->payment_error);

				// Call TourCMS API, storing the payment
				$payment_store_result = $tourcms_int->log_failed_payment($payment_data, $this->channel_id);

				$this->payment_store_status = $payment_store_result->error;

			// TMT instead send an email

//			} else {
//
//				$this->payment_can_tell_status = false;
//				$this->confirmed_booking = true;
//
//				$this->confirmed_booking_id = $current_booking["booking_id"];
//
//				$tmtfail_log = $this->cache_location . "logs/payments/gateways/";
//
//				if (!is_dir($tmtfail_log)) {
//				    mkdir($tmtfail_log);
//				}
//
//				$tmtfail_log .= "tmtfail.log";
//				error_log( date("r") . " | Payment failed CH:$channel_id BO:" . $current_booking["booking_id"] . " PA:" . $_GET["booking_id"] . "\r\n", 3, $tmtfail_log);
//
//
//				$subj = 'Failed payment check';
//
//				if($this->test_gateway)
//					$subj = '(Test) ' . $subj;
//
//				$mh->setSubject($subj);
//
//				$bod = "Please can you check and inform TourCMS of the status\r\n";
//				$bod .= $this->channel_name . " (CH:$channel_id AC:" . $result->channel->account_id . ")\r\n";
//				$bod .= "Trust My Travel: " . $_GET["booking_id"] . "\r\n";
//				$bod .= "TourCMS: " . $current_booking["booking_id"] . "\r\n";
//				$bod .= "Amount: " . $current_booking["amount_due"] . $current_booking["sale_currency"];
//
//				$mh->setBody($bod);
//
//				$mh->to('ps@tourcms.com', 'Paul Slugocki');
//				$mh->to('alex.bainbridge@tourcms.com', 'Alex Bainbridge');
//				$mh->to('will.plummer@trustmytravel.com', 'Will Plummer');
//				$mh->to('paulslugocki@gmail.com', 'Paul Gmail');
//				$mh->from('support@tourcms.com');
//
//				// Doesn't appear to set a return value so can't check it
//				//$response = $mh->sendMail();
//				$mh->sendMail();
//
//			}

		}

		*/

		 // Clear up after ourselves

		 // Delete the record of our temporary/confirmed booking
//		 $q = $conn->prepare("DELETE FROM tcmsc_bookings WHERE cart_id = :cart_id AND channel_id = :channel_id AND booking_id = :booking_id");
//
//		 $q->execute(array(":cart_id" => $cart_id, ":channel_id" => $channel_id, ":booking_id" => $current_booking["booking_id"]));

		// Set the booking to complete
		// This stops it loading again if this page is refreshed
		$q =  $wpdb->prepare(
		    
		    "UPDATE wp_tcmsc_bookings SET status = 'COMPLETE' WHERE cart_id = %s AND channel_id = %s AND booking_id = %s",
		    
		    array(
		        $cart_id,
		        $channel_id,
		        $current_booking["booking_id"]
		    )
		);

		$wpdb->query($q, ARRAY_A);

		 // Select items
		 // Remove the items just booked from the shopping cart
        $query =  $wpdb->prepare(
            
            "SELECT id, tour_id, channel_id, tour_name_long, price, rate_breakdown, product_type FROM wp_tcmsc_cartitems WHERE cart_id = %s AND channel_id = %s",
            
            array(
                $cart_id,
                $channel_id
            )
        );
        
        $cart_items = $wpdb->get_results($query, ARRAY_A);

//		 print '<pre>';
//		 print_r($cart_items);
//		 print '</pre>';

		 foreach ($cart_items as $cart_item) {

		 	// Quantity
		 	$rate_info = json_decode($cart_item["rate_breakdown"]);
		 	$quant = 0;
		 	foreach($rate_info as $rate) {
		 		$quant = $quant + (int)$rate->quantity;
		 	}

		 	$this->tracking_items[] = array(
		 		"sku" => $cart_item["channel_id"] . "/" . $cart_item["tour_id"],
		 		"name" => addslashes($cart_item["tour_name_long"]),
		 		"quantity" => $quant
		 	);
		 }

	/*
	Options
	*/

		 $cartitem_ids = array();

		 foreach($cart_items as $item) {

		 	$cartitem_ids[] = (int)$item["id"];
		 }

		 $str_cartitem_ids = implode(",", $cartitem_ids);

        $query =  $wpdb->prepare(
            
            "SELECT * from wp_tcmsc_cartoptions where cart_id = %s AND cartitem_id IN ($str_cartitem_ids)",
            
            array(
                $cart_id
            )
        );
        
        $cart_options = $wpdb->get_results($query, ARRAY_A);

		foreach($cart_options as $row) {
			$row_cartitem_id = (string)$row["cartitem_id"];
			if(!array_key_exists((string)$row_cartitem_id, $this->options)) {
				$this->options[(string)$row_cartitem_id] = array();
			}

			$this->options[(string)$row_cartitem_id][] = $row;
		}

	 /*
	 End Options
	 */


//		 print '<pre>';
//		 print_r($this->tracking_items);
//		 print '</pre>';

		//////\FB::error("KEEP THEM IN FOR TESTING - NOT DELETING CART ITEMS DURIG TESTTING");
		// KEEP THEM IN FOR TESTING
		 // Remove the items just booked from the shopping cart
        $q2 =  $wpdb->prepare(
            
             "DELETE FROM wp_tcmsc_cartitems WHERE cart_id = %s AND channel_id = %s",
            
            array(
                $cart_id,
                $channel_id    
            )
        );

        $deleted_count = $wpdb->query($q2, ARRAY_A);

		 // Delete promo code for this channel
        $q3 =  $wpdb->prepare(
            
             "DELETE FROM wp_tcmsc_promo WHERE cart_id = %s AND channel_id = %s",
            
            array(
                $cart_id,
                $channel_id    
            )
        );

		 // Check for other items in the shopping cart (i.e. other channels)
        $q4 =  $wpdb->prepare(
            
             "SELECT id FROM wp_tcmsc_cartitems WHERE cart_id = %s",
            
            array(
                $cart_id    
            )
        );

        $rows = $wpdb->get_results($q4, ARRAY_A);

		 $this->num_items_remaining = count($rows);


		 /*
		 	Data layer

		 if(!empty($this->confirmed_booking_id)):

		 $impressions = array();

		 /*$channels_string = file_get_contents($cache_location."channels.php");
		 $channels=json_decode($channels_string,true);*/
		/*

		 foreach($cart_items as $item) {

		 	// Add impressions
		 	$brand = (string)$item["channel_id"];

		 	$item_price = $item["price"];

			//Quantity
			$rate_info = json_decode($item["rate_breakdown"]);
			 ////\FB::info("Rate INFO");
			 ////\FB::log($rate_info);
			$quant = 0;
			foreach($rate_info as $rate){
				$quant += (int)$rate->quantity;
			}

			 $item["quant"] = $quant;

			 ////\FB::info("ECOM, quantity is "  .$item["quant"]);
		 	// Check if there are any Options for this component
		 	$row_cartitem_id = (string)$row["cartitem_id"];
		 	if(array_key_exists((string)$row_cartitem_id, $this->options)) {

		 		// Loop through and add on each Option
		 		$item_options = $this->options[(string)$row_cartitem_id];

		 		foreach($item_options as $item_option) {
		 			$item_price += $item_option["price"];
		 		}
			}

			 ////\FB::info("ECOM, item price before quant division: $item_price");


			$item_price = (float)($item_price / $item["quant"]);

			 ////\FB::info("ECOM, item price AFTER quant division: $item_price");

		 	$impressions[] = array(
		 	   'name'=> (string)$item["tour_name_long"],
		        'id'=> $item["channel_id"] . "/" . $item["tour_id"],
		        'price'=> sprintf('%0.2F', $item_price),
			'brand'=> $this->channel_name,
			'quantity'=> $item["quant"],
		        'category'=> get_analytics_category((int)$item["product_type"]),
		        'variant'=> ""
		 	);

			 ////\FB::info("ECOM DATA");
			 ////\FB::log($impressions);

		 }

		 // Write dataLayer
		 // E-commerce impressions
		 $dl_array = array(
		    'ecommerce' => array(
	        'currencyCode' => $this->sale_currency,
		 	'purchase'=> array(
		 		'actionField'=> array(
		 			'revenue'=> $this->sales_revenue,
 					'id'=> $this->channel_id . "/" . $this->confirmed_booking_id,
		 		),
		 		'products'=> $impressions
		 	)
		 ));

		 $dl_array["sale_currency"] = $this->sale_currency;
		 $dl_array["sales_revenue"] = $this->sales_revenue;
		 $dl_array["confirmed_booking_id"] = $this->confirmed_booking_id;
		 $dl_array["is_licensee_page"] = "true";
		 $dl_array["licensee_channel_id"] = (string)$this->channel_id;

		 $dl = json_encode($dl_array);

		 $this->data_layer = "dataLayer.push($dl)";


		 endif;

		 /*
		 	End dataLayer
		 */



	}

}
