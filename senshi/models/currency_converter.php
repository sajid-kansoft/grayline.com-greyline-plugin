<?php

namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

class CurrencyConverter
{

    public function __construct()
    {
    }

    public function convertUsd2Currency($sale_currency, $sel_currency, $price)
    {
        // until we have adyen rates, just FEX 1 for testing
        $multiplier = 1;
//        if (strtoupper($sel_currency) === "USD") {
//            return $this->number_format_converted_price($price);
//        }
//        $json = $this->chooseFexRates();
//        $tmt_currencies = json_decode($json);
//        //var_dump("NORMAL FEX CONVERTING PRICE");
//        $multiplier = $tmt_currencies->$sale_currency->rates->$sel_currency;
        //var_dump("RATE USED: $multiplier");
        $amount = (float)$price * (float)$multiplier;
        $amount = $this->number_format_converted_price($amount);
        $amount = $this->roundNearestWhole($amount);

        //var_dump("PRICE CONVERSION $sale_currency $price => $sel_currency $amount");

        return $amount;
    }

    private function roundNearestWhole($amount)
    {
        if (defined('ROUND_PRICES') && ROUND_PRICES == true) {
            $amount = ceil($amount);
            $amount = sprintf('%.2F', $amount);
        }

        return $amount;
    }

    private function number_format_converted_price($converted_amount)
    {
        return sprintf('%.2F', $converted_amount);
    }

}
