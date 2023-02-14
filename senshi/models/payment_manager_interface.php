<?php

namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

interface PaymentManagerInterface
{

    public function makePayment();

    public function getMessage();

    public function getError();

}