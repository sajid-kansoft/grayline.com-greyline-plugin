<?php

namespace GrayLineTourCMSControllers;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

use TourCMS\Utils\TourCMS as TourCMS;

class CartController extends MainController
{

    protected $api_private_key;
    protected $marketplace_account_id;

    protected $dbtype;
    protected $dbhost;
    protected $dbport;
    protected $dbname;
    protected $dbuser;
    protected $dbpass;

    protected $cache_location;

    public $channel_info;

    public $items = array();

    public $options = array();

    public $promo_codes = array();

    public $channels = array();

    public $sorted_items = array();

    public $last_location;

    public $num_channels = 0;

    public $messages = array();

    public $checkout_url;

    protected $cacheBust;

    protected $channel_id;

    public function on_start()
    {

        include(GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."libraries/3rdparty/tourcms/config.php");

        // $this->cacheBust = $cacheBust;

        $this->api_private_key = $api_private_key;
        $this->marketplace_account_id = $marketplace_account_id;

        $this->dbtype = $dbtype;
        $this->dbhost = $dbhost;
        $this->dbport = $dbport;
        $this->dbname = $dbname;
        $this->dbuser = $dbuser;
        $this->dbpass = $dbpass;

        $this->channel_id = $channel_id;

        $this->cache_location = $cache_location;

        $json_channels = file_get_contents($cache_location."channels.php");

        $this->channel_info = json_decode($json_channels);

        if ((strpos($_SERVER["HTTP_HOST"], '.local') === false) && (strpos(
                    $_SERVER["HTTP_HOST"],
                    'gl-demo.com'
                ) === false) && (strpos($_SERVER["HTTP_HOST"], 'localhost:8888') === false) && (strpos(
                    $_SERVER["HTTP_HOST"],
                    'localhost'
                ) === false) && (strpos($_SERVER["HTTP_HOST"], 'macbook') === false) && (strpos(
                    $_SERVER["HTTP_HOST"],
                    'xip.io'
                ) === false)
        ) {
        	// need to check this
            // $this->checkout_url = "https://".$_SERVER["HTTP_HOST"].DIR_REL."/checkout_key/";
            $this->checkout_url = "checkout_key/";
        } else {
        	// need to check this
        	$this->checkout_url = "checkout_key/";
            # $this->checkout_url = DIR_REL."/checkout_key/";
        }
    }

    public function view()
    {

        global $wpdb;
        
        // Check if we have a cart, if so, load the items
        if (isset($_SESSION["tcmscartid"])) {
            
            $cart_id = $_SESSION["tcmscartid"];

            /* Load Tours */


	        $query =  $wpdb->prepare(
	            
	            "SELECT * from wp_tcmsc_cartitems where cart_id = %s ORDER BY id DESC",
	            
	            array(
	                $cart_id
	            )
	        );

	        $rows = $wpdb->get_results($query, ARRAY_A);

            if (count($rows) > 0) {

                $row_chan = (int)$rows[0]["channel_id"];
                //print $row_chan;

                wp_redirect(home_url($this->checkout_url."?channel_id=".$row_chan));
                exit();
//				foreach ($rows as $row) {
//				
//					$row_chan = (int)$row["channel_id"];
//					
//					header("Location: " . $this->checkout_url . "?channel_id=" $row_chan);
//					exit();
//					
//					// SSL images
//					// Only if called via SSL
//					if(!empty($_SERVER['HTTPS'])) {
//						$row["thumbnail_image"] = $this->tour_image_secure($row["thumbnail_image"]);
//					}
//									
//					// Put this channel in the channels array if it's not already;
//					if(!in_array($row_chan, $this->channels)) {
//						$this->channels[] = $row_chan;
//					}
//					
//					// Create a sorted_items element for this channel if it doesn't already exist	
//					if(!array_key_exists((string)$row_chan, $this->sorted_items)) {
//						$this->sorted_items[(string)$row_chan] = array();
//					}
//						
//					$this->sorted_items[(string)$row_chan][] = $row;
//				}

                $this->num_channels = count($this->channels);
            }

            $this->items = $rows;

            /* Load Options */

//			$sql = $conn->prepare("select * from tcmsc_cartoptions where cart_id = :cart_id");
//			
//			$sql->execute(array(':cart_id' => $cart_id));
//			$rows = $sql->fetchAll();
//			
//			foreach($rows as $row) {
//				$row_cartitem_id = (string)$row["cartitem_id"];
//				if(!array_key_exists((string)$row_cartitem_id, $this->options)) {
//					$this->options[(string)$row_cartitem_id] = array();
//				}
//				
//				$this->options[(string)$row_cartitem_id][] = $row;
//			}
//			
            /* Load Promo code */

//				$sql = $conn->prepare("select channel_id, promo from tcmsc_promo where cart_id = :cart_id");
//				
//				$sql->execute(array(':cart_id' => $cart_id));
//				$rows = $sql->fetchAll();
//				
//				foreach($rows as $row) {
//					$row_channel_id = (string)$row["channel_id"];
//					
//					$this->promo_codes[(string)$row_channel_id] = $row["promo"];
//				}

        }

        // Last location
        if (!empty($_SESSION["last_location"])) {
            $this->last_location = $_SESSION["last_location"];
        }

        wp_redirect(home_url('/cart'));

        // // add scripts to page
        // $v = View::getInstance();
        // // We also need destinations code as that page is included in view if error status
        // $v->addFooterItem('<script defer src="/themes/grayline_global/js/build/cart/min/build.js?v='.$this->cacheBust.'"></script>');
        // $v->addFooterItem('<script defer src="/themes/grayline_global/js/build/destinations/min/build.js?v='.$this->cacheBust.'"></script>');

    }

    public function add()
    {
        global $wpdb;

        isset($_POST["component-key"]) ? $component_file = htmlspecialchars(
            $_POST["component-key"]
        ) : $component_file = "";

        $cart_id = isset($_SESSION["tcmscartid"]) ? (int)$_SESSION["tcmscartid"] : 0;

        $component_file = $this->cache_location."availcache/".str_replace("/", "-slash-", $component_file.".xml");
       
        if (!file_exists($component_file)) { 
        	wp_redirect(home_url($this->checkout_url."?channel_id=$this->channel_id&status=error&action=add&problem=nofile"));
        } 
        $component_info = simplexml_load_file($component_file);
        
        $tour_id = (int)$component_info->tour_id;
        $channel_id = (int)$component_info->channel_id;

        $tour_id = $_POST['tour_id'];
        $channel_id = $this->channel_id;


        $tourcms = new TourCMS($this->marketplace_account_id, $this->api_private_key, "simplexml");

        $result = $tourcms->show_tour($tour_id, $channel_id);

        // try again if problem
        if ($result === false || (string)$result->error != "OK" || !$result->tour) {
            //\FB::error("CART ISSUE NO TOUR");
            $result = $tourcms->show_tour($tour_id, $channel_id);
        } else {
            //\FB::error("CART ISSUE - OK NO PROBLEM WITH TOUR");
        }


        if ($result === false || (string)$result->error != "OK" || !$result->tour) {
        	wp_redirect(home_url($this->checkout_url."?channel_id=$channel_id&status=error&action=add&problem=tour_show"));
        }

        $pickup_choice = "";

        $pickup_key = "";
        $pickup_text = "";
        $pickup_name = "";
        $pickup_time = "";
        $pickup_note = "";

        $pickup_id = "";

        $sub_type = null;

        foreach ($result->tour->product_type->attributes() as $a => $b) {
            if ($a == 'direction') {
                $sub_type = (int)$b;
            }
        }
        
        // Pickup points
        if (isset($component_info->pickup_points->pickup) || !empty($component_info->pickup_on_request_key)) {
            $pickup_choice = isset($_POST["final_pickup_choice"]) ? $_POST["final_pickup_choice"] : "";

            // If the customer has chosen a pickup point
            if ($pickup_choice == "fchosen") {
                $pickup_id = isset($_POST["pickup_id"]) ? $_POST["pickup_id"] : "";
                $pickup_key = isset($_POST["pickup_point"]) ? $_POST["pickup_point"] : "";

                if ($pickup_key == "") {
                    $pickup_key = isset($_POST["pick_up_key"]) ? $_POST["pick_up_key"] : "";
                }

                $pickup_offset = isset($_POST["pickup_points"]) ? (int)$_POST["pickup_points"] : "";
                
                // Check if there's a pickup
                if ($pickup_key != "") {

                    // Check if the customer is entering their own pickup text
                    if (!empty($component_info->pickup_on_request_key)) {
                        if ($pickup_key == (string)$component_info->pickup_on_request_key) {
                            $pickup_text = strip_tags($_POST["pickup_text"]);
                            $pickup_name = strip_tags($_POST["pickup_text"]);
                        }
                    }

                    $pickups = $component_info->pickup_points->xpath("//pickup[pickup_key='".$pickup_key."']");
                    
                    if (count($pickups) > 0) {
                        $pickup = $pickups[0];

                        $pickup_name = (string)$pickup->pickup_name;
                        $pickup_note = (string)$pickup->description;
                        $pickup_text = "";
                        $pickup_time = "";

                        if (!empty($pickup->time)) {
                            $pickup_time = $pickup->time;
                            //$pickup_text = "(" . $pickup_time . ") " . $pickup_text;
                        }
                    }

                }
            } elseif ($pickup_choice == "unlisted" && !empty($_POST["pickup_text"])) {
                // Process unlisted
                if (!empty($component_info->pickup_on_request_key)) {
                    $pickup_key = isset($_POST["pickup_key"]) ? $_POST["pickup_key"] : "";
                    if ($pickup_key == (string)$component_info->pickup_on_request_key) {
                        $pickup_text = strip_tags($_POST["pickup_text"]);
                        $pickup_name = strip_tags($_POST["pickup_text"]);
                        $pickup_time = "";
                    }
                }
            }

        }

        if ($cart_id == 0) {
            // Query
	            $query =  $wpdb->prepare("INSERT INTO wp_tcmsc_carts (num_items) VALUES (%s)",
	            	array(0)
        		);
				$result_carts = $wpdb->query($query);
				$cart_id = $wpdb->insert_id;

				if (false === $result_carts) {
					$error = true;
				} else {
					$_SESSION["tcmscartid"] = $cart_id;
				}
        } else {
            if (!empty($_POST['edit'])) {
                if ($_POST['edit'] != 'false') {
                    $edit = (int)$_POST['edit'];

		            $del_sql =  $wpdb->prepare("DELETE FROM wp_tcmsc_cartitems WHERE cart_id = %s AND channel_id = %s AND id = %s",
		            	array(	(int)$cart_id,
		            			$channel_id,
		            			$edit
	            			 ) 
	        		);

	        		$wpdb->query($del_sql);
                }
            }
        }

        $tour = $result->tour;

        $component_price_display = "50";

        // A bit hacky, serialise the rate info and save that to a column
        $rates = array();
        
        foreach ($component_info->rates->rate as $rate) {

            $rate_count = (string)$rate->rate_count;
            $rate_id = (string)$rate->rate_id;

            $tour_rate = $tour->new_booking->people_selection->xpath("//rate[rate_id='".$rate_id."']");

            $label = empty($tour_rate[0]->label_1) ? "People" : (string)$tour_rate[0]->label_1;

            $rates[] = array(
                "label" => $label,
                "quantity" => $rate_count,
            );
        }
        $ratesForex = json_encode($component_info->rates);
// special offer code
        $offer = 0;
        $was_price = null;
        $was_price_display = null;
        if ((string)$component_info->was_price && (string)$component_info->was_price !== (string)$component_info->total_price) {
            $offer = 1;
            $was_price = (string)$component_info->was_price;
            $was_price_display = (string)$component_info->was_price_display;
        }

        // badges and easy cancellation
        $badges = !empty($_POST['badges']) ? $_POST['badges'] : null;
        $cancel = !empty($_POST['cancel']) && $_POST['cancel'] === "1" ? 1 : 0;

        $insert = array(
            (int)$cart_id,
            $tour_id,
            $channel_id,
            (string)$component_info->component_key,
            (string)$tour->tour_name_long,
            $this->tour_image_secure((string)$tour->images->image[0]->url_thumbnail),
            get_product_url($tour->tour_name_long, $channel_id, $tour_id, $tour->location),
            (string)$component_info->total_price,
            (string)$component_info->total_price_display,
            (string)$offer,
            $was_price,
            $was_price_display,
            $badges,
            $cancel,
            substr((string)$component_info->sale_currency, 0, 3),
            substr((string)$component_info->cost_currency, 0, 3),
            $ratesForex,
            (string)$component_info->start_date,
            (string)$component_info->end_date,
            (string)$component_info->start_time,
            (string)$component_info->end_time,
            (string)$component_info->date_code,
            (string)$component_info->note,
            json_encode($rates),
            (int)$component_info->expires,
            (int)$tour->product_type,
            $sub_type,
            $pickup_time,
            $pickup_name,
            $pickup_choice,
            $pickup_text,
            $pickup_note,
            $pickup_key
        );

        $query =  $wpdb->prepare(
            
            "INSERT INTO wp_tcmsc_cartitems (cart_id, tour_id, channel_id, component_key, tour_name_long, thumbnail_image, url, price, price_display, offer, was_price, was_price_display, badges, cancel, currency, cost_currency, rates_forex, start_date, end_date, start_time, end_time, date_code, note, rate_breakdown, expires, product_type, sub_type, pickup_time, pickup_name, pickup_choice, pickup_text, pickup_note, pickup_key, modified) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW())",
            
			$insert
        );

        $result = $wpdb->query($query);

        
        if (false !== $result) {

            // Add Options
            if (isset($_POST["option"])) {


                // Cart Item ID
                $cartitem_id = $conn->lastInsertId();

                $options = $_POST["option"];

                foreach ($options as $option_key) {
                    $xpath = "//option[quantities_and_prices/selection/component_key='$option_key']";

                    $option_details = $component_info->xpath($xpath);

                    if (count($option_details) > 0) {
                        $option = $option_details[0];

                        $xpath = "//selection[component_key='$option_key']";
                        $selection = $component_info->xpath($xpath);

                        $o_price = number_format((double)$selection[0]->price, 2, '.', '');
                        $o_quantity = number_format((double)$selection[0]->quantity, 2, '.', '');
//						print $o_price . "<br />";
//						print $_quantity . "<br />";
                        $o_mult = $option->sale_quantity_rule == "GROUP" ? 1 : $o_quantity;
                        $o_total_price = number_format($o_mult * $o_price, 2, '.', '');

                        $insert = array(
                            $cartitem_id,
                            (int)$cart_id,
                            (string)$option->option_id,
                            $option_key,
                            (string)$option->option_name,
                            (string)$option->extension,
                            (string)$selection[0]->quantity,
                            (string)$selection[0]->price,
                            (string)$selection[0]->price_display,
                            $o_total_price,
                            $o_total_price,
                            (int)$option->local_payment,
                            (string)$option->sale_quantity_rule,
                            (string)$option->per_text,

                        );

                       
				        $query =  $wpdb->prepare(
				            
				            "INSERT INTO wp_tcmsc_cartoptions (cartitem_id, cart_id, option_id, component_key, name, extension, quantity, price, price_display, total_price, total_price_display, local_payment, sale_quantity_rule, per_text)) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW())",
				            
							$insert
				        );

						$result = $wpdb->query($query);
                    }
                }
            }

            // Set the last added location

            // Location field
            $last_location = (string)$tour->location;

            // Country name
            $country_json = file_get_contents($this->cache_location."/locations/countries.php");
            $gl_country_list = (array)json_decode($country_json);

            $tour_countries = explode(",", (string)$tour->country);

            if (isset($gl_country_list[$tour_countries[0]])) {
                $last_location .= ", ".ucwords(strtolower($gl_country_list[$tour_countries[0]]));
            }

            $_SESSION["last_location"] = $last_location;

            // Redirect to the "View cart" page
            
            wp_redirect(home_url($this->checkout_url."?channel_id=$channel_id&status=ok&action=add"));
            exit();
        } else { 
        	wp_redirect(home_url($this->checkout_url."?channel_id=$channel_id&status=error&action=add&problem=insert"));
        }

    }

    public function remove()
    {
        global $wpdb;

        $item_id = isset($_POST["item_id"]) ? (int)$_POST["item_id"] : 0;
        
        $cart_id = isset($_SESSION["tcmscartid"]) ? (int)$_SESSION["tcmscartid"] : 0;

        if ($item_id > 0 && $cart_id > 0) {

            /* Delete row */

            $query =  $wpdb->prepare(
                
                 "DELETE FROM wp_tcmsc_cartitems WHERE id = %s AND cart_id = %s",
                
                array(
                    $item_id,
                    $cart_id
                )
            );

            $result = $wpdb->query($query, ARRAY_A);


            /* Delete any options */

            $query =  $wpdb->prepare(
                
                 "DELETE FROM wp_tcmsc_cartoptions WHERE cartitem_id = %s AND cart_id = %s",
                
                array(
                    $item_id,
                    $cart_id
                )
            );

            $result = $wpdb->query($query, ARRAY_A);

            if ($result > 0) {
            	// wp_redirect(home_url('cart/'));
                $this->on_start();
                $this->view();
                exit();
            } else {
            	// wp_redirect(home_url('cart/'));
                $this->on_start();
            	$this->view();
                exit();
            }

        } else {
        	// wp_redirect(home_url('cart/'));
            $this->on_start();
            $this->view();
            exit();
        }
    }

    // Add a promo code
    public function add_promo()
    {

        $promo = isset($_POST["hp"]) ? $_POST["hp"] : "";

        if ($promo == "AAA") {
            $promo = "AAAMEMBER";
        }

        if ($promo == "") {
            $promo = isset($_POST["p"]) ? $_POST["p"] : "";
        }

        $channel_id = isset($_POST["c"]) ? (int)$_POST["c"] : 0;
        $cart_id = isset($_SESSION["tcmscartid"]) ? $_SESSION["tcmscartid"] : 0;

        if ($promo == "" || $channel_id == 0 || $cart_id == 0) {
        	wp_redirect(home_url("cart?status=error&action=promo"));
            exit();
        }

        $tourcms = new TourCMS($this->marketplace_account_id, $this->api_private_key, "simplexml");

        $result = $tourcms->show_promo($promo, $channel_id);

        if ($result->error == "OK") {

            $promo_info = array(
                $cart_id,
                $channel_id,
                (string)$result->promo->promo_code,
                (string)$result->promo->requires_membership,
                (string)$result->promo->membership_mask,
            );

            $query =  $wpdb->prepare(
                
                 "INSERT INTO tcmsc_promo (cart_id, channel_id, promo, membership, membership_mask) VALUES (%s, %s, %s, %s, %s)",
                
                array(
                    $promo_info
                )
            );

            $wpdb->query($query);

            wp_redirect(home_url('cart?status=ok&action=promo'));
            exit();
        } else {
        	wp_redirect(home_url('cart?status=notok&action=promo'));
            exit();
        }

    }

    public function remove_promo()
    {
        $promo = isset($_POST["p"]) ? $_POST["p"] : "";
        $channel_id = isset($_POST["c"]) ? (int)$_POST["c"] : 0;
        $location = isset($_POST["r"]) ? "checkout?channel_id=$channel_id" : "cart?";

        $cart_id = isset($_SESSION["tcmscartid"]) ? (int)$_SESSION["tcmscartid"] : 0;

        if ($channel_id > 0 && $cart_id > 0 && $promo != "") {

            /* Delete row */

            $fields = array(
                $promo,
                $cart_id,
                $channel_id
            );

            $query =  $wpdb->prepare(
                
                 "DELETE FROM tcmsc_promo WHERE promo = %s AND cart_id = %s AND channel_id = %s",
                
                array(
                    $fields
                )
            );


            $result = $wpdb->query($query, ARRAY_A);

            if ($result > 0) {
            	wp_redirect(home_url($location."status=ok&action=delete_promo"));
            } else {
            	wp_redirect(home_url($location."status=error&action=delete_promo"));
            }
        }

    }

    public function tour_image_secure($url)
    {

        $url = str_replace("http://", "https://", $url);
        $urlparts = explode('.', $url);

        // check for presence of 'r' segment
        if (preg_match('/r\d+/', $urlparts[1])) {
            $urlparts[1] = 'ssl';
            // put url back together
            $url = implode('.', $urlparts);
        }

        return ($url);
    }
} 
