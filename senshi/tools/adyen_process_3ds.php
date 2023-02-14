<?php
/**
 * adyen_process_payment.php
 * User: Iona iona@palisis.com
 * Date: 2020-09-03
 * Time: 10:14
 */
defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

loadSenshiModal('dic');

// JSON SHOULD ***NOT**** BE RETURNED, USER SHOULD BE REDIRECTED TO CHECKOUT COMPLETE OR CHECKOUT PAGE WITH ERROR MESSAGE
try {
    // we need the channel id for redirects
    include(GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."libraries/3rdparty/tourcms/config.php");

    error_log("CHECKOUT ADYEN 3DS1 PROCESSING COMMENCED");
    set_time_limit(180);

    // booking_id is passed in a GET param on the return url
    $booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : null;


    // problem with session dropping, so cart_id is passed in a GET param on the return url, and reset
    $cart_id = isset($_GET['cart_id']) ? (int)$_GET['cart_id'] : null;

    $dic = new \GrayLineTourCMSSenshiModals\Dic();
    $tourcmsBookingProcess = $dic::objectTourcmsBookingProcess();
    $tourcmsBookingProcess->process3DSecure1($booking_id, $_POST, $_GET, $cart_id);
} catch (RedirectSuccessException $e) {
    wp_redirect($e->getRedirectUrl());
    exit;
} catch (RedirectException $e) {
    error_log($e->getMessage());
    wp_redirect($e->getRedirectUrl());
    exit;
} catch (AdyenPaymentException $e) {
    error_log($e->getMessage());
    $publicError = urlencode("Sorry there was a problem with our payment gateway, please try again");
    wp_redirect(home_url("/checkout?status=error&channel_id=".$channel_id."&reason=$publicError"));
    exit;
} catch (PublicException $e) {
    // we should show this to user, could be important information for them.
    error_log($e->getMessage());
    $publicError = urlencode($e->getMessage());
    wp_redirect("/checkout?status=error&channel_id=".$channel_id."&reason=$publicError");
    header("Location: ".$redirect_url);
    exit;
} catch (\Exception $e) {
    error_log($e->getMessage());
    $publicError = urlencode("Sorry there was a problem with your request, please try again");
    wp_redirect("/checkout?status=error&channel_id=".$channel_id."&reason=$publicError");
    header("Location: ".$redirect_url);
    exit;
}
