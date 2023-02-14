<?php

namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

abstract class SenshiLogger
{
    private $cacheLocation;
    private $logLocation;
    private $mailHelper;
    const LOG_DIR = 'logs/';
    const PAYMENT_LOG_DIR = "payments/channels/";
    const SUBMIT_LOG_DIR = "";

    public function __construct()
    {
        include("tourcms/config.php");
        $this->cacheLocation = $cache_location;
        $this->logLocation = $this->cacheLocation . self::LOG_DIR;

        // TO DO
        // $this->mailHelper = Loader::helper('mail');
    }

    public function getCacheLocation()
    {
        return $this->cacheLocation;
    }

    public function setCacheLocation($cacheLocation)
    {
        $this->cacheLocation = $cacheLocation;
    }

    public function log($message, $fileName)
    {
        if(!file_exists(dirname($fileName))) mkdir(dirname($fileName), 0770, true);
    }

    public function sendLogEmail($message, $recipients)
    {

    }

    public function paymentLog($message)
    {
        $this->log($message);
    }

    public function logFailedPayment($channelId, $tempBookingId, $paymentReference)
    {

        $this->critical();
    }


}
