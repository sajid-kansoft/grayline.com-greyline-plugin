<?php

namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

class AdyenMoney
{

    public function __construct()
    {
    }

    public function integerAmount($amount, $currency)
    {
        if ($currency === "JPY" || $currency === "KRW") {
            return $amount;
        }
        // bug with floating point precision, e.g. 912 becoming 911.999999999
        $amount = round(100 * $amount);
        $amount = intval($amount);

        return $amount;
    }

    public function reverseIntegerAmount($amount, $currency)
    {
        if ($currency === "JPY" || $currency === "KRW") {
            return $amount;
        }
        $amount = $amount / 100;
        $amount = sprintf('%.2F', $amount);
        $amount = floatval($amount);

        return $amount;
    }

}
