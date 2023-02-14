<?php

namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

class BookingTourcms
{

    protected $tourcmsApi;

    protected $showBookingXml;


    protected $bookingId;

    protected $spreedlyToken;

    protected $total;
    protected $currency;
    protected $altTotal;
    protected $altCurrency;

    protected $postVars;

    protected $startDate;
    protected $endDate;
    protected $title;
    protected $firstname;
    protected $surname;
    protected $email;
    protected $address;
    protected $address2;
    protected $postcode;
    protected $country;
    protected $city;
    protected $state;
    protected $tel;
    protected $description;

    protected $promoValueType;


    public function __construct(TourcmsApi $tourcmsApi)
    {
        $this->tourcmsApi = $tourcmsApi;
    }

    public function setBookingId($val)
    {
        $this->bookingId = (int)$val;
    }

    public function setTotal($val)
    {
        $this->total = $val;
    }

    public function setCurrency($val)
    {
        $this->currency = $val;
    }

    public function setAltTotal($val)
    {
        $this->altTotal = $val;
    }

    public function setAltCurrency($val)
    {
        $this->altCurrency = $val;
    }

    public function setPostVars($val)
    {
        $this->postVars = $val;
    }

    public function getChannelId()
    {
        return (int)$this->showBookingXml->booking->channel_id;
    }

    public function getBookingId()
    {
        return $this->bookingId;
    }

    public function getShowBookingXml()
    {
        return $this->showBookingXml;
    }

    public function getTotal()
    {
        return $this->total;
    }

    public function getCurrency()
    {
        return $this->currency;
    }

    public function getAltTotal()
    {
        return $this->altTotal;
    }

    public function getAltCurrency()
    {
        return $this->altCurrency;
    }

    public function getSpreedlyToken()
    {
        return $this->spreedlyToken;
    }

    public function getStartDate()
    {
        return $this->startDate;
    }

    public function getEndDate()
    {
        return $this->endDate;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function getFirstname()
    {
        return $this->firstname;
    }

    public function getSurname()
    {
        return $this->surname;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function getAddress()
    {
        return $this->address;
    }

    public function getAddress2()
    {
        return $this->address2;
    }

    public function getPostcode()
    {
        return $this->postcode;
    }

    public function getCountry()
    {
        return $this->country;
    }

    public function getCity()
    {
        return $this->city;
    }

    public function getState()
    {
        return $this->state;
    }

    public function getTel()
    {
        return $this->tel;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function getPromoValueType() { return $this->promoValueType; }


    public function setupVars()
    {
        try {
            $this->showBookingXml();
            $this->spreedlyToken = $this->postVars['payment_token'];
            $this->startDate = (string)$this->showBookingXml->booking->start_date;
            $this->endDate = (string)$this->showBookingXml->booking->end_date;

            $this->firstname = $this->postVars["firstname"];
            $this->surname = $this->postVars["surname"];
            $this->email = $this->postVars['email'];
            $this->address = $this->postVars["address1"];
            $this->postcode = $this->postVars["postcode"];
            $this->country = substr($this->postVars["country"], 0, 2);

            $this->title = '';    // on purpose field doesn't exist checkout page
            $this->address2 = '';  // on purpose field doesn't exist checkout page
            $this->city = ''; // on purpose field doesn't exist checkout page
            $this->state = ''; // on purpose field doesn't exist checkout page

            // Just using one phone number not (home & mobile)
            $this->tel = $this->postVars["tel_home"];


            $this->promoValueType = '';
            if(!empty($this->showBookingXml->booking->promo) && !empty($this->showBookingXml->booking->promo->value_type)) {
              $this->promoValueType = (string)$this->showBookingXml->booking->promo->value_type;
            }
            $this->description = $this->generateDescription();
            error_log($this->description);
        } catch (\PublicException $e) {
            // Need to relay to customer somehow TODO
            // throw this back up the chain
            error_log('Booking Tourcms Set up Vars Public Exception ' . $e->getMessage());
            throw new \PublicException($e->getMessage());
        }
    }

    protected function generateDescription()
    {
        $totalDisplay = (string)$this->showBookingXml->booking->sales_revenue_display;
        $total = (float)$this->showBookingXml->booking->sales_revenue;
        $currency = (string)$this->showBookingXml->booking->sale_currency;
        $desc = "GrayLine.com Online Booking \n";
        $desc .= "Total $totalDisplay";
        $desc .= ' (' . $this->getPromoDescription() . ')' . "\n";
        $desc .= $this->getAgentDescription();

        return $desc;
    }

    private function getGlwwCommission($total, $payout)
    {
        $commission = 100 * (1 - ($payout / $total));
        $commission = $this->format($commission);
        $commission = $commission . '%';
        return $commission;
    }

    private function format($n)
    {
        return sprintf('%.2F', $n);
    }

    private function getLicenseePayout()
    {
        $total = 0.00;
        $components = $this->showBookingXml->booking->components->component;
        $totalC = count($components);
        $components = $totalC > 1 ? $components : array($components);
        foreach ($components as $component) {
            $sale_price = (float)$component->cost_price_inc_tax_total_base;
            $total += $sale_price;
        }
        return $total;
    }

    private function getPromoDescription()
    {
        $promo = "No Promo";
        if ($this->showBookingXml->booking->promo) {
            $promo = (float)$this->showBookingXml->booking->promo->value;
            $promoCode = (string)$this->showBookingXml->booking->promo->promo_code;
            if ($this->promoValueType == "FIXED_VALUE") {
                $promo = "Gift Code Applied $promoCode @ -{$promo} USD";
            }
            // otherwise standard percentage promo
            else {
                $promo = "Promo Applied $promoCode @ $promo%";
            }
        }

        return $promo;
    }


    private function getAgentDescription()
    {
        $string = "(No Agent)\n";
        $agentName = htmlentities((string)$this->showBookingXml->booking->agent_name);
        if ($agentName) {
            $agentType = (string)$this->showBookingXml->booking->agent_type;
            $commissionVal = (string)$this->showBookingXml->booking->commission_display;
            $commission = (string)$this->showBookingXml->booking->commission;
            $string = "-$commissionVal $agentName ($agentType) \n";
        }

        return $string;
    }

    protected function showBookingXml()
    {
        try {
            $this->showBookingXml = $this->tourcmsApi->showBooking($this->bookingId);
            // Testing showBookingXml response before we amend component price
            //throw new TourcmsException(json_encode($this->showBookingXml, JSON_UNESCAPED_SLASHES));

        } catch (\PublicException $e) {
            // Need to relay to customer somehow TODO
            // throw this back up the chain
            throw new \PublicException($e->getMessage());
        }
    }


    public function commitBooking()
    {
        try {
            $xml = new \SimpleXMLElement('<booking />');
            $xml->addChild('booking_id', $this->bookingId);
            $this->tourcmsApi->commitNewBooking($xml, $this->bookingId);
        } catch (\PublicException $e) {
            // Need to relay to customer somehow TODO
            // throw this back up the chain
            throw new \PublicException($e->getMessage());
        }
    }


}