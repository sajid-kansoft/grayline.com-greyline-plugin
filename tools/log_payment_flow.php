<?php
defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

use TourCMS\Utils\TourCMS as TourCMS;

class LogPaymentFlow {

    public function __construct($request) {
        if(!empty($request['booking_id'])) {
            try {
                $logger = new logger();

                $booking_id = isset($request['booking_id']) ? $request['booking_id'] : null;
                $string = isset($request['message_string']) ? $request['message_string'] : null;
                $marketplace_account_id = get_option('grayline_tourcms_wp_marketplace');
                $api_private_key = get_option('grayline_tourcms_wp_apikey');
                $tourcms = new TourCMS($marketplace_account_id, $api_private_key, 'simplexml'); 
                
                // The text to store
                $note = "Some information to store on the booking";
                // The type of note to store
                $note_type = "AUDIT";

                // Call TourCMS API, adding the note on to the booking
                $channel_id = get_option('grayline_tourcms_wp_channel');
                $result = $tourcms->add_note_to_booking($booking_id, $channel_id, $string, $note_type); 
                

                // Check the result, will be "OK" if the booking was updated
                if ($result->error == "OK") {
                    // Print a success message
                    $string2 = "Note stored";
                    $logger->logBookingFlow($booking_id, $string2);
                } else {
                    // Some  problem
                    $string2 = "Problem storing note in TCMS " . $result->error;
                    $logger->logBookingFlow($booking_id, $string2);
                }



                $logger->logBookingFlow($booking_id, $string);

                $json['error'] = 0;

                $json = json_encode($json, JSON_UNESCAPED_SLASHES);
                echo $json;
                exit;
            }
            catch (\Exception $e) {
                error_log($e->getMessage());
                $publicError = "Sorry there was a problem, please try again";
                $json['error'] = 1;
                $json['response'] = $publicError;
                $json = json_encode($json, JSON_UNESCAPED_SLASHES);
                echo $json;
                exit;
            }
        }
    }
}
