<?php


namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

use Adyen\AdyenException;

class TourcmsBookingProcess
{

    protected $tourcmsWrapper;
    protected $adyenPayment;
    protected $tourcmsBookingFinalise;
    protected $licenseesPayout;
    protected $adyenSplit;
    protected $adyenBinLookup;
    protected $cart;
    protected $agent;
    protected $logger;
    protected $channel_id;
    protected $booking_id;
    protected $balance_owed_by;
    protected $alt_total;
    protected $alt_currency;
    protected $total;
    protected $currency;
    protected $fee_estimate_note;
    protected $merchant_account;

    public function __construct(
        TourcmsWrapper $tourcmsWrapper,
        AdyenPayment $adyenPayment,
        Cart $cart,
        AdyenBinLookup $adyenBinLookup,
        TourcmsBookingFinalise $tourcmsBookingFinalise,
        Agent $agent,
        LicenseesPayout $licenseesPayout,
        AdyenSplit $adyenSplit,
        Logger $logger
    ) {
        $this->tourcmsWrapper = $tourcmsWrapper;
        $this->adyenPayment = $adyenPayment;
        $this->cart = $cart;
        $this->adyenBinLookup = $adyenBinLookup;
        $this->tourcmsBookingFinalise = $tourcmsBookingFinalise;
        $this->agent = $agent;
        $this->licenseesPayout = $licenseesPayout;
        $this->adyenSplit = $adyenSplit;
        $this->logger = $logger;
        $this->channel_id = $tourcmsWrapper->getChannelId();
        $this->merchant_account = $adyenPayment->getMerchantAccount();
    }

    public function makeFreePayment($booking_id)
    {
        // we want to catch this redirect exception in calling code, not in this function, so that we can redirect via AJAX or PHP accordingly
        // we just commit the booking, and redirect to checkout complete page
        $payment_reference = "Nothing to pay";
        $this->tourcmsBookingFinalise->commitBooking($booking_id, $payment_reference);
        $errorMsg = "NOTHING TO PAY on booking $booking_id";
        $redirect_url = home_url("/checkout_submit/?np=1&pt=np&channel_id=".$this->channel_id."&bo=".$booking_id);

        $this->logger->logBookingFlow($booking_id, $this->channel_id, "$errorMsg $redirect_url");
        throw new \RedirectSuccessException($errorMsg, $redirect_url);
    }

    public function makeAdyenCardPayment($booking_id, $total, $currency, $alt_total, $alt_currency, $postVars)
    {
        if (!$booking_id || !$total || !$currency || !$postVars) {
            $errorMsg = "CRITICAL: cannot make Adyen Card Payment on booking_id $booking_id";
            $errorMsg .= ". Args: $booking_id, $total, $currency, $alt_total, $alt_currency, ".json_encode(
                    $postVars,
                    JSON_UNESCAPED_SLASHES
                );
            throw new \AdyenPaymentException($errorMsg, 'CRITICAL');
        }
        // we make payment with adyen card processing
        $payment_reference = "Adyen Card";
        $this->tourcmsBookingFinalise->commitBooking($booking_id, $payment_reference);
        // do adyen payment, then process accordingly
        try {
            $booking = $this->tourcmsWrapper->show_booking($booking_id, $this->channel_id);
        } catch (\Exception $e) {
            $this->logger->logBookingFlow($booking_id, $this->channel_id, "can't access show booking, trying again");
            error_log($e->getMessage());
            // try again
            $booking = $this->tourcmsWrapper->show_booking($booking_id, $this->channel_id);
        }
        $lead_customer_name = (string)$booking->booking->lead_customer_name;
        $customer_id = (int)$booking->booking->lead_customer_id;
        // $url = Loader::helper('concrete/urls');
        // $tool_url = $url->getToolsURL('adyen_process_3ds', 'senshi');
        $cart_id = isset($_SESSION["tcmscartid"]) ? (int)$_SESSION["tcmscartid"] : null;
        $returnUrl = home_url("?booking_id=$booking_id&cart_id=$cart_id&utm_nooverride=1");

        $merchant_reference = $this->merchantReference($booking_id);
        $shopper_reference = $this->shopperReference($customer_id);
        $this->total = $total;
        $this->currency = $currency;
        $this->alt_currency = $alt_currency;
        $this->alt_total = $alt_total;
        $fee_percent = $this->adyenBinLookup->getFeePercent();
        $higher_fee = false;
        $payment_method = null;
        try {
            $this->adyenBinLookup->feeEstimate(
                $booking_id,
                $this->channel_id,
                $total,
                $currency,
                $alt_total,
                $alt_currency,
                $shopper_reference,
                (string)$postVars['encryptedCardNumber']
            );
            $fee_percent = $this->adyenBinLookup->getFeePercent();
            $this->cart->feeInfo($booking_id, $this->adyenBinLookup);
            $this->fee_estimate_note = $this->adyenBinLookup->getFeeNote();

            // we want to see what type of card we have, and apply flat fee logic accordingly
            $higher_fee = $this->adyenBinLookup->higherFee($total, $currency, (string)$postVars['encryptedCardNumber']);
            $payment_method = $this->adyenBinLookup->getPaymentMethod();
        }
        catch (\Exception $e) {
            // we need to add this as a note onto the booking / financial data json
            // DO NOT end the booking process if we have trouble getting a cost estimate or trying to find out the card type!
            $this->fee_estimate_note = $e->getMessage();
        }
        try {
            $this->adyenSplit->setBookingId($booking_id);
            $this->adyenSplit->generateSplitArray(
                $merchant_reference,
                $booking,
                $total,
                $currency,
                $fee_percent,
                $payment_method,
                $higher_fee,
                $alt_total,
                $alt_currency
            );
        } catch (\AdyenPaymentException $e) {
            $this->logger->logBookingFlow($booking_id, $this->channel_id, $e->getMessage());
            // we need to cancel these bookings so they're not left stranded in TourCMS=
            $this->tourcmsBookingFinalise->cancelBooking($booking_id, $e->getMessage());
            // still have to retain message, could be useful for end user
            throw new \PublicException("Split calculation error, please try again or try a new product selection.");
        }
        $split = $this->adyenSplit->getSplitArray();
        // let's update TourCMS financial data as we might lose this on a 3ds redirect
        $this->updateBooking($booking_id, $merchant_reference, $split);
        try {
            $result = $this->adyenPayment->createOrder(
                $booking_id,
                $merchant_reference,
                $split,
                $this->channel_id,
                $total,
                $currency,
                $alt_total,
                $alt_currency,
                $lead_customer_name,
                $shopper_reference,
                $returnUrl,
                $postVars
            );

            // Payment could now be authorised, failed, or need some 3DS processing
            return $this->switchResultCode($result, $booking_id);

        } catch (\PublicException $e) {
            $this->logger->logBookingFlow($booking_id, $this->channel_id, $e->getMessage());
            // we need to cancel these bookings so they're not left stranded in TourCMS=
            $this->tourcmsBookingFinalise->cancelBooking($booking_id, $e->getMessage());
            // still have to retain message, could be useful for end user
            throw new \PublicException($e->getMessage());
        } catch (\AdyenPaymentException $e) {
            $this->logger->logBookingFlow($booking_id, $this->channel_id, $e->getMessage());
            // we need to cancel these bookings so they're not left stranded in TourCMS
            $this->tourcmsBookingFinalise->paymentCancel(null, $booking_id);
            // still have to retain message, could be useful for end user
            throw new \PublicException($e->getMessage());
        }


    }

    protected function processTimeout($booking_id, $error_message)
    {
        // update the cart to state booking is a timeout booking, we need this for async processing
        $this->tourcmsBookingFinalise->cartTimeoutStatusUpdate($booking_id, $error_message);
        $note = 'Adyen payment webhook async check timeout, please either manually process this booking / wait for the async code to auto complete & update the status';
        $this->tourcmsBookingFinalise->provisionalBooking(
            $booking_id,
            $this->merchantReference($booking_id),
            $this->currency,
            $note
        );
        // Let user know there was a checkout, to check their email / try again.
        // Also have async call in to process payment further via webhook
        $publicError = "Oh snap! There was a timeout problem with our payment gateway, please check your email to verify the status of this booking (ID $booking_id). Note: you may have to wait a couple of minutes. If the booking failed, please try again, if it is confirmed, enjoy your trip!";
        $string = $publicError;
        $this->logger->logBookingFlow($booking_id, $this->channel_id, $string);
        throw new \PublicException($publicError);

    }


    public function process3DSecure1($booking_id, $postVars, $getVars, $cart_id)
    {
        // Store log
        $string = "3DS1 processing begins ".'POST: '.json_encode($postVars, JSON_UNESCAPED_SLASHES).' GET '.json_encode(
                $getVars,
                JSON_UNESCAPED_SLASHES
            );
        $this->logger->logBookingFlow($booking_id, $this->channel_id, $string);

        // we could either have the 3ds1 bank send us post vars, or get vars with a payload, have to cover both aspects
        $md = isset($postVars['MD']) ? $postVars['MD'] : null;
        $pa_res = isset($postVars['PaRes']) ? $postVars['PaRes'] : null;
        $payload = isset($getVars['redirectResult']) ? $getVars['redirectResult'] : null;

        if (!($md && $pa_res) && !$payload) {
            // Store log
            $publicError = "Cannot process 3ds on this booking (ID: $booking_id), no return data from bank";
            $string = "CRITICAL ERROR - 3DS1 processing, $publicError";
            $this->logger->logBookingFlow($booking_id, $this->channel_id, $string);
            throw new \AdyenPaymentException($publicError);
        }

        if ($cart_id) {
            $_SESSION["tcmscartid"] = $cart_id;
            $this->logger->logBookingFlow(
                $booking_id,
                $this->channel_id,
                "Cart id is $cart_id, setting session tcmscartid: ".$_SESSION["tcmscartid"]
            );
        }

        try {
            $result = $this->adyenPayment->paymentDetails(
                $booking_id,
                $this->merchantReference($booking_id),
                $this->channel_id,
                $md,
                $pa_res,
                $payload,
                $this->getSessionPaymentData()
            );

            // Payment could now be authorised, failed, refused, or pending
            return $this->switchResultCode($result, $booking_id);
        } catch (\PublicException $e) {
            $this->logger->logBookingFlow($booking_id, $this->channel_id, $e->getMessage());
            // we need to cancel these bookings so they're not left stranded in TourCMS
            $this->tourcmsBookingFinalise->cancelBooking($booking_id, $e->getMessage());
            // still have to retain message, could be useful for end user
            throw new \PublicException($e->getMessage());
        }
        catch (\AdyenWebhookException $e) {
            $this->processTimeout($booking_id, $e->getMessage());
        }

    }

//    public function process3DSecure2($postVars, $getVars)
//    {
//        $booking_id = $this->getSessionBookingId();
//        if (!$booking_id) {
//            // Store log
//            $publicError = "Cannot process 3ds2 on this booking, no booking id available";
//            $string = "CRITICAL ERROR - 3DS2 processing, $publicError";
//            $this->logger->logBookingFlow($booking_id, $this->channel_id, $string);
//            throw new AdyenPaymentException($publicError);
//        }
//        // Store log
//        $string = "3DS2 processing begins ".'POST: '.json_encode($postVars, JSON_UNESCAPED_SLASHES).' GET '.json_encode(
//                $getVars,
//                JSON_UNESCAPED_SLASHES
//            );
//        $this->logger->logBookingFlow($booking_id, $this->channel_id, $string);
//
//        // we could either have the 3ds1 bank send us post vars, or get vars with a payload, have to cover both aspects
//        $fingerprint_result = isset($postVars['fingerPrintResults']) ? $postVars['fingerPrintResults'] : null;
//        $challenge_result = isset($postVars['challengeResult']) ? $postVars['challengeResult'] : null;
//
//        if (!$fingerprint_result && !$challenge_result) {
//            // Store log
//            $publicError = "Cannot process 3ds2 on this booking $booking_id, no return fingerprint or challenge post data";
//            $string = "CRITICAL ERROR - 3DS2 processing, $publicError";
//            $this->logger->logBookingFlow($booking_id, $this->channel_id, $string);
//            throw new AdyenPaymentException($publicError);
//        }
//
//        try {
//            $result = $this->adyenPayment->paymentDetails3ds2(
//                $booking_id,
//                $this->merchantReference($booking_id),
//                $this->channel_id,
//                $fingerprint_result,
//                $challenge_result,
//                $this->getSessionPaymentData()
//            );
//
//            // Payment could now be authorised, failed, refused, or pending, or even more 3ds2 processing
//            return $this->switchResultCode($result, $booking_id);
//        } catch (PublicException $e) {
//            $this->logger->logBookingFlow($booking_id, $this->channel_id, $e->getMessage());
//            // we need to cancel these bookings so they're not left stranded in TourCMS
//            $this->tourcmsBookingFinalise->cancelBooking($booking_id, $e->getMessage());
//            // still have to retain message, could be useful for end user
//            throw new PublicException($e->getMessage());
//        } catch (AdyenWebhookException $e) {
//            $this->processTimeout($booking_id, $e->getMessage());
//        }
//    }


    public function processStateData($post_vars)
    {
        $booking_id = $this->getSessionBookingId();
        if (!$booking_id) {
            // Store log
            $publicError = "Cannot process state data on this booking, no booking id available";
            $string = "CRITICAL ERROR - STATE DATA processing, $publicError";
            $this->logger->logBookingFlow($booking_id, $this->channel_id, $string);
            throw new \AdyenPaymentException($publicError);
        }
        // Store log
        $string = "STATE DATA processing begins ".'POST: '.json_encode($post_vars, JSON_UNESCAPED_SLASHES);
        $this->logger->logBookingFlow($booking_id, $this->channel_id, $string);


        try {
            $result = $this->adyenPayment->paymentDetailsStateData(
                $booking_id,
                $this->merchantReference($booking_id),
                $this->channel_id,
                $post_vars,
                $this->getSessionPaymentData()
            );

            // Payment could now be authorised, failed, refused, or pending, or even more 3ds2 processing
            return $this->switchResultCode($result, $booking_id);
        } catch (\PublicException $e) {
            $this->logger->logBookingFlow($booking_id, $this->channel_id, $e->getMessage());
            // we need to cancel these bookings so they're not left stranded in TourCMS
            $this->tourcmsBookingFinalise->cancelBooking($booking_id, $e->getMessage());
            // still have to retain message, could be useful for end user
            throw new \PublicException($e->getMessage());
        } catch (\AdyenWebhookException $e) {
            $this->processTimeout($booking_id, $e->getMessage());
        }
    }



    public function merchantReference($booking_id)
    {
        // we need to make merchant reference absolutely unique, which requires channel ID not just booking ID
        $account_id = $this->tourcmsWrapper->getAccountId();
        $channel_id = $this->tourcmsWrapper->getChannelId();
        $ref = "{$account_id}_{$channel_id}_{$booking_id}";

        return $ref;
    }

    public function shopperReference($customer_id)
    {
        // we need to make shopper reference absolutely unique
        $account_id = $this->tourcmsWrapper->getAccountId();
        $ref = "$account_id-$customer_id";

        return $ref;
    }


    protected function switchResultCode($result, $booking_id)
    {
        // Payment could now be authorised, failed, or need some 3DS processing
        $resultCode = $result['resultCode'];
        $this->tourcmsBookingFinalise->logAudit($booking_id, "ADYEN RESULT CODE: $resultCode");

        // Check if further action is needed
        if (array_key_exists("action", $result)){
            // Pass the action object to your frontend.
            $json = $this->paymentAction($result, $booking_id);
            return $json;
        }
        else {


            $this->tourcmsBookingFinalise->logAudit($booking_id, json_encode($result, JSON_UNESCAPED_SLASHES));
            // No further action needed, pass the resultCode to your front end

            switch ($resultCode) {
                case 'Authorised':
                    $this->paymentAuthorised($result, $booking_id);
                    break;
                case 'RedirectShopper':
                    // 3ds1
                    $json = $this->paymentRedirect($result, $booking_id);

                    return $json;
                    break;
                case 'IdentifyShopper':
                    // 3ds2
                    $json = $this->paymentIdentify($result, $booking_id);

                    return $json;
                    break;
                case 'ChallengeShopper':
                    // 3ds2
                    $json = $this->paymentChallenge($result, $booking_id);

                    return $json;
                    break;
                case 'Pending':
                    // Pending
                    // TODO
                    $json['error'] = 1;
                    $json['response'] = "Payment Pending";

                    return $json;
                    break;
                case 'Received':
                    // Received
                    // TODO
                    $json['error'] = 1;
                    $json['response'] = "Payment Received";

                    return $json;
                    break;
                case 'Cancelled':
                    $this->paymentCancel($result, $booking_id);
                    break;
                case 'Error':
                    $this->paymentCancel($result, $booking_id);
                    break;
                case 'Refused':
                    $this->paymentCancel($result, $booking_id);
                    break;
                default:
                    $this->paymentCancel($result, $booking_id);

            }
        }
    }


    protected function paymentAuthorised($result, $booking_id)
    {
        $this->tourcmsBookingFinalise->paymentAuthorised($result, $booking_id);
    }


    protected function paymentRedirect($result, $booking_id)
    {
        // we need to post data to the redirect url, via a form & JS
        // can't do this through cURL, as the user has to complete a form on the 3rd party server
        $redirect = $result['redirect']['url'];
        $params = array();
        $json['error'] = 3;
        $json['response'] = '3D Secure Challenge';
        $params['PaReq'] = $result['redirect']['data']['PaReq'];
        $params['TermUrl'] = $result['redirect']['data']['TermUrl'];
        $params['MD'] = $result['redirect']['data']['MD'];
        $json['params'] = $params;
        $json['redirect_url'] = $redirect;
        $json['secure_3ds'] = 1;

        // we have to store paymentData in session variable to verify response post 3D secure
        $this->setSessionPaymentData($result['paymentData']);

        // Store log
        $string = "3DS1 Redirect to $redirect";
        $this->logger->logBookingFlow($booking_id, $this->channel_id, $string);

        return $json;
    }

    protected function paymentIdentify($result, $booking_id)
    {
        // we need to keep user on the page, and have them complete 3ds2 processing
        $params = array();
        $json['error'] = 3;
        $json['response'] = '3D Secure 2 Challenge';
        $json['secure_3ds'] = 2;
        $json['secure_type'] = "FingerPrint";
        // important, don't rename resultObject
        $json['resultObject'] = $result;

        // we have to store paymentData in session variable to verify response post 3D secure
        $this->setSessionPaymentData($result['paymentData']);
        // we have to store the booking_id in a session variable so we have it to process after 3ds2 js ajax further calls
        $this->setSessionBookingId($booking_id);

        // Store log
        $string = "3DS2 Identify Shopper, Fingerprint ".json_encode($result, JSON_UNESCAPED_SLASHES);
        $this->logger->logBookingFlow($booking_id, $this->channel_id, $string);

        return $json;
    }

    protected function paymentChallenge($result, $booking_id)
    {
        // we need to keep user on the page, and have them complete 3ds2 processing
        $params = array();
        $json['error'] = 3;
        $json['response'] = '3D Secure 2 Challenge';
        $json['secure_3ds'] = 2;
        $json['secure_type'] = "ChallengeShopper";
        // important, don't rename resultObject
        $json['resultObject'] = $result;

        // we have to store paymentData in session variable to verify response post 3D secure
        $this->setSessionPaymentData($result['paymentData']);
        // we have to store the booking_id in a session variable so we have it to process after 3ds2 js ajax further calls
        $this->setSessionBookingId($booking_id);

        // Store log
        $string = "3DS2 Challenge Shopper".json_encode($result, JSON_UNESCAPED_SLASHES);
        $this->logger->logBookingFlow($booking_id, $this->channel_id, $string);

        return $json;
    }

    protected function paymentAction($result, $booking_id)
    {
        // we need to keep user on the page, and have them complete 3ds2 processing
        $params = array();
        $json['error'] = 3;
        $json['response'] = 'Payment Action Required';
        $json['secure_type'] = "Action";
        // important, don't rename resultObject
        $json['resultObject'] = $result;

        // we have to store paymentData in session variable to verify response post 3D secure
        $this->setSessionPaymentData($result['action']['paymentData']);
        // we have to store the booking_id in a session variable so we have it to process after 3ds2 js ajax further calls
        $this->setSessionBookingId($booking_id);

        // Store log
        $string = "Action Required ".json_encode($result, JSON_UNESCAPED_SLASHES);
        $this->logger->logBookingFlow($booking_id, $this->channel_id, $string);

        return $json;
    }


    protected function setSessionPaymentData($paymentData)
    {
        $name = 'MySession-paymentData';
        // we have to store paymentData in session variable to verify response post 3D secure
        $_SESSION[$name] = urlencode($paymentData);
    }

    protected function getSessionPaymentData()
    {
        $name = 'MySession-paymentData';
        $val = urldecode($_SESSION[$name]);

        return $val;
    }

    protected function setSessionBookingId($booking_id)
    {
        $name = 'MySession-booking_id';
        // we have to store paymentData in session variable to verify response post 3D secure
        $_SESSION[$name] = (int)$booking_id;
    }

    protected function getSessionBookingId()
    {
        $name = 'MySession-booking_id';
        $val = (int)$_SESSION[$name];

        return $val;
    }

    protected function paymentCancel($result, $booking_id)
    {
        $this->tourcmsBookingFinalise->paymentCancel($result, $booking_id);
    }

    protected function updateBooking($booking_id, $merchant_reference, $split)
    {
        $data = [
            'Adyen MarketPay Split' => 'Payment split data as passed to Adyen. ISO Minor Units used for transaction amounts (integer only)',
            'iso_minor_units' => true,
            'merchantAccount' => $this->merchant_account,
            'merchant_reference' => $merchant_reference,
            'currency' => $this->currency,
            'total' => $this->adyenSplit->getTotal(),
            'alt_currency' => $this->alt_currency,
            'alt_total' => $this->adyenSplit->getAltTotal(),
            'fex_rate_interbank_3' => $this->adyenSplit->getFexRate(),
            'fee_note' => $this->fee_estimate_note,
        ];
        $data['splits'] = $split;
        $json = json_encode($data, JSON_UNESCAPED_SLASHES);
        $xml = new \SimpleXMLElement('<booking />');
        $xml->addChild('booking_id', $booking_id);
        $cf = $xml->addChild('custom_fields');
        $f = $cf->addChild('field');
        $name = $f->addChild('name', 'financials');
        $v = $f->addChild('value', htmlspecialchars($json));
        $this->logger->logBookingFlow($booking_id, $this->channel_id, 'Custom fields data', $xml);
        $this->tourcmsWrapper->update_booking($xml, $this->channel_id);
        $this->logger->logBookingFlow($booking_id, $this->channel_id, 'DEBUG: custom field update complete');
    }


}
