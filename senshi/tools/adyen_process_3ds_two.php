<?php

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

header('Content-type:application/json;charset=utf-8');

loadSenshiModal('dic');

// MAKE SURE JSON IS RETURNED
try {
    error_log("CHECKOUT ADYEN PAYMENT PROCESSING COMMENCED");
    set_time_limit(180);

    $json['error'] = 0;
    $json['response'] = 'testing';

    $dic = new \GrayLineTourCMSSenshiModals\Dic();
    $tourcmsBookingProcess = $dic::objectTourcmsBookingProcess();
    $json = $tourcmsBookingProcess->process3DSecure2($_POST, $_GET);

    $json = json_encode($json, JSON_UNESCAPED_SLASHES);
    echo $json;
    exit;
} catch (RedirectSuccessException $e) {
    // only for positive redirect, e.g. gift code, payment authorised
    $publicError = "Redirecting you now...";
    $json['error'] = 0;
    $json['response'] = $publicError;
    $json['redirect_url'] = $e->getRedirectUrl();
    $json = json_encode($json, JSON_UNESCAPED_SLASHES);
    echo $json;
    exit;
} catch (RedirectException $e) {
    // not necessarily an error, could be a positive redirect, e.g. 3DS1 or gift code
    $publicError = "Redirecting you now...";
    $json['error'] = 2;
    $json['response'] = $publicError;
    $json['redirect_url'] = $e->getRedirectUrl();
    $json = json_encode($json, JSON_UNESCAPED_SLASHES);
    echo $json;
    exit;
} catch (AdyenPaymentException $e) {
    error_log($e->getMessage());
    $publicError = "Sorry there was a problem with our payment gateway, please try again";
    $json['error'] = 1;
    $json['response'] = $publicError;
    $json = json_encode($json, JSON_UNESCAPED_SLASHES);
    echo $json;
    exit;
} catch (PublicException $e) {
    // we should show this to user, could be important information for them.
    error_log($e->getMessage());
    $publicError = $e->getMessage();
    $json['error'] = 1;
    $json['response'] = $publicError;
    $json = json_encode($json, JSON_UNESCAPED_SLASHES);
    echo $json;
    exit;
} catch (\Exception $e) {
    error_log($e->getMessage());
    $publicError = "Sorry there was a problem with your request, please try again";
    $json['error'] = 1;
    $json['response'] = $publicError;
    $json = json_encode($json, JSON_UNESCAPED_SLASHES);
    echo $json;
    exit;
}
