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
    $adyenPayment = $dic::objectAdyenPayment();

    $amount = isset($_GET['amount']) ? $_GET['amount'] : null;
    $currency = isset($_GET['currency']) ? $_GET['currency'] : null;

    $result = $adyenPayment->applePayConfig($amount, $currency);
    $json['error'] = 0;
    $json['apple_config'] = $result;

    $json = json_encode($json, JSON_UNESCAPED_SLASHES);
    echo $json;
    exit;
}
catch (\Exception $e) {
    error_log($e->getMessage());
    $publicError = "Sorry there was a problem with retrieving the apple payment configuration, please try again";
    $json['error'] = 1;
    $json['response'] = $publicError;
    $json = json_encode($json, JSON_UNESCAPED_SLASHES);
    echo $json;
    exit;
}
