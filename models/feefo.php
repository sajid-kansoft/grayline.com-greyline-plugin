<?php

class Feefo{

    // Wrapper for the Feefo API
    protected $baseUrl                 = "https://api.feefo.com/api/";
    protected $merchantId              = "gray-line";
    protected $acceptLang				= "&accept-language=";
    protected $locale					= "en";
    protected $imageNameStr				= 'grayline-product-stars_';

    function __construct() {
        $this->merchantId = FEEFO_MERCHANT_ID;
        if($this->merchantId  == "") {
            $this->merchantId = "gray-line";
        }

        $langCode = LANG_CODE;
        
        if (TRANSLATE)
        {
            $acceptLang = $this->acceptLang . $langCode;
            $this->locale = $langCode;
        }
        else
        {
            $acceptLang = '';
        }
        $this->acceptLang = $acceptLang;
    }

    private function request($path) {
       
        try {

            libxml_use_internal_errors(true);

            $url = $this->baseUrl . $path;

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            //curl_setopt($ch, CURLOPT_CAINFO, "C:/wamp64/www/ca-bundle.crt");

            $result = curl_exec($ch);
            
            // $result could be string, e.g. "merchant not found"
            $xml = simplexml_load_string($result); 
            if (!$xml) {
                throw new Exception("not well formed XML");
            }
            return $xml;
        }
        catch (Exception $e) {
            // silent fail
            return false;
        }
    }

    public function getMerchantFeedback($params = array()) {
        return $this->request('xmlfeedback?merchantidentifier=' . $this->merchantId);
    }

    public function getProductFeedback($vendorRef, $params = "") {
        $vendorRef     = rawurlencode($vendorRef); 
        $requestString = 'xmlfeedback?' . $params . '&merchantidentifier=' . $this->merchantId . '&vendorref=' . $vendorRef . "&since=all&minimumproductstarrating=3" . $this->acceptLang;
        return $this->request($requestString);
    }

    public function getMerchantId(){
        return $this->merchantId;
    }

    public function getLogoUrl($imageName, $vendorRef = '', $params = '', $starsOnly=false) {
        // Need to make this locale aware
        if (! $starsOnly)
            $imageName = $this->imageNameStr . $this->locale . '.png';
        if (get_option('grayline_tourcms_wp_feefo_show') != 1) {

            return '';
        }
        return $this->baseUrl . 'logo?merchantidentifier=' . $this->merchantId . '&template=' . $imageName . '&vendorref=' . $vendorRef . "&since=all&minimumproductstarrating=3" . $params . $this->acceptLang;
    }

    public function organicServiceStars()
    {
        $result = $this->getMerchantFeedback();
        if (is_object($result) && is_object($result->SUMMARY)) {
            $result = $result->SUMMARY;
        }
        else {
            return false;
        }
        if (! $result) {
            return false;
        }
        $name = (string)$result->TITLE;
        $name = json_encode($name);
        $url = (string)$result->URL;
        $logo = (string)$result->SUPPLIERLOGO;
        $ratingValue = (string)$result->FIVESTARAVERAGE;
        $reviewCount = (string)$result->COUNT;
        $script = <<<HEREDOC
<script type="application/ld+json">
{
    "@context":"http://schema.org",
     "@type": "Organization",
     "name": $name,
     "url": "$url",
     "logo": "$logo",
    "aggregateRating":{
        "@type":"AggregateRating",
        "ratingValue":"$ratingValue",
        "reviewCount": $reviewCount
        }
}
</script>
HEREDOC;
        return $script;
    }
}