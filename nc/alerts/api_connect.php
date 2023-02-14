<?php

class CheckApiConnect {

    protected $responseCode = 200;
    protected $isTest;
    protected $isWarn;
    protected $message = "TOURCMS_OK";
    protected $dummyResponse;
    protected $response;
    protected $tourCms;

    public function __construct()
    {
        include(GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."libraries/3rdparty/tourcms/config.php");
        $warn = isset($_GET['warn']) ? true : false;
        $test = isset($_GET['test']) ? true : false;
        $this->isWarn = $warn;
        $this->isTest = $test;
        $this->tourCms = $tourcms;
    }

    public function checkLimit()
    {
        // Query the TourCMS API
        $result = $this->tourCms->api_rate_limit_status(CHANNEL_ID);
        // change, testing only once to catch issues here faster & make alert more sensitive
        /*
        // test 3 times
        if (!$result) {
            $result = $this->tourCms->api_rate_limit_status(CHANNEL_ID);
        }

        if (!$result) {
            $result = $this->tourCms->api_rate_limit_status(CHANNEL_ID);
        }
        */
        if ($result) {
            // do nothing
        }
        else {
            $this->responseCode = 503;
            $errorMsg = "TOURCMS_NOTOK API Rate Limit Method. Cannot connect to API.\n ";
            $errorMsg .= "Result: " .  json_encode($result, JSON_UNESCAPED_SLASHES);
            throw new \Exception($errorMsg);
        }
    }

    public function getResponseCode()
    {
        return $this->responseCode;
    }

    public function getMessage()
    {
        return $this->message;
    }

}
$checker = new CheckApiConnect();

try {
    $checker->checkLimit();
    $message = $checker->getMessage();
}
catch (\Exception $e) {
    $message = $e->getMessage();
    error_log($message);
}
http_response_code($checker->getResponseCode());
header('content-type: text/html; charset=utf-8');
echo $message;