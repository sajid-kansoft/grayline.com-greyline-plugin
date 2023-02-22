<?php
define('TIMEZONE', 'America/Chicago'); // dallas
date_default_timezone_set(TIMEZONE);


function removeWhitespace($string)
{
        $string = preg_replace('/\s+/', ' ', $string);
        return $string;
}

function removeSpaces($string)
{
        $string = str_replace(' ', '', $string);
        return $string;
}

function cleanString($string)
{
        $string = trim($string);
        $string = removeWhitespace($string);
        $string = rtrim($string);
        $string = rtrim($string, ",");
        return $string;
}

// new cookie protocol, for Chrome's 2020 CRSF security restrictions
function palisis_set_cookie($name, $val, $expire=0, $path='/')
{
    setcookie($name, $val, $expire, $path);
    /*header("Set-Cookie: {$name}={$val}; Expires{$expire}; path={$path}; SameSite=None; Secure", false);*/
}

function palisis_get_cookie($name)
{
    return isset($_COOKIE[$name]) ? $_COOKIE[$name] : null;
}
function product_code($string)
{
    return trim((string)strtolower($string));
}

function product_badge($string)
{
    $string = strtoupper($string);
    $array = array(
        "SO" => "Likely To Sell Out",
        "NT" => "New Tour",
        "BS" => "Bestseller",
        "ET" => "Essential Tour",
        "MS" => "Must See",
        "PT" => "Private Tour"
    );

    if (array_key_exists($string, $array)) {
        return $array[$string];
    }

    return null;
}


function usd_convert($json, $sale_currency, $sel_currency, $price)
{
    die("NO USD CONVERT NEEDED");
    $multiplier = $tmt_currencies->$sale_currency->rates->$sel_currency;
    $amount = (float)$price * (float)$multiplier;
    $amount = roundNearestWhole($amount);

    return $amount;
}

function parity_reverse($tmt_currencies, $sale_currency, $sel_currency, $price)
{
    die("NO PARITY REVERSE NEEDED");
    $multiplier = fex_rate_used($tmt_currencies, $sale_currency, $sel_currency);
    $converted_amount = (float)$price / (float)$multiplier;

    return $converted_amount;
}

function fex_rate_used($tmt_currencies, $sale_currency, $sel_currency)
{
    die("NOT TMT FEX RATE USED");
    $multiplier = $tmt_currencies->$sel_currency->rates->$sale_currency;

    return $multiplier;
}

function number_format_converted_price($converted_amount)
{
    return sprintf('%.2F', $converted_amount);
}

function roundNearestWhole($amount)
{
    if (defined('ROUND_PRICES') && ROUND_PRICES == true) {
        $amount = ceil($amount);
        $amount = sprintf('%.2F', $amount);
    }
    return $amount;
}

// 24 / 12 hour conversion
// 24-hour time to 12-hour time
function to_12_hour($val)
{
    return date("g:i a", strtotime($val));
}

// 12-hour time to 24-hour time
function to_24_hour($val)
{
    return date("H:i", strtotime($val));
}

// Function for prettifying dates
function prettify_date($date)
{
    global $date_format;

    return date($date_format, strtotime($date));
}

function simple_tidy_url($name)
{
    return str_replace("%20", "-", rawurlencode(strtolower(str_replace("/", "-_and_-", $name))));
}

function get_location_url($country, $location = "")
{
    $return = "/things-to-do/".simple_tidy_url($country)."/";

    if ($location != "") {
        $return .= simple_tidy_url($location)."/";
    }

    return $return;
}

// Get a product url
function get_product_url($name, $channel_id, $tour_id, $location, $type = "tour")
{
    //return "/tours/".simple_tidy_url($location)."/".nofunnychar_name_url($name)."-".$channel_id."_".$tour_id."/";

    //dheeraj 
    /*return home_url("/tours/".simple_tidy_url(nofunnychar_name_url($location))."/".nofunnychar_name_url($name)."-".get_original_channel_id(
        $tour_id
    )."_".get_original_tour_id($tour_id)."_".$channel_id."_".$tour_id."/");*/

    //manoj
    return home_url("/tours/".nofunnychar_name_url($name));
}

function get_channel_id_from_supplier_tour_code($supplier_tour_code)
{
    preg_match('/\|([0-9]+)/', $supplier_tour_code, $return);

    return isset($return[1])?$return[1]:"";
}

function get_tour_id_from_supplier_tour_code($supplier_tour_code)
{
    preg_match('/([0-9]+)\|/', $supplier_tour_code, $return);

    return isset($return[1])?$return[1]:"";
}

function get_original_channel_id($tour_id)
{  

    if (empty($tour_id)) {
        return CHANNEL_ID;
    }

    $args = array(
        'meta_query' => array(
            array(
                'key' => 'grayline_tourcms_wp_tour_id',
                'value' => (int)$tour_id
            )
        ),
        'post_type' => 'tour',
        'post_status' => array('publish')
    );

    $posts = get_posts($args);

    if(count($posts) == 0) { 
        return CHANNEL_ID;
    } else {
        
        $post_ID = $posts[0]->ID;
        $original_channel_id1 = get_post_meta($post_ID, 'grayline_tourcms_wp_original_channel_id');
        
        if(is_array($original_channel_id1)) { 
            $original_channel_id = isset($original_channel_id1[0])?$original_channel_id1[0]:0;
        } else {
            $original_channel_id = $original_channel_id1;
        }
    }
   
    if (empty($original_channel_id)) {
        return CHANNEL_ID;
    }

    return $original_channel_id;
}

function get_original_tour_id($tour_id)
{
    $args = array(
        'meta_query' => array(
            array(
                'key' => 'grayline_tourcms_wp_tour_id',
                'value' => (int)$tour_id
            )
        ),
        'post_type' => 'tour',
        'post_status' => array('publish')
    );

    $posts = get_posts($args);

    if(count($posts) == 0) {
        return (int)$tour_id;
    } else {
        $post_ID = $posts[0]->ID;
        $original_tour_id1 = get_post_meta($post_ID, 'grayline_tourcms_wp_original_tour_id');
        if(is_array($original_tour_id1)) {
            $original_tour_id = isset($original_tour_id1[0])?$original_tour_id1[0]:0;
        } else {
            $original_tour_id = $original_tour_id1;
        }
    }

    if (empty($original_tour_id)) {
        return $tour_id;
    }

    return $original_tour_id;
}

function get_licensee_name($tour_id)
{
    global $wpdb;

    $channel_id = get_original_channel_id($tour_id);
    if (empty($channel_id)) {
        return 'Gray Line Worldwide';
    }

    $query =  $wpdb->prepare(
        
        "SELECT channel_name from wp_senshi_glww_commission where channel_id = %s",
        array(
           get_original_channel_id($tour_id)
        )
    );

    $row = $wpdb->get_row($query, ARRAY_A);

    if (empty($row) || empty($row['channel_name'])) {
       return 'Gray Line Worldwide';
    }

    return $row['channel_name'];
}

function get_analytics_category($product_type)
{

    $all_tourcms_product_types = array(
        1 => 'Accommodation',
        2 => 'Transport/Transfer',
        3 => 'Multi-day Trip',
        4 => 'Day tour',
        5 => 'Tailor made',
        6 => 'Event',
        7 => 'Training/education',
        8 => 'Other',
        9 => 'Restaurant / Meal alternative',
    );

    if (!empty($all_tourcms_product_types[$product_type])) {
        return (string)$all_tourcms_product_types[$product_type];
    } else {
        return $product_type;
    }
}

function asterisk2Ul($text)
{
    $text = preg_replace("/^\*+(.*)?/im", "<ul><li>$1</li></ul>", $text);
    $text = preg_replace("/(\<\/ul\>\n(.*)\<ul\>*)+/", "", $text);

    return $text;
}

function nofunnychar_name_url($name)
{
    //$name = mb_convert_encoding( $name, 'ISO-8859-1', 'UTF-8' );

    $table = array(
        'Š' => 'S',
        'š' => 's',
        'Đ' => 'Dj',
        'đ' => 'dj',
        'Ž' => 'Z',
        'ž' => 'z',
        'Č' => 'C',
        'č' => 'c',
        'Ć' => 'C',
        'ć' => 'c',
        'À' => 'A',
        'Á' => 'A',
        'Â' => 'A',
        'Ã' => 'A',
        'Ä' => 'A',
        'Å' => 'A',
        'Æ' => 'A',
        'Ç' => 'C',
        'È' => 'E',
        'É' => 'E',
        'Ê' => 'E',
        'Ë' => 'E',
        'Ì' => 'I',
        'Í' => 'I',
        'Î' => 'I',
        'Ï' => 'I',
        'Ñ' => 'N',
        'Ò' => 'O',
        'Ó' => 'O',
        'Ô' => 'O',
        'Õ' => 'O',
        'Ö' => 'O',
        'Ø' => 'O',
        'Ù' => 'U',
        'Ú' => 'U',
        'Û' => 'U',
        'Ü' => 'U',
        'Ý' => 'Y',
        'Þ' => 'B',
        'ß' => 'Ss',
        'à' => 'a',
        'á' => 'a',
        'â' => 'a',
        'ã' => 'a',
        'ä' => 'a',
        'å' => 'a',
        'æ' => 'a',
        'ç' => 'c',
        'è' => 'e',
        'é' => 'e',
        'ê' => 'e',
        'ë' => 'e',
        'ì' => 'i',
        'í' => 'i',
        'î' => 'i',
        'ï' => 'i',
        'ð' => 'o',
        'ñ' => 'n',
        'ò' => 'o',
        'ó' => 'o',
        'ô' => 'o',
        'õ' => 'o',
        'ö' => 'o',
        'ø' => 'o',
        'ù' => 'u',
        'ú' => 'u',
        'û' => 'u',
        'ý' => 'y',
        'ý' => 'y',
        'þ' => 'b',
        'ÿ' => 'y',
        'Ŕ' => 'R',
        'ŕ' => 'r',
        'á' => 'a',
    );

    // $name = utf8_decode($name);
    // assumes has already been stripslashed
    // Need to swap spaces for _ in the $name
    $name = trim($name);
    $name = str_replace("/", "-", $name);
    $name = str_replace("\\", "-", $name);
    $name = str_replace(":", "-", $name);
    $name = str_replace(";", "-", $name);
    $name = str_replace("@", "-", $name);
    $name = str_replace(">", "-", $name);
    $name = str_replace("<", "-", $name);
    $name = str_replace(".", "-", $name);
    $name = str_replace(",", "-", $name);
    $name = str_replace("(", "-", $name);
    $name = str_replace(")", "-", $name);
    $name = str_replace("|", "-", $name);
    $name = str_replace("'", "-", $name);
    $name = str_replace("’", "", $name);
    $name = str_replace('"', "-", $name);
    $name = str_replace('?', "-", $name);
    $name = str_replace('&', "-", $name);
    $name = str_replace('_', "-", $name);
    $name = str_replace('~', "-", $name);
    $name = str_replace('#', "-", $name);
    $name = str_replace(" ", "-", $name);
    $name = str_replace("---", "-", $name);
    $name = str_replace("--", "-", $name);
    $name = str_replace("*", "", $name);
    //$name = str_replace( "„", "", $name );
    //$name = str_replace( "“", "", $name );
    //$name = utf8_decode($name);
    $name = strtr($name, utf8_decode("řäåöáøë"), "raaoaoe");
    //print $name;

    $name = preg_replace('/\W/', ' ', $name);
    $name = trim($name);
    $name = strtolower($name);
    $name = preg_replace('/\s+/', '-', $name);

    return mb_strtolower($name);
}

// Function to convert http TourCMS Marketplace image URLs to https ones

function tour_image_secure($url)
{   
    $count = 1;
    $url = str_replace("http://", "https://", $url, $count);
    $urlparts = explode('.', $url);

    // check for presence of 'r' segment
    if (preg_match('/r\d+/', $urlparts[1])) {
        $urlparts[1] = 'ssl';
        // put url back together
        $url = implode('.', $urlparts);
    }

    return ($url);
}

function google_geocode($string, $existing_geocodes = array())
{
    /*error_log("string");
    error_log($string);
    error_log(array_key_exists($string, $existing_geocodes));
    error_log(print_r($existing_geocodes[$string], true));



    exit();*/

    ini_set("log_errors", 1);
//		 	ini_set("error_log", "/var/www/vhosts/grayline.com/httpdocs/glww2/data/locations/new.log");

    // If we have a geocode already, use that
    if (array_key_exists($string, $existing_geocodes)) {

        error_log($string.' using existing');

        $array = array(
            "lat" => $existing_geocodes[$string]["lat"],
            "lng" => $existing_geocodes[$string]["lng"],
            "location_type" => "",
        );

        return $array;
    }

    // Otherwise, let's geocode!
    error_log($string.' not using existing');

    $string = str_replace(" ", "+", urlencode($string));
    $details_url = "https://maps.googleapis.com/maps/api/geocode/json?address=".$string."&sensor=false&key=".urlencode(
            "AIzaSyAAa6Zlq_nYsVEKbE5-P48ekoBPmIqFcSU"
        );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $details_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $outcome = curl_exec($ch);
    $response = json_decode($outcome, true);

    error_log("String: ".$string);
    error_log("Outcome: ".$outcome);
    error_log("Response: ".print_r($response, true));


    // If Status Code is ZERO_RESULTS, OVER_QUERY_LIMIT, REQUEST_DENIED or INVALID_REQUEST
    if ($response['status'] != 'OK') {

        $response = json_decode(curl_exec($ch), true);

        if ($response['status'] != 'OK') {

            $response = json_decode(curl_exec($ch), true);

            if ($response['status'] != 'OK') {
                return null;
            }
        }
    }

    $geometry = $response['results'][0]['geometry'];

    $longitude = $geometry['location']['lng'];
    $latitude = $geometry['location']['lat'];

    $array = array(
        'lng' => $geometry['location']['lng'],
        'lat' => $geometry['location']['lat'],
        'location_type' => $geometry['location_type'],
    );

    return $array;

}


/* Moved these into ForexLevy and ForexLevyComponent Classes for OOP
function forex_levy($val, $costCurrency)
{
    //\FB::error("FOREX LEVY FUNCTION");
    $price = $val;
    $isForexLevy = is_forex_levy($costCurrency);
    if ($isForexLevy)
    {
        $price = 1.03 * (float)$val;
        $price = sprintf('%.2F', $price);
        //\FB::warn("Final price has been LEVVIED: $price");

    }
    //\FB::info("Final price is: $price");
    return $price;
}

function is_forex_levy($costCurrency)
{
    $isForexLevy = false;
    // we need to add on 3% to all prices where cost currency is USD and basket is not USD
    if ($costCurrency == "USD")
    {
        $cookieCurrency = empty($_COOKIE['selcurrency']) ? "USD" : (string)$_COOKIE['selcurrency'];
        if (strtoupper($cookieCurrency) != "USD")
        {
            // Yes, we need to levy a forex charge. Cost currency is USD, user is viewing site in non-USD
            $isForexLevy = true;
        }

    }
    //\FB::info("IsForexLevy on costCurrency($costCurrency): $isForexLevy");
    return $isForexLevy;
}


function rate_price_per_person($comp=null, $i, $quantity, $sale_quantity, $rate_element)
{
    //\FB::log("PRICE BREAKDOWN on component");

    $attributes = $comp->price_breakdown->price_row[$i]->attributes();

    $row_price = (float)$attributes["price"];

    // So, we use the cost price & multiply by
    $rule = $sale_quantity;
    if ($rule == "PERSON")
    {
        $quantity = (float)$quantity;
    }
    else if ($rule == "GROUP")
    {
        $quantity = (float)1;
    }

    $price_pp = $row_price / $quantity;

    $price_pp =  sprintf('%.2F', $price_pp);

    $rate_element->addChild("price_pp", $price_pp);
    return $rate_element;
}
*/

function get_iso_country_list() {
    include_once('countries.php');
    return $iso_country_list;
}
?>
