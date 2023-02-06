<?php
class logger
{
    protected $cache_location;
    protected $gateway = 'unknown';

    public function __construct()
    {
        $this->cacheLocation();
    }

    public function log_action($string, $booking_id = 0, $type = "payment", $gateway = "unknown")
    {
        $this->tourcms_log($string, $booking_id, $type, $gateway, $this->cache_location);
    }

    public function logBookingFlow($booking_id, $string, $xml = null)
    {
        $data = date("r");
        $data .= " | ";
        $data .= $string;
        if ($xml) {
            $data .= ": ";
            $data .= str_replace(array("\r", "\n"), '', $xml->asXML());
        }
        $this->writeBookingToFile($booking_id, $data);
    }

    protected function cacheLocation()
    {
        // Root for files
        $doc_root = GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH;
        $cache_location = $doc_root .'/logs/';
        $this->cache_location = $cache_location;
    }


    protected function writeBookingToFile($booking_id, $string)
    {
        // Create directory if it doesn't already exist
        $log_file = $this->cache_location."bookings/";

        if (!is_dir($log_file)) {
            mkdir($log_file, 0770, true);
        }

        // Finish setup for log file
        $log_file .= $booking_id.".log";

        // Write log
        error_log($string."\n", 3, $log_file);
    }

    protected function tourcms_log(
        $string,
        $booking_id = 0,
        $type = "payment",
        $gateway = "",
        $cache_location = ""
    ) {

        if ($type == "booking") {

            // Create directory if it doesn't already exist

            $log_file = $cache_location."bookings/channels/";

            if (!is_dir($log_file)) {
                mkdir($log_file, 0770, true);
            }
            // Finish setup for log file

            $log_file .= $booking_id.".log";

            // Write log

            error_log($string."\n", 3, $log_file);

        } else {

            // Create directory if it doesn't already exist

            $channel_payment_log = $cache_location."payments/channels/";

            if (!is_dir($channel_payment_log)) {
                mkdir($channel_payment_log, 0770, true);
            }

            $gateway_payment_log = $cache_location."payments/gateways/";

            if (!is_dir($gateway_payment_log)) {
                mkdir($gateway_payment_log, 0770, true);
            }

            $gateway_payment_log .= $gateway.".log";

            // Write logs

            error_log($string."\n", 3, $channel_payment_log);
            error_log($string."\n", 3, $gateway_payment_log);

        }
    }

}
