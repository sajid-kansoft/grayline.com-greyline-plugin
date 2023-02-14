<?php

namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

class PaymentManagerTmt implements PaymentManagerInterface
{
    protected $bookingTourcms;

    protected $message;
    protected $error;


    public function __construct(BookingTourcms $bookingTourcms)
    {
        $this->bookingTourcms = $bookingTourcms;
    }

    public function getError()
    {
        // TODO: Implement getError() method.
    }


    public function getMessage()
    {
        // TODO: Implement getMessage() method.
    }

    public function makePayment()
    {
        // TODO: Implement makePayment() method.
    }


}