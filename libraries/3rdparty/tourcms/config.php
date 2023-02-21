<?php
//has to come first
if (!defined('ROUND_PRICES')) {
    define('ROUND_PRICES', true);
}

// include_once('libraries/Mustache.php');
// include_once('libraries/feefo.php');
//include_once('libraries/FirePHPCore/fb.php');
include_once('functions.php');
// include_once('countries.php');

// use this to bust users' browser cache
$cacheBust = md5('newlaunch2019v173');
$adyen_version = "4.5.0";
$release_version = "5.0.2";

$production = false;
$tmtStage = false;
$urlAgents = '';
$localhost = false;
$firePhp = false;
$remote_stage_webhook = false;

// we need to bypass htpasswd on remote staging because it is already password protected
if (strpos($_SERVER["HTTP_HOST"], 'localhost/Grayline/') !== false) {
    $remote_stage_webhook = true;
}

if (strpos($_SERVER["HTTP_HOST"], 'localhost:8888') !== false
    || strpos($_SERVER["HTTP_HOST"], 'localhost') !== false
    || strpos($_SERVER["HTTP_HOST"], '.local') !== false
    || strpos($_SERVER["HTTP_HOST"], 'ngrok.io') !== false
    || strpos($_SERVER["HTTP_HOST"], 'staging.grayline.com') !== false
) {
    ########################### DO NOT HAVE TRUE ON LIVE
    $dummy_production = false; // trialling live Adyen checkout TRIAL *******
    $production = $dummy_production ? true : false;
    $localhost = true;
}

if ($production) {
   // sensitive live data removed for KanSoft
} else {
    //include_once('libraries/tourcms-test.php');
    // AGENT CONFIG
    $urlAgents = 'https://test-admin.tourcms.com';
    // TMT CONFIG
    $tmtStage = true;
    // FEEFO CONFIG
    $feefo_api_key = '14282439-0b1e-443d-aaa8-65a2d357a1c2';
    $feefo_merchant_id = 'gray-line';
    //$giftCodeMailTo = 'notifications@palisis.com';
    // GIFT CODE CONFIG
    // $giftCodeMailTo = 'iona@palisis.com';
    $giftCodeMailTo = 'manoj.t@kansoftware.com';

    // ADYEN CONFIG (Palisis MarketPlace account)
    // WEB SERVICE USER: ws_141801@Company.PalisisAG
    $adyen_env = 'test';
    $adyen_api_key = 'AQErhmfxLo7PYxVDw0m/n3Q5qf3VeIpBBIBDS0RxsBm2QqnolD2zQ6ajvNJ7aRDBXVsNvuR83LVYjEgiTGAH-ADapSDgT700icOKNUQ9zdmK/hhgUr+TnRx7vglbBUas=-X>GI;sZwFdxL9Vs>';
    $adyen_merchant_name = 'PalisisAG_PM_TEST';
    $adyen_shopper_statement = 'Gray Line Corporation TEST';
    if (!defined('ADYEN_CLIENT_KEY')) {
        define('ADYEN_CLIENT_KEY', 'test_EAD7Q3ZLGJFZVHWE2O7WC325AEJ4H54Z');
    }
    // GLWW ADYEN STORE
    $store = '955c0448-b479-45aa-b8ff-e1881ab50bb4';

    // APPLE CONFIG
    $adyen_merchant_identifier = 'merchant.com.adyen.PalisisAGMP.test';
    $adyen_shop_name = 'Palisis Test Store';
    $adyen_live_enpoint_url_prefix = null;
    $apple_cert_pass = 'ZNztNwPYQba3sLHzwViUqxnZ';


    // used for asynchronous webhooks, must activate this in Adyen & confirm working
    // e.g. https://884815877444.ngrok.io/index.php/tools/packages/senshi/adyen_webhook_accept.php
    $adyen_webhook_user = 'adyen_grayline';
    $adyen_webhook_password = '7i8be!!0cGGOt***1231';

    // ADYEN SPLIT, commission and account numbers
    $pal_earn = 70; // 70% residual amount, GLWW gets 30%
    $fee_commission = 2.4;  // 2.4% of transaction to cover fees
    $higher_fee_commission = 3.95; // 3.95% of transaction for AMEX or Discover or Diners cards
    // Accounts for the earnings
    $pal_acc = 8816128049136952;
    $pal_acc_submerchant_new = 1216382807884169;
    $glww_acc = 8816128048076944;
    // Account for the fees
    $pal_fee_acc = 102621719;

    // Email alerts
    if (!defined('MAIL_DEBUG')) {

        define('MAIL_DEBUG', false); // use iona@palisis.com sendgrid mail account and debug messages
    }

}

define('DIR_REL', get_home_url());

if (!defined('PRODUCTION')) {
    define('PRODUCTION', $production);
}
if (!defined('ADYEN_ENV')) {
    define('ADYEN_ENV', $adyen_env);
}
if (!defined('FIREPHP')) {
    define('FIREPHP', false);
}
if (!defined('TMT_STAGE')) {
    define('TMT_STAGE', $tmtStage);
}
if (!defined('URL_AGENTS')) {
    define('URL_AGENTS', $urlAgents);
}
if (!defined('FEEFO_API_KEY')) {
    define('FEEFO_API_KEY', $feefo_api_key);
}
if (!defined('FEEFO_MERCHANT_ID')) {
    define('FEEFO_MERCHANT_ID', $feefo_merchant_id);
}
if (!defined('DIR_REL')) {
    define('DIR_REL', '');
}

if (!defined('CCM_REL')) {
    define('CCM_REL', '');
}
if (!defined('GIFT_CODE_MAILTO')) {
    define('GIFT_CODE_MAILTO', $giftCodeMailTo);
}

//FB::setEnabled($firePhp);


if (!defined('TMT_ENVIRO_KEY')) {
    define('TMT_ENVIRO_KEY', "2VliQoIvXLLyW2vTn7kPWotrMId");
};

// TESTOPERATOR ACCOUNT
//define("CHANNEL_ID", 12426);
//define("API_PRIVATE_KEY", "83ef2345745c");
if (!defined('SPECIAL_OFFERS')) {
    define('SPECIAL_OFFERS', true);
}
if (!defined('CHANNEL_ID')) {
    define('CHANNEL_ID', 12130);
}
if (!defined('API_PRIVATE_KEY')) {
    define('API_PRIVATE_KEY', "a08ceaeb009f");
}
if (!defined('TOURCMS_PRODUCTION_MODE')) {
    define('TOURCMS_PRODUCTION_MODE', false);
}
// VEGAS TEST OFFERS
//define("CHANNEL_ID", 6147);
//define("API_PRIVATE_KEY", "5d2107f49c31");

// Root for files

$dir_rel = substr(__DIR__, strlen($_SERVER['DOCUMENT_ROOT']));
$dir_rels = explode('/', $dir_rel); 
$dir_rel = isset($dir_rels[1])? "/".$dir_rels[1]:"/"; 
if(isset($dir_rels[1])) {
    if ($dir_rels[1] == "libraries") {  
        $dir_rel = "";
    } 
} 
//$doc_root = $_SERVER['DOCUMENT_ROOT'].$dir_rel;


if (strpos($_SERVER["HTTP_HOST"], 'stage2.gl-demo.com') !== false) {
    //$cache_location = "/var/www/vhosts/gl-demo.com/staging2/content/data/";
    $cache_location = GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH.'/toplevel_data/';
} else {
    $cache_location = GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH.'toplevel_data/';
}


// Allowed currencies


// Allowed currencies
$allowed_currencies = array("USD", "AUD", "CAD", "EUR", "GBP", "HKD");

$currency_symbols = array(
    "USD" => "$",
    "GBP" => "£",
    "EUR" => "€",
);

$currency_countries = array(
    "USD" => "US",
    "GBP" => "GB",
    "AUD" => "AU",
    "CAD" => "CA",
    "HKD" => "HK",
);

// Base for page titles (not used)
$page_titles_base = "";

// TourCMS API settings
$marketplace_account_id = 0;
$channel_id = CHANNEL_ID; 
$api_private_key = API_PRIVATE_KEY;
$account_id = 11676;


// Cache times (seconds)
$cache_times = array(
    "default" => 5 * 60,
    "dates_and_deals" => 60 * 60,
    "tour" => 60 * 60,
    "tours" => 60 * 60 * 2,
);

// Other API settings
$google_api_key = "AIzaSyCNPmNVGMLYwlqRN8dE2oDfnB4spewCyUg";

// Database settings
// configuration
$dbtype = "mysql";
$dbhost = "rds-main.internal.dns";
$dbport = 3306;

// create new blank db to speed up queries
#$dbname = "glcom_cart_2019";
#$dbuser = "glcom_cart";
// AWS DDB
$dbname = "graylinecart_prod";
$dbuser = "graylinecartprod";
$dbpass = "aU69r1e*!!@902";


if (strpos($_SERVER["HTTP_HOST"], '.local') !== false || strpos(
        $_SERVER["HTTP_HOST"],
        'ngrok.io'
    ) !== false
) {
    $dbhost = "localhost";
    $dbuser = "homestead";
    $dbpass = "secret";
    $dbname = "glww_tcmscart_clean";
} else {
    if (strpos($_SERVER["HTTP_HOST"], 'staging.grayline.com') !== false) {
        $dbname = "graylinecart_stg";
        $dbuser = "graylinecartstg";
        $dbpass = "aU69r1e*!!@903";
    }
}


// Format for prettifying dates
// http://php.net/manual/en/function.date.php
$date_format = "l jS M Y";

use TourCMS\Utils\TourCMS as TourCMS;

// Create a new TourCMS instance
// NEW: 35 second timeout, to deal with the search customer hanging for 1 minute on checkout buggy behaviour
//$tourcms = new TourCMS($marketplace_account_id, $api_private_key, "simplexml", 35);

// if(!class_exists()) {
    $tourcms = new TourCMS($marketplace_account_id, $api_private_key, "simplexml", 35);
// }



// Set up our partials
$partials = array();
