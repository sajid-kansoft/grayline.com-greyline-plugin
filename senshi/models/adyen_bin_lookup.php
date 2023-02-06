<?php

namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

use Adyen\AdyenException;
use Adyen\Service\BinLookup;

class AdyenBinLookup
{

    protected $adyenClient;
    protected $adyenMoney;
    protected $logger;
    protected $issuingCountry = 'US'; // default
    protected $paymentMethod;
    protected $fee_currency;
    protected $fee_value;
    protected $fee_result_code;
    protected $fee_percent = 0.0;
    protected $fee_note;
    protected $adyen_fixed_fee = 0.13;

    protected $bin_error_codes = [
        '193' => "Bin Details not found for the given card number",
        '905' => "Payment details are not supported",
    ];

    public function __construct(AdyenClient $adyenClient, AdyenMoney $adyenMoney, Logger $logger)
    {
        $this->adyenClient = $adyenClient;
        $this->adyenMoney = $adyenMoney;
        $this->logger = $logger;
    }

    /**
     * @return mixed
     */
    public function getPaymentMethod()
    {
        return $this->paymentMethod;
    }

    /**
     * @return mixed
     */
    public function getFeeCurrency()
    {
        return $this->fee_currency;
    }

    /**
     * @return mixed
     */
    public function getFeeValue()
    {
        return $this->fee_value;
    }

    /**
     * @return mixed
     */
    public function getFeeResultCode()
    {
        return $this->fee_result_code;
    }

    /**
     * @return float
     */
    public function getFeePercent(): float
    {
        return $this->fee_percent;
    }

    /**
     * @return mixed
     */
    public function getFeeNote()
    {
        return $this->fee_note;
    }

    /**
     * @return mixed
     */
    public function getIssuingCountry()
    {
        return $this->issuingCountry;
    }


    public function higherFee($amount, $currency, $encryptedCardNumber)
    {
        $higher_fee = false;
        // we need to know the payment method, check to see if it's already been set on the object
        if (!$this->paymentMethod) {
            $this->cardBin($amount, $currency, $encryptedCardNumber);
        }
        // could be apple pay so not exact equivalence test
        $pos_amex = strpos(strtolower($this->paymentMethod), 'amex');
        $pos_discover = strpos(strtolower($this->paymentMethod), 'discover');
        $pos_diners = strpos(strtolower($this->paymentMethod), 'diner');
        if ($pos_amex !== false) {
            $higher_fee = true;
        }
        else if ($pos_discover !== false) {
            $higher_fee = true;
        }
        else  if ($pos_diners !== false) {
            $higher_fee = true;
        }
        return $higher_fee;
    }


    public function cardBin($amount, $currency, $encryptedCardNumber)
    {
        try {

            $client = $this->adyenClient->getClient();
            $service = new BinLookup($client);

            $params = array(
                "amount" => array(
                    "value" => $this->adyenMoney->integerAmount($amount, $currency),
                    "currency" => (string)$currency,
                ),
                "assumptions" => array(
                    "assumeLevel3Data" => true,
                    "assume3DSecureAuthenticated" => true,
                ),
                "encryptedCardNumber" => $encryptedCardNumber,
                "merchantAccount" => $this->adyenClient->getMerchantName(),
                "shopperInteraction" => "Ecommerce",
            );

            $result = $service->getCostEstimate($params);

            $this->paymentMethod = isset($result['cardBin']) && isset($result['cardBin']['paymentMethod']) ? $result['cardBin']['paymentMethod'] : null;

            $this->issuingCountry = isset($result['cardBin']) && isset($result['cardBin']['issuingCountry']) ? $result['cardBin']['issuingCountry'] : null;

            return $result;
        } catch (AdyenException $e) {
            // now we have to check the adyen bin error code
            // we don't want to halt if adyen can't return any bin details
            // we do want to halt if adyen says payment type not supported
            if ($e->getErrorType() === 'validation') {
                return true;
            } else {
                if ($e->getErrorType() === 'configuration') {
                    throw new \AdyenPaymentException($e->getMessage());
                } else {
                    return true;
                }
            }
        }


    }

    public function feeEstimate(
        $booking_id,
        $channel_id,
        $total,
        $currency,
        $alt_total,
        $alt_currency,
        $shopper_reference,
        $encrypted_card_number
    ) {
        try {
            if ($alt_currency && $alt_total) {
                $total = $alt_total;
                $currency = $alt_currency;
            }
            $client = $this->adyenClient->getClient();
            $service = new BinLookup($client);

            $params = array(
                "amount" => array(
                    "value" => $this->adyenMoney->integerAmount($total, $currency),
                    "currency" => (string)$currency,
                ),
                "assumptions" => array(
                    "assumeLevel3Data" => true,
                    "assume3DSecureAuthenticated" => true,
                ),
                "encryptedCardNumber" => $encrypted_card_number,
                "merchantAccount" => $this->adyenClient->getMerchantName(),
                "shopperReference" => $shopper_reference,
                "shopperInteraction" => "Ecommerce",
            );

            // Store log
            $string = "Adyen Fee Estimate Params => ".json_encode($params, JSON_UNESCAPED_SLASHES);
            $this->logger->logBookingFlow($booking_id, $channel_id, $string);

            $result = $service->getCostEstimate($params);

            $this->fee_value = isset($result['costEstimateAmount']) && isset($result['costEstimateAmount']['value']) ? $result['costEstimateAmount']['value'] : null;
            $this->fee_currency = isset($result['costEstimateAmount']) && isset($result['costEstimateAmount']['currency']) ? $result['costEstimateAmount']['currency'] : null;
            $this->fee_result_code = isset($result['resultCode']) ? $result['resultCode'] : null;
            $this->paymentMethod = isset($result['cardBin']) && isset($result['cardBin']['paymentMethod']) ? $result['cardBin']['paymentMethod'] : null;

            try {
                if ($this->fee_value) {
                    if ($this->fee_currency !== $currency) {
                        $string = "Adyen Fee Estimate cannot compute percentage as currency returned ($this->fee_currency) does not match currency $currency, can't compute a percentage";
                        $this->logger->logBookingFlow($booking_id, $channel_id, $string);
                        throw new \AdyenPaymentException(
                            $string
                        );

                    }
                    $fee_estimate_percent = $this->fee_value / $total;
                    $fee_estimate_percent = sprintf('%.2F', $fee_estimate_percent);
                    $this->fee_percent = floatval($fee_estimate_percent);
                    $this->fee_percent += $this->adyen_fixed_fee;
                }
            } catch (AdyenPaymentException $e) {
                // we need to add this as a note onto the booking / financial data json
                // DO NOT end the booking process if we have trouble getting a cost estimate
                $this->fee_note = $e->getMessage();
            }

            $string = "Adyen Fee Estimate Result: ".json_encode($result, JSON_UNESCAPED_SLASHES);
            $this->logger->logBookingFlow($booking_id, $channel_id, $string);

            return true;

        } catch (AdyenException $e) {

            // Store log of exception on booking
            $string = "Adyen Fee Estimate AdyenException: ".$e->getMessage();
            $this->logger->logBookingFlow($booking_id, $channel_id, $string);

            // now we have to check the adyen bin error code
            // we don't want to halt payment adyen can't return any bin details
            // we do want to halt if adyen says payment type not supported
            if ($e->getErrorType() === 'validation') {
                return true;
            } else {
                if ($e->getErrorType() === 'configuration') {
                    throw new \AdyenPaymentException($e->getMessage(), 'DEBUG');
                } else {
                    return true;
                }
            }


        } catch (\Exception $e) {
            // Store log of exception on booking
            $string = "Adyen Fee Estimate Exception: ".$e->getMessage();
            $this->logger->logBookingFlow($booking_id, $channel_id, $string);

            throw new \AdyenPaymentException($e->getMessage(), 'DEBUG');
        }
    }

//    public function threeDSAvailability($card_number)
//    {
//        try {
//            $client = $this->adyenClient->getClient();
//
//            $service = new BinLookup($client);
//
//            $params = array(
////                "cardNumber" => (string)$card_number,
//                "merchantAccount" => $this->adyenClient->getMerchantName(),
//                "brands" => 'master'
//            );
//
//            print_r($params);
//
//            $result = $service->get3dsAvailability($params);
//
//            var_dump($params);
//            var_dump($result);
//            die();
//
//            return $result;
//
//
//        } catch (\Exception $e) {
//            throw new AdyenPaymentException($e->getMessage(), 'DEBUG');
//        }
//    }
}
