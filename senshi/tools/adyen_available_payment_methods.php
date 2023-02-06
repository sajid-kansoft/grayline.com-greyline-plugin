<?php

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Content-type:application/json;charset=utf-8');

loadSenshiModal('dic');

// MAKE SURE JSON IS RETURNED
try {
    $dic = new \GrayLineTourCMSSenshiModals\Dic();
    $apm = $dic::objectAdyenPaymentMethods();
    $countryCode = isset($_GET['countryCode']) ? $_GET['countryCode'] : null;
    $amount = isset($_GET['amount']) ? $_GET['amount'] : null;
    $currency = isset($_GET['currency']) ? $_GET['currency'] : null;
    $json = $apm->paymentMethods($currency, $amount, $countryCode);
    $json = json_encode($json, JSON_UNESCAPED_SLASHES);
    echo $json;
    exit;
}
catch (\Exception $e) {
    error_log($e->getMessage());
    $publicError = "Sorry there was a problem with retrieving the available payment methods, please try again";
    $json['error'] = 1;
    $json['response'] = $publicError;
    $json = json_encode($json, JSON_UNESCAPED_SLASHES);
    echo $json;
    exit;
}
