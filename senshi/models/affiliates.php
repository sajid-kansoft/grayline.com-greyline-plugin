<?php

namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

class Affiliates
{

    protected $bookingTourcms;
    protected $agent;
    protected $tmtAffiliateIdConstant = "grayline_affiliate_";
    protected $affiliates;
    protected $affiliateAgent;
    protected $costPrice;


    public function __construct(BookingTourcms $bookingTourcms, Agent $agent, CostPrice $costPrice)
    {
        $this->bookingTourcms = $bookingTourcms;
        $this->agent = $agent;
        $this->costPrice = $costPrice;
    }

    public function getAllAffiliates()
    {
        $all = $this->affiliates;
        if ($this->affiliateAgent)
            $all[] = $this->affiliateAgent;
        return $all;
    }


    public function getAffiliatesJson()
    {
        $all = $this->getAllAffiliates();
        $json = json_encode($all, JSON_UNESCAPED_SLASHES);
        return $json;
    }

    public function getAffiliates() { return $this->affiliates; }

    public function generateAffiliates()
    {
        $affiliates = array();

        $xml = $this->bookingTourcms->getShowBookingXml();
        $bookingId = (int)$xml->booking->booking_id;
        $components = $xml->booking->components->component;
        $totalC = count($components);
        $components = $totalC > 1 ? $components : array($components);

        foreach ($components as $component)
        {
            //\FB::log($component);
            //$cP = new CostPrice($xml, $component, $bookingId, $comObj);
            $cP = $this->costPrice;
            $cP->setXml($xml);
            $cP->setBookingId($bookingId);
            $cP->setComponent($component);
            $cP->setup();
            $cP->calculatePrice();

            $cId = $cP->getComponentId();
            $costCurrency = $cP->getCostCurrency();
            $id = $cP->getLicenseeId();
            $costPriceTotal = $cP->getCostPriceTotal();
            $description = $cP->getComponentNote();

            //\FB::log("Component Description");
            //\FB::log($description);

            /*
            $name = $cP->getName();
            // Adding on rate name here so TMT data is more complete (we maintain TCMS component breakdown)
            $description = $name . " " . "(" . $cP->getSaleQuantity() . " x "  . $cP->getRateName() . ")";
            $id = $cP->getLicenseeId();
            // TourCMS requires price per person, TMT price total. Rounding errors occur here after commission
            // So, we use the cost price & multiply by
            $costPriceTotal = $cP->getCostPriceTotal();
            $costPrice = $cP->getCostPrice();
            $costCurrency = $cP->getCostCurrency();
            $cId = $cP->getComponentId();
            $costPriceOriginal = $cP->getCostPriceOriginal();
            $description .= " | ORIGINAL PRICE $costPriceOriginal";


            $description .= $cP->getPromoDescription();
            // Adding some more data to Description - want to do this for affiliate agent bookings too
            $agentId = (string)$xml->booking->agent_id;
            if ($agentId) {
                $agentName = (string)$xml->booking->agent_name;
                $agentCode = (string)$xml->booking->agent_code;
                $agentType = (string)$xml->booking->agent_type;
                $agentCommission = (string)$cP->getAgentCommission() . "%";
                // Adding on a description too
                $description .= " | AGENT TRANSACTION: (Name: $agentName, Commission: $agentCommission, Type: $agentType, ID: $agentId, Code: $agentCode)";
            }
            $glCom = (string)$cP->getGlwwCommissionPercent() . "%";
            $description .= " | GLWW: (Commission: $glCom)";
            //\FB::log("Component $name Price($costPriceTotal) Currency($costCurrency) Licensee ID ($id)");
            */

            if($costPriceTotal < 0)
              $costPriceTotal = $costPriceTotal * -1;

            // Cost might be 0 - e.g. infants etc
            if ($costPriceTotal > 0)
            {
                $affiliate = array('affiliate_id' => $id, 'total' => $costPriceTotal, 'currency' => $costCurrency, 'description' => $description, 'cId' => $cId);
                $affiliates[] = $affiliate;
                //\FB::log("Affiliate: " . implode(",", $affiliate));
            }
        }

        $this->affiliates = $affiliates;
        $this->affiliateAgent($xml);
    }


    private function affiliateAgent($xml)
    {
        // This is for a tripadvisor type affiliate agent
        $agentType = (string)$xml->booking->agent_type;
        $isAgent = $this->agent->getIsAgent();
        // Affiliates could be logged in, OR following a tracking link
        // We only send TMT an "AFFILIATE" node if it's NOT a logged in agent, i.e. only for a tracking link
        $loggedIn = $isAgent ? '(Agent Logged In)' : '';
        if ($agentType == "AFFILIATE")
        {
            //\FB::warn("We have a proper tracking affiliate agent, need to pass some extra data to TMT");
            $agentId = (string)$xml->booking->agent_id;
            $agentName = (string)$xml->booking->agent_name;
            $agentCode = (string)$xml->booking->agent_code;
            $id = $this->tmtAffiliateIdConstant . $agentId;
            $commission = (string)$xml->booking->commission;
            // Adding on a description too
            $description = "Affiliate Agent Payment: $agentName (ID $agentId) (Agent Code $agentCode) (Agent Type $agentType) $loggedIn";
            $currency = (string)$xml->booking->commission_currency;
            $affiliate = array('affiliate_id' => $id, 'total' => $commission, 'currency' => $currency, 'description' => $description);
            $this->affiliateAgent = $affiliate;
        }
    }



}
