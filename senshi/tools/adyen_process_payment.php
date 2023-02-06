<?php

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Content-type:application/json;charset=utf-8');

loadSenshiModal('dic');

// MAKE SURE JSON IS RETURNED
try {
    set_time_limit(180);

    // Set up objects
    $dic = new \GrayLineTourCMSSenshiModals\Dic(); 
    $startNewBooking = $dic::objectStartNewBooking($_POST);
    // if it's ajax, onload is not appropriate
    $startNewBooking->startNewBooking(false);
    $tourcmsBookingProcess = $dic::objectTourcmsBookingProcess();

    // get booking_id and price to process payment
    $booking_id = $startNewBooking->getBookingId();
    $total = $startNewBooking->getTotal();
    $currency = $startNewBooking->getCurrency();
    $alt_currency = $startNewBooking->getAltCurrency();
    $alt_total = $startNewBooking->getAltTotal();

    // when we call this, we could either get a redirect "error" thrown, which could be a success & redirection to checkout complete (submit)
    // or we could be dealing with a 3DS1 redirection
    // or we could be dealing with a 3DS2 challenge / fingerprint
    // or we could return an error to the user with their card details
    // or we could have an internal Adyen Exception
    // or we could have a TourCMS error with processing

    // If we have a USD 0.00 booking (e.g. gift code), we bypass Adyen
    try {
        if ($total <= 0) {
            throw new FinanceException("Booking ($booking_id) 0.00 Total Booking, Gift Card Used, bypassing Adyen, manual settlement required.");
        }
       
        $json = $tourcmsBookingProcess->makeAdyenCardPayment($booking_id, $total, $currency, $alt_total, $alt_currency, $_POST);

    }
    catch (FinanceException $e) {
        $tourcmsBookingProcess->makeFreePayment($booking_id);
    }
    // if we have a 3ds1 / 3ds2 flow initiated, we return json to be processed
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
