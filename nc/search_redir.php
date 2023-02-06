<?php
ini_set('display_errors', 'On');
error_reporting(E_ALL);

$country_json = get_option('countries');
$gl_country_list = (array)json_decode($country_json);

$to = home_url();

// New geocode search
if (!empty($_GET['geocode'])) {

    // Redirect
    $url = home_url("/search?geocode=".$_GET['geocode']);
    header("Location: $url");
    exit();

}

// Traditional city search
if (!empty($_GET['q'])) {

    // Check the normal geography
    $exploded = explode(", ", $_GET['q']);

    if (count($exploded) > 1) {

        $country = strtoupper($exploded[count($exploded) - 1]);


        //$key = array_search($country, $gl_country_list);

        if (in_array($country, $gl_country_list)) { 
            
            unset($exploded[count($exploded) - 1]);

            $location = implode(", ", $exploded);  
            $country_tidy_url = simple_tidy_url($country);
            $location_tidy_url = simple_tidy_url($location);
            $to = home_url("/search?q=".$location_tidy_url."&country_name=".$country_tidy_url);
        } else { 
            $to = home_url("search?q=".$_GET["q"]);
        }

    } elseif (in_array(strtoupper($exploded[0]), $gl_country_list)) {  
        $simple_tidy_url = simple_tidy_url($exploded[0]);
        $to = home_url("/search?country_name=".$simple_tidy_url);
    } else {  
        $to = home_url("/search?q=".$_GET["q"]);
    }
    
}

header("HTTP/1.1 301 Moved Permanently"); 
header("Location: $to"); 
exit();