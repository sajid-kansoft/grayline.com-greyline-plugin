<?php

class FeefoReviews
{
    /**
     * Store the API data retrieved
     *
     */
    public $feefoData;
    public $error;
    protected $acceptLang = "&accept-language=";
    protected $locale = "en";
    protected $imageNameStr = 'grayline-product-stars_';
    protected $resultSummary;
    protected $vendorRef;

    /**
     * Constructor
     *
     * @param Feefo $feefoObject Feefo API wrapper
     * @param string $vendorRef Product vendor reference
     * @param int $limit number of reviews to show (should probably be part of the config array...)
     * @param array $config config array (on grayline loaded from the config file)
     */
    function __construct($feefoObject, $vendorRef, $limit, $config = array())
    {

        $this->vendorRef = $vendorRef;

        $cache_location = $config["cache_location"];

        $cache_time = $config["cache_time"];

        $loaded_cache = false;

        $langCode = LANG_CODE;
        if (TRANSLATE) {
            $acceptLang = $this->acceptLang.$langCode;
            $this->locale = $langCode;
        } else {
            $acceptLang = '';
        }
        $this->acceptLang = $acceptLang;

        // Create cache directory if it doesn't exist
        $output_dir = $cache_location."feefo/";
        if (!is_dir($output_dir)) {
            mkdir($output_dir);
        }

        // Check if the file exists
        $cache_file = $output_dir.$vendorRef.".xml";

        if (file_exists($cache_file) && (time() - $cache_time < filemtime($cache_file))) {
            
            $xmlData = file_get_contents($cache_file);
            if (trim($xmlData) != '') {
                $result = simplexml_load_file($cache_file);
                $loaded_cache = true;
            }
        } 
        if (!$loaded_cache) { 
            // Query the API
            $result = $feefoObject->getProductFeedback($vendorRef, 'limit='.$limit.'&mode=product');

            // Check we have something returned from the API
            if (is_object($result) && $result->getName() === 'FEEDBACKLIST') {
                // Write the result to cache
                $fh = fopen($cache_file, 'w');
                $stringData = $result->asXML();
                fwrite($fh, $stringData);
                fclose($fh);

                // If not, load the cache file even though it's expired
            } elseif (file_exists($cache_file)) {
                $result = simplexml_load_file($cache_file);
                $loaded_cache = true;
            }
        }



        if (is_object($result) && is_object($result->SUMMARY)) {
            $this->resultSummary = $result->SUMMARY;
        }

        // Process the details
        if (is_object($result) && $result->getName() === 'FEEDBACKLIST') {

            $url = $this->imageNameStr.$this->locale.'.png';
            $logoUrl = "grayline-product-stars_".$this->locale.'.png';

            $this->percentage = (string)$result->SUMMARY->AVERAGE;
            $this->reviews = $result->FEEDBACK;
            $this->read_more_url = 'https://api.feefo.com/en-us/reviews/'.$feefoObject->getMerchantId(
                ).'/products/'.$this->seoUrl((string)$result->SUMMARY->TITLE).'?sku='.$vendorRef;
            $this->feefo_image_url = $feefoObject->getLogoUrl($logoUrl, $vendorRef, '&smallonzero=true', true);
            $this->feefo_image_text_url = $feefoObject->getLogoUrl($url, $vendorRef, '&forceimage=true');
            $this->rateToFive = (string)$result->SUMMARY->FIVESTARAVERAGE;


            $reviewsSuppress = array();

            $counter = 0;

            $strfmttime = '%B %e, %Y';

            foreach ($this->reviews as $review) {
                if ((float)$review->PRODUCTSTARRATING >= 4 && $counter <= 5) {
                    $counter++;
                    $date = date_create($review->HREVIEWDATE);
                    $customerName = $review->PUBLICCUSTOMER->NAME ? $review->PUBLICCUSTOMER->NAME : __('a Gray Line customer', 'wp_grayline_tourcms_theme');
                    $colours = ['comb1', 'comb2', 'comb3', 'comb4', 'comb5', 'comb6','comb7', 'comb8', 'comb9','comb10', 'comb11', 'comb12'];
                    $rndColourNum = rand(0,11);
//                    $review->formatted_date = date_format($date, 'M d Y');
                    $date_to_fmt = $date->format('Y-m-d H:i:s');
                    $review->formatted_date = strftime($strfmttime,strtotime($date_to_fmt));
                    
                    $review->feefo_image_url = '/css/feefo_stars/'.$review->HREVIEWRATING.'.png';
                    $review->CUSTOMERCOMMENT = strip_tags($review->CUSTOMERCOMMENT, '<br />');
                    $review->SHORTCUSTOMERCOMMENT = strip_tags($review->SHORTCUSTOMERCOMMENT, '<br />');
                    $review->CUSTOMERNAME = strip_tags($customerName, '<br />');
                    $review->classColourName = $colours[$rndColourNum];
                    $reviewsSuppress[] = $review;
                }
            }

            $this->reviews = $reviewsSuppress;

        }


    }

    private function seoUrl($string)
    {

        //http://stackoverflow.com/questions/11330480/strip-php-variable-replace-white-spaces-with-dashes

        //Lower case everything
        $string = strtolower($string);
        //Make alphanumeric (removes all other characters)
        $string = preg_replace("/[^a-z0-9_\s-]/", "", $string);
        //Clean up multiple dashes or whitespaces
        $string = preg_replace("/[\s-]+/", " ", $string);
        //Convert whitespaces and underscore to dash
        $string = preg_replace("/[\s_]/", "-", $string);

        return $string;
    }

    public function has_reviews()
    {
        return !empty($this->reviews);
    }

    public function organicProductStars($img, $description)
    {
        $result = $this->resultSummary; 
        if (!$result) { 
            return false;
        } 
        $ratingValue = (string)$result->FIVESTARAVERAGE; 
        if ((int)$ratingValue < 3) {
            return '';
        }
        $name = (string)$result->TITLE;
        $name = json_encode($name);
        $description = json_encode($description);
        $reviewCount = (string)$result->COUNT;
        $sku = $this->vendorRef;
        $brand = "Gray Line ".FEEFO_PRODUCT_BRAND; 
        $script = <<<HEREDOC
<script type="application/ld+json">
{
    "@context":"http://schema.org",
    "@type": "Product",
    "sku":"$sku",
    "name":$name,
    "description":$description,
    "image":"$img",
    "brand":"$brand",
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

?>
