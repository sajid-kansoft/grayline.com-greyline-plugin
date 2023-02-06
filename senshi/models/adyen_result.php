<?php

namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

class AdyenResult
{
    protected $adyenMoney;
    protected $additional_data;
    protected $authorised_amount;
    protected $psp_reference;
    protected $card_type;
    protected $card_country;
    protected $cardholder_name;
    protected $payment_method_number;
    protected $expiry;
    protected $billing_id_type;
    protected $billing_id;
    protected $result_code;
    protected $refusal_reason;

    public function __construct(AdyenMoney $adyenMoney)
    {
        $this->adyenMoney = $adyenMoney;
    }

    public function build($result)
    {
        $this->setAuthorisedAmount($result);
        $this->setBillingId($result);
        $this->setBillingIdType();
        $this->setCardType($result);
        $this->setCardCountry($result);
        $this->setCardholderName($result);
        $this->setExpiry($result);
        $this->setPaymentMethodNumber($result);
        $this->setPspReference($result);
        $this->setResultCode($result);
        $this->setRefusalReason($result);
    }

    /**
     * @return mixed
     */
    public function getResultCode()
    {
        return $this->result_code;
    }

    /**
     * @return mixed
     */
    public function getRefusalReason()
    {
        return $this->refusal_reason;
    }

    /**
     * @return mixed
     */
    public function getAuthorisedAmount()
    {
        return $this->authorised_amount;
    }

    /**
     * @return mixed
     */
    public function getPspReference()
    {
        return $this->psp_reference;
    }

    /**
     * @return mixed
     */
    public function getCardType()
    {
        return $this->card_type;
    }

    /**
     * @return mixed
     */
    public function getCardCountry()
    {
        return $this->card_country;
    }

    /**
     * @return mixed
     */
    public function getCardholderName()
    {
        return $this->cardholder_name;
    }

    /**
     * @return mixed
     */
    public function getPaymentMethodNumber()
    {
        return $this->payment_method_number;
    }

    /**
     * @return mixed
     */
    public function getExpiry()
    {
        return $this->expiry;
    }

    /**
     * @return mixed
     */
    public function getBillingIdType()
    {
        return $this->billing_id_type;
    }

    /**
     * @return mixed
     */
    public function getBillingId()
    {
        return $this->billing_id;
    }



    protected function setPspReference($result)
    {
        $this->psp_reference = isset($result['pspReference']) ? $result['pspReference'] : 'PSPUnknown';
    }

    protected function setAuthorisedAmount($result)
    {
        $string = '';
        if (isset($result['additionalData']['authorisedAmountValue']) && isset($result['additionalData']['authorisedAmountCurrency'])) {
            $currency = $result['additionalData']['authorisedAmountCurrency'];
            $amount = $this->adyenMoney->reverseIntegerAmount(
                $result['additionalData']['authorisedAmountValue'],
                $currency
            );
            $string = "Authorised ($currency $amount) ";
        }

        $this->authorised_amount = $string;
    }

    protected function setCardType($result)
    {
        $apple_pay = isset($result['apple_pay']) && $result['apple_pay'] === 1 ? true : false;
        $card_type_method = isset($result['additionalData']['cardPaymentMethod']) ? $result['additionalData']['cardPaymentMethod'] : null;
        if ($apple_pay) {
            $card_type = $card_type_method ? 'applepay_'.$card_type_method : 'applepay';
        } else {
            $card_type = $card_type_method;
        }

        $this->card_type = $card_type;
    }

    protected function setCardCountry($result)
    {
        $country = isset($result['additionalData']['cardIssuingCountry']) ? $result['additionalData']['cardIssuingCountry'] : null;

        $this->card_country = $country;
    }

    protected function setCardholderName($result)
    {
        $val = isset($result['additionalData']['cardHolderName']) ? $result['additionalData']['cardHolderName'] : '';

        $this->cardholder_name = $val;
    }

    protected function setPaymentMethodNumber($result)
    {
        $val = isset($result['additionalData']['cardSummary']) ? $result['additionalData']['cardSummary'] : '';

        $this->payment_method_number = $val;
    }

    protected function setExpiry($result)
    {
        $val = isset($result['additionalData']['expiryDate']) ? $result['additionalData']['expiryDate'] : '';
        // val is returned in format 03/2030
        $pieces = explode('/', $val);
        if (is_array($pieces)) {
            $month = $pieces[0];
            $year = $pieces[1];
            $date = new \DateTime();
            $date->setDate($year, $month, 1);
            $val = $date->format('Y-m');
        }

        $this->expiry = $val;
    }

    protected function setBillingIdType()
    {
        $billing_id_prefix = 'PALMP';

        $this->billing_id_type = $billing_id_prefix;
    }

    protected function setBillingId($result)
    {
        $shopper_reference = isset($result['additionalData']['recurring.shopperReference']) ? $result['additionalData']['recurring.shopperReference'] : 'no_shopper_reference';
        $card_token = isset($result['additionalData']['recurring.recurringDetailReference']) ? $result['additionalData']['recurring.recurringDetailReference'] : 'no_token_returned';
        $billing_id = "SHOPPERREF_$shopper_reference|TOKEN_$card_token";

        $this->billing_id = $billing_id;
    }

    protected function setResultCode($result)
    {
        $val = isset($result['resultCode']) ? $result['resultCode'] : '';
        $this->result_code = $val;
    }

    protected function setRefusalReason($result)
    {
        $val = isset($result['refusalReason']) ? $result['refusalReason'] : 'Reason Unknown';
        $this->refusal_reason = $val;
    }


}
