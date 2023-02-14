<?php

namespace GrayLineTourCMSControllers;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

use TourCMS\Utils\TourCMS as TourCMS;

class Checkout extends MainController
{

    public $iso_country_list;


    protected $api_private_key;
    protected $marketplace_account_id;

    protected $dbtype;
    protected $dbhost;
    protected $dbport;
    protected $dbname;
    protected $dbuser;
    protected $dbpass;

    public $channel_id;
    public $cart_id;

    public $new_booking;

    protected $fex;

    public $show_credit_fields = true;


    public $calc_total = 0;
    public $alt_total = 0;
    public $alt_total_due_now = 0;
    public $alt_total_display = "";
    public $alt_total_due_now_display = "";
    public $retail_price;

    // Fixed amounts, if totals don't add up
    public $fixed_fex_amount = null;
    public $fixed_fex_amount_display = "";
    public $alt_amount_hash = "";

    public $sel_curr = "";
    public $support_fex_currencies = true;

    public $invalid_fields = array();

    public $items;

    public $num_items;

    public $promo = array();

    public $transfer_ids = array();

    public $messages = array();


    public $hidden_fields = array();

    public $supported_card_types = array();

    public $earliest_item_expiry = 99999999999;
    public $key_expired_timer = 0;

    public $is_agent = false;
    public $agent_name = '';
    public $booking_key;

    public $channel_info;

    public $data_layer;

    protected $cacheBust;

    private function get_alt_amount_hash($amount)
    {
        return hash("md5", "9^SsS6DXd1m$".($amount * 100));
    }

//     public function loadJs()
//     {
//         // add scripts to page
//         $v = View::getInstance();
//         $html = Loader::helper('html');


//         $v->addFooterItem('<script defer src="/themes/grayline_global/js/build/checkout/min/build.js?v='.$this->cacheBust.'"></script>');
//         $v->addFooterItem(
//             '<script defer src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/11.0.1/js/intlTelInput.min.js?v='.$this->cacheBust.'"></script>'
//         );
//         $v->addFooterItem(
//             '<script defer src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/11.0.1/js/utils.js?v='.$this->cacheBust.'"></script>'
//         );

//         // add CSS to the page
//         $v->addFooterItem(
//             '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/11.0.1/css/intlTelInput.css?v='.$this->cacheBust.'" />'
//         );


//         // Tagible removed
// //        $v->addFooterItem(
// //            '<script defer src="https://app.tagibletravel.com/js/tft_integration_script.js?v='.$this->cacheBust.'" id="tft-integrated-script"></script>'
// //        );
//     }

    public function on_start()
    {
        include(GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."libraries/3rdparty/tourcms/config.php");

        $this->iso_country_list = get_iso_country_list();
        loadSenshiModal('dic');
        $dic = new \GrayLineTourCMSSenshiModals\Dic();
        
        $this->fex = $dic::objectFex();
        $this->sel_curr = $this->fex->getSelectedCurrency();
        // $this->set('fex', $this->fex);


        // $this->api_private_key = $api_private_key;
        $this->api_private_key = API_PRIVATE_KEY;
        $this->marketplace_account_id = $marketplace_account_id;

        $this->dbtype = $dbtype;
        $this->dbhost = $dbhost;
        $this->dbport = $dbport;
        $this->dbname = $dbname;
        $this->dbuser = $dbuser;
        $this->dbpass = $dbpass;

        $this->cache_location = $cache_location;

        $json_channels = file_get_contents($cache_location."channels.php");

        $this->channel_info = json_decode($json_channels);

        $promo_code = isset($_POST['promo_code']) ? $_POST['promo_code'] : '';
        
        if (!empty($promo_code)) {
            //FB::log("calling promo code ajax function");
            $this->promoCode();
        }
        $this->isAppleDevice();
    }

    protected function publicError()
    {
        if(isset($_GET['reason'])) {
            $this->public_error = $_GET['reason'];
        }
    }

    protected function getCartId()
    {
        $cart_id = isset($_SESSION["tcmscartid"]) ? (int)$_SESSION["tcmscartid"] : 0;

        return $cart_id;
    }

    protected function getChannelId()
    {
        return CHANNEL_ID;
    }

    public function isAppleDevice()
    {
        include(GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH.'libraries/3rdparty/Mobile_Detect.php');
        $md = new \MobileDetect();
        // problem with just matching safari, is that Chrome Shows Safari too in user agent. Firefox does not
        // we want to know if Safari, so that we can show Apple Pay as default payment method
        $chrome = $md->match('Chrome');
        $firefox = $md->match('Firefox');
        $safari = $md->match('Safari');
        $safari = ! $chrome && ! $firefox && $safari ? true : false;

        $headers = getallheaders();

        $ios = (is_array($headers) && array_key_exists('Cloudfront-Is-Ios-Viewer', $headers)) ? $headers['Cloudfront-Is-Ios-Viewer'] : null;
        // string type coercion
        $ios = $ios === 'true' ? true : false;

        $apple_device = $ios || $safari || $md->isIOS() ? 1 : 0;

        $deviceType = 'desktop';
        $isMobile = 0;
        if ($md->isTablet()) {
            $deviceType = 'tablet';
            $isMobile = 1;
        } else {
            if ($md->isMobile()) {
                $deviceType = 'mobile';
                $isMobile = 1;
            }
        }

        $this->isMobile = $isMobile;
        $this->deviceType = $deviceType;
        $this->apple_device = $apple_device;

        return $apple_device;
    }

    public function view()
    {
        include(GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."libraries/3rdparty/tourcms/config.php");

        global $wpdb;
        // $this->loadJs();
        // Process any errors

        $this->publicError();

        $errorMsg = !empty($_GET['message']) ? $_GET['message'] : '' ;
        $this->errorMsg = $errorMsg;

        if (!empty($_GET["action"])) {
            if ($_GET["action"] == "validate_email") {
                $this->invalid_fields[] = "email";
            }
        }
        loadSenshiModal("cart_item");
                
        // Loader::model("cart_item", "senshi");

        // Database connection object
        // $conn = new PDO(
        //     $this->dbtype.":host=".$this->dbhost.";port=".$this->dbport.";dbname=".$this->dbname."",
        //     $this->dbuser,
        //     $this->dbpass
        // );


        // Check we have a Channel ID and Cart ID
        $channel_id = isset($_GET["channel_id"]) ? (int)$_GET["channel_id"] : 0;
        $cart_id = isset($_SESSION["tcmscartid"]) ? (int)$_SESSION["tcmscartid"] : 0;

        // $cart_id = !empty(session_id()) ? session_id() : '';

        $this->channel_id = $channel_id;
        $this->cart_id = $cart_id;

        if (empty($cart_id) || $channel_id == 0) {
            error_log("Checkout view error, no cart id ($cart_id) or channel id ($channel_id)");
            // echo "need to check redirect not working";
            wp_redirect( home_url("/cart?status=error&action=checkout_check") );
            exit();
        }

        $channel12130Info = $cache_location."channels12130.php";
        $result = null;
        
        if (file_exists($channel12130Info)) {
            
            $json = file_get_contents($channel12130Info);

            $result = json_decode($json);
        }
        
        // $internal_api_key = (string)$api_private_key;
        $internal_api_key = (string)API_PRIVATE_KEY;

        $tourcms_int = new TourCMS(0, $internal_api_key, "simplexml");


        // Check if we have any existing temporary bookings
        $query =  $wpdb->prepare(
            
            "SELECT * FROM tcmsc_bookings WHERE cart_id = %s AND channel_id = %s AND status = 'TEMP'",
            
            array(
                $cart_id,
                $channel_id
            )
        );

        $rows = $wpdb->get_results($query, ARRAY_A);

        // If so, cancel them (should be max 1)

        if ($rows !== false && count($rows) > 0) {
            foreach ($rows as $booking) {

                $booking_id = (int)$booking["booking_id"];

                $deleted = $tourcms_int->delete_booking($booking_id, $channel_id);

                $query =  $wpdb->prepare(
                    
                    "DELETE FROM wp_tcmsc_bookings WHERE cart_id = %s AND channel_id = %s AND booking_id = %s",
                    
                    array(
                        $cart_id,
                        $channel_id,
                        $booking_id
                    )
                );

                $wpdb->query($query, ARRAY_A);
            }
        }

        $sqlcc =  $wpdb->prepare(
            
            "SELECT item_id, channel_id FROM wp_tourcms_cart WHERE sesh_id = %s",
            
            array(
                $cart_id
            )
        );
        // print_r($sqlcc);die;
        $cart_countall = $wpdb->get_results($sqlcc, ARRAY_A);
        // print_r($cart_countall);die;
        $this->num_items = count($cart_countall);

       // palisis_set_cookie("numcartitems", $this->num_items);

//        foreach ($cart_countall as $item) {
//            if (!in_array($item["channel_id"], $this->channel_ids)) {
//                $this->channel_ids[] = $item["channel_id"];
//            }
//        }
//
//        $this->num_channels = count($this->channel_ids);


        // Tours

        $query =  $wpdb->prepare(
            
            "SELECT * from wp_tcmsc_cartitems where cart_id = %s AND channel_id = %s",
            
            array(
                $cart_id,
                $channel_id
            )
        );
        
        $cart_items = $wpdb->get_results($query, ARRAY_A);

        $this->items = $cart_items;
        if (count($cart_items) == 0) {
            header("Location: ".DIR_REL."/cart?status=error&action=checkout");
            exit();
        }

        // Options
        $cartitem_ids = array();

        // Calculate price display format and exchange rate for alternate currency
        $sel_curr = $this->sel_curr;

        $this->item_count = count($this->items);

        for ($i = 0; $i < count($this->items); $i++) {

            $itPrice = $this->items[$i]["price"];
            $cost_curr = $this->items[$i]["cost_currency"];

            $altPrice = $itPrice;

            $altPrice = $this->fex->convertPrice($sel_curr, $altPrice);

            $altPriceDisplay = $this->fex->displayPrice($sel_curr, $altPrice);

            // special offer prices
            if ($this->items[$i]["offer"] === "1") {
                $was_price = $this->items[$i]["was_price"];
                $alt_was_price = $this->fex->convertPrice($sel_curr, $was_price);
                $alt_was_price_display =  $this->fex->displayPrice($sel_curr, $alt_was_price);
                $this->items[$i]["alt_was_price"] = $alt_was_price;
                $this->items[$i]["alt_was_price_display"] = $alt_was_price_display;
            }

            // Quick mod to set a converted currency
            if ($this->items[$i]["currency"] !== $sel_curr) {
                $this->items[$i]["alt_currency"] = $sel_curr;
                $this->items[$i]["alt_price"] = $altPrice;
                $this->items[$i]["alt_price_display"] = $altPriceDisplay;
            }
            // End quick mod to set converted currency

            $cartitem_ids[] = (int)$this->items[$i]["id"];

            if (!empty($_SERVER['HTTPS'])) {
                $this->items[$i]["thumbnail_image"] = $this->tour_image_secure($this->items[$i]["thumbnail_image"]);
            }

            // Find out when our earliest component_key expiry is
            if ($this->items[$i]["expires"] < $this->earliest_item_expiry) {
                $this->earliest_item_expiry = $this->items[$i]["expires"];
            }


            // Build dataLayer
        }


        // Set a time (in miliseconds) until one of the component keys expire
        $this->key_expired_timer = ($this->earliest_item_expiry - time()) * 1000;

        $str_cartitem_ids = implode(",", $cartitem_ids);


        // Promo code
        $promo_code = "";

        $query =  $wpdb->prepare(
            
            "SELECT * from tcmsc_promo where cart_id = %S AND channel_id = %s",
            
            array(
                $cart_id,
                $channel_id
            )
        );

        $cart_promo = $wpdb->get_results($query, ARRAY_A);

        if (!empty($cart_promo[0]["promo"])) {

            $promo = $cart_promo[0];
            $this->promo = $promo;
            $this->promo["formatted_mask"] = "";

            if (!empty($this->promo["membership_mask"])) {

                $pattern = '/^[-0-9]+\z/';

                if (preg_match($pattern, $this->promo["membership_mask"])) {

                    $exploded = explode("-", $this->promo["membership_mask"]);
                    $temp = "";
                    foreach ($exploded as $item) {
                        for ($i = 1; $i <= $item; $i++) {
                            $temp .= "* ";
                        }
                        $temp .= "  ";
                    }

                    $temp = substr($temp, 0, strlen($temp) - 2);
                    $this->promo["formatted_mask"] = $temp;

                }
            }

            $promo_code = $promo["promo"];

        }


        // Start building the booking XML
        $booking = new \SimpleXMLElement('<booking />');

        // Skip inline webhooks here as we're only getting the price
        $booking->addChild('skip_inline_webhook', '1');

        // Append the total customers, we'll add their details on below
        $booking->addChild('total_customers', '1');

        // We are either making a booking as an agent using an undocumented login
        // In which case we send agent_booking_key
        // OR
        // We are booking as a regular customer
        // In which case we send booking_key
        if (!empty($_COOKIE["tcms_ag_bk"]) && !empty($_SESSION["agent_name"])) {
            $this->is_agent = true;
            $this->agent_name = $_SESSION["agent_name"];
            $this->booking_key = $_COOKIE["tcms_ag_bk"];
            $booking->addChild('agent_booking_key', $_COOKIE["tcms_ag_bk"]);
        } else {
            $this->booking_key = $_COOKIE["tcms_bk_$channel_id"];
            $booking->addChild('booking_key', $_COOKIE["tcms_bk_$channel_id"]);
        }

        if ($promo_code != "") {
            $booking->addChild('promo_code', $promo_code);
            if ($promo["membership"] == 1) {
                $booking->addChild('promo_membership', $promo_code);
            }
        }
        ////FB::info("Promo code is $promo_code");
        // Append a container for the components to be booked
        $components = $booking->addChild('components');

        // Add on each component
        foreach ($cart_items as $cart_item) {


            $component = $components->addChild("component");

            $component->addChild("component_key", $cart_item["component_key"]);

            //$component->addChild("note", "");

            if ($cart_item["product_type"] == 2) {
                $this->transfer_ids[] = (int)$cart_item["id"];
            }
        }


        // TEST PROMO
        //$booking->addChild('promo_code', "SENSHITO2");

        // Query the TourCMS API, creating the booking
        try {

            $new_booking = $tourcms_int->start_new_booking($booking, $channel_id);

            $error = $new_booking === false ? 'NO_DATA' : (string)$new_booking->error;
            if ($error !== 'OK') {
                error_log("Start new booking, checkout initial get price, error from TourCMS: $error");
                if ($error === 'EXPIRED BOOKING KEY' || $error === 'MISSING OR INCORRECT KEY') {
                    throw new \RedirectException('Expired booking key');
                }
                else {
                    throw new \TourcmsException($error);
                }
            }
        }
        catch (\RedirectException $e) {
            palisis_set_cookie("tcms_ag_bk", '', time() - 3600);
            palisis_set_cookie("tcms_bk_$channel_id", '', time() - 3600);
            palisis_set_cookie("tcms_bk_{$channel_id}_expires", '', time() - 3600);
            header("Location: ".DIR_REL."/checkout_key");

        }
        catch (\Exception $e) {
            header("Location: ".DIR_REL. "/?status=error&action=checkout&problem=unable+to+create_booking&err=".urlencode($e->getMessage()));
        }


        /*
        echo "<pre>";
        print_r($booking);
        print_r($new_booking);
        */


        if ($new_booking->error == "OK") {

            $this->new_booking = $new_booking;

            // Get the booking ID
            $new_booking_id = (int)$new_booking->booking->booking_id;

            // Check for unavailable components
            $num_unavailable = (int)$new_booking->booking->unavailable_component_count;

            if ($num_unavailable > 0) {
                wp_redirect(home_url("/checkout_check?channel_id=$channel_id&check=all"));
            } else {

                if ($new_booking_id > 0) {
                    $query =  $wpdb->prepare(
                        
                         "INSERT INTO tcmsc_bookings (cart_id, channel_id, booking_id, status) VALUES (%s, %s, %s, 'TEMP')",
                        
                        array(
                            $cart_id,
                            $channel_id,
                            $new_booking_id
                        )
                    );

                    $wpdb->query($query);
                } else {
                    header("Location: ".DIR_REL."/cart?status=error&action=checkout&problem=booking_id");
                }

            }
        } else {

            if ($new_booking->error == "AGENT NOT CONNECTED TO CHANNEL") {
                error_log("AGENT NOT CONNECTED TO CHANNEL");

                header(
                    "Location: ".DIR_REL."/agents/?status=error&message=You+are+not+currently+connected+to+that+channel"
                );

            } elseif ($new_booking->error == "EXPIRED AGENT BOOKING KEY") {

                error_log("EXPIRED AGENT BOOKING KEY");
                setcookie("tcms_ag_bk", "", strtotime("-1 hour"), "/");
                unset($_SESSION["agent_name"]);
                header(
                    "Location: ".DIR_REL."/agents/?status=error&message=Please+log+in+again+to+complete+this+booking"
                );

            } elseif ($new_booking->error == "NO TOUR COMPONENTS ADDED") {

                error_log("NO TOUR COMPONENTS ADDED");
                setcookie("numcartitems", "0", 0, "/");

                header(
                    "Location: ".DIR_REL."/?status=error&action=checkout&problem=unable+to+create_booking&err=".urlencode(
                        $new_booking->error
                    )
                );

            } else {
                try {
                    $logMsg = urlencode($new_booking->error);
                    error_log($logMsg);
                    throw new \TourcmsException($logMsg, 'CRITICAL');
                }
                    catch (\TourcmsException $e) {
                            header(
                                "Location: ".DIR_REL."/?status=error&action=checkout&problem=unable+to+create_booking&err=".urlencode(
                                    $new_booking->error
                                )
                            );
                 }


            }
        }

        // Quick mod to calculate total amounts

        // Set due now
//        // If the total is due now, use that
//        if ((float)$this->new_booking->booking->sales_price_due_ever == (float)$this->new_booking->booking->sales_revenue_due_now) {
//            $this->retail_price = (float)$this->new_booking->booking->sales_price_due_ever;
//        }
//        else {
//            $this->retail_price = (float)$this->sales_revenue_due_now;
//        }

        $actual_amount_due_now = (float)$this->new_booking->booking->sales_revenue_due_now > (float)$this->new_booking->booking->sales_price_due_ever ? (string)$this->new_booking->booking->sales_price_due_ever : (string)$this->new_booking->booking->sales_revenue_due_now;
        $this->retail_price = $actual_amount_due_now;

        if ('USD' !== $sel_curr) {

//              print "<pre>";
//              print_r($this->items);
//              print "</pre>";

            // Loop items
            $i = 0;
            $subtotal_total = 0;
            foreach ($this->items as $item) {
                $subtotal_total += (float)$item["alt_price"];
                $this->calc_total += (float)$item["price"];
            }
//
//
//            echo "<br><br><br><br><br><br><br><br>";
//            echo "CALC TOTAL: $this->calc_total <br>";
//            echo "ALT TOTAL: $this->alt_total <br>";

            $this->alt_total = number_format($this->alt_total, "2", ".", "");
            $item_alt_total = $this->alt_total;
            $this->calc_total = number_format($this->calc_total, "2", ".", "");
            $subtotal_total = number_format($subtotal_total, "2", ".", "");


            $this->alt_total_due_now = $this->fex->convertPrice($sel_curr, $this->retail_price);
            $this->alt_total = $this->alt_total_due_now;

            // if totalling the subtotal FEX amounts is greater than the actual total price, we use the former for consistency
            // we hash this amount, and pass it to Start New Booking for comparison sake so it can't be tampered with
            if ($subtotal_total > $this->alt_total) {
                $this->alt_total = $subtotal_total;
            }

            // Set display versions
            $this->alt_total_display = $this->fex->displayPrice($sel_curr, $this->alt_total);
            $this->alt_total_due_now_display = $this->fex->displayPrice($sel_curr,$this->alt_total_due_now);
//
//            echo "<br><br><br><br><br><br><br><br>";
//            echo "CALC TOTAL: $this->calc_total <br>";
//            echo "ALT TOTAL: $this->alt_total <br>";
//            echo "ALT DUE NOW: $this->alt_total_due_now <br>";


            // Set display versions
//            $item_alt_total_display = str_replace("{amount}", $item_alt_total, $this->currency_format_new);


            // If the calculated total is different to the TourCMS one
            // and that difference is within allowed limits (0.2%)

            // alt_total 51
            // straight_conversion 50

            //$straight_conversion = number_format(round(((float)$this->new_booking->booking->sales_price_due_ever * $this->exchange_rate) * 100) / 100, 2, ".", "");
            //$priceConvert = (float)$this->new_booking->booking->sales_price_due_ever;
            //$straight_conversion = $this->tmtFexRates->convertUsd2Currency('USD', $sel_curr, $priceConvert);

            // echo $this->calc_total;
            // echo "<br /> Sales Revenue due now<br /> ";
            // echo (float)$this->new_booking->booking->sales_revenue_due_now;
            // echo "<br /> Sales Revenue due ever<br /> ";
            // echo (float)$this->new_booking->booking->sales_revenue_due_ever;
            // echo "<br /> Item basket ALt total<br /> ";
            // echo $item_alt_total;
            // echo "<br /> Alt Total<br /> ";
            // echo $this->alt_total;
            // echo "<br /> calc TOtal<br /> ";
            // echo $this->calc_total;


            //echo "<br /> Straight Conversion<br /> ";
            // echo $straight_conversion;
            //echo "<br /> Conversion * 1.008<br /> ";
            // echo $straight_conversion * 1.008;


            // agent scenario
            if ($this->is_agent) {
                // Set fixed amounts
                $this->fixed_fex_amount = $this->alt_total;
                $this->fixed_fex_amount_display = $this->alt_total_display;
                $this->alt_amount_hash = $this->get_alt_amount_hash($this->fixed_fex_amount);
            } else {
                if ($this->alt_total) {
                    // Set fixed amounts
                    $this->fixed_fex_amount = $this->alt_total;
                    $this->fixed_fex_amount_display = $this->alt_total_display;
                    $this->alt_amount_hash = $this->get_alt_amount_hash($this->fixed_fex_amount);
                }
            }

        }
        // always need agent pricing if agent is logged in, in USD and FEX trx
        if ($this->is_agent) {
            $this->agentPricing();
        }
        // End quick mod to calculate total amounts

        // Payment gateway

        // everyone is ADYEN now

        //$this->gateway->provider = "TMT";
        $this->support_fex_currencies = true;

        // Supported card types
        if ((int)$result->channel->payment_gateway->take_visa) {
            $this->supported_card_types[] = "visa";
        }

        if ((int)$result->channel->payment_gateway->take_mastercard) {
            $this->supported_card_types[] = "mastercard";
        }

        if ((int)$result->channel->payment_gateway->take_diners) {
            $this->supported_card_types[] = "diners-club";
        }

        if ((int)$result->channel->payment_gateway->take_discover) {
            $this->supported_card_types[] = "discover";
        }

        if ((int)$result->channel->payment_gateway->take_amex) {
            $this->supported_card_types[] = "american-express";
        }

        //          if((int)$result->channel->payment_gateway->take_unionpay)
        //              $this->supported_card_types[] = "unionpay";



        /*
            Data layer
        */
        $impressions = array();

        $channel_ids = array();
        for ($i = 0; $i < count($this->items); $i++) {

            $item = $this->items[$i];

            // Add impressions
            $brand = (string)$item["channel_id"];
            $this->items[$i]["brand"] = $brand;

            if (!empty($this->channel_info->$brand)) {
                $this->items[$i]["brand"] = $this->channel_info->$brand->name;
            }

            $item_price = $item["price"];
            $this->items[$i]["analytics_category"] = get_analytics_category((int)$item["product_type"]);


            // Quantity
            $rate_info = json_decode($this->items[$i]["rate_breakdown"]);
            $quant = 0;

            foreach ($rate_info as $rate) {
                $this->items[$i]["quant"] = $quant + (int)$rate->quantity;
            }

            // Check if there are any Options for this component
            // $row_cartitem_id = (string)$row["cartitem_id"];
//            if (array_key_exists((string)$row_cartitem_id, $this->options)) {
//
//                // Loop through and add on each Option
//                $item_options = $this->options[(string)$row_cartitem_id];
//
//                foreach ($item_options as $item_option) {
//                    $item_price += $item_option["price"];
//                }
//            }

            $item_price = $item_price / $this->items[$i]["quant"];
            $original_channel_id = get_original_channel_id($item['tour_id']);
            $impressions[] = array(
                'name' => (string)$item["tour_name_long"],
                'id' => $original_channel_id."/".get_original_tour_id($item["tour_id"]),
                'price' => sprintf('%0.2f', $item_price),
                'quantity' => $this->items[$i]["quant"],
                'brand' => get_licensee_name($item["tour_id"]),
                'category' => $this->items[$i]["analytics_category"],
                'variant' => "",
            );
            if (!in_array($original_channel_id, $channel_ids)) {
                $channel_ids[] = $original_channel_id;
            }

        }

        // Write dataLayer
        // E-commerce impressions
        // $dl_array = array(
        //     'ecommerce' => array(
        //         'currencyCode' => $this->items[0]["currency"],
        //         'checkout' => array(
        //             'actionField' => array(
        //                 'option' => (string)$this->gateway_type,
        //             ),
        //             'products' => $impressions,
        //         ),
        //     ),
        // );

        // $dl_array["sale_currency"] = $this->items[0]["currency"];

        // if (count($channel_ids) == 1) {
        //     $dl_array["is_licensee_page"] = "true";
        //     $dl_array["licensee_channel_id"] = $channel_ids[0];
        // }

        // $dl = json_encode($dl_array);

        // $this->data_layer = "dataLayer.push($dl)";

        /*
            End dataLayer
        */




    }

    public function agentPricing()
    {
        $new_booking = $this->new_booking;
        $fex = $this->fex;
        $currency = $this->sel_curr;
        $comm_curr = $new_booking->booking->commission_currency;
        $pr = $new_booking->booking->commission;
        $comm = $fex->convertPrice($currency, $pr);
        $commDisplay = $fex->displayPrice($currency, $comm);


        $origcurr = (string)$new_booking->booking->sale_currency;
        $retail_price = (string)$new_booking->booking->sales_revenue;
        $pr = (string)$new_booking->booking->sales_revenue_due_now;

        $retail_price_display = (string)$new_booking->booking->sales_revenue_display;
        $due_now_display = (string)$new_booking->booking->sales_revenue_due_now_display;

//        print "<br><br><br><br><br><br><br><br><br><br><br>";
        if ($currency != $new_booking->booking->sale_currency && $this->support_fex_currencies) {
//            var_dump("FEX AGENT");
//            echo " FORMAT " . $this->fex->getFormat();
            $retail_fex = $fex->convertPrice($currency, $retail_price);
            $retail_price = $retail_fex;
            $retail_fex_display = $fex->displayPrice($currency, $retail_fex);
            $retail_price_display = $retail_fex_display;
            $dueNow = $fex->convertPrice($currency, $pr);
            $dueNowDisplayFex = $fex->displayPrice($currency, $dueNow);
            $due_now_display = $dueNowDisplayFex;
            $this->fixed_fex_amount = $dueNow;
            $this->fixed_fex_amount_display = $due_now_display;
//            echo "RETAIL FEX $retail_fex <br>";
//            echo "RETAIL FEX DISPLAY $retail_fex_display <br>";
//            echo "DUE NOW FEX $dueNow <br>";
//            echo "DUE NOW DISPLAY FEX <br>";
        }

        // assume agent logged in for instant commission
        $commission_type = "Instant Commission";
        if ($retail_price_display === $due_now_display) {
            // we have an pay out later type of commission
            $commission_type = "Agent Direct Pay";
        }
//        var_dump($currency);
//        var_dump($origcurr);
//        echo "REVENUE DUE NOW $pr <br>";
//        echo "DUE NOW $dueNow <br>";
//        echo "DUE NOW $due_now_display <br>";
//        echo "RETAIL PRICE $retail_price <br>";
//        echo "RETAIL PRICE DISPLAY $retail_price_display <br>";
//        echo "RETAIL PRICE FEX $retail_fex <br>";
//        echo "RETAIL PRICE DISPLAY $retail_fex_display <br>";
//        echo "COMMISSION DISPLAY $commDisplay <br>";
//        echo "COMMISSION TYPE $commission_type <br>";

        $this->retail_price = $retail_price;

        $this->set('retail_price_display', $retail_price_display);
        $this->set('commDisplay', $commDisplay);
        $this->set('commission_type', $commission_type);
        $this->set('due_now_display', $due_now_display);
    }

    public function tour_image_secure($url)
    {
        $url = str_replace("http://", "https://", $url,  1);
        $urlparts = explode('.', $url);

        // check for presence of 'r' segment
        if (preg_match('/r\d+/', $urlparts[1])) {
            $urlparts[1] = 'ssl';
            // put url back together
            $url = implode('.', $urlparts);
        }

        return ($url);
    }

    public function gateway_method()
    {

        if (is_callable(array($this->gateway, 'gateway_method'))) {

            return $this->gateway->gateway_method();
        } else {
            return '';
        }

    }

    public function field_name($field)
    {
       /* if (is_callable(array($this->gateway, 'field_name'))) {

            $field = $this->gateway->field_name($field);
        }
*/
        return $field;
    }

    public function form_action()
    {
        return DIR_REL.'/checkout_payment';
        /*
        if(is_callable(array($this->gateway, 'post_url'))) {
            return $this->gateway->post_url();
        } else {
            return '/checkout_payment';
        }
        */
    }

    protected function isAgent()
    {
        if (!empty($_COOKIE["tcms_ag_bk"]) && !empty($_SESSION["agent_name"])) {
            $this->is_agent = true;
        }
    }

    protected function isGLWWAgent()
    {
        $agentName = $_SESSION["agent_name"];
        if (strpos($agentName, 'GLWW') !== false) {
            return true;
        }

        return false;
    }

    public function promoCode()
    {
        global $wpdb;

        include(GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."libraries/3rdparty/tourcms/config.php");
                // if it is an agent logged in, return false
        $this->isAgent();
        // New system, non GLWW agents can't use promo codes
        if ($this->is_agent) {
            if (!$this->isGLWWAgent()) {
                $result = array();
                $result["error"] = "NOTOK";
                $result["message"] = "Sorry, promo and gift codes are not valid in combination with agent discounts";
                print json_encode($result);
                exit();
            }
        }
        
        loadSenshiModal("cart_item");
        
        $tourcms = new TourCMS($this->marketplace_account_id, $this->api_private_key, "simplexml");
        
        $promo_code = isset($_POST["promo_code"]) ? $_POST["promo_code"] : "";
        $cart_id = isset($_POST["cart_id"]) ? $_POST["cart_id"] : "";
        $channel_id = isset($_POST["channel_id"]) ? $_POST["channel_id"] : "";
        $is_agent = isset($_POST["agent"]) ? (int)$_POST["agent"] : "0";
        $b_k = isset($_POST["b_k"]) ? $_POST["b_k"] : "";

        $bkg_currency = isset($_POST["bkg_currency"]) ? $_POST["bkg_currency"] : "";
        $sel_currency = isset($_POST["sel_currency"]) ? $_POST["sel_currency"] : "";
//
//        print_r($sel_currency);
//        print_r($bkg_currency);

//        $this->support_fex_currencies = (!empty($_POST["tmt_currencies"]) && $_POST["tmt_currencies"] == "true");


        $result = array();

        $result["requires_membership"] = 0;

        if ($channel_id == "" || $cart_id == "" || $b_k == "" || $bkg_currency == "" || $sel_currency == "") {
            
            $result["error"] = "NOTOK";
            $result["message"] = "Sorry, there has been a problem";
        } else {
            
            $promo_ok = false;

            if ($promo_code == "") {
                $promo_ok = true;
            } else {
                
                $check_promo = $tourcms->show_promo($promo_code, $channel_id);

                // Try again, API comms error perhaps
                if (empty($check_promo->error)) {
                    $check_promo = $tourcms->show_promo($promo_code, $channel_id);
                }

                // Try again, API comms error perhaps
                if (empty($check_promo->error)) {
                    $check_promo = $tourcms->show_promo($promo_code, $channel_id);
                }

                // API comms error perhaps
                if (empty($check_promo->error)) {
                    $result["error"] = "NOTOK";
                    $result["message"] = "Unable to verify code, please try again";
                } elseif ($check_promo->error == "OK") {
                    $promo_ok = true;
                    if ($check_promo->promo->requires_membership == 1) {
                        $result["requires_membership"] = 1;
                    }
                } else {
                    $result["error"] = "NOTOK";
                    $result["message"] = "Sorry, that code is not valid";
                }
            }

            // Promo either passed basic validation or empty
            if ($promo_ok) {

                // Promo is valid on this channel

                // DB
                // $conn = new PDO($dbtype.":host=".$dbhost.";port=".$dbport.";dbname=".$dbname."", $dbuser, $dbpass);


                $tourcms_int = $tourcms;


                // Check if we have any existing temporary bookings

                $query =  $wpdb->prepare(
                    
                     "SELECT * FROM tcmsc_bookings WHERE cart_id = %s AND channel_id = %s AND status = 'TEMP'",
                    
                    array(
                        $cart_id,
                        $channel_id
                    )
                );

                $rows = $wpdb->get_results($query, ARRAY_A);

                // If so, cancel them (should be max 1)

                if (count($rows) > 0) {
                    foreach ($rows as $booking) {

                        $booking_id = (int)$booking["booking_id"];

                        $deleted = $tourcms_int->delete_booking($booking_id, $channel_id);

                        $query =  $wpdb->prepare(
                            
                             "DELETE FROM tcmsc_bookings WHERE cart_id = %s AND channel_id = %s AND booking_id = %s",
                            
                            array(
                                $cart_id,
                                $channel_id,
                                $booking_id    
                            )
                        );

                        $wpdb->query($query, ARRAY_A);
                    }
                }

                // Create new temporary booking to get final prices

                // Query the DB to get our items

                // Tours

                $query =  $wpdb->prepare(
                    
                     "SELECT * from wp_tcmsc_cartitems where cart_id = %s AND channel_id = %s",
                    
                    array(
                        $cart_id,
                        $channel_id    
                    )
                );

                $cart_items = $wpdb->get_results($query, ARRAY_A);

                if (count($cart_items) == 0) {
                    $result["error"] = "NOTOK";
                    $result["message"] = "Unable to check promotional code";

                    print json_encode($result);
                    exit();
                }

                // Start building the booking XML
                $booking = new \SimpleXMLElement('<booking />');

                // Append the total customers, we'll add their details on below
                $booking->addChild('total_customers', '1');

                // We are either making a booking as an agent using an undocumented login
                // In which case we send agent_booking_key
                // OR
                // We are booking as a regular customer
                // In which case we send booking_key

                $key_field = $is_agent ? 'agent_booking_key' : 'booking_key';

                $booking->addChild($key_field, $b_k);

                if ($promo_code != "") {

                    $booking->addChild('promo_code', $promo_code);

                    if ($check_promo->promo->requires_membership == "1") {
                        $booking->addChild('promo_membership', $promo_code);
                    }

                }

                // Append a container for the components to be booked
                $components = $booking->addChild('components');

                // Add on each component
                foreach ($cart_items as $cart_item) {

                    $component = $components->addChild("component");

                    $component->addChild("component_key", $cart_item["component_key"]);
                }

                // Query the TourCMS API, creating the booking
                $new_booking = $tourcms_int->start_new_booking($booking, $channel_id);

                // New with net rates - don't allow promo codes in combo with non-logged in agent, affiliate tracking links
                if ($new_booking->error == "OK") {
                    if (!$this->is_agent && $new_booking->booking->agent_id) {
                        $result = array();
                        $result["error"] = "NOTOK";
                        $result["message"] = "Sorry, promo and gift codes are not valid in combination with affiliate links";
                        print json_encode($result);
                        exit();
                    }
                }


                if ($new_booking->error == "OK") {

                    $qnb =  $wpdb->prepare(
                        
                         "INSERT INTO tcmsc_bookings (cart_id, channel_id, booking_id, status) VALUES (%s, %s, %s, 'TEMP')",
                        
                        array(
                            $cart_id,
                            $channel_id,
                            (string)$new_booking->booking->booking_id
                        )
                    );

                    $wpdb->query($qnb, ARRAY_A);

                    $booking_promo = $new_booking->booking->promo;
                    if ($booking_promo->valid == "OK" || $promo_code == "") {

                        $result["support_fex_currencies"] = $this->support_fex_currencies;
                        $result["error"] = "OK";
                        $result["action"] = $promo_code == "" ? "remove" : "add";
                        $result["promo_code"] = $promo_code;

                        $actual_amount_due_now = (float)$new_booking->booking->sales_revenue_due_now > (float)$new_booking->booking->sales_price_due_ever ? (string)$new_booking->booking->sales_price_due_ever : (string)$new_booking->booking->sales_revenue_due_now;


                        if ($promo_code != "") {
                            $promo_value = $booking_promo->value;
                            $promo_value_type = $booking_promo->value_type;
                            if($promo_value_type == "FIXED_VALUE") {
                              $promo_value_currency = $booking_promo->value_currency;
                              if($promo_value_currency == "USD") {
                                  $promo_value = "$" . $promo_value;
                              } else {
                                  $promo_value .= " $promo_value_currency";
                              }

//                              // Start fixing two things:
//                              // 1) Site is taking sales_revenue, should take sales_revenue_due_now
//                              // 2) A bug in "_due_now" values, not taking into account the fixed price promo
//                              // TODO: Remove "2" once core TourCMS bug fixed [TOURCMS-3347]
//
//                              // 1) Site is taking sales_revenue, should take sales_revenue_due_now
//                              $new_booking->booking->sales_revenue_display = (string)$new_booking->booking->sales_price_due_ever_display;
//                              $new_booking->booking->sales_revenue = (string)$new_booking->booking->sales_price_due_ever;
//
//                              // 2) A bug in "_due_now" values, not taking into account the fixed price promo
//                              if((float)$new_booking->booking->sales_revenue_due_now > (float)$new_booking->booking->sales_price_due_ever) {
//                                $new_booking->booking->sales_revenue_due_now = (string)$new_booking->booking->sales_price_due_ever;
//                                $new_booking->booking->sales_revenue_due_now_display = (string)$new_booking->booking->sales_price_due_ever_display;
//                              }
//                              // End fixing two things
                            } else {
                              $promo_value .= "%";
                            }
                            $result["message"] = "Your $promo_value code has been applied";
                        } else {
                            $result["message"] = "Code removed";
                        }

//                        print_r($new_booking->booking);

                        $result["amounts"] = array(
                            'sales_revenue_display' => (string)$new_booking->booking->sales_revenue_display,
                            'sales_revenue_display_approx' => (string)$new_booking->booking->sales_revenue,
                            'sales_revenue_due_now' => (string)$new_booking->booking->sales_revenue_due_now,
                            'sales_revenue_due_now_display' => (string)$new_booking->booking->sales_revenue_due_now_display,
                            'sales_revenue_due_now_display_approx' => (string)$new_booking->booking->sales_revenue_due_now,
                            'amount_due_now' => $actual_amount_due_now,
                            'commission_display' => (string)$new_booking->booking->commission_display,
                            'commission_display_approx' => (string)$new_booking->booking->commission,
                            'commission_tax_display' => (string)$new_booking->booking->commission_tax_display,
                            'commission_tax_display_approx' => (string)$new_booking->booking->commission_tax,
                        );



                        foreach ($result["amounts"] as $amount_name => $amount) {

                            if (strpos($amount_name, "approx") !== false) {

                                $price = (float)$amount;
                                $new_amount = $this->fex->convertPrice(
                                    $sel_currency,
                                    $price
                                );


                                $new_amount = $this->fex->displayPrice($sel_currency, $new_amount);
                                $result["amounts"][$amount_name] = $new_amount;
                            }

                        }

                        // If we support FEX currencies we also need to convert the display amount for the total
                        // and due now
                        if ($this->support_fex_currencies) {

                            $fex_price =  $this->fex->convertPrice(
                                $sel_currency,
                                $actual_amount_due_now
                            );
                            $result["amounts"]['alt_amount_due_now'] = $fex_price;
                            $result["amounts"]["alt_amount_hash"] = $this->get_alt_amount_hash($fex_price);

                            $result["amounts"]["sales_revenue"] = $fex_price;
                            $result["amounts"]["sales_revenue_display"] = $this->fex->displayPrice($sel_currency, $result["amounts"]["sales_revenue"]);

                            $price = (float)$new_booking->booking->sales_revenue_due_now;
                            $price = sprintf('%.2F', $price);
                            $sales_revenue_due_now_fex = $this->fex->convertPrice($sel_currency, $price);
                            $result["amounts"]["sales_revenue_due_now"] = $sales_revenue_due_now_fex;
                            $result["amounts"]["sales_revenue_due_now_display"] =  $this->fex->displayPrice($sel_currency, $sales_revenue_due_now_fex);

                            $price = (float)$new_booking->booking->commission;
                            $result["amounts"]["commission_display_approx_fex"] = $this->fex->convertPrice(
                                $sel_currency,
                                $price
                            );
                            $result["amounts"]["commission_display_fex"] =  $this->fex->displayPrice($sel_currency, $result["amounts"]["commission_display_approx_fex"]);


                        }

                    } else {
                        $result["error"] = "NOTOK";
                        $result["message"] = "Sorry, that code is not valid for this booking";
                    }
                } else {
                    $result["error"] = "NOTOK";
                    $result["message"] = "Unable to check that code";
                }

                //print "<pre>";
                //  print_r($new_booking);
                //  print "</pre>";
                //exit();

            }
        }
        echo json_encode($result);
        exit;
    }
}
