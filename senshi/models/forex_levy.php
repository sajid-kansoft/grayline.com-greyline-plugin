<?php

namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

class ForexLevy
{
    
    protected $defCurrency = "USD";

    protected $isForexLevy;
    protected $selCurrency;
    protected $levy = 1.03; // i.e. 3% levy

    public function __construct()
    {
        die("OLD FEX CODE ForexLevy");
        $isForexLevy = false;
        $selCurrency = empty($_COOKIE['selcurrency']) ? $this->defCurrency : (string)$_COOKIE['selcurrency'];
//        //\FB::error("Selected currency $selCurrency def currency is " . $this->defCurrency);
//        if (strtoupper($selCurrency) == $this->defCurrency)
//        {
//            // Yes, we need to levy a forex charge. Cost currency is USD, user is viewing site in non-USD
//            $isForexLevy = true;
//            //\FB::warn("Selected currency $selCurrency is USD, we need to apply a forex levy on all non USD cost currency items");
//        }
        // We are cancelling forex levies - NET RATES project means that prices come back from TCMS with MarkUp
        $isForexLevy = false;
        $this->selCurrency = $selCurrency;
        $this->isForexLevy = $isForexLevy;
    }

    public function getIsCostCurrencyLevy($costCurrency)
    {
        $isCostCurrencyLevy = false;
        //\FB::warn("IS FOREX LEVY? " . $this->getIsForexLevy());
        if ($this->getIsForexLevy())
        {
            $costCurrency = (string)$costCurrency;
            if ($costCurrency != $this->getDefCurrency())
            {
                //\FB::warn("Cost currenct is not USD, selected Currency USD, we need to apply a levy to the price");
                $isCostCurrencyLevy = true;
            }
        }
        return $isCostCurrencyLevy;
    }

    public function levyPrice($val)
    {
        $price = $this->levy * (float)$val;
        $price = sprintf('%.2F', $price);
        //\FB::warn("Final price has been LEVVIED from $val to: $price");
        return $price;
    }

    public function levyPriceDisplay($string, $val, $ovverride=null)
    {
        $array = preg_split("/;/", $string);
        $currHtml = $array[0];
        $price = $ovverride ? $val : $this->levyPrice($val);
        $priceDisplay = $currHtml . ";" . $price;
        return $priceDisplay;
    }


    public function getIsForexLevy() { return $this->isForexLevy; }
    

    public function getSelCurrency() { return $this->selCurrency; }

    public function getDefCurrency() { return $this->defCurrency; }





}
