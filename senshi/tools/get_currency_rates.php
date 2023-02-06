<?php
/**
 * get_currency.php
 */
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Content-type:application/json;charset=utf-8');

loadSenshiModal('dic');
$dic = new \GrayLineTourCMSSenshiModals\Dic();
// MAKE SURE JSON IS RETURNED.
// NOTE: should never really be an error, as defaults to USD, but in here for completion's sake

try {
    // Get the currency we are converting to
    $to_currency = isset($_GET['to_currency']) ? $_GET['to_currency'] : null;
    $from_currencies = isset($_GET['from_currencies']) ? $_GET['from_currencies'] : null;

    if (!$to_currency) {
        throw new InternalException("No to currency set");
    }
    if (!$from_currencies) {
        throw new InternalException("No from currencies set");
    }
    $fex = $dic::objectFex(); 
    $rates = $fex->getCurrencyRates($to_currency, $from_currencies);
    $json['error'] = 0;
    $json['response'] = $rates;
    $json = json_encode($json, JSON_UNESCAPED_SLASHES);
    echo $json;
    exit;
}
catch (\Exception $e) {
    error_log($e->getMessage());
    $publicError = "Sorry there was a problem returning the currency, please try again";
    $json['error'] = 1;
    $json['response'] = $publicError;
    $json = json_encode($json, JSON_UNESCAPED_SLASHES);
    echo $json;
    exit;
}
