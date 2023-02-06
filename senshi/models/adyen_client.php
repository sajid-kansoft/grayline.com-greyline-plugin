<?php

namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

use Adyen\Client;
use Adyen\Environment;
use Adyen\AdyenException;

class AdyenClient
{

    protected $client;
    protected $apiKey;
    protected $merchantName;
    protected $live;
    protected $liveEndpointUrlPrefix;
    protected $merchantIdentifier;

    public function __construct()
    {
        // this works to include library config file ok
        include(GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."libraries/3rdparty/tourcms/config.php");
        $this->apiKey = $adyen_api_key;
        $this->merchantName = $adyen_merchant_name;
        $this->merchantIdentifier = $adyen_merchant_identifier;
        $this->live = strtolower($adyen_env) === 'live' ? 1 : 0;
        $this->liveEndpointUrlPrefix = $adyen_live_enpoint_url_prefix;
        $this->client = $this->adyenClient();
    }

    /**
     * @return Client
     */
    public function getClient()
    {
        return $this->client;
    }



    /**
     * @return mixed
     */
    public function getMerchantName()
    {
        return $this->merchantName;
    }

    /**
     * @return mixed
     */
    public function getMerchantIdentifier()
    {
        return $this->merchantIdentifier;
    }


    private function adyenClient() : \Adyen\Client
    {
        $publicError = "Sorry, there was a problem generating the adyen client";
        try {
            $client = new \Adyen\Client();
            // Set your X-API-KEY with the API key from the Customer Area.
            $client->setXApiKey($this->apiKey);
            if ($this->live) {
                $client->setEnvironment(Environment::LIVE, $this->liveEndpointUrlPrefix);
            } else {
                $client->setEnvironment(Environment::TEST);
            }
            return $client;
        } catch (AdyenException $e) {
            $errorMsg = "ERROR, AdyenException Client ";
            $errorMsg .= "{$e->getMessage()}";
            throw new AdyenPaymentException($errorMsg, 'CRITICAL');
        } catch (\Exception $e) {
            $errorMsg = "ERROR, Exception Origin Key ";
            $errorMsg .= "{$e->getMessage()}";
            throw new PublicException($publicError);
        }
    }

}
