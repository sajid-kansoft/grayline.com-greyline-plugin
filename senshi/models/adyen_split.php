<?php

namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

class AdyenSplit
{
    protected $adyenMoney;
    protected $fex;
    protected $fee_commission;
    protected $higher_fee_commission;
    protected $PAL_EARN;
    protected $PAL_ACC;
    protected $PAL_ACC_SUB_MERCHANT_NEW;
    protected $GLWW_ACC;
    protected $PAL_FEE_ACC;
    protected $split_array = [];
    protected $financial_array;
    protected $subtotal_usd = 0; // should be integer amount
    protected $booking_id;
    protected $total;
    protected $alt_total;

    public function __construct(AdyenMoney $adyenMoney, Fex $fex)
    {
        // this works to include library config file ok, settings come from here
        include(GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."libraries/3rdparty/tourcms/config.php");
        $this->PAL_EARN = $pal_earn;
        $this->fee_commission = $fee_commission;
        $this->higher_fee_commission = $higher_fee_commission;
        $this->PAL_FEE_ACC = $pal_fee_acc;
        $this->PAL_ACC = $pal_acc;
        $this->PAL_ACC_SUB_MERCHANT_NEW = $pal_acc_submerchant_new;
        $this->GLWW_ACC = $glww_acc;
        $this->PAL_FEE_ACC = $pal_fee_acc;
        $this->adyenMoney = $adyenMoney;
        $this->fex = $fex;
    }

    public function getFexRate()
    {
        return $this->fex->getExchangeRatePlus3();
    }

    /**
     * @return mixed
     */
    public function getTotal()
    {
        return $this->total;
    }

    /**
     * @return mixed
     */
    public function getAltTotal()
    {
        return $this->alt_total;
    }



    /**
     * @param mixed $booking_id
     */
    public function setBookingId($booking_id): void
    {
        $this->booking_id = $booking_id;
    }


    /**
     * @return mixed
     */
    public function getSplitArray(): array
    {
        return $this->split_array;
    }

    public function generateSplitArray($merchant_reference, $xml, $total, $currency, $fee_estimate_percent, $payment_method, $higher_fee, $alt_total = false, $alt_currency = false)
    {
        $this->total = $this->adyenMoney->integerAmount($total, $currency);
        $this->alt_total = $this->adyenMoney->integerAmount($alt_total, $alt_currency);
        $this->feeSplit($merchant_reference, $total, $currency, $fee_estimate_percent, $payment_method, $higher_fee);
        // we need special logic for a gift code that only partially covers the total booking

        $gift_code = $xml->booking->promo && (string)$xml->booking->promo->value_type === 'FIXED_VALUE' ? true : false;
        if ($gift_code) {
            $this->giftCodeSplit($merchant_reference, $xml, $currency);
        } else {
            $this->componentsSplit($merchant_reference, $xml);
            $this->agentSplit($merchant_reference, $xml);
            // IMPORTANT - Earnings split must be done last as it deals with the remainder
            $this->earningSplit($merchant_reference, $total, $currency, $alt_total, $alt_currency);
        }
    }

    protected function giftCodeSplit($merchant_reference, $xml, $currency)
    {
        $amount = $this->total - $this->subtotal_usd;
        $promo = $xml->booking->promo;
        $desc = '(' . (string)$promo->promo_code . ') ' . (string)$promo->value . ' ' . (string)$promo->value_currency . ', ' . (string)$promo->value_type;
        $desc .= ". *** MANUAL SETTLEMENT REQUIRED";
        $fee_split = array(
            "amount" => array(
                "value" => $amount,
                "currency" => $currency, // subject to change, might need to be converted to USD
            ),
            "type" => "MarketPlace",
            "account" => $this->PAL_FEE_ACC,
            "reference" => "TCMSGIFT|$merchant_reference",
            "description" => "Gift Code Used: $desc"
        );
        $this->split_array[] = $fee_split;
    }

    protected function feeSplit($merchant_reference, $total, $currency, $fee_estimate_percent, $payment_method, $higher_fee)
    {
        $fee_estimate_percent = null; // important that we're not switching this on for now
        $total = $this->adyenMoney->integerAmount($total, $currency);
        // Palisis 2.4 % commission on every transaction, of the USD amount
        // if we have a fee estimate, and that's bigger than the fee, use the fee estimate
        if ($fee_estimate_percent && $fee_estimate_percent > $this->fee_commission) {
            $this->fee_commission = $fee_estimate_percent;
        }
        if ($higher_fee) {
            $this->fee_commission = $this->higher_fee_commission;
        }
        $commission = $this->commission($total);
        $this->subtotal_usd += $commission;
        $fee_split = array(
            "amount" => array(
                "value" => $commission,
                "currency" => $currency, // subject to change, might need to be converted to USD
            ),
            "type" => "Commission",
            "account" => $this->PAL_FEE_ACC,
            "reference" => "FEE|$merchant_reference",
            "description" => "Payment fee @ {$this->fee_commission}% | {$payment_method}"
        );
        $this->split_array[] = $fee_split;
    }

    protected function earningSplit($merchant_reference, $total, $currency, $alt_total, $alt_currency)
    {
        $remainder = $this->total - $this->subtotal_usd;
        if ($remainder < 0) {
            // TODO throw new error!! Can't have earnings be less than 0, FAIL transaction
            $remainder_val = $this->adyenMoney->reverseIntegerAmount($remainder, $currency)." $currency";
            $split = json_encode($this->split_array, JSON_UNESCAPED_SLASHES);
            throw new AdyenPaymentException(
                "Booking ID: {$this->booking_id}. Negative earning warning! Fail transaction. Transaction remainder: $remainder_val\n".$split, 'CRITICAL'
            );
        }
        $pal_earn = ceil(($this->PAL_EARN / 100) * $remainder);
        $glww_earn = $remainder - $pal_earn;
        $glww_earn_desc = 100 - $this->PAL_EARN; // remainder split to GLWW
        $earning_split_pal = array(
            "amount" => array(
                "value" => $pal_earn,
                "currency" => $currency, // subject to change, might need to be converted to USD
            ),
            "type" => "MarketPlace",
            "account" => $this->PAL_ACC,
            "reference" => "PAL_EARN|$merchant_reference",
            "description" => "Palisis earning split @ {$this->PAL_EARN}%"
        );
        $earning_split_glww = array(
            "amount" => array(
                "value" => $glww_earn,
                "currency" => $currency, // subject to change, might need to be converted to USD
            ),
            "type" => "MarketPlace",
            "account" => $this->GLWW_ACC,
            "reference" => "GLWW_EARN|$merchant_reference",
            "description" => "Gray Line earning split @ {$glww_earn_desc}%"
        );
        $this->split_array[] = $earning_split_pal;
        $this->split_array[] = $earning_split_glww;
    }

    protected function agentSplit($merchant_reference, $xml)
    {
        // could be an affiliate type tracking agent, or logged in agent.
        // note we send this for 2 types of agent, direct pay (logged in + affiliate) and affiliate tracking
        $agentType = (string)$xml->booking->agent_type;
//        $loggedIn = $this->agent->getIsAgent() ? '(Agent Logged In)' : '(Tracking)';
        if ($agentType == "AFFILIATE") {
            // We have a proper tracking affiliate agent, need to pass an extra payout node in financial data
            $agentId = (string)$xml->booking->agent_id;
            $agentCode = (string)$xml->booking->agent_code;
            $agentName = (string)$xml->booking->agent_name;
            $reference = "A|$agentId|$agentCode|$merchant_reference";
            // TODO, extract Adyen Agent account ID from TourCMS
            // For now, we use Palisis Account to send agent payments to
            $account_id = $this->PAL_ACC;
            $currency = (string)$xml->booking->commission_currency;
            $com = (float)$xml->booking->commission;
            $commission = $this->adyenMoney->integerAmount($com, $currency);
            $description = "Agent Payment: $agentName (Type $agentType)";
            $agent_split = array(
                "amount" => array(
                    "value" => $commission,
                    "currency" => $currency, // subject to change, might need to be converted to USD
                ),
                "type" => "MarketPlace",
                "reference" => $reference,
                "account" => $account_id,
                "description" => $description
            );
            $this->subtotal_usd += $commission;
            $this->split_array[] = $agent_split;

        } else {
            return false;
        }
    }

    protected function componentsSplit($merchant_reference, $xml)
    {
        $components = $xml->booking->components;
        $components_split = [];
        if ($components) {
            foreach ($components->component as $component) {
                $c_id = (int)$component->component_id;
                $supplier_tour_code = (string)$component->supplier_tour_code;
                $reference = "P|$c_id|$supplier_tour_code|$merchant_reference";
                $cost_total = (float)$component->cost_price_inc_tax_total;
                $cost_currency = (string)$component->cost_currency;
                $licensee_row = $this->getLicenseeRow($supplier_tour_code);
                $account_id = $this->licenseeAdyenAccountId($licensee_row);
                $licensee_name = $this->licenseeName($licensee_row, $supplier_tour_code);
                $net_rate = $cost_currency.' '.$cost_total;
                $exchange_rate = (string)$component->cost_exchange_rate;
                // we need to also work out how much USD we need to cover this payout
                $usd_value = $cost_total / $exchange_rate;
                $usd_value = $this->adyenMoney->integerAmount($usd_value, 'USD');
                $cost_total_int = $this->adyenMoney->integerAmount($cost_total, $cost_currency);
//                echo "RATE $exchange_rate, NET RATE $net_rate, USD VAL $usd_value";
                $this->subtotal_usd += $usd_value;
                $description = $this->genComponentInfo($component);
                $split = array(
                    "amount" => array(
                        "value" => $cost_total_int,
                        "currency" => $cost_currency,
                    ),
                    "type" => "MarketPlace",
                    "account" => $account_id,
                    "reference" => $reference,
                    "description" => "$licensee_name: $description"
                );
                $this->split_array[] = $split;
            }
        }

        return $components_split;
    }

    protected function getLicenseeRow($supplier_tour_code)
    {
        global $wpdb;

        $channel_id = $this->licenseeChannelId($supplier_tour_code);
        if (empty($channel_id)) {
            return false;
        }

        $query =  $wpdb->prepare(
            
            "SELECT channel_name, adyen_account_id from wp_senshi_glww_commission where channel_id = %s",
            
            array(
                $channel_id
            )
        );

        $row = $wpdb->get_row($query, ARRAY_A);

        if (is_array($row)) {
            return $row;
        }
        else {
            return false;
        }
    }

    protected function licenseeAdyenAccountId($row)
    {
        if ($row && isset($row['adyen_account_id'])) {
            $acc_id = $row['adyen_account_id'];
            if (! empty($acc_id)) {
                return $acc_id;
            }
        }
        // now we check for the date, if after 2022-07-01 then use the new account
        $date_now = date('Y-m-d');
        if ($date_now >= '2022-07-01') {
            return $this->PAL_ACC_SUB_MERCHANT_NEW;
        }
        return $this->PAL_ACC;
    }

    protected function licenseeName($row, $supplier_tour_code)
    {
        $channel_name = "Unknown ($supplier_tour_code)";
        if (! $row) {
            return $channel_name;
        }
        if (isset($row['channel_name'])) {
          $channel_name = $row['channel_name'];
        }
        $channel_name = str_replace('"', '\'', $channel_name);
        return $channel_name;
    }
//    protected function licenseeName($supplier_tour_code)
//    {
//        $channel_name = "Unknown ($supplier_tour_code)";
//        $channel_id = $this->licenseeChannelId($supplier_tour_code);
//        if (empty($channel_id)) {
//            return $channel_name;
//        }
//        $db = Loader::db();
//        $row = $db->GetRow('select channel_name from SenshiGlwwCommission where channel_id = ?', $channel_id);
//        if (is_array($row)) {
//            if (isset($row['channel_name'])) {
//                $channel_name = $row['channel_name'];
//            }
//        }
//        $channel_name = str_replace('"', '\'', $channel_name);
//        return $channel_name;
//    }

    protected function licenseeChannelId(string $supplier_tour_code)
    {
        preg_match('/\|([0-9]+)/', $supplier_tour_code, $return);

        return $return[1];
    }

    private function genComponentInfo($component)
    {
        $name = str_replace('"', '\'', (string)$component->component_name);
        $quant = (string)$component->sale_quantity;
        $rate = (string)$component->rate_description;
        $bookingId = $this->booking_id;
        $string = "$name ($quant x $rate)";
        return $string;
    }

    protected function commission($total)
    {
        $commission = intval(ceil(($this->fee_commission / 100) * $total));

        return $commission;
    }

    protected function remainder($total, $commission)
    {
        $remainder = $total - $commission;
        $remainder = (int)$remainder;

        return $remainder;
    }


}
