<?php

namespace GrayLineTourCMSControllers;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

use TourCMS\Utils\TourCMS as TourCMS;
use \DateTime;
use \Feefo;
use \FeefoReviews;

class TourSingle extends MainController {
	private $cache_time = 43200; // 12 hours
	private $cache_location = "api_json/";

	public function form_add_cart_advanced($add_to_cart_json) {
		global $wpdb;

		// $seshID = session_id();
		// session_destroy();
        $cart_id = isset($_SESSION["tcmscartid"]) ? (int)$_SESSION["tcmscartid"] : 0;

		// $cpKEY = NULL;
		$add_to_cart_json = json_decode( $add_to_cart_json);
		// print_r($json_item);die;
		// print_r($add_to_cart_json);die;
		// $add_to_cart_json = base64_encode(serialize($add_to_cart_json));

		// $json = array();
		if ( $add_to_cart_json ) {

			$tour_id = $add_to_cart_json->tour_info->tour_id;
			$channel_id = $add_to_cart_json->tour_info->channel_id;
			$component_key = $add_to_cart_json->component_key;
			$tour_name_long = $add_to_cart_json->tour_info->tour_name;
			$thumbnail_image = $add_to_cart_json->tour_info->thumb;
			$url = $add_to_cart_json->tour_info->tour_url;
			$price = $add_to_cart_json->total_price;
			$price_display = $add_to_cart_json->total_price_display;
			// $valid = $add_to_cart_json_item->component_key_valid_for;

	        // special offer code, need to check this again
	        $offer = 0;
	        $was_price = null;
	        $was_price_display = null;
	        // if ((string)$component_info->was_price && (string)$component_info->was_price !== (string)$component_info->total_price) {
	        //     $offer = 1;
	        //     $was_price = (string)$component_info->was_price;
	        //     $was_price_display = (string)$component_info->was_price_display;
	        // }

	        // badges and easy cancellation, need to check this code
	        $badges = !empty($add_to_cart_json->badges) ? $add_to_cart_json->badges : null;
	        $cancel = !empty($add_to_cart_json->cancel) && $add_to_cart_json->cancel === "1" ? 1 : 0;

	        $currency = substr($add_to_cart_json->sale_currency, 0, 3);
			$cost_currency = substr($add_to_cart_json->cost_currency, 0, 3);
			// not getting this currently, will add it
			$ratesForex = '';
			// $ratesForex = json_encode($component_info->rates);

			$start_date = $add_to_cart_json->start_date;
			$end_date = $add_to_cart_json->end_date;
			$date_code = $add_to_cart_json->date_code;
			$note = $add_to_cart_json->note;
			$note = $add_to_cart_json->tour_info->product_type;
            $product_type= json_encode($add_to_cart_json->tour_info->product_type);
            $rate_breakdown= json_encode($add_to_cart_json->price_breakdown);
            
            $rates_forex = '';

            $start_time = $add_to_cart_json->start_time;
            $end_time = $add_to_cart_json->end_time;
            $expires = '';
            $sub_type = '';
            $pickup_time = '';
            $pickup_name = '';
            $pickup_choice = '';
            $pickup_text = '';
            $pickup_note = '';
            $pickup_key = '';

            // Insert info into database
            // $time = new DateTime();
            // $expires = new DateTime();
            // $modifier = "+{$valid} seconds";
            // $expires->modify($modifier);
            // $time = $time->format('Y-m-d H:i:s');
            // $expires = $expires->format('Y-m-d H:i:s');
            // $avail = 1;

            $error = false;

	        if ($cart_id == 0) {
	            // Query
	            $query =  $wpdb->prepare("INSERT INTO wp_tcmsc_carts (num_items) VALUES (%s)",
	            	array(0) 
        		);
				$result = $wpdb->query($query);
				$cart_id = $wpdb->insert_id;

				if (false === $result) {
					$error = true;
				} else {
					$_SESSION["tcmscartid"] = $cart_id;
				}
	        }

	        // need to check whether we require this in current flow now
	        // else {
	        //     if (!empty($_POST['edit'])) {
	        //         if ($_POST['edit'] != 'false') {
	        //             $edit = (int)$_POST['edit'];
				     //    $query = $wpdb->prepare("DELETE FROM tcmsc_cartitems WHERE cart_id = %s AND channel_id = %s AND id = %s",
				     //        array((int)$cart_id, $channel_id, $edit)
				     //    );
	        //         }
	        //     }
	        // }

            $query =  $wpdb->prepare(
				
			    "INSERT INTO wp_tcmsc_cartitems (cart_id, tour_id, channel_id, component_key, tour_name_long, thumbnail_image, url, price, price_display, offer, was_price, was_price_display, badges, cancel, currency, cost_currency, rates_forex, start_date, end_date, start_time, end_time, date_code, note, rate_breakdown, expires, product_type, sub_type, pickup_time, pickup_name, pickup_choice, pickup_text, pickup_note, pickup_key, modified) VALUES ( %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW())",
			    
			    array(
					$cart_id,
					$tour_id,
					$channel_id,
					$component_key,
					$tour_name_long,
					$thumbnail_image,
					$url,
					$price,
					$price_display,
					$offer,
					$was_price,
					$was_price_display,
					$badges,
					$cancel,
					$currency,
					$cost_currency,
					$rates_forex,
					$start_date,
					$end_date,
					$start_time,
					$end_time,
					$date_code,
					$note,
					$rate_breakdown,
					$expires,
					$product_type,
					$sub_type,
					$pickup_time,
					$pickup_name,
					$pickup_choice,
					$pickup_text,
					$pickup_note,
					$pickup_key
			    )
			);

			$result = $wpdb->query($query);

            $json = array();
            $response = __("success, your item has been added to the cart", "gray-line-licensee-wordpress-tourcms-plugin");
            $json["error"] = 0;
            $cookie_num = 0;

            if (false === $result) {
            	$error = true;
            }

            if($error == true) {
                $json["error"] = 1;
                $response = __("error, there was a problem adding your item to the cart", "gray-line-licensee-wordpress-tourcms-plugin");
            } else {
                // Let's update the cart cookie
                $cookie_num = self::updateCartCookie();
            }
            $json["response"] = $response;
            $json["cookie_num"] = $cookie_num;
		}
		$json = json_encode($json);
		return $json;
	}

	public function check_avail_advanced($request) {
		// // FB::trace( "form_check_avail_advanced()" );

		$qs = $request['check_avail_qs'];
		$tID = $request['tID'];
		$chID = $request['chID'];

		$result = $this->call_tourcms_api("availability", $qs, $tID, null, $chID, null);

		if(empty($result)) {
			exit;
		}
		
		$components = $result->available_components->component;
		$valid = $result->component_key_valid_for;
		$json = NULL;
		// see how many components tourcms has returned
		if ( count( $components) > 0 )  {
			foreach ( $components as $component ) {
				// Let's add in how long the component is valid for
				$component->component_key_valid_for = $valid;
				$json[] = $component;
			}
			$json = json_encode($json, JSON_UNESCAPED_SLASHES);
		}
		// Also want to remove any single quotes, as before, as the data is passed around in attributes
		// data- attribute retrieved data() jquery; single quotes cause bugs
		//$json = str_replace("'", "&#39;", $json);
		//// FB::log("components " . $json );
		echo $json;
   		exit;
	}

	// Will make it common functions
	private static function setCartCookie( $seshID, $num ) {
		// FB::trace( "setCartCookie()" );
		$expire = time() + CART_COOKIE_EXPIRE;
		$info["seshID"] = $seshID;
		$info["num"] = $num;
		unset($_COOKIE['cart_cookie']);
		
		setcookie( "cart_cookie", json_encode($info), $expire, "/" );
	}

	private static function updateCartCookie() {
		// // FB::trace( "updateCartCookie()" );
		$seshID = session_id();
		$num = 0;

		// Test if cookie exists, update if so, create & set to 1 if not
		if ( isset( $_COOKIE["cart_cookie"] ) ) {

			$info = json_decode( stripslashes( $_COOKIE["cart_cookie"] ), ARRAY_A );

			// Test if it matches the current session
			if ( $info["seshID"] == $seshID ) {
				$num = $info["num"] + 1;
 				self::setCartCookie( $seshID, $num );
			}
			// If it doesn't, destroy the cookie, and create a new one
			else {
				$num = 1;
				self::setCartCookie( $seshID, 1 );
			}
		}
		else {
			$num = 1;
			setcookie('my_cookie', 'some default value', strtotime('+1 day'));
			self::setCartCookie( $seshID, 1 );
		}
		return $num;
	}

	/**
	 * Initialiser for dates and deals
	 * Generates the dates and deals xml file
	 * Passes necessary info to view.php
	 *
	 * @param SimpleXMLElement $obj
	 *
	 * @return void
	 */

	public function datesDeals($deals_json) {
		//print_r($deals);die;
		// We need JSON all dates available, JSON deals dates, first date available and price 1 adult
		$ret = [];
		$dates = array();
		$deals = array();
		$date_count = array();
		$first_date = "";
		$price = 0;
		// If there are multiple deals, we are dealing with an Array
		// If there is just one deal, we are not
		$today_long = date('l jS \of F Y h:i:s A');
		$today = date("Y-m-d" );
		if(!empty($deals_json)) {
			if ( $deals_json->total_date_count == 1 ) {
				$deal = $deals_json->dates_and_prices->date;
				if ( $deal->start_date >= $today ) {
					$dates[] = (string)$deal->start_date;
					$deals[(string)$deal->start_date] = $deal;
				}
			}
			$deals_json_dates = is_array( $deals_json->dates_and_prices->date ) ? $deals_json->dates_and_prices->date : array( $deals_json->dates_and_prices->date );
			foreach($deals_json_dates as $deal) {
				// Because we have cached the xml file, we need to make sure we are dealing with fresh data,
				// i.e. make sure the date of the deal is not in the past  -IMPORTANT
				if ( $deal->start_date >= $today ) {

					$dates[] = (string)$deal->start_date;
					$date_count[(string)$deal->start_date] = isset($date_count[(string)$deal->start_date])? $date_count[(string)$deal->start_date]+1:0;
					$deals[(string)$deal->start_date][$date_count[(string)$deal->start_date]] = $deal;
				}
			}
			
			$first_date_form = $dates[0];
			// FB::log( "first date form is " . $first_date_form );
			if ( $first_date_form != "" )
				$first_date_cal = convertDate("Y-m-d", "d/m/Y", $first_date_form);
			// FB::log( "first date cal is $first_date_cal" );
			$dates = json_encode($dates); 
			$deals = str_replace("'", "&#39;", json_encode($deals));
			
			$ret["first_date_form"] = $first_date_form;
			$ret["first_date_cal"] = $first_date_cal;
			$ret["dates"] = $dates;
			$ret["deals"] = $deals;
		}
		return $ret;
	}

	public function get_deals_dates($tour_id) {

		$marketplace_account_id = get_option('grayline_tourcms_wp_marketplace');
		$channel_id = get_option('grayline_tourcms_wp_channel');
		$api_private_key = get_option('grayline_tourcms_wp_apikey');

		$tourcms = new TourCMS($marketplace_account_id, $api_private_key, 'simplexml');
		
        $cache_key = str_replace( "&", "-", $tour_id );
        $cache_key = preg_replace(array("/[\s]/","/[^0-9A-Z_a-z-.]/"),array("_",""), $cache_key);

        
		//$date_results = get_transient($cache_key); 
		$date_results = get_data_from_transient($cache_key); 

		if(empty($date_results)) {

			$date_results = $tourcms->show_tour_datesanddeals($tour_id, $channel_id, '');
			// Load data into runtime cache for 12 hours
			//set_transient($cache_key, json_encode($date_results), 12 * 60 * 60 );
			set_data_in_transient($cache_key, $date_results);
			$date_results = get_data_from_transient($cache_key);

		}

		// print_r($date_results);die;
		return $date_results;
	}

	public function feefoReviews($tour_id)
	{ 
   	    global $post;
		$plugin_path = GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH;
		require_once $plugin_path.'/models/feefo_reviews.php';
		require_once $plugin_path.'/models/feefo.php';
		$original_channel_id = get_post_meta($post->ID, 'grayline_tourcms_wp_original_channel_id', true );

		$feefo = new Feefo(); 
		//$feefo_image_url = $feefo->getLogoUrl('grayline-product-stars_en.png', GLWW_CHANNEL . '_' . (string)$tour_id); 
		//$this->set_query_var('setfeefo_img_url', $feefo_image_url);

		$feefo_data = new FeefoReviews($feefo, (string) $original_channel_id . '_' . (string) $tour_id, 15, array(
				"cache_time" => $this->cache_time,
				"cache_location" => $plugin_path.$this->cache_location
		));

		$hasReviews = $feefo_data->has_reviews();
		
		$feefoHide  = false;

		// client might want to switch off for time being
		if (get_option('grayline_tourcms_wp_feefo_show') != 1)
		{   
			$feefoHide  = true;
			$feefo_data = null;
			$hasReviews = false;
		}

		$this->set_query_var('feefo_data', $feefo_data);
		$this->set_query_var('hasReviews', $hasReviews);
		$this->set_query_var('feefoHide', $feefoHide);
	}

	private function set_query_var( $var, $value ) {
	   set_query_var( $var, $value );
	}

	public function get_widget_ticket( $args ) {
		$tour_id = $args['tour_id'] ? $args['tour_id'] : false;
		$product_name =  $args['product_name'] ? $args['product_name'] : false;
		$badge_code =  $args['badge_code'] ? strtolower($args['badge_code']) : false;
		$badge_name =  $args['badge_name'] ? $args['badge_name'] : false;
		$date = $args['date'] ? $args['date'] : '';
		$total_price =  $args['total_price'] ? $args['total_price'] : false;
		$offer =  $args['offer'] ? $args['offer'] : false;
		$offer_note =  $args['offer_note'] ? $args['offer_note'] : 'Special Offer';
		$was_price =  $args['was_price'] ? $args['was_price'] : false;
		$customisation =  $args['customisation'] ? $args['customisation'] : false;
		$duration = $args['duration'] ? $args['duration'] : false;
		$pickup = $args['pickup'] ? $args['pickup'] : false;
		$time = $args['time'] ? $args['time'] : false;
		$percentage_discount = null;
		$price_brekdown = $args['price_brekdown'] ? $args['price_brekdown'] : null;
		$pickup_point=$args['pickup_point'] ? $args['pickup_point'] : 0;
		$cart_button=$args['cart_button'] ? $args['cart_button'] : 0;
		// check on special offer 
		if ($was_price === $total_price) {
			$offer = false;
		}
		// don't divide by zero - fatal error
		if ($offer && $was_price > 0) :
			$percentage_discount = ceil((1 - ($total_price / $was_price)) * 100);
		endif;
	?>
	<div id="widget-ticket" class="border border-primary my-4 shadow-sm">
		 
		  	<?php if($pickup_point===1){?>
			<input type='hidden' name='pickup_point' value=''>
			<?php }?>

		  <input type="hidden" name="tour_id" value="<?php echo $tour_id; ?>">
		  <!-- <input type="hidden" name="component-key" id="component_key"> -->
		  <input type="hidden" name="add_to_cart_json" id="add_to_cart_json" value=""/>

		  <input type="hidden" name="add_to_cart" value="1">
		  
		  <div class="border-top <?php echo @$bg_class ?> border-primary border-4">

		    <!-- white part of ticket -->
		    <div class="pt-2 pb-3 px-3">

		      <div class="d-flex align-items-center justify-content-between">
		        <div class="w-50">
		          <img class="gl-logo" src="<?php echo get_template_directory_uri() ?>/img/gray-line-ticket-logo.svg" alt="Gray Line Logo" />
		        </div>
		        <div>
		          <?php if ($badge_code) : ?> <div><span class="badge badge-<?php echo $badge_code ?>"><?php echo $badge_name ?></span></div>
		          <?php endif; // badge 
		          ?>
		        </div>
		      </div>
		      <div class="d-flex mt-3 border-1 border-bottom">
		        <div>
		          <h5><?php echo $product_name ?></h5>
		        </div>
		      </div>

		      <div class="d-flex mt-3">
		        <span class="d-block"><?php echo $date ?></span>
		      </div>
		      <div class="my-3 pb-3 border-1 border-bottom">
		        <?php if ($time) : ?>
		          <div class="mb-1"><small class="text-muted d-block">Time</small><?php echo $time ?></div>
		        <?php endif; ?>
		        <?php if ($customisation) : ?>
		          <div class="mb-1"><small class="text-muted d-block">Customisation</small><?php echo $customisation ?></div>
		        <?php endif; ?>
		        <?php if ($duration) : ?>
		          <div class="mb-1"><small class="text-muted d-block">Duration</small><?php echo $duration ?></div>
		        <?php endif ?>
		        <?php if ($pickup) : ?>
		          <div class="mb-1"><small class="text-muted d-block">Pickup</small><span id='#ticket_pickup_label'><?php echo $pickup ?></span></div>
		        <?php endif; ?>
		      </div>

		      <div><small class="text-muted d-block">Price breakdown</small></div>
		      <?php if ($price_brekdown) :
		        foreach ($price_brekdown as $pb) : ?>
		          <div class="d-flex align-items-center justify-content-between">
		            <div id="bd-quant-rate"><span id="bd-quant-int"><?php echo $pb['quant'] ?></span> x <span id="bd-rate"><?php echo $pb['rate'] ?></span></div>
		            <div id="bd-price">US$ <?php echo $pb['price'] ?></div>
		          </div>
		      <?php endforeach;
		      endif; ?>


		    </div><!-- end white part of ticket -->

		    <!-- Grey price, add to cart -->
		    <div class="d-flex justify-content-between align-items-center bg-gray-200 py-2 px-3">
		      <!-- PRICE -->
		      <div>

		        <?php 

		        if ($offer=='true' ) :  ?>

		          <span class="d-block mb-1">
		            <small class="text-muted">Total Price</small>
		          </span>
		          <span class="d-block price-list fs-4 fw-500 text-offer lh-s">US$ <?php echo $total_price ?></span>
		          <span class="d-block mt-2 text-muted small"><s>US$ <?php echo $was_price ?></s> <span class="text-offer">-<?php echo $percentage_discount ?>%</span></span>

		        <?php endif; // offer 
		        ?>
		        <?php if ($offer=='false') : ?>
		          <span class="d-block mb-1">
		            <small class="text-muted">Total Price</small>
		          </span>
		          <span class="d-block price-list fs-4 fw-500 lh-s mb-2">US$ <?php echo $total_price ?></span>
		        <?php endif; // notoffer 
		        ?>
		        <small class="d-block text-muted xs">All taxes and fees included</small>

		      </div>
		      <!-- END PRICE -->

		      <!-- BOOK BTN -->
		      <div class="">

		        <button id="add-to-cart-btn" type="sumbit" class="btn btn-primary px-md-3" <?php if($cart_button == 0) { ?>disabled<?php } ?> >Add to cart</button>
		      </div>
		      <?php
		      /**
		       * TODO: Toggle between loading button when adding to cart
		       */
		      ?>
		      <button id="add-to-cart-btn-spinner" class="btn btn-primary btn btn-primary px-md-3 d-none" type="button" disabled>
		        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
		        <span class="visually-hidden">Loading...</span>
		      </button>
		      <!-- END BOOK BTN -->

		    </div>
		    <!-- End grey price, add to cart -->
		  </div>
		
		</div>
	<?php }
	/*public function generateBread( $sep ) {

        // First we will look for a defined parent page in TourCMS
        $sublevels = $this->getSubLevels();
        /*if (!$sublevels) {
            $bcrumb = false;
            return $bcrumb;
        }
        else {
            $bcrumb = '';
            $link = '';
            if (!empty($sublevels)) {
                foreach ($sublevels as $p) {
                    //dojoTourcmsPackage::debug_to_console($p, "P VALS");
                    if ($p["link"] != false && $p["title"] != false) {
                        $link .= '';
                        $link .= '<a href="'.$p["link"].'">';
                        $link .= $p["title"];
                        $link .= '</a></span>';
                        $link .= '<span class="swp-breadcrumbs-level"> <span class="delim">'.htmlspecialchars(
                                $sep
                            ).'</span> ';
                    }
                }
            }

            // add in the current page
            $bcrumb .= "$link <strong>{$this->tour->tour_name_long}</strong>";

            return $bcrumb;
        }
	}*/

	/*private function getSubLevels() {
		global $post;
		// First we will look for a defined parent page in TourCMS
        $bcrumb_parent_page_id = get_post_meta(get_the_ID(), 'grayline_tourcms_wp_custom_breadcrumb');
        if (empty($bcrumb_parent_page_id)) {
            return false;
        }

        $path = [];

		if ($bcrumb_parent_page_id) {
            $c = $this->getPageDetail((int)$bcrumb_parent_page_id, $path);
            //$parent_url = $nh->getCollectionURL($c);

            $path[] = array( "link" => $parent_url, "title" => $c->getCollectionName());

			$trail = $nh->getTrailToCollection($c);

            if (!empty($trail)) {
                foreach ($trail as $page) {
                    $pageLink = false;
                    if ($page->getCollectionAttributeValue('replace_link_with_first_in_nav')) {
                        $firstChild = $page->getFirstChild();
                        if ($firstChild instanceof Page) {
                            $pageLink = $nh->getCollectionURL($firstChild);
                        }
                    }
                    if (empty($pageLink)) {
                        $pageLink = $nh->getCollectionURL($page);
                    }
                    // add onto end
                    array_unshift(
                        $path,
                        array(
                            "link" => $pageLink,
                            "title" => $page->vObj->cvName,
                        )
                    );
                }
            }


        }

		return $path;
	}*/

	/*private function getPageDetail($page_id, $path) {
		$a = get_post($page_id);
		print_r($a);exit;
	}*/
}
