<?php 

function tourcms_log($string, $channel_id, $booking_id = 0, $type = "payment", $gateway = "", $cache_location = "") {

	if($type == "booking") {
		
		// Create directory if it doesn't already exist
		
		$log_file = $cache_location . "logs/bookings/channels/";
		
		if (!is_dir($log_file)) {
		    mkdir($log_file, 0770, true);
		}
		
		$log_file .= $channel_id . "/";
		
		if (!is_dir($log_file)) {
		    mkdir($log_file, 0770, true);
		}
		
		// Finish setup for log file
		
		$log_file .= $booking_id . ".log";	
		
		// Write log
		
		error_log("\n" . $string, 3, $log_file);
		
	} else {
	
		// Create directory if it doesn't already exist
	
		$channel_payment_log = $cache_location . "logs/payments/channels/";
		
		if (!is_dir($channel_payment_log)) {
		    mkdir($channel_payment_log, 0770, true);
		}
		
		$channel_payment_log .= $channel_id . ".log";
		
		$gateway_payment_log = $cache_location . "logs/payments/gateways/";
		
		if (!is_dir($gateway_payment_log)) {
		    mkdir($gateway_payment_log, 0770, true);
		}
		
		$gateway_payment_log .= $gateway . ".log";
		
		// Write logs
		
		error_log($string . "\n", 3, $channel_payment_log);
		error_log($string . "\n", 3, $gateway_payment_log);
		
	}
}

?>
