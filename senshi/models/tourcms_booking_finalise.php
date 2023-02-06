<?php


namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

class TourcmsBookingFinalise
{

    protected $tourcmsWrapper;
    protected $adyenResult;
    protected $cart;
    protected $logger;
    protected $channel_id;
    protected $booking_id;

    public function __construct(
        TourcmsWrapper $tourcmsWrapper,
        AdyenResult $adyenResult,
        Cart $cart,
        Logger $logger
    ) {
        $this->cart = $cart;
        $this->tourcmsWrapper = $tourcmsWrapper;
        $this->adyenResult = $adyenResult;
        $this->logger = $logger;
        $this->channel_id = $tourcmsWrapper->getChannelId();
    }

    public function cartTimeoutStatusUpdate($booking_id, $error_message)
    {
        $this->cart->paymentTimeout($booking_id, $this->channel_id);
        // catch a webhook lookup fail & log to booking
        $this->logFailedPayment($booking_id, $error_message);
        return true;
    }


    public function paymentAuthorised($result, $booking_id)
    {
        $string = "Authorising payment: " . json_encode($result, JSON_UNESCAPED_SLASHES);
        $this->logger->logBookingFlow($booking_id, $this->channel_id, $string);
        // card tokenisation for future payment
        $this->adyenResult->build($result);
        $issuing_country = $this->adyenResult->getCardCountry();
        $note_add = '';
        $note_add .= $this->adyenResult->getAuthorisedAmount();
        $note_add .= "Card Country ($issuing_country)";
        $card_type = $this->adyenResult->getCardType();
        $psp_reference = $this->adyenResult->getPspReference();
        $reference = $psp_reference;
        $note = "ADYEN: ".$note_add;
        $this->logger->logBookingFlow(
            $booking_id,
            $this->channel_id,
            "DEBUG: Payment Authorised $note, $psp_reference"
        );
        $this->createPayment($booking_id, $card_type, $reference, $note, $issuing_country);

        // Log new status
        $this->cart->paymentAuthorised($booking_id, $this->channel_id, $psp_reference);

        // now we want to redirect the user to checkout complete
        $errorMsg = "BOOKING PAID {$booking_id}";
        
        $redirect_url = home_url("/checkout_submit/?np=1&channel_id=".$this->channel_id."&bo=".$booking_id);
        
        $this->logger->logBookingFlow($booking_id, $this->channel_id, "DEBUG: Redirecting.... $redirect_url");
        throw new \RedirectSuccessException($errorMsg, $redirect_url);
    }

    public function paymentCancel($result, $booking_id)
    {
        $string = "Cancelling payment: " . json_encode($result, JSON_UNESCAPED_SLASHES);
        $this->logger->logBookingFlow($booking_id, $this->channel_id, $string);

        $this->adyenResult->build($result);
        $resultCode = $this->adyenResult->getResultCode();
        $reason = $this->adyenResult->getRefusalReason();
        $psp_reference = $this->adyenResult->getPspReference();
        $reference = $psp_reference;
        $note = "Adyen Card Payment ResultCode {$resultCode}, PSP Ref# {$reference}, Reason: $reason";
        $this->cancelBooking($booking_id, $note);
        $this->cart->cancelBooking($booking_id, $this->channel_id, $psp_reference);
        $publicError = "Payment cancelled, reason: $reason";
        $this->logger->logBookingFlow($booking_id, $this->channel_id, $publicError);
        throw new \PublicException($publicError);
    }

    // used for adyen timeouts
    public function provisionalBooking(int $booking_id, $reference, $currency, $note)
    {
        try {
            $xml = new SimpleXMLElement('<payment />');

            $xml->addChild('booking_id', $booking_id);
            $xml->addChild('payment_value', 0);
            $xml->addChild('payment_currency', $currency);
            $xml->addChild('payment_type', '');
            $xml->addChild('payment_reference', $reference);
            $xml->addChild('payment_transaction_reference', '');
            $xml->addChild('payment_note', $note);
            $xml->addChild('payment_by', '');
            // Store log of create payment
            $string = "Create provisional booking / create payment XML";
            $this->logger->logBookingFlow($booking_id, $this->channel_id, $string, $xml);

            $result = $this->tourcmsWrapper->create_payment($xml, $this->channel_id);

            // Store log of response to create payment
            $string = "Response to Create Payment XML";
            $this->logger->logBookingFlow($booking_id, $this->channel_id, $string, $result);
        } catch (Exception $e) {
            $string = "CRITICAL: cannot create_payment in TourCMS ".$e->getMessage();
            $this->logger->logBookingFlow($booking_id, $this->channel_id, $string);
            // throw this back up the chain
            $errorMsg = "CRITICAL: cannot create_payment in TourCMS on booking_id $booking_id";
            throw new \PublicException($errorMsg, 'CRITICAL');
        }
    }

    public function commitBooking(int $temp_booking_id, string $payment_reference = "")
    {
        try {

            // Build the XML to post to TourCMS
            $booking = new \SimpleXMLElement('<booking />');
            $booking->addChild('booking_id', $temp_booking_id);
            // Query the TourCMS API, upgrading the booking from temporary to live
            $confirmed_booking = $this->tourcmsWrapper->commit_new_booking($booking, $this->channel_id);

            // If everything isn't ok, the tourcmswrapper will throw a Critical TourcmsException
            // We have a booking! Get the Id
            $confirmed_booking_id = (int)$confirmed_booking->booking->booking_id;
//            $this->confirmed_booking_id = $confirmed_booking_id;

            // And the voucher URL
            $voucher_url = !empty($confirmed_booking->booking->voucher_url) ? (string)$confirmed_booking->booking->voucher_url : "";

            // Update the record of our temporary booking
            $this->cart->bookingCommitted($confirmed_booking_id, $this->channel_id, $payment_reference, $voucher_url);

            // Store log of response to commit booking
            $string = "Response to Commit Booking XML";
            $this->logger->logBookingFlow($confirmed_booking_id, $this->channel_id, $string, $confirmed_booking);

            return true;

        } catch (TourcmsException $e) {
            // throw this back up the chain
            $publicError = "Sorry we are having difficulty committing this booking, please try again";
            throw new \PublicException($publicError);
        }

    }

    // Update the booking in TourCMS asynchronously if there was a timeout. AdyenWebhookProcess class uses this method
    public function finaliseTimeoutBooking($booking_id, $success, $result)
    {
        // we only do this for registered timeout bookings, otherwise we can run into trouble with double payment / cancellation etc...
        $is_timeout = $this->cart->getBookingTimeoutStatusFromDb($booking_id);
        // we are only going to update bookings that are not already cancelled / confirmed, as we don't want a double payment logged etc
        if (!$is_timeout) {
            return true;
        }
        else if ($is_timeout) {
            try {
                $this->logger->logBookingFlow(
                    $booking_id,
                    $this->channel_id,
                    "Adyen Webhook Async Process Confirmed Timeout booking: $is_timeout"
                );
                $booking = $this->tourcmsWrapper->show_booking($booking_id, $this->channel_id);
            } catch (Exception $e) {
                $this->logger->logBookingFlow(
                    $booking_id,
                    $this->channel_id,
                    "can't access show booking, trying again"
                );
                error_log($e->getMessage());
                // try again
                $booking = $this->tourcmsWrapper->show_booking($booking_id, $this->channel_id);
            }
            $status = (int)$booking->booking->status;
            $cancel_reason = (int)$booking->booking->cancel_reason;
            $string = "status $status, cancel reason $cancel_reason";
            $this->logger->logBookingFlow($booking_id, $this->channel_id, $string);
            try {
                if ($status === 2 || $status === 3) {
                    // do nothing, booking is confirmed
                    $string = "do nothing, booking is confirmed";
                    $this->logger->logBookingFlow($booking_id, $this->channel_id, $string);

                    return true;
                } else {
                    if ($cancel_reason === 0) {
                        $string = "Provisional / Quote booking, attempt to update the status asynchronously automatically";
                        $this->logger->logBookingFlow($booking_id, $this->channel_id, $string);
                        // we could now have a quote / provision booking, not cancelled, that needs to get updated
                        if ($success === 'true') {
                            // build a result array to process in the normal way and stop code duplication
                            $this->paymentAuthorised($result, $booking_id);

                        } else {
                            $this->paymentCancel($result, $booking_id);
                        }
                    }
                }
            } catch (Exception $e) {
                $string = "ASYNC BOOKING PROCESS ERROR: ".$e->getMessage();
                $this->logger->logBookingFlow($booking_id, $this->channel_id, $string);
                throw new \AdyenWebhookException(
                    "Async process error, cannot update booking id $booking_id. ".$e->getMessage()
                );
            }
        }
    }


    protected function createPayment(int $booking_id, $cardType = "Credit Card", $reference = null, $note = null, $issuing_country = null)
    {
        // we are using showBookingXml to get total & currency, as we might have gone through a 3ds1 redirect & lost those values
        $showBookingXml = $this->tourcmsWrapper->show_booking($booking_id, $this->channel_id);

        // strange bug on booking id 777469 where 3ds1 was called multiple times, again one hour after booking
        // maybe back button was used, triggering 3ds1 all over again
        // let's check the status of the booking before we do a create payment
        $status = (int)$showBookingXml->booking->status;
        try {
            if ($status === 2 || $status === 3) {
                // do nothing, booking is confirmed
                $status_text = (string)$showBookingXml->booking->status_text;
                $string = "Booking ID $booking_id is already confirmed. Status $status_text ($status)";
                $this->logger->logBookingFlow($booking_id, $this->channel_id, $string);
                throw new InternalException($string, 'CRITICAL');
            }
        }
        catch (InternalException $e) {
            // don't throw an exception which will cancel the booking
            return true;
        }

        $balance_owed_by = (string)$showBookingXml->booking->balance_owed_by;
        // might not know sales total if in a redirect situation
        // might be an agent, in which case they've paid less than the sales revenue
        // We need to extract the AMOUNT_DUE from the DB, as there could have been a partial gift code etc used, we don't get that info from
        // show_booking sales_revenue
        // TODO - test this logic around deposit with all types of agent, might not be the case
        // TODO - see if this is just the case for (string)$showBookingXml->booking->agent_type === 'RETAIL'
        $item_db = $this->cart->getBookingFromDb($booking_id);
        $sales_revenue = !empty($item_db) && isset($item_db['amount_due']) ? $item_db['amount_due'] : (string)$showBookingXml->booking->sales_revenue;
        if ((string)$showBookingXml->booking->agent_id !== '') {
            $sales_revenue = (string)$showBookingXml->booking->deposit;
        }
        $currency = (string)$showBookingXml->booking->sale_currency;

        try {
            $gift_code = $showBookingXml->booking->promo && (string)$showBookingXml->booking->promo->value_type === 'FIXED_VALUE' ? true : false;
            if ($gift_code) {
                $gift_code = (string)$showBookingXml->booking->promo->promo_code.' @ ';
                $gift_code .= (string)$showBookingXml->booking->promo->value . ' ';
                $gift_code .= (string)$showBookingXml->booking->promo->value_currency;
                throw new FinanceException("Booking ID $booking_id. $currency $sales_revenue. Gift code used $gift_code. Manual split & payment settlement necessary");
            }
        }
        catch (FinanceException $e) {
           // continue on...
        }


        try {
            $xml = new \SimpleXMLElement('<payment />');
            // TODO - change this, there will be a CC Fee
            $creditcard_fee_type = 0;
//            $paid_by = $this->agent->getIsAgent() ? "A" : "C";
//            $payment_by = $this->balance_owed_by ? $this->balance_owed_by : $paid_by;
            $payment_by = $balance_owed_by;

            $xml->addChild('booking_id', $booking_id);
            $xml->addChild('payment_value', $sales_revenue);
            $xml->addChild('payment_currency', $currency);
            $xml->addChild('payment_type', $cardType);
            $xml->addChild('payment_reference', $reference);
            $xml->addChild('payment_transaction_reference', 'PALMP|'.$reference);
            $xml->addChild('payment_note', $note);
            $xml->addChild('payment_by', $payment_by);
            $xml->addChild('creditcard_fee_type', $creditcard_fee_type);

            // new field to add in json data for adyen result which should include issuing country

            // now we add in the tokenised card details for future payments using new TCMS API method
            $array = ['cardIssuingCountry' => $issuing_country];
            $json = json_encode($array, JSON_UNESCAPED_SLASHES);
            $xml->addChild('json_data', $json);

            // we may not have this data in case of a timeout booking where we async process
            // we're no longer tokenising the card for now
//            if ($this->adyenResult->getPaymentMethodNumber()) {
//                $token_xml = $xml->addChild('tokenized_payment_details');
//                $token_xml->addChild('billing_id', $this->adyenResult->getBillingId());
//                $token_xml->addChild('billing_id_type',$this->adyenResult->getBillingIdType());
//                $token_xml->addChild('payer_name', $this->adyenResult->getCardholderName());
//                $token_xml->addChild('payment_method_type', $this->adyenResult->getCardType());
//                $token_xml->addChild('payment_method_number', $this->adyenResult->getPaymentMethodNumber());
//                $token_xml->addChild('payment_method_expiry', $this->adyenResult->getExpiry());
//            }

            // Store log of create payment
            $string = "Create Payment XML";
            $this->logger->logBookingFlow($booking_id, $this->channel_id, $string, $xml);

            $result = $this->tourcmsWrapper->create_payment($xml, $this->channel_id);

            // Store log of response to create payment
            $string = "Response to Create Payment XML";
            $this->logger->logBookingFlow($booking_id, $this->channel_id, $string, $result);


        } catch (Exception $e) {
            $string = "CRITICAL: cannot create_payment in TourCMS ".$e->getMessage();
            $this->logger->logBookingFlow($booking_id, $this->channel_id, $string);
            // throw this back up the chain
            $errorMsg = "CRITICAL: cannot create_payment in TourCMS on booking_id $booking_id";
            throw new PublicException($errorMsg, 'CRITICAL');
        }
    }

    protected function logFailedPayment($booking_id, $msg)
    {
        // Note, this triggers a failed payment email, so check if the booking is already confirmed before going ahead with this:
        $showBookingXml = $this->tourcmsWrapper->show_booking($booking_id, $this->channel_id);
        $status = (int)$showBookingXml->booking->status;
        if ($status === 2 || $status === 3) {
            // do nothing, booking is confirmed
            $status_text = (string)$showBookingXml->booking->status_text;
            $string = "Booking ID $booking_id is already confirmed. Do not log failed payment. Status $status_text ($status)";
            $this->logger->logBookingFlow($booking_id, $this->channel_id, $string);
            return false;
        }
        else {
            $xml = new SimpleXMLElement('<booking />');
            $xml->addChild('booking_id', $booking_id);
            // Optionally add a message
            $xml->addChild('audit_trail_note', $msg);

            // Store log of xml
            $string = "Log Failed Payment XML";
            $this->logger->logBookingFlow($booking_id, $this->channel_id, $string, $xml);

            $result = $this->tourcmsWrapper->log_failed_payment($xml, $this->channel_id);

            // Store log of response
            $string = "Log Failed Payment Response";
            $this->logger->logBookingFlow($booking_id, $this->channel_id, $string, $result);
        }
    }


    public function cancelBooking($booking_id, $note = null)
    {
        $xml = new SimpleXMLElement('<booking />');
        $xml->addChild('booking_id', $booking_id);
        // Optionally add a note explaining why the booking is cancelled
        $xml->addChild('note', $note);

        // Store log of xml
        $string = "Log Cancel Booking XML";
        $this->logger->logBookingFlow($booking_id, $this->channel_id, $string, $xml);

        try {
            // Cancel the booking in tourCMS
            $result = $this->tourcmsWrapper->cancel_booking($xml, $this->channel_id);
            // Store log of response
            $string = "Log Cancel Booking Response XML";
            $this->logger->logBookingFlow($booking_id, $this->channel_id, $string, $result);
            // we are moving payment log api call to here so that if we have a previously cancelled booking, we're not logging a failed payment twice
            // as this triggers an email to customer
            $this->logFailedPayment($booking_id, $note);
            // Log new status in cart DB
            $this->cart->cancelBooking($booking_id, $this->channel_id);
        }
        catch (TourcmsException $e) {
            error_log("booking id $booking_id " . $e->getMessage());
            $this->logger->logBookingFlow($booking_id, $this->channel_id, $e->getMessage());
            // still want to return the reason to the end user, not something generic if we get the 'PREVIOUSLY CANCELLED' tourcms api error
        }


    }


    public function logAudit($booking_id, $string)
    {
        $note_type = "AUDIT";
        try {
            $this->tourcmsWrapper->add_note_to_booking($booking_id, $this->channel_id, $string, $note_type);
        }
        catch (Exception $e) {
            error_log("booking id $booking_id " . $e->getMessage());
            $this->logger->logBookingFlow($booking_id, $this->channel_id, $e->getMessage());
            // non blocking error, just a log
        }

    }

}
