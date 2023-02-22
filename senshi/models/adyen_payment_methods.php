<?php

namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

use Adyen\AdyenException;

class AdyenPaymentMethods
{

    protected $adyenClient;
    protected $adyenClientKey;
    protected $geoLocate;
    protected $adyenMoney;
    protected $json;
    protected $logger;
    protected $adyenEnabled = true;
    protected $publicError;

    protected $brands = [
        'mc' => 'Mastercard',
        'visa' => 'Visa',
        'amex' => 'Amex',
        'cup' => 'Union Pay',
        'diners' => 'Diners',
        'discover' => 'Discover',
        'jcb' => 'JCB',
        'maestro' => 'Maestro',
    ];


    public function __construct(
        AdyenClient $adyenClient,
        GeoLocate $geoLocate,
        AdyenMoney $adyenMoney,
        AdyenClientKey $adyenClientKey
    ) {
        $this->adyenClient = $adyenClient;
        $this->geoLocate = $geoLocate;
        $this->adyenMoney = $adyenMoney;
        $this->adyenClientKey = $adyenClientKey;
    }

    public function brandNames($currency, $amount, $countrCode = null)
    {
        $avail_methods = $this->adyenPaymentMethods($currency, $amount);

        $brands = '';
        foreach ($avail_methods['paymentMethods'] as $method) {
            if ($method['name'] === 'Credit Card') {
                $cc_types = $method['brands'];
                foreach ($cc_types as $val) {
                    $brands .= isset($this->brands[$val]) ? $this->brands[$val].", " : null;
                }
            }
        }
        $brands = rtrim($brands, ", ");
        return $brands;
    }

    public function paymentMethods($currency, $amount, $countryCode = null)
    {

        $publicError = __('Something went wrong, we could not retrieve the payment methods. Please try again', 'wp_grayline_tourcms_theme');
        try {

            // we might be passing in country code for AliPay
            $result = $this->adyenPaymentMethods($currency, $amount, $countryCode);

            $this->json['error'] = 0;
            $this->json['response'] = "Success";
            $this->json['client_key'] = $this->adyenClientKey->getClientKey();
            $this->json['payment_methods'] = $result;
            $cc_types = null;
            foreach ($result['paymentMethods'] as $method) {
                if ($method['name'] === 'Credit Card') {
                    $cc_types = $method['brands'];
                }
            }
            $this->json['cc_types'] = $cc_types;

            return $this->json;

        } catch (\AdyenPaymentException $e) {
            throw new \Exception($publicError);
        }
    }

    protected function adyenPaymentMethods($currency, $amount, $countryCode = null)
    {
        $publicError = "Sorry, there was a problem generating the payment methods";
        try {
            if ($currency && $amount) {
                $client = $this->adyenClient->getClient();
                $service = new \Adyen\Service\Checkout($client);

                if (!$countryCode) {
                    $countryCode = $this->geoLocate->getCountryCode();
                }

                $params = array(
                    "merchantAccount" => $this->adyenClient->getMerchantName(),
                    "countryCode" => $countryCode,
                    "amount" => array(
                        "currency" => $currency,
                        "value" => $this->adyenMoney->integerAmount($amount, $currency),
                    ),
                    "channel" => "Web",
                );

                
                $result = $service->paymentMethods($params);
                return $result;
            } else {

                $errorMsg = "ADYEN ERROR, Payment Methods called, currency or amount not set. Currency $currency, amount $amount, countryCode $countryCode";
                throw new \AdyenPaymentException($errorMsg);
            }

        } catch (\AdyenPaymentException $e) {
            $errorMsg = $e->getMessage();

            throw new \Exception($publicError);
        } catch (AdyenException $e) {

            $errorMsg = "ADYEN ERROR, AdyenException Payment Methods ";
            $errorMsg .= "{$e->getErrorType()}, {$e->getPspReference()}, {$e->getStatus()}, {$e->getCode()}, {$e->getFile()}, {$e->getLine()}, {$e->getMessage()}";
             // print_r($errorMsg);
            throw new \AdyenPaymentException($errorMsg, 'CRITICAL');

        } catch (\Exception $e) {
            print_r('string3');
            $errorMsg = "ADYEN ERROR, Exception Payment Methods ";
            $errorMsg .= "{$e->getCode()}, {$e->getFile()}, {$e->getLine()}, {$e->getMessage()}";
            print_r($errorMsg);die;
            throw new \AdyenPaymentException($errorMsg, 'CRITICAL');
        }
    }

}
