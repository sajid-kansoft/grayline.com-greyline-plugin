<?php

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

class PaymentException extends Exception { 

    // path to the log file 
    private $log_file = 'log_payment_exceptions.txt'; 

    public function __construct( $message ) { 
		$dir = GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."logs/{$this->log_file}";
 
        //$message = "There was a file error"; 
 
        // open the log file for appending
        $fp = fopen($dir, (file_exists($dir)) ? 'a' : 'w');
        if ($fp) {
 
            // construct the log message 
            $log_msg = date("[Y-m-d H:i:s]") . 
                " Message: $message\n"; 
 
            fwrite($fp, $log_msg); 
 
            fclose($fp); 
        } 
 
        // call parent constructor 
        parent::__construct($message ); 
    } 
}

class InvoicePaymentException extends Exception {

    // path to the log file
    private $log_file = 'log_invoice_payment_exceptions.txt';


    public function __construct( $message ) {

        $dir = GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."logs/{$this->log_file}";

        //$message = "There was a file error";

        // open the log file for appending
        $fp = fopen($dir, (file_exists($dir)) ? 'a' : 'w');
        if ($fp) {


            // construct the log message
            $log_msg = date("[Y-m-d H:i:s]") .
                " Message: $message\n";

            fwrite($fp, $log_msg);

            fclose($fp);
        }

        // call parent constructor
        parent::__construct($message );
    }

}

class AgentException extends Exception {

    // path to the log file
    private $log_file = 'agent_exceptions.txt';


    public function __construct( $message ) {

        $dir = GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."logs/{$this->log_file}";

        //$message = "There was a file error";

        // open the log file for appending
        $fp = fopen($dir, (file_exists($dir)) ? 'a' : 'w');
        if ($fp) {


            // construct the log message
            $log_msg = date("[Y-m-d H:i:s]") .
                " Message: $message\n";

            fwrite($fp, $log_msg);

            fclose($fp);
        }

        // call parent constructor
        parent::__construct($message );
    }

}


class FileException extends Exception { 
 
    // path to the log file 
    private $log_file = 'log_file_exceptions.txt'; 
 
 
    public function __construct( $message ) { 

		$dir = GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."logs/{$this->log_file}";
 
        //$message = "There was a file error"; 
 
        // open the log file for appending
        $fp = fopen($dir, (file_exists($dir)) ? 'a' : 'w');
        if ($fp) {
        //if ($fp = fopen($dir,'a')) {
 
            // construct the log message 
            $log_msg = date("[Y-m-d H:i:s]") . 
                " Message: $message\n"; 
 
            fwrite($fp, $log_msg); 
 
            fclose($fp); 
        } 
 
        // call parent constructor 
        parent::__construct($message ); 
    } 
 
}


class TourcmsException extends Exception { 
 
    // path to the log file 
    private $log_file = 'log_tourcms_exceptions.txt'; 
 
 
    public function __construct( $message ) { 
    	
		$dir = GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."logs/{$this->log_file}";
 
        //$message = "There was a file error"; 

        // open the log file for appending
        $fp = fopen($dir, (file_exists($dir)) ? 'a' : 'w');
        if ($fp) {
        //if ($fp = fopen($dir,'a')) {
 
            // construct the log message 
            $log_msg = date("[Y-m-d H:i:s]") . 
                " Message: $message\n"; 
 
            fwrite($fp, $log_msg); 
 
            fclose($fp); 
        } 
 
        // call parent constructor 
        parent::__construct($message ); 
    } 
 
}
?>