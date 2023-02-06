<?php

namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

// If selected currency is USD, no levy required
// If selected currency is USD, and cost currency is NOT USD, no levy required
class CartItem
{

    protected $forexLevy;
    protected $cartItem;
    protected $tourName;
    protected $costCurrency;
    protected $tmtFexRates;
    protected $forceLevyItem = false;


    protected function startNewLevyBooking($cart_items, $tourcms_int, $channel_id)
    {
        // Forex Levy - 3% onto USD items in non-USD cart
        Loader::model("forex_levy", "senshi");
        Loader::model("cart_item", "senshi");
        $forexLevy = new ForexLevy();
        $resultXml = null;
        if ($forexLevy->getIsForexLevy())
        {
            //Start building the Temporary booking XML
			$booking = new SimpleXMLElement('<booking />');
            // In which case we send booking_key
            if(!empty($_COOKIE["tcms_ag_bk"]) && !empty($_SESSION["agent_name"])) {
                $booking->addChild('agent_booking_key', $_COOKIE["tcms_ag_bk"]);
                if(!empty($_POST['agent_ref']))
                    $booking->addChild('agent_ref', $_POST['agent_ref']);
            } else {
                $booking->addChild('booking_key', $_COOKIE["tcms_bk_$channel_id"]);
            }

            if($this->promo != ""){
                $booking->addChild('promo_code', $this->promo);
                if(!empty($_POST['promo_membership'])) {
                    $booking->addChild('promo_membership', $_POST["promo_membership"]);
                }
            }
            // Append a container for the components to be booked
            $components = $booking->addChild('components');
            foreach ($cart_items as $cart_item)
            {

                $component = $components->addChild("component");

                $component->addChild("component_key", $cart_item["component_key"]);

                // New code to levy a 3% forex fee on items cost currency USD when selected currency is not USD
                $forexLevyCartItem = new CartItem($forexLevy, $cart_item);
                $component = $forexLevyCartItem->forexLevyXml($component);
            }
            // Query the TourCMS API, creating the booking
            $new_booking = $tourcms_int->start_new_booking($booking, $channel_id);
            if($new_booking->error == "OK")
            {
                $resultXml = $new_booking;
            }
        }
        return $resultXml;
    }



    public function __construct(ForexLevy $forexLevy, $cartItem)
    {
        $this->forexLevy = $forexLevy;
        $this->cartItem = $cartItem;
        $this->tourName = $cartItem["tour_name_long"];
        $this->costCurrency = strtoupper((string)$cartItem["cost_currency"]);
        // New code - we also need to levy items, in a non USD checkout, where the cost currency is not same as selected currency
        Loader::model('tmt_fex_rates', 'senshi');
        $tmtFexRates = new TmtFexRates();
        //$this->forceLevyItem = $tmtFexRates->isLevyItemInBasket($this->costCurrency);
    }

    public function forexLevySalePrice()
    {
        $salePrice = $this->cartItem["price"];
        //\FB::warn("FOREX LEVY CART ITEM SALE PRICE");

        // rounding errors mean we can't just forex levy the total - we need to compare this against the breakdown (which we pass to start new booking)
        $subTotal = 0;
        $ratesForexJson = $this->cartItem["rates_forex"];
        $ratesForex = json_decode($ratesForexJson);
        //\FB::warn($ratesForex);
        $ratesForex = is_array($ratesForex->rate) ? $ratesForex->rate : array($ratesForex->rate);
        foreach($ratesForex as $rate)
        {
            $pricePPLevy = (float)$rate->price_pp_levy;
            $subTotal += $pricePPLevy;
        }
        $salePrice = $this->applyForexLevy($salePrice);
        $salePrice = $salePrice == $subTotal ? $salePrice : $subTotal;
        return $salePrice;
    }


    public function getIsLevyItem()
    {
        $isLevy = false;
        if ($this->forceLevyItem) {
            $isLevy = true;
        }
        else if ($this->forexLevy->getIsCostCurrencyLevy($this->costCurrency))
        {
            $isLevy = true;
        }
        return $isLevy;
    }


    public function forexLevyDisplayPrice($overridePrice=null)
    {
        $priceDisplay = $this->cartItem["price_display"];
        $price = $overridePrice ? $overridePrice : $this->cartItem["price"];
        //\FB::warn("FOREX LEVY CART ITEM DISPLAY PRICE $priceDisplay");
        if ($this->getIsLevyItem())
        {
            $priceDisplay = $this->forexLevy->levyPriceDisplay($priceDisplay, $price, $overridePrice);
        }
        //\FB::warn("NEW PRICE DISPLAY $priceDisplay");
        return $priceDisplay;
    }


    // We might want to apply a forex levy on a specific price
    // E.g. it's already had currency conversion on it, or is a breakdown price etc (legacy code)
    public function applyForexLevy($price=null)
    {
        //$isCostCurrencyLevy = $this->forexLevy->getIsCostCurrencyLevy($this->costCurrency);
        $isCostCurrencyLevy = $this->getIsLevyItem();
        if ($isCostCurrencyLevy)
        {
            $newPrice = $this->forexLevy->levyPrice($price);
            //\FB::warn("FOREX LEVY HAS BEEN APPLIED to {$this->tourName}, From $price to $newPrice COST CURRENCY ($this->costCurrency)");
            $price = $newPrice;
        }
        else
        {
            //\FB::warn("FOREX LEVY HAS NOT BEEN APPLIED to {$this->tourName}, $price COST CURRENCY ($this->costCurrency)");
        }
        return $price;
    }

    public function forexLevyXml($xml)
    {
        // 3% forex levy new code, we might have to update the component prices of items
        //$isCostCurrencyLevy = $this->forexLevy->getIsCostCurrencyLevy($this->costCurrency);
        $isCostCurrencyLevy = $this->getIsLevyItem();
        //\FB::info("Is Cost Currency Levy $isCostCurrencyLevy");
        if ($isCostCurrencyLevy)
        {
            //\FB::warn("Checkout forex levy IS APPLIED on start new booking for {$this->tourName} cost currency is {$this->costCurrency}");
            // We need to update a few things now
            // Quantity
            $ratesForexJson = $this->cartItem["rates_forex"];
            $ratesForex = json_decode($ratesForexJson);
            //\FB::warn($ratesForex);
            $ratesForex = is_array($ratesForex->rate) ? $ratesForex->rate : array($ratesForex->rate);
            foreach($ratesForex as $rate)
            {
                $rateElement = $xml->addChild("rate");
                //$quantity = (int)$rate->quantity;
                $rate_id = (string)$rate->rate_id;
                $price_pp = (float)$rate->price_pp;
                $price_pp_levy = (float)$rate->price_pp_levy;
                $sale_quantity_rule = (string)$rate->sale_quantity_rule;
                $rateElement->addChild("rate_id", $rate_id);
                $rateElement->addChild("sale_price", $price_pp_levy);
                $rateElement->addChild("sale_quantity_rule", $sale_quantity_rule);
                //\FB::warn("Rate ID $rate_id, Price PP $price_pp and Levied is now $price_pp_levy, sale quant rule $sale_quantity_rule");
            }
        }
        else
        {
            //\FB::info("NO forex levy on start new booking for {$this->tourName}, cost currency is USD {$this->costCurrency}");
        }
        return $xml;
    }



}
