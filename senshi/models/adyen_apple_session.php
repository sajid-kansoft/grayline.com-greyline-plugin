<?php

namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

class AdyenAppleSession
{
    protected $json;
    protected $live;
    protected $validationUrl = "https://apple-pay-gateway-cert.apple.com/paymentservices/startSession";
    protected $merchantIdentifier;
    protected $displayName;
    protected $initiative = "Web";
    protected $initiativeContext;
    protected $certificateKey;
    protected $certificateKeyPath;
    const SANDBOX_CERTIFICATE_KEY = 'senshi/assets/keys/sandbox/ApplePayPalisisAGTest.key.pem';
    const SANDBOX_CERTIFICATE_CERT = 'senshi/assets/keys/sandbox/ApplePayPalisisAGTest.crt.pem';
    protected $pass_sandbox = "ZNztNwPYQba3sLHzwViUqxnZ***";
    const PRODUCTION_CERTIFICATE_KEY = 'senshi/assets/keys/production/ApplePayPalisisAGLive.key.pem'; ///your/path/to/applepay_includes/ApplePay.key.pem
    const PRODUCTION_CERTIFICATE_CERT = 'senshi/assets/keys/production/ApplePayPalisisAGLive.crt.pem';  // /your/path/to/applepay_includes/ApplePay.crt.pem
    // This is the password you were asked to create in terminal when you extracted ApplePay.key.pem
    protected $pass = "eyelids947!impracticableness";

    public function __construct()
    {
        // this works to include library config file ok
        include(GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."libraries/3rdparty/tourcms/config.php");
        $this->merchantIdentifier = $adyen_merchant_identifier;
        $this->displayName = $adyen_shop_name;
        $this->pass = $apple_cert_pass;
        $this->live = $adyen_env === 'live' ? 1 : 0;
    }

    public function session($validationUrl=null)
    {
        $this->initiativeContext = $_SERVER["HTTP_HOST"]; // can't have https etc
        $this->validationUrl = $validationUrl ? $validationUrl : $this->validationUrl;
        $data = array(
            'merchantIdentifier' => $this->merchantIdentifier,
            'displayName' => $this->displayName,
            'initiative' => $this->initiative,
            'initiativeContext' => $this->initiativeContext,
        );
        $jsonPost = json_encode($data, JSON_UNESCAPED_SLASHES);

        $this->json = $this->curlJson($this->validationUrl, $jsonPost);

        return $this->json;
    }


    protected function curlJson($url, $jsonPost)
    {
        if ($this->live) {

            $cert = GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH.self::PRODUCTION_CERTIFICATE_CERT;
            $key = GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH.self::PRODUCTION_CERTIFICATE_KEY;
            $pass = $this->pass;
        } else {
            $cert = GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH.self::SANDBOX_CERTIFICATE_CERT;
            $key = GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH.self::SANDBOX_CERTIFICATE_KEY;
            $pass = $this->pass_sandbox;
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSLCERT, $cert);
        curl_setopt($ch, CURLOPT_SSLKEY, $key);
        curl_setopt($ch, CURLOPT_SSLKEYPASSWD, $pass);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPost);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // debug
        // $this->debug($ch, $verbose);

        try {
            $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);   //get status code
            $error = curl_error($ch);
            $data = curl_exec($ch);
            if ($error) {
                $error .= $jsonPost;
                throw new \AdyenPaymentException("Apple Pay Error: $error, Status Code: $status_code", "CRITICAL");
            }

            if ($data === false) {
                $error = "cURL Error";
                $error .= curl_errno($ch)." - ".curl_error($ch);
                $error .= $jsonPost;
                throw new \AdyenPaymentException("Apple Pay curl error $error", "CRITICAL");

            }
            curl_close($ch);

            return $data;

        } catch (\Exception $e) {
            curl_close($ch);
            $logMsg = "Error Apple Pay Session, ".$e->getMessage();

            error_log($logMsg);

            throw new \PublicException("Error returning the apple pay session, please try again or try another payment method");
        }
    }

    protected function debug($ch, $verbose)
    {
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        $verbose = fopen('php://temp', 'w+');
        curl_setopt($ch, CURLOPT_STDERR, $verbose);

        rewind($verbose);
        $verboseLog = stream_get_contents($verbose);

        echo "Verbose information";
        echo htmlspecialchars($verboseLog);

        $version = curl_version();
        echo "cURL version";
        print_r($version);
    }

}