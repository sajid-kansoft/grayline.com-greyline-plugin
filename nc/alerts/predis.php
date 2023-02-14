<?php

class WebhookException extends Exception {

    protected $log_file = 'log_webhook_exceptions.txt';

    public function __construct( $message, $flag=null )
    {
        include(GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."libraries/3rdparty/tourcms/config.php");

        $folder = $cache_location . 'logs/';
        $file = $folder . $this->log_file;
        // if not a directory, then create it
        if (!is_dir($folder)) {
            mkdir($folder, 0750, true);
        }
        $date = new DateTime("now", new DateTimeZone('UTC'));
        $msg = array('datetime' => $date->format('Y-m-d H:i:sP'));
        $msg[] = $message;
        $msg = json_encode($msg, JSON_UNESCAPED_SLASHES);
        // open the log file for appending
        $fp = fopen($file, (file_exists($file)) ? 'a' : 'w');
        if ($fp) {
            $log_msg = "$msg\n";
            fwrite($fp, $log_msg);
            fclose($fp);
        }
    }
}
class CheckPredis {

    protected $responseCode = 200;
    protected $failureCode = 503; // Service unavailable
    protected $message;
    protected $response;
    protected $testFakeError = false;
    // Munich Neuschwanstein, Linderhof Royal Castle & Oberammer Tour
    protected $tourId = 13;
    protected $tourIds = array(13, 168, 1814);

    public function __construct()
    {
    }

    public function checkAvailability()
    {
        $testFakeError = isset($_GET['test']) ? htmlspecialchars($_GET['test']) : false;
        $testFakeError = $testFakeError === 'true' ? true : false;
        $tourId = isset($_GET['id']) ? htmlspecialchars($_GET['id']) : false;
        $tourId = $tourId ? (int)$tourId : $this->tourId;
        if ($testFakeError) {
            $rates = "r1,r2,r3&r1=100&r2=0&r3=0"; // 100 adult
        }
        else {
            $rates = "r1,r2,r3&r1=1&r2=0&r3=0"; // 1 adult
        }
        $date = new DateTime();
        $date->add(new DateInterval('P3M'));
        $futureDate = $date->format('Y-m-d');
        // exclude xmas and new year
        $xmasDate = date('Y') . '-12-25';
        $newYear = (date('Y') + 1) . '-01-01';
        if ($futureDate == $xmasDate || $futureDate == $newYear) {
            $date->add(new DateInterval('P3D'));
            $futureDate = $date->format('Y-m-d');
        }
        $url = "https://www.grayline.com/nc/alerts/check_availability_alert.php?tour_id={$tourId}&channel_id=12130&date={$futureDate}&rates={$rates}&cost_currency=EUR";
        //$url = "http://localhost/glww/nc/alerts/check_availability_alert.php?tour_id={$tourId}&channel_id=12130&date={$futureDate}&rates={$rates}&cost_currency=EUR";
        // Query the TourCMS API
        try {
            $json = $this->curlNC($url);
            $data = json_decode($json, true);
            $successMessage = "SUCCESS: Components returned";
            if ($data['error'] == 0 || $data['error'] == 2) {
                $this->message = $successMessage;
            }
            else if ($data['error'] == 1) {
                throw new WebhookException($data, "DEFAULT");
            }
            if ($data['error'] == 2) {
                // cannot connect to API error, but don't show that here
                // fail silently & log to error log
                error_log($json);
            }
        }
        catch (WebhookException $e)
        {
            $this->responseCode = $this->failureCode;
            // using this to log these specific errors to one file
            $this->message = 'Caught webhook exception: ' .  $data['message'];
            if ($testFakeError) {
                $this->message .= ". TESTING FAKE ERROR, 100 ADULTS";
            }
            throw new Exception($e);
        }
        catch (Exception $e)
        {
            $this->responseCode = $this->failureCode;
            $this->message = "FAILURE: ";
            $this->message .= "Availability for rates $rates on date $futureDate, tour ID $tourId";
            if ($testFakeError) {
                $this->message .= ". TESTING FAKE ERROR, 100 ADULTS";
            }
            throw new Exception($e);
        }
    }

    protected function curlNC($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // TLS version deprecation, as of 30th June 2018 v1.0 end of life
        curl_setopt($ch, CURLOPT_SSLVERSION, 5);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);   //get status code
        $error = curl_error($ch);
        $result = curl_exec($ch);
        curl_close($ch);
        if ($error)
        {
            throw new Exception("Error: $error, Status Code: $status_code");
        }
        return $result;
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
$checker = new CheckPredis();

try {
    $checker->checkAvailability();
}
catch (\Exception $e) {
    $message = $checker->getMessage() . " | " . $e->getMessage();
    error_log($message);
}
http_response_code($checker->getResponseCode());
header('content-type: text/html; charset=utf-8');
echo $checker->getMessage();