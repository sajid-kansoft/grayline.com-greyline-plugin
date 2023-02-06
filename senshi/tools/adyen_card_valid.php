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
    $binLookup = $dic::objectAdyenBinLookup();

    $amount = isset($_POST['total']) ? htmlspecialchars($_POST['total']) : null;
    $currency = isset($_POST['currency']) ? htmlspecialchars($_POST['currency']) : null;
    $encryptedCardNumber = isset($_POST['encryptedCardNumber']) ? $_POST['encryptedCardNumber'] : null;


    $result = $binLookup->cardBin($amount, $currency, $encryptedCardNumber);

    // we're only testing for AMEX just now
    $json['error'] = 0;
    $json['card_country'] = $binLookup->getIssuingCountry();
    $json['response'] = "No errors, proceed....";
    $json = json_encode($json, JSON_UNESCAPED_SLASHES);
    echo $json;
    exit;
}
catch (AdyenPaymentException $e) {
    error_log($e->getMessage());
    $error_msg = $e->getMessage() . ".";
    $apm = Dic::objectAdyenPaymentMethods();
    $brands = $apm->brandNames($currency, $amount);
    if ($brands) {
        $error_msg .= " Please choose another payment type (we support: ";
        $error_msg .= $brands .')';
    }
    $publicError = $error_msg;
    $json['error'] = 1;
    $json['response'] = $publicError;
    $json = json_encode($json, JSON_UNESCAPED_SLASHES);
    echo $json;
    exit;
}
catch (PublicException $e) {
    // we should show this to user, could be important information for them.
    error_log($e->getMessage());
    $publicError = $e->getMessage();
    $json['error'] = 1;
    $json['response'] = $publicError;
    $json = json_encode($json, JSON_UNESCAPED_SLASHES);
    echo $json;
    exit;
}
catch (\Exception $e) {
    error_log($e->getMessage());
    $publicError = "Sorry there was a problem with retrieving the card validity, please try again";
    $json['error'] = 1;
    $json['response'] = $publicError;
    $json = json_encode($json, JSON_UNESCAPED_SLASHES);
    echo $json;
    exit;
}
