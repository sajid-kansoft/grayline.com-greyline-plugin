<?php

namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

class TourcmsWrapper
{
    protected $tourcms;
    protected $channel_id;
    protected $account_id;
    protected $positive_errors = ['OK', 'BOOKING ALREADY COMMITTED'];
    protected $non_critical_errors = [
        'EXPIRED KEY (LASTS 1 HOUR) - CHECK AVAILABILITY AGAIN',
        'MISSING OR INCORRECT KEY',
        'PREVIOUSLY CANCELLED'
    ];

    public function __construct()
    {
        include(GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."libraries/3rdparty/tourcms/config.php");
        $this->tourcms = $tourcms;
        $this->channel_id = $channel_id;
        $this->account_id = $account_id;
    }

    public function getChannelId()
    {
        return $this->channel_id;
    }

    public function getAccountId()
    {
        return $this->account_id;
    }

    /**
     * Magic method that will try to pass any method calls to the TourCMS library,
     * throwing an TourcmsException if there is an error, and otherwise just returning the response
     */
    public function __call($name, $arguments)
    {
        /**
         * If the response from TourCMS doesn't have an error node then something weird has happened
         * we try to connect again to the api, 3 times, in case of connection drop off, then fail
         */
        $n = 0;

        do {
            $n++;
            $response = call_user_func_array([$this->tourcms, $name], $arguments);

            if (isset($response->error)) {
                $error = strtoupper((string)$response->error);
                if (in_array($error, $this->positive_errors)) {
                    return $response;
                } else {
                    $errorMsg = 'TourCMS API Error: '.$error.' | '.(string)$response->request;
                    $flag = in_array($error, $this->non_critical_errors) ? 'DEFAULT' : 'CRITICAL';
                    throw new \TourcmsException($errorMsg, $flag);
                }
            }
        } while (!$response && $n < 3);

        $errorMsg = "Unexpected response from TourCMS attempt $n: function: $name, arguments: ".json_encode(
                $arguments,
                JSON_UNESCAPED_SLASHES
            ).', response: '.(string)$response;
        throw new \TourcmsException($errorMsg, 'CRITICAL');
    }
}
