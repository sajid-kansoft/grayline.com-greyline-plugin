<?php

//Loader::library('exceptions', 'senshi');
namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

class FeefoApi
{
    //The Feefo URL
    protected $url = 'https://api.feefo.com/api/entersaleremotely';
    protected $api_key;
    protected $feefo_merchant_id;
    protected $locale = 'en';
    protected $pre_tags = '[campaign=pre]';
    protected $post_tags = '[campaign=post]';
    protected $pre_append = '_pre';
    protected $post_append = '';
    protected $append;
    protected $tags;
    protected $campaign;



    public function __construct()
    {
        //include("tourcms/config.php");
        include(GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."libraries/3rdparty/tourcms/config.php"); 
        $this->api_key = FEEFO_API_KEY;
        $this->feefo_merchant_id = FEEFO_MERCHANT_ID;
    }

    public function postSale(
        $email,
        $name,
        $description,
        $product_search_code,
        $order_ref,
        $currency,
        $amount,
        $product_link,
        $customer_ref,
        $date

    ) {

        $order_ref = $this->orderRefUnique($order_ref);

        $params = [
            'apikey' => $this->api_key,
            'merchantidentifier' => $this->feefo_merchant_id,
            'email' => $email,
            'name' => $name,
            'date' => $date,
            'description' => $description,
            'productsearchcode' => $product_search_code,
            'orderref' => $order_ref,
            'currency' => $currency,
            'amount' => $amount,
            'productlink' => $product_link,
            'customerref' => $customer_ref,
            'locale' => $this->locale,
            'tags' => $this->tags
        ];

        //Build up the query and use curl to execute it.
        $data = http_build_query($params, '', '&');

        try { 
            $this->curlData($data);
        }
        catch (FeefoException $e) {
            error_log($e->getMessage());
            // don't block the flow of the script, not fatal
        }

    }

    protected function curlData($data)
    {
        $ch=curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CAINFO, "C:/wamp64/www/ca-bundle.crt");

        $reply=curl_exec($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);   //get status code
        $error = curl_error($ch);
        curl_close($ch);  
        if ($error) { 
            throw new FeefoException("Curl Feefo Error: $error, Status Code: $status_code, Data $data");
        }
        if (!$reply) { 
            throw new FeefoException("Curl Feefo No Reply: $error, Status Code: $status_code, Data $data");
        }
        //echo "The response received was: $reply";
        return true;

    }

    protected function orderRefUnique($order_ref)
    {
        return $order_ref.$this->append;
    }

    public function genTags(array $tags)
    {
        $tag_string = '';
        foreach ($tags as $key => $val) {
            $tag_string .= $key . "=" . $val . ',';
        }
        $tag_string = rtrim($tag_string, ',');
        $tag_string = '[' . $tag_string . ']';
        $this->tags = $tag_string;
    }

    public function typeOfCampaign($type)
    {
        if ($type === 'pre') {
            $this->append = $this->pre_append;
        }
        else if ($type === 'post') {
            $this->append = $this->post_append;
        }
    }


}