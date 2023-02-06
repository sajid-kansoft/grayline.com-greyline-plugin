<?php
/**
 * check_availability.php
 * 
 */

namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

class CheckAvailability {

    protected $fex;
    protected $tour_id;
    protected $channel_id;
    protected $rates;
    protected $qs;
    protected $cache_location;
    protected $tourcms;
    protected $logMsg;

    public function __construct($fex)
    {
        include(GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."libraries/3rdparty/tourcms/config.php");
        $this->fex = $fex;
        $this->tourcms = $tourcms;
        $this->cache_location = $cache_location;
    }

    public function checkAvailability()
    {   

        // Query the TourCMS API 
        $result = $this->tourcms->check_tour_availability($this->qs, $this->tour_id, $this->channel_id);

        if(empty($result->error)) {
            // If we didn't get a response, try again
            $result = $this->tourcms->check_tour_availability($this->qs, $this->tour_id, $this->channel_id);
        }

        if(empty($result->error)) {
            // If we didn't get a response, try again
            $result = $this->tourcms->check_tour_availability($this->qs, $this->tour_id, $this->channel_id);
        }

        $log = date("r") . " check availability tour id $this->tour_id, qs $this->qs, channel_id $this->channel_id " . $_SERVER["REQUEST_URI"];
        $this->logMsg = $log;
        $tcmsError = (string)$result->error;
        $log .= " ($tcmsError)";

        if ($tcmsError != "OK") {
            error_log("TCMS COMPONENT ERROR | " . $log);
        }
        if(empty($result->error) || $tcmsError != "OK") {
            throw new \InternalException($log);
        }
        else {  
            $result = $this->processComponents($result);
        } 
        return $result;
    }

    /**
     * We want to FEX each component price, and massage the price breakdown for further use
     */
    protected function processComponents($result)
    {
        $components = array();

        // Check cache dir exists
        $output_dir = $this->cache_location."availcache";

        if (!is_dir($output_dir)) {
            mkdir($output_dir);
        }

        // Documentation is wrong, sale quantity stored outside component
        // Update, we need to use group pricing on all items, temp TourCMS bug
        $saleQuantRule = (string)$result->sale_quantity_rule;
        $saleQuantRule = "GROUP";
        $componentArr = $result->available_components->component;
        $total = count($componentArr);

        $sel_currency = $this->fex->getSelectedCurrency();
        
        $currency_format = $this->fex->formatCurrency($sel_currency);
        
        if ($total > 0) {
            foreach ($componentArr as $component) {
                // Times
                if (!empty($component->start_time)) {
                    $component->start_time = to_12_hour((string)$component->start_time);
                }

                // End time
                if (!empty($component->end_time)) {
                    $component->end_time = to_12_hour((string)$component->end_time);
                }

                $components[] = $component;
                $filename = (string)$component->component_key;
                //$filename = str_replace("+", "-plus-", $filename);
                $filename = str_replace("/", "-slash-", $filename);
                $filename .= ".xml";

                $component->tour_id = (string)$this->tour_id;
                $component->channel_id = (string)$this->channel_id;
                $component->expires = time() + (int)$result->component_key_valid_for;

                $rates_element = $component->addChild('rates');

                $sale_price = (string)$component->total_price;

                $tourName = (string)$result->tour_name;
                $req = (string)$result->request;

                $i = 0;

                $check_avail_qs = isset($_POST['check_avail_qs']) ? $_POST['check_avail_qs'] : null;
                
                parse_str($check_avail_qs, $output);

                foreach ($this->rates as $rate) {

                    if (isset($output[$rate])) {
                        $rate_count = (int)$output[$rate];
                        
                        try {

                            if ($rate_count > 0) {
                                //	$qs .= "&" . $rate . "=" . $rate_count;
                                //	$total_people = $total_people + $rate_count;

                                //	$component->rates->rate[] = $rate."_".$rate_count;
                                $rate_element = $rates_element->addChild('rate');
                                //$rate_element->addChild('rate', $rate."_".$rate_count);
                                $rate_element->addChild('rate_id', $rate);
                                $rate_element->addChild('rate_count', $rate_count);

                                $i++;
                            }
                        } catch (Exception $e) {
                            $breakdown = $component->price_breakdown->price_row;
                            $totalPrices = count($breakdown);
                            $log = "Component Price Breakdown total ($totalPrices) doesn't match number of rates | ";
                            $log .= date("r")." check availability tour id $this->tour_id ".$_SERVER["REQUEST_URI"];
                            $log .= " $tourName | $req";
                            error_log($log);
                            $error = array();
                            echo json_encode($error);
                            exit;
                        }
                    }
                }

                // Price breakdown
                $handle = fopen($output_dir."/".$filename, 'w') or die('Cannot open file:'); //

                // My Apache server doesn't have the right to read this file
                /*if (!fopen($output_dir."/".$filename, 'w')) {
                   // var_dump($php_errormsg);
                   print "Unable to open file.";
                }*/

                //implicitly creates file
                fwrite($handle, $component->asXML());

            }

            foreach ($components as $comp) {


                // rounding errors mean we can't forex levy the total
                $subTotal = 0;
                $subTotal_n_minus_one = 0;
                $total_breaks = count($comp->price_breakdown->price_row);

                // Currency conversion

                $breakdown_multiple_items = count($comp->price_breakdown->price_row) >= 2 ? true : false;
                // In Fex scenarios, we have to make sure the subtotal matches the total. So make the price of the last element, the remainder
                for ($i = 0; $i < count($comp->price_breakdown->price_row); $i++) {
                    $attributes = $comp->price_breakdown->price_row[$i]->attributes();

                    $row_label = $attributes["label"];
                    $row_price = $attributes["price"];
                    $row_currency = $attributes["sale-currency"];

                    if ($row_currency != $sel_currency) {
                        $sale_currency = $row_currency;
                        $price = $row_price;
                        $converted_amount = $this->fex->convertPrice($sel_currency, $row_price, false, $breakdown_multiple_items);
//                        print_r("Row curr != sel curr, Price convert $row_currency $row_price to $sel_currency $converted_amount");
                        $subTotal += $converted_amount;
                        if ($i < ($total_breaks - 1)) {
                            $subTotal_n_minus_one += $converted_amount;
                        }

                        $comp->price_breakdown->price_row[$i] = $row_label." ".str_replace(
                                "{amount}",
                                $converted_amount,
                                $currency_format
                            );
                    } else {
                        $converted_amount = $row_price;
//                        print_r("Row curr == sel curr, Price convert $row_currency $row_price to $sel_currency $converted_amount");
                        $subTotal += $converted_amount;
                        $comp->price_breakdown->price_row[$i] = $row_label." ".str_replace(
                                "{amount}",
                                $converted_amount,
                                $currency_format
                            );
                    }
                }

                // Convert main price
                if ($comp->sale_currency != $sel_currency) {

                    $sale_currency = $comp->sale_currency;
                    // Move this code to functions
                    // We have a problem whereby the rounding means there is a creep on price, we need to make sure the sale price matches
                    // subtotal of components
                    $price = $comp->total_price;
                    $converted_amount = $this->fex->convertPrice($sel_currency, $price);
                    $subTotal = $this->fex->formatPrice($subTotal);
                    if ($subTotal !== $converted_amount) {
                        // we have to make the last element the remainder of the addition
                        // we need to break down & rebuild
                        $item = $comp->price_breakdown->price_row[$total_breaks - 1];
                        $converted_amount_item = $converted_amount - $subTotal_n_minus_one;
                        $converted_amount_item = $this->fex->formatPrice($converted_amount_item);
                        $attributes = $item->attributes();

                        $row_label = $attributes["label"];
                        $comp->price_breakdown->price_row[$total_breaks - 1] = $row_label." ".str_replace(
                                "{amount}",
                                $converted_amount_item,
                                $currency_format
                            );
                    }
                    $comp->total_price = $converted_amount;

//
//                    print_r("SUB $subTotal");
//                    print_r("CONVERTED TOTAL " .$converted_amount);
//                    print_r("TOTAL " .$comp->total_price);

                    $comp->total_price_display = str_replace("{amount}", $converted_amount, $currency_format);

                    $comp->sale_currency = $sel_currency;

                }
                else {
                    $converted_amount = (string)$comp->total_price;
                    $comp->total_price = $converted_amount;
                    $comp->total_price_display = str_replace("{amount}", $converted_amount, $currency_format);
                }
            } 
            
            return $components;
        }
        else {
            throw new \InternalException($this->logMsg . " - No components returned", 'DEFAULT');
        }
    }

    /**
     * @param mixed $tour_id
     */
    public function setTourId($tour_id): void
    {
        $this->tour_id = $tour_id;
    }

    /**
     * @param mixed $channel_id
     */
    public function setChannelId($channel_id): void
    {
        $this->channel_id = $channel_id;
    }

    /**
     * @param mixed $qs
     */
    public function setQs($qs): void
    {
        $this->qs = $qs;
    }

    /**
     * @param mixed $rates
     */
    public function setRates($rates): void
    {
        $this->rates = $rates;
    }
}
