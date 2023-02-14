<?php
include(GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."libraries/3rdparty/tourcms/config.php");

header("Access-Control-Allow-Origin: *");
header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

isset($_GET['tour_id']) ? $tour_id = (int)$_GET['tour_id'] : null;
isset($_GET['channel_id']) ? $channel_id = (int)$_GET['channel_id'] : exit();
isset($_GET['date']) ? $date = $_GET['date'] : null;
isset($_GET['rates']) ? $rate_string = $_GET['rates'] : null;

$data = array(
    "error" => 2,
    "message" => "Predis script check availability, problem with query string params",
    "result" => $_GET
);

if (! $tour_id || ! $channel_id || ! $date || ! $rate_string) {
    print json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

// Build querystring
$qs = "date=".$date;
$rates = explode(",", $rate_string);
$total_people = 0;
foreach ($rates as $rate) {
    if(isset($_GET[$rate])) {
        $rate_count = (int)$_GET[$rate];
        if($rate_count > 0) {
            $qs .= "&" . $rate . "=" . $rate_count;
            $total_people = $total_people + $rate_count;
        }
    }
}
$qs .= '&webhook_info=1';

// Query the TourCMS API
$result = $tourcms->check_tour_availability($qs, $tour_id, $channel_id);
if(!$result) {
    // If we didn't get a response, try again
    $result = $tourcms->check_tour_availability($qs, $tour_id, $channel_id);
}
if(!$result) {
    // If we didn't get a response, try again
    $result = $tourcms->check_tour_availability($qs, $tour_id, $channel_id);
}
if(!$result) {
    $data = array(
        "error" => 2,
        "message" => "Predis script check availability, cannot connect to API, 3 attempts made",
        "result" => $result
    );
}
else {

    $componentArr = $result->available_components->component;

    $total = count($componentArr);

    if ($total == 0) {
        $data = array(
            "error" => 1,
            "message" => "Predis script check availability, no components returned, but can connect to API",
            "result" => $result
        );
    } else {
        if ($total > 0) {
            $data = array(
                "error" => 0,
                "message" => "Predis script check availability, components returned",
                "result" => $result
            );
        }
    }
}

print json_encode($data, JSON_UNESCAPED_SLASHES);
exit;