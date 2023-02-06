<?php
/**
 * get_currency.php
 */
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Content-type:application/json;charset=utf-8');

//Loader::library('exceptions', 'senshi');
loadSenshiModal('dic');
$dic = new \GrayLineTourCMSSenshiModals\Dic();

// MAKE SURE JSON IS RETURNED.
// NOTE: should never really be an error, as defaults to USD, but in here for completion's sake
try {
    $fex = $dic::objectFex();

    $json['error'] = 0;
    $json['response'] = ['currency' => $fex->getSelectedCurrency()];
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
