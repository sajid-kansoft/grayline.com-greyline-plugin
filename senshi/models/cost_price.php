<?php

namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

class CostPrice
{

    //private $api;
    private $showBookingXml;
    private $component;
    private $bookingId;
    private $componentId;
    private $licenseeId;

    // Commission Object is variable per licensee, new functionality
    private $comObj;

    // TMT needs the overall cost price, TCMS needs individual cost price as they multiply by quantity internally
    private $costPriceOriginal;
    private $costPrice;
    private $costPriceTotal;
    private $salePriceTotal;
    private $costCurrency;
    private $name;
    private $agentCommissionRetail = 0.10;
    private $quantity = 1;
    private $tmtIdConstant = "grayline_";
    private $licenseeChannelId;
    private $glwwCommission;
    private $glwwCommissionVal;
    private $agentCommissionVal;
    private $promoTotal;
    private $componentNote = '';
    private $commissionDef = 0; // changing this to zero for net rates


    private $agentCommission;

    public function __construct()
    {
        ////\FB::info("Cost price object initiated");
//        $this->comObj = $comObj;
    }

    public function getComponentNote() { return $this->componentNote; }

    public function setXml($val) { $this->showBookingXml = $val; }
    public function setComponent($val) { $this->component = $val; }
    public function setBookingId($val) { $this->bookingId = (int)$val; }

    public function setup()
    {
        $this->componentId = $this->genComponentId();
        $this->licenseeId = $this->genLicenseeId();
        //$this->glwwCommission = $this->genGlwwCommission();
        $this->costCurrency = $this->genCostCurrency();
        $this->name = $this->genName();
    }

    public function getLicenseeId() { return $this->licenseeId; }
    public function getLicenseeChannelId() { return $this->licenseeChannelId; }
    public function getComponentId() { return $this->componentId; }
    public function getCostCurrency() { return $this->costCurrency; }
    public function getCostPrice() { return $this->costPrice; }
    public function getCostPriceOriginal() { return $this->costPriceOriginal; }
    public function getCostPriceTotal() { return $this->costPriceTotal; }
    public function getSalePriceTotal() { return $this->salePriceTotal; }
    public function getQuantity() { return $this->quantity; }

    public function getName() { return $this->name; }


//    private function genGlwwCommission()
//    {
////        $com = $this->comObj->getCommission($this->licenseeChannelId);
//        $com = $this->commissionDef;
//        $commission = floatval($com / 100);
//        ////\FB::error("GLWW VARAIBLE LICENSEE COMMISION IS: $commission");
//        return $commission;
//
//    }

    private function genName()
    {
        $name = (string)$this->component->component_name;
        ////\FB::log("Component name: $name");
        return $name;

    }

    public function getSaleQuantity()
    {
        return (string)$this->component->sale_quantity;
    }

    public function getQuantityRule()
    {
        return (string)$this->component->cost_quantity_rule;
    }

    public function getRateName()
    {
        return (string)$this->component->rate_description;
    }

    private function genComponentId()
    {
        $id = (string)$this->component->component_id;
        ////\FB::log("component id is $id");
        return $id;
    }


    private function genCostCurrency()
    {
        $curr = (string)$this->component->cost_currency;
        ////\FB::log("cost currency is $curr");
        return $curr;
    }

    public function calculatePrice()
    {
        // Promo code first
        //  Then agent commission
        // Then variable GLWW commission
        // We are now going to use the cost price total including tax and work all prices based on this to cover tax which isn't included in cost price
        $this->costPriceTotal = (float)$this->component->cost_price_inc_tax_total;
        $this->costPriceOriginal = $this->costPriceTotal;
        // Using sale price total for info only
        $this->salePriceTotal = (float)$this->component->sale_price_inc_tax_total;
        // TourCMS requires price per person, TMT price total. Rounding errors occur here after commission
        // So, we use the cost price & multiply by
        $rule = $this->component->cost_quantity_rule;
        if ($rule == "PERSON")
        {
            ////\FB::log("Cost price is per item");
            $quantity = (float)$this->component->cost_quantity;
            $this->quantity = $quantity;
            ////\FB::log("Cost price is per PERSON, quantity is $quantity");
        }
        else if ($rule == "GROUP")
        {
            $this->quantity = (float)1;
            ////\FB::log("Cost price is per GROUP, quanity is ONE " . $this->quantity);
        }
        ////\FB::info("Cost Price total ORGINAL = " . $this->costPriceTotal);
        ////\FB::info("Sale Price total ORGINAL = " . $this->salePriceTotal);
        //$this->applyPromoCode();   // NET RATES REMOVE COST PRICE REDUCTION
        //$this->applyAgentLogin(); // NET RATES REMOVE COST PRICE REDUCTION
        //$this->applyGlwwCommission(); // NET RATES REMOVE COST PRICE REDUCTION
        $this->finalisePrices();
        $this->genNote();
    }

    private function applyAgentLogin()
    {
        $agentType = (string)$this->showBookingXml->booking->agent_type;
        ////\FB::log("Agent type is $agentType");
        $agentLogin = false;
        ////\FB::info("AGENT LOGIN SESSION  " . $_SESSION["agent_name"]);
        if(!empty($_COOKIE["tcms_ag_bk"]) && !empty($_SESSION["agent_name"]))
        {
            $agentLogin = true;
        }
        ////\FB::info("Agent login true / false? $agentLogin");
        if ($agentType == "RETAIL")
        {
            ////\FB::info("AGENT TYPE: RETAIL, 10% OFF");
            ////\FB::log("we are deducting retail agent commission off this component {$this->agentCommissionRetail}");
            $comTotal = $this->calculateCommission($this->agentCommissionRetail, $agentType);
            $this->agentCommission = $this->agentCommissionRetail * 100;
            $this->agentCommissionVal = $comTotal;
        }
        if ($agentType == "AFFILIATE")
        {
            ////\FB::info("AGENT TYPE: AFFILIATE, CALCULATE % OFF");
            $commission = (float)$this->showBookingXml->booking->commission;
            if ($commission > 0)
            {
                $salesRevenue = (float)$this->showBookingXml->booking->sales_revenue;
                $affiliateCommission = ($commission / $salesRevenue);
                ////\FB::log("AFFILIATE COMMISSION IS $affiliateCommission");
                $comTotal = $this->calculateCommission($affiliateCommission, $agentType);
                $this->agentCommission = round($affiliateCommission * 100);
                $this->agentCommissionVal = $comTotal;
            }
            else
            {
                ////\FB::error("commission is 0 for agent type $agentType");
            }
        }
        // If the agent type is Affiliate Track, and they get commission, then they are flagged as AFFILIATE
        // we don't need to do anything for TRACK.
        if ($agentType == "TRACK")
        {
            ////\FB::error("AGENT TYPE:  TRACK, NOT DOING ANYTHING ");
            // Todo
        }
        // Trusted travel agent - pays zero at checkout, GLWW don't use this type of agent
        if ($agentType == "TRUSTED")
        {
            ////\FB::error("AGENT TYPE:  TRUSTED, NOT DOING ANYTHING");
            // Todo
        }
    }

    private function calculateCommission($commission, $agentType=null)
    {
        ////\FB::info("CALCULATING COMMISSION ($commission) for AGENT TYPE ($agentType)");
        $multiplier = (float)(1 - $commission);
        $origCostPrice = $this->costPriceTotal;
        $costPriceTotal = $multiplier * $this->costPriceTotal;
        //$salePriceTotal = $multiplier * $this->salePriceTotal;
        $this->costPriceTotal = $costPriceTotal;
        //$this->salePriceTotal = $salePriceTotal;
        ////\FB::log("costPrice after agent commission: $costPriceTotal");
        $commissionTotal = $origCostPrice - $costPriceTotal;
        $commissionTotal = sprintf('%.2F', $commissionTotal);
        return $commissionTotal;

    }

    public function getAgentCommission()
    {
        return $this->agentCommission;
    }

    public function getGlwwCommissionPercent()
    {
        return $this->glwwCommission * 100;
    }


    private function applyGlwwCommission()
    {
        $comTotal = $this->calculateCommission($this->glwwCommission, "GLWW");
        $this->glwwCommissionVal = $comTotal;
        /*
        $multiplier = (float)(1-$this->glwwCommission);
        $costPrice = $multiplier * $this->costPrice;
        $salePriceTotal = $multiplier * $this->salePriceTotal;
        */
    }

    private function genNote()
    {

        // Adding on rate name here so TMT data is more complete (we maintain TCMS component breakdown)
        $name = str_replace('"', '\'', $this->getName());
        $quant = $this->getSaleQuantity();
        $rate = $this->getRateName();
        //$promo = $this->getPromoDescription();
        //$agent = $this->getAgentDescription();
        //$glww = $this->getGlwwDescription();
        $origPrice = $this->getCostPriceOriginal();
        //$costPrice = $this->getCostPriceTotal();
        $bookingId = $this->bookingId;
        $currency = (string)$this->costCurrency;

        $string = "Gray Line Booking $bookingId: \n";
        $string .= "$origPrice $currency $name ($quant x $rate) \n";
//        $string .= $promo;
//        $string .= $agent;
//        $string .= $glww;
//        $string .= "= $costPrice $currency";

        $this->componentNote = $string;
    }

    private function getPromoDescription()
    {
        $string = '';
        $promoApply = (string)$this->component->promo_apply;
        if ($promoApply == "1" && $this->showBookingXml->booking->promo->value_type != "FIXED_VALUE") {
            $currency = (string)$this->costCurrency;
            $promo = (float)$this->showBookingXml->booking->promo->value;
            $promoCode = (string)$this->showBookingXml->booking->promo->promo_code;
            $promoTotal = $this->promoTotal;
            $string = "-$promoTotal $currency PROMO $promoCode @ $promo% \n";
        }
        return $string;
    }


    private function getAgentDescription()
    {
        $string = '';
        $agentName = (string)$this->showBookingXml->booking->agent_name;
        if ($agentName) {
            $commissionVal = $this->agentCommissionVal;
            $commission = $this->agentCommission;
            $currency = (string)$this->costCurrency;
            $string = "-$commissionVal $currency $agentName @ $commission% \n";
        }
        return $string;
    }



    private function getGlwwDescription()
    {
        $commissionVal = $this->glwwCommissionVal;
        $commission = $this->getGlwwCommissionPercent();
        $currency = (string)$this->costCurrency;
        $string = "-$commissionVal $currency GLWW @ $commission% \n";
        return $string;
    }

    private function applyTax()
    {
        $taxInclusive = (int)$this->component->cost_tax_inclusive;
    }


    private function finalisePrices()
    {
        //$this->salePriceTotal = sprintf('%.2F', $this->salePriceTotal);
        $this->costPriceTotal = sprintf('%.2F', $this->costPriceTotal);
        //$costPriceTotal = $multiplier * $this->costPriceTotal;
        // We cannot use the costPriceTotal value post-commission to send to TMT due to rounding discrepancies
        // TCMS requires costPrice per person updated post-commission, they then multiply that by quantity.
        // We follow this process so that the figures marry up
        // We are now changing this for tax reasons, use the cost price total for all calculations, then update TCMS with this divided by quant
        $costPrice = $this->costPriceTotal / $this->quantity;
        $this->costPrice = sprintf('%.2F', $costPrice);
        ////\FB::log("Final Cost Price Total = " . $this->costPriceTotal);
    }


    private function applyPromoCode()
    {
        $promoApply = (string)$this->component->promo_apply;
        ////\FB::log("promoApply? $promoApply - " . $this->name);
        // We apply the promo to both the cost price and the cost price total
        // If we are using the update method, we need to get the individual cost price - tourcms multiplies by number of people
        // if not we get the overall cost price
        if ($promoApply === "1" && $this->showBookingXml->booking->promo->value_type != "FIXED_VALUE")
        {
            $costPriceOrig = $this->costPriceTotal;
            $this->costPriceTotal = $this->promoCostPrice($this->costPriceTotal);
            $promoTotal = $costPriceOrig - $this->costPriceTotal;
            $this->promoTotal =  sprintf('%.2F', $promoTotal);
        }
    }

    public function getPromoApply()
    {
        $promoApply = (string)$this->component->promo_apply;
        return $promoApply == "1" ? true : false;
    }

    public function getPromoVal()
    {
        $promoVal = (float)$this->showBookingXml->booking->promo->value;
        return $promoVal;
    }



    private function promoCostPrice($val)
    {
        $xml = $this->showBookingXml;
        // promos are always percentage
        $promo = $xml->booking->promo->promo_code;
        if ($promo)
        {
            $promoVal = (float)$xml->booking->promo->value;
            $promoPercentage = 1 - ($promoVal/100);
            $costPricePromo = sprintf('%.2F', ($promoPercentage * $val));
            ////\FB::log("promo percentage is $promoPercentage, Val: $val, Cost price Promo: $costPricePromo");
        }
        else
        {
            $costPricePromo = $val;
        }
        return $costPricePromo;
    }

    private function genLicenseeId()
    {
        $licenseeChannelId = $this->getChannelId();
        $this->licenseeChannelId = $licenseeChannelId;
        $val = $this->tmtIdConstant . $this->licenseeChannelId;
        return $val;
    }


    private function getChannelId()
    {
        $supplier_tour_code = (string)$this->component->supplier_tour_code;
        preg_match('/\|([0-9]+)/', $supplier_tour_code, $return);
        return $return[1];
    }



}
