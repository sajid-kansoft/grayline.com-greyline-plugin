<?php

namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

use \InternalException as InternalException;

class Fex {

    protected $cacheCurrenciesJob;
    protected $geoLocate;
    protected $palisisCookie;
    protected $selected_currency;
    protected $exchange_rate_plus_3;
    protected $allowed_currencies;
    protected $cache_location;
    protected $opex_rates;
    protected $round_prices;
    protected $format;
    const DEF_CURRENCY = 'USD';
    const COOKIE_NAME = 'selcurrency';
    const DEBUG = true; // SET TO FALSE ON GO LIVE

    public function __construct($cacheCurrenciesJob, $geoLocate, $palisisCookie)
    {
        include(GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."libraries/3rdparty/tourcms/config.php");

        $this->cacheCurrenciesJob = $cacheCurrenciesJob;
        $this->geoLocate = $geoLocate;
        $this->palisisCookie = $palisisCookie;
        $this->allowed_currencies = $allowed_currencies;
        $this->cache_location = $cache_location;
        $this->round_prices = defined('ROUND_PRICES') && ROUND_PRICES == true ? true : false;
    }

    /**
     * @return mixed
     */
    public function getSelectedCurrency()
    {
        $this->loadSelectedCurrency();

        return $this->selected_currency;
    }

    /**
     * @return mixed
     */
    public function getFormat()
    {
        return $this->format;
    }



    /**
     * @return mixed
     */
    public function getExchangeRatePlus3()
    {
        if (!$this->exchange_rate_plus_3) {
            $this->loadRates();
            $to_currency = $this->getSelectedCurrency();
            $actual_rate = $this->opex_rates->rates->$to_currency;
            $rate = $this->levyRate($actual_rate, $to_currency);
            $this->exchange_rate_plus_3 = $rate;
        }
        return $this->exchange_rate_plus_3;
    }

    public function convertPrice($to_currency, $price, $format=false, $breakdown_multiple_items=false)
    {
        if (!in_array($to_currency, $this->allowed_currencies)) {
            error_log("Converting to a non allowed currency $to_currency");
            $to_currency = self::DEF_CURRENCY;
        }
        if ($to_currency === self::DEF_CURRENCY) {
            $converted_price = $price;
        }
        else {
            $this->loadRates();
            $actual_rate = $this->opex_rates->rates->$to_currency;
            $rate = $this->levyRate($actual_rate, $to_currency);
            $converted_price = $rate * $price;
        }
        // don't round USD prices, can cause problems with commissions and agent calculations.
        // Let TourCMS be responsible for USD rounding
        if ($to_currency !== self::DEF_CURRENCY) {
            $converted_price = $this->roundNearestWhole($converted_price, $breakdown_multiple_items);
        }
        if ($format) {
            $currency_format = $this->formatCurrency($to_currency);
            $converted_price = str_replace("{amount}", $converted_price,$currency_format);
        }
        return $converted_price;

    }

    protected function roundNearestWhole($amount, $breakdown_multiple_items=false)
    {
        // if we have a breakdown price with multiple items in the breakdown, it causes an adding error if all subtotals are rounded up
        if ($this->round_prices === true) {
            $amount = $breakdown_multiple_items ? round($amount) : ceil($amount);
            $amount = sprintf('%.2F', $amount);
        }
        else {
            $amount = sprintf('%.2F', $amount);
        }
        return $amount;
    }

    public function formatPrice($converted_amount)
    {
        return sprintf('%.2F', $converted_amount);
    }


    public function getCurrencyRates($to_currency, $from_currencies)
    { 
        if (!in_array($to_currency, $this->allowed_currencies)) {
            $to_currency = self::DEF_CURRENCY;
        }
        $from_currencies = rtrim($from_currencies, "|");
        $from_currencies = explode("|", $from_currencies);

        $rates = array();
        $currency_formats = array();
        $this->loadRates(); 
        // NOTE, assumed from currency is always USD as GLWW is now one channel
        foreach ($from_currencies as $from_currency) {
            // don't access undefined property && don't divide by zero

            if (isset($this->opex_rates->rates->$to_currency) && $this->opex_rates->rates->$to_currency != 0) { 
                $actual_rate = $this->opex_rates->rates->$to_currency; 
                $rates[$from_currency] = $this->levyRate($actual_rate, $to_currency);
            } 
        } 
        if (count($rates) === 0) { 
            throw new \InternalException("Cannot generate rates from currencies submitted" . implode(', ', $from_currencies));
        } 
        // Formatting
        $currency_formats[$to_currency] = $this->formatCurrency($to_currency);

        // Build our return object
        $return = new \stdClass();
        $final_currency = $to_currency ? $to_currency : "USD"; 
        $return->rates = $rates;
        $return->final_currency = $final_currency;
        $return->currency_formats = $currency_formats;
        $return->round = $this->round_prices;  
        return $return;
    }

    public function displayPrice($to_currency, $price)
    {
//        var_dump($to_currency, $price, $this->format);
        $price = sprintf('%.2F', $price);
        if (!$this->format) {
        
        try {
            $this->formatCurrency($to_currency);
        } catch (InternalException $e) {
            error_log("Choose currency error: " . $e->getMessage());
        }

        }
        $display = str_replace("{amount}", $price, $this->format);
        return $display;
    }

    public function formatCurrency($to_currency)
    {   
        // Formatting
        $file = GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."libraries/3rdparty/tourcms/libraries/currency_rendering.xml";
        $currency_rendering_xml = simplexml_load_file($file);
        if (is_bool($currency_rendering_xml)) {
            throw new \InternalException("Cant load currency rendering xml file", 'CRITICAL');
        }

        $currency_displays = $currency_rendering_xml->xpath("//row[currency_id='".$to_currency."']");

        if (count($currency_displays) == 1) {
            $cd = $currency_displays[0];

            $pre_num = $cd->html_pre_num == "(NULL)" ? "" : $cd->html_pre_num;

            if ($pre_num != "") {
                if (strpos($pre_num, "&#") === false) {
                    $pre_num .= " ";
                }
            }

            $post_num = $cd->html_post_num == "(NULL)" ? "" : " ".$cd->html_post_num;

            $format = "$pre_num{amount}$post_num";

            $this->format = $format;

            return $this->format;
        }
        else {  
            throw new \InternalException("Cannot format currency $to_currency", 'CRITICAL');
        }
    }

    protected function levyRate($rate, $to_currency='USD')
    {
        if ($to_currency !== 'USD') {
            $rate = $rate * 1.03; // always add on three percent to cover foreign exchange costs
        }
        return $rate;
    }


    protected function loadSelectedCurrency()
    {
        if ($this->selected_currency) {
            return true;
        }
        try { 
            $currency_cookie = isset($_COOKIE[self::COOKIE_NAME]) ? $_COOKIE[self::COOKIE_NAME] : null;
            if ($currency_cookie && in_array($currency_cookie, $this->allowed_currencies)) {
                $this->selected_currency = $currency_cookie;
                return true;
            }
            else if ($currency_cookie && !in_array($currency_cookie, $this->allowed_currencies)) {
                throw new \InternalException("Currency cookie ($currency_cookie) is NOT in allowed currencies", "DEBUG");
            }
            // we need to generate a currency cookie based on the user's geolocation
            else {  
                $this->selected_currency = $this->countryToCurrency();

                $this->palisisCookie->setCookie(self::COOKIE_NAME, $this->selected_currency);
                return true;
            }
        } //catch (InternalException $e) {
        //     error_log("Choose currency error: " . $e->getMessage());
        // } 
            catch (\Exception $e) {
                error_log("Choose currency error: " . $e->getMessage());
                // we default to USD in all scenarios, don't want to break the site here
                $this->selected_currency = self::DEF_CURRENCY;
                $this->palisisCookie->setCookie(self::COOKIE_NAME, $this->selected_currency);
                return true;
        }
    }

    private function countryToCurrency()
    { 
       $country_name = $this->geoLocate->getCountryName(); 

        if (!empty($country_name) && $country_name !== "") {

            $geo_dir = GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH.'/libraries/3rdparty/maxmind';

            $country_name = strtoupper($country_name);

            $country_currencies = simplexml_load_file("$geo_dir/dl_iso_table_a1.xml");

            if (is_bool($country_currencies)) {
                throw new \InternalException("Cant' simple xml load country currencies from dir $geo_dir", 'DEBUG');
            }

            $currency_nodes = $country_currencies->xpath("//ISO_CURRENCY[ENTITY='".$country_name."']");

            if (is_array($currency_nodes) && count($currency_nodes) > 0) {
                $loc_currency = $currency_nodes[0]->ALPHABETIC_CODE;
                $loc_currency = strtoupper($loc_currency);

                if ((strtoupper($loc_currency) == "TRY") || (strtoupper($loc_currency) == "MAD")) {
                    $loc_currency = "EUR";
                }

                if ((strtoupper($country_name) == "SWITZERLAND")) {
                    $loc_currency = "CHF";
                }

                if (!in_array(strtoupper($loc_currency), $this->allowed_currencies)) {

                    throw new \InternalException("Currency ($loc_currency) located for user country ($country_name) is NOT in allowed list of currencies", 'DEFAULT');
                }
                else {
                    return $loc_currency;
                }
            }
            else {
                throw new \InternalException("Can't find currency for country: $country_name", 'DEFAULT');
            }
        }
        else {
            throw new \InternalException("Cannot select currency for empty country", 'DEFAULT');
        }

    }

    private function loadRates()
    {
        try {
            $file = $this->cache_location.'currencies.php'; 
            $errorMsg = "Cannot load currency information $file";
            if (file_exists($file)) { 
                $json = file_get_contents($file);
            } else {
                try {
                    $this->cacheCurrenciesJob->run();
                }
                catch(Exception $e) {
                    throw new Exception($e);
                }
                if (file_exists($file)) {
                    $json = file_get_contents($file);
                    if ($json === false) {
                        throw new \InternalException($errorMsg , " json is false", "CRITICAL");
                    }
                }
                else {
                    throw new \InternalException($errorMsg . " file does not exist", "CRITICAL");
                }
            } 
            $this->opex_rates = json_decode($json); 
        }
        catch (InternalException $e) {
            // Need to relay to customer somehow TODO
            // throw this back up the chain
            throw new \PublicException("Cannot load fex rates at this time, please try again");
        }
        catch (Exception $e) {
            echo $e->getMessage()."\n";
        }
    }
}
