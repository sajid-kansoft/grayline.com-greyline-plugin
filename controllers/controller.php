<?php

namespace GrayLineTourCMSControllers;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

use TourCMS\Utils\TourCMS as TourCMS;

class MainController
{
    protected static $tc;

    public function __construct() { 
        $tc = new TourCMS(get_option('grayline_tourcms_wp_marketplace'), get_option('grayline_tourcms_wp_apikey'), 'simplexml');

		// we use tourcms test api for certain scenarios
		if (defined('TOURCMS_PRODUCTION_API') && TOURCMS_PRODUCTION_API !== true) {
			$tc->set_base_url("https://test-api.tourcms.com");
		}

        self::$tc = $tc;
    }

	public static function getTc() {
		return self::$tc;
	}

	public static function call_tourcms_api($method, $qs = null, $tour_id = null, $booking_id = null, $channel_id = null, $url_data = null, $extra = null) {
        
        $tc = self::getTc(); 
        $result = NULL;

        try {

            if($channel_id == null) {
                $channel_id = get_option('grayline_tourcms_wp_channel');
            }

            if ( $method == "list" || $method == "related" ) { 
                if ( $extra != "TOUR_URL_UPDATE" )
                    $qs .= QS_GLOBAL; 
                return $tc->search_tours($qs, $channel_id);
            }
            else if($method == "tour") {

                $result =  $tc->show_tour($tour_id, $channel_id, "show_options=1&show_offers=1");

                $direction = -1; 
                if(!empty($result)) {
                    if ( $result->tour->product_type == 2 ) {
                        $direction = $result->tour->product_type->attributes()->direction;
                    }
                    // Make sure we actually have a tour before adding a child
                    if ( $result->tour )
                        $result->tour->addChild( "transfer_direction", $direction );
                }
                return $result;
            } else if ($method == "deals") {
                $result = $tc->show_tour_datesanddeals($tour_id, $channel_id, 'distinct_start_dates=1');
            } else if ($method == "availability") {
                $result = $tc->check_tour_availability($qs, $tour_id, $channel_id);
            } else if($method == "list_tours") {
                $result = $tc->list_tours($channel_id);
            } else if($method == "show_booking") {
                $result = $tc->show_booking( $booking_id, $channel_id );
            } else if ($method == 'show_channel') {
                $result = $tc->show_channel( $channel_id );
            } else if ( $method == "booking_key" ) {
                $result = $tc->get_booking_redirect_url( $url_data, $channel_id );
            } else if( $method == "api_rate_limit_status") {
                $result = $tc->api_rate_limit_status( $channel_id );
            } else if( $method == "update") { 
                $result = $tc->update_tour_url( $tour_id, $channel_id, $extra );
            }
		}
		catch( TourcmsException $e ) {
			// This can stop JSON repsonses from working, so let's just log the error instead.
			//echo "<div class='message error'>We seem to be having issues connecting to our tour source. We are sorry for the inconvenience. </div>";
		}
		catch ( Exception $e ) {
			//echo "<div class='message error'>" . $e->getMessage() . "</div>";
		}

		return $result;
	}

}
