<?php

namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

class AdyenClientKey
{


    protected $clientKey;

    public function __construct()
    {
        // this works to include library config file ok
        include(GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."libraries/3rdparty/tourcms/config.php");
    }


    public function getClientKey()
    {
        $publicError = "Sorry, there was a problem getting the adyen client key";
        try {
            if (! defined('ADYEN_CLIENT_KEY')) {
                throw new SenshiException('Adyen client key not defined', 'CRITICAL');
            }
            $this->clientKey = ADYEN_CLIENT_KEY;

            return $this->clientKey;
        } catch (Exception $e) {
            return $publicError;
        }
    }


}
