<?php
/**
 * get_currency.php
 * 
 */

header("Access-Control-Allow-Origin: *");
header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
header("Pragma: no-cache");
header('Content-type:application/json;charset=utf-8');

/*Loader::library('exceptions', 'senshi');*/
loadSenshiModal('dic');
$dic = new \GrayLineTourCMSSenshiModals\Dic();

// MAKE SURE JSON IS RETURNED.
try { 
    $vars = http_build_query($_POST); 
    $object = $dic::objectCheckAvailability();
    
    $tour_id = isset($_POST['tID']) ? (int)$_POST['tID'] : null;
    $log_vars = "(vars $vars)";

    if (!$tour_id) {
        throw new InternalException("Cannot check availability: No tour id submitted $log_vars", 'DEBUG');
    }

    $channel_id = isset($_POST['chID']) ? (int)$_POST['chID'] : null;
    if (!$channel_id) {
        throw new InternalException("Cannot check availability: No channel id submitted $log_vars", 'DEBUG');
    }

    $check_avail_qs = isset($_POST['check_avail_qs']) ? $_POST['check_avail_qs'] : null;
    if (!$check_avail_qs || $check_avail_qs == 'undefined') {
        throw new InternalException("Cannot check availability: No avail query string submitted $log_vars", 'DEBUG');
    }

    parse_str($check_avail_qs, $output);

    $date = isset($output['date']) ? $output['date'] : null;

    if (!$date || $date == 'undefined') {
        throw new InternalException("Cannot check availability: No date submitted $log_vars", 'DEBUG');
    }


    $rate_string = isset($output['rates']) ? $output['rates'] : null;
    if (!$rate_string) {
        throw new InternalException("Cannot check availability: No rates submitted $log_vars", 'DEBUG');
    }
    $hdur = isset($_POST['hdur']) ? $hdur : 0;

    // Build querystring
    $qs = "date=".$date;

    // Duration (hotel type pricing only)
    $hdur > 0 ? $qs .= "&hdur=".$hdur : null;

    // Rates & number of people
    $rates = explode(",", $rate_string);
    $total_people = 0;

    foreach ($rates as $rate) {
        if(isset($output[$rate])) {
            $rate_count = (int)$output[$rate];

            if($rate_count > 0) {
                $qs .= "&" . $rate . "=" . $rate_count;
                $total_people = $total_people + $rate_count;
            }
        }
    }
    
    $object->setTourId($tour_id);
    $object->setChannelId($channel_id);
    $object->setQs($qs);
    $object->setRates($rates);
    
    $result = $object->checkAvailability();
    
//    print_r($result);
//    print_r($object);
    $json['error'] = 0;
    $json['response'] = $result;
    $json = json_encode($json, JSON_UNESCAPED_SLASHES);
    echo $json;
    exit;
}
catch (InternalException $e) {
    $publicError = "Sorry there was a problem returning the availability, please try again";
    $json['error'] = 1;
    $json['response'] = $publicError;
    $json = json_encode($json, JSON_UNESCAPED_SLASHES);
    echo $json;
    exit;
}
catch (\Exception $e) {
    error_log($e->getMessage());
    $publicError = "Sorry there was a problem returning the availability, please try again";
    $json['error'] = 1;
    $json['response'] = $publicError;
    $json = json_encode($json, JSON_UNESCAPED_SLASHES);
    echo $json;
    exit;
}
