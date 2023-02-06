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
    $adyenAppleSession = $dic::objectAdyenAppleSession();

    $validationURL = isset($_GET['validationURL']) ? $_GET['validationURL'] : null;

    $result = $adyenAppleSession->session($validationURL);

    // **** FYI must not json encode this result, it's already encoded from cURL function ****
    $json = $result;
    echo $json;
    exit;
}
catch (\Exception $e) {
    error_log($e->getMessage());
    $publicError = "Sorry there was a problem with retrieving the apple payment session, please try again or try another payment method";
    $json['error'] = 1;
    $json['response'] = $publicError;
    $json = json_encode($json, JSON_UNESCAPED_SLASHES);
    echo $json;
    exit;
}
