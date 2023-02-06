<?php

namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

class AdyenWebhookProcess
{

    protected $tourcmsBookingFinalise;

    public function __construct(TourcmsBookingFinalise $tourcmsBookingFinalise)
    {
        $this->tourcmsBookingFinalise = $tourcmsBookingFinalise;
    }

    public function dbStore($postVars, $tourcms_account_id)
    {
        global $wpdb;

        $result = null;
        $currency = $postVars['currency'];
        $total = $postVars['value'];
        $eventCode = $postVars['eventCode'];
        $eventDate = $postVars['eventDate'];
        $merchantAccountCode = $postVars['merchantAccountCode'];
        $merchantReference = $postVars['merchantReference'];
        $originalReference = $postVars['originalReference'];
        $pspReference = $postVars['pspReference'];
        $reason = $postVars['reason'];
        $success = $postVars['success'];
        $paymentMethod = $postVars['paymentMethod'];
        $operations = $postVars['operations'];
        $additionalData = $postVars['additionalData'];
        $details = null;
        $sql = null;
        try {
            if (!$merchantReference && !$pspReference) {
                $publicError = "Adyen webhook notification error missing merchantReference {$merchantReference} or pspReference {$pspReference}";
                throw new AdyenWebhookException($publicError);
            }
            // we only want to store GLWW payments, not all Market Pay bookings.
            if (!$this->confirmPaymentAccount($tourcms_account_id, $merchantReference)) {
                return true;
            }
            // problems with sql accepting Adyen Zulu Time (e.g. 2020-09-16T13:55:44.76Z)
            $eventDateTime = strtotime($eventDate);
            $sql_datetime = date("Y-m-d H:i:s", $eventDateTime);
            $now = date("Y-m-d H:i:s");

            // we need some data for TourCMS population about card & cardholder
            $card_payment_method = 1;
            // additional data array build
            $array = [];
            foreach ($postVars as $key => $val) {
                if (strpos($key, 'additionalData_') !== false) {
                    $name = substr($key, 15);
                    // slightly weird where 2 values are different from Adyen direct result vs Adyen Webhook
                    // recurring_recurringDetailReference vs recurring.recurringDetailReference
                    // recurring_shopperReference vs recurring.shopperReference
                    // we have to fix this, so later processing calls to webhook generated $result don't break
                    $name = str_replace('_', '.', $name);
                    $array[$name] = $val;
                }
            }
            $array['cardPaymentMethod'] = $postVars['paymentMethod'];

            $additional_data_json = json_encode($array, JSON_UNESCAPED_SLASHES);

            // $query =  $wpdb->prepare(
                
            //     "INSERT INTO adyen_webhooks (logged, currency, total, eventCode, eventDate, merchantAccountCode, merchantReference, originalReference, pspReference, reason, success, paymentMethod, operations, additionalData) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)",
                
            //     array(
            //         $cart_id,
            //         $channel_id
            //     )
            // );

            // $rows = $wpdb->get_results($query, ARRAY_A);
            $sql = "INSERT INTO adyen_webhooks (logged, currency, total, eventCode, eventDate, merchantAccountCode, merchantReference, originalReference, pspReference, reason, success, paymentMethod, operations, additionalData) VALUES (:now, :currency, :total, :eventCode, :eventDate, :merchantAccountCode, :merchantReference, :originalReference, :pspReference, :reason, :success, :paymentMethod, :operations, :additionalData)";
            $q = $conn->prepare($sql);
            $details = [
                ':now' => $now,
                ':currency' => $currency,
                ':total' => $total,
                ':eventCode' => $eventCode,
                ':eventDate' => $sql_datetime,
                ':merchantAccountCode' => $merchantAccountCode,
                ':merchantReference' => $merchantReference,
                ':originalReference' => $originalReference,
                ':pspReference' => $pspReference,
                ':reason' => $reason,
                ':success' => $success,
                ':paymentMethod' => $paymentMethod,
                ':operations' => $operations,
                ':additionalData' => $additional_data_json,
            ];

            $result = $q->execute($details);
            try {
                sleep(5); // wait for db to be updated and booking to be marked as timeout
                $pieces = explode('_', $merchantReference);
                $booking_id = $pieces[2];
                $adyen_result = $this->resultFromDb($merchantReference);
                $this->tourcmsBookingFinalise->finaliseTimeoutBooking($booking_id, $success, $adyen_result);
            } catch (\Exception $e) {
                error_log($e->getMessage());
                // do nothing, don't halt this script, we're already logging above
            }

        } catch (\Exception $e) {
            $data = json_encode($details, JSON_UNESCAPED_SLASHES);
            $result_string = json_encode($result, JSON_UNESCAPED_SLASHES);
            $publicError = "Adyen webhook notification error {query} $sql\n, {queryData} $data\n, {result} $result_string \n{error} ".$e->getMessage(
                );
            throw new AdyenWebhookException($publicError);
        }
        // we must always accept notifications, even if they are duplicates (db won't store duplicates)
        if (!$result) {
            // continue
        }

        return true;
    }

    protected function confirmPaymentAccount($tourcms_account_id, $merchant_reference)
    {
        $correct_account = false;
        $length = strlen($tourcms_account_id);
        $match = substr($merchant_reference, 0, $length);
        if ($match === (string)$tourcms_account_id) {
            $correct_account = true;
        }

        return $correct_account;

    }

    public function resultFromDb($merchant_reference)
    {
        // we need to build up a result array for further processing
        // important to have additional data here, and shopper reference and card token etc
        $row = $this->dbGetRow($merchant_reference);

        if ($row) {
            $result = array();
            $resultCode = $row['eventCode']." ".$row['success'];
            if ($row['eventCode'] === 'AUTHORISATION') {
                if ($row['success'] === 'true') {
                    $resultCode = "Authorised";
                } else {
                    if ($row['success'] === 'false') {
                        $resultCode = "Refused";
                        $result['refusalReason'] = $row['reason'];
                    }
                }
            }
            $result['resultCode'] = $resultCode;
            $result['pspReference'] = $row['pspReference'];
            $result['refusalReason'] = $row['reason'];
            $additional_data = json_decode($row['additionalData'], true);
            if (! isset( $additional_data['cardPaymentMethod'])) {
                $additional_data['cardPaymentMethod'] = $row['paymentMethod'];
            }
            $result['additionalData'] = $additional_data;

            return $result;
        } else {
            return false;
        }
    }


    protected function dbGetRow($merchantReference)
    {
        // Query the DB to get our items
        $sql = $conn->prepare("SELECT * FROM adyen_webhooks WHERE merchantReference = :merchantReference");
        $details = array(':merchantReference' => $merchantReference);
        $sql->execute($details);

        $rows = $sql->fetchAll();

        if (is_array($rows) && isset($rows[0])) {
            $row = $rows[0];

            return $row;
        } else {
            return false;
        }
    }

    /**
     * Receive notifcations from Adyen using HTTP Post
     *
     * Whenever a payment is made, a modification is processed or a report is
     * available we will notify you. The notifications tell you for instance if
     * an authorisation was performed successfully.
     * Notifications should be used to keep your backoffice systems up to date with
     * the status of each payment and modification. Notifications are sent
     * using a SOAP call or using HTTP POST to a server of your choice.
     * This file describes how HTTP Post notifcations can be received in PHP.
     *
     * @link    3.Notifications/httppost/notification_server.php
     * @author    Created by Adyen - Payments Made Easy
     */

    /**
     * The variabele $_POST contains an array including
     * the following keys.
     *
     * $_POST['currency']
     * $_POST['value']
     * $_POST['eventCode']
     * $_POST['eventDate']
     * $_POST['merchantAccountCode']
     * $_POST['merchantReference']
     * $_POST['originalReference']
     * $_POST['pspReference']
     * $_POST['reason']
     * $_POST['success']
     * $_POST['paymentMethod']
     * $_POST['operations']
     * $_POST['additionalData']
     *
     *
     * We recommend you to handle the notifications based on the
     * eventCode types available, please refer to the integration
     * manual for a comprehensive list. For debug purposes we also
     * recommend you to store the notification itself.
     *
     * Security:
     * We recommend you to secure your notification server. You can secure it
     * using a username/password which can be configured in the CA. The username
     * and password will be available in the request in: $_SERVER['PHP_AUTH_USER'] and
     * $_SERVER['PHP_AUTH_PW']. Alternatively, is to allow only traffic that
     * comes from Adyen servers.
     */
    //        switch ($_POST['eventCode']) {
//
//            case 'AUTHORISATION':
//                // Handle AUTHORISATION notification.
//                // Confirms whether the payment was authorised successfully.
//                // The authorisation is successful if the "success" field has the value true.
//                // In case of an error or a refusal, it will be false and the "reason" field
//                // should be consulted for the cause of the authorisation failure.
//                break;

//            case 'CANCELLATION':
//                // Handle CANCELLATION notification.
//                // Confirms that the payment was cancelled successfully.
//                break;
//
//            case 'REFUND':
//                // Handle REFUND notification.
//                // Confirms that the payment was refunded successfully.
//                break;
//
//            case 'CANCEL_OR_REFUND':
//                // Handle CANCEL_OR_REFUND notification.
//                // Confirms that the payment was refunded or cancelled successfully.
//                break;
//
//            case 'CAPTURE':
//                // Handle CAPTURE notification.
//                // Confirms that the payment was successfully captured.
//                break;
//
//            case 'REFUNDED_REVERSED':
//                // Handle REFUNDED_REVERSED notification.
//                // Tells you that the refund for this payment was successfully reversed.
//                break;
//
//            case 'CAPTURE_FAILED':
//                // Handle AUTHORISATION notification.
//                // Tells you that the capture on the authorised payment failed.
//                break;
//
//            case 'REQUEST_FOR_INFORMATION':
//                // Handle REQUEST_FOR_INFORMATION notification.
//                // Information requested for this payment .
//                break;
//
//            case 'NOTIFICATION_OF_CHARGEBACK':
//                // Handle NOTIFICATION_OF_CHARGEBACK notification.
//                // Chargeback is pending, but can still be defended
//                break;
//
//            case 'CHARGEBACK':
//                // Handle CHARGEBACK notification.
//                // Payment was charged back. This is not sent if a REQUEST_FOR_INFORMATION or
//                // NOTIFICATION_OF_CHARGEBACK notification has already been sent.
//                break;
//
//            case 'CHARGEBACK_REVERSED':
//                // Handle CHARGEBACK_REVERSED notification.
//                // Chargeback has been reversed (cancelled).
//                break;
//
//            case 'REPORT_AVAILABLE':
//                // Handle REPORT_AVAILABLE notification.
//                // There is a new report available, the URL of the report is in the "reason" field.
//                break;
//        }
}
