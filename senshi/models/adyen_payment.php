<?php

namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

use Adyen\AdyenException;
use Adyen\Service\CheckoutUtility;

class AdyenPayment
{
    protected $logger;
    protected $adyenWebhookProcess;
    protected $adyenMoney;
    protected $adyenClient;
    protected $geoLocate;
    protected $version;
    protected $shopper_statement;
    protected $store;
    protected $merchantName;
    protected $merchantIdentifier;


    public function __construct(
        GeoLocate $geoLocate,
        Logger $logger,
        AdyenWebhookProcess $adyenWebhookProcess,
        AdyenMoney $adyenMoney,
        AdyenClient $adyenClient
    ) {
        // this works to include library config file ok
        include(GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."libraries/3rdparty/tourcms/config.php");
        $this->geoLocate = $geoLocate;
        $this->logger = $logger;
        $this->adyenWebhookProcess = $adyenWebhookProcess;
        $this->adyenMoney = $adyenMoney;
        $this->adyenClient = $adyenClient;
        $this->shopper_statement = $adyen_shopper_statement;
        $this->version = $adyen_version;
        $this->store = $store;
        $this->merchantName = $adyen_merchant_name;
        $this->merchantIdentifier = $adyen_merchant_identifier;
    }

    public function applePayConfig($total, $currency)
    {
        $result = array(
            "amount" => $this->adyenMoney->integerAmount($total, $currency),
            "currency" => (string)$currency,
            "countryCode" => $this->geoLocate->getCountryCode(),
            "configuration" => array(
                "merchantName" => $this->merchantName,
                "merchantIdentifier" => $this->merchantIdentifier,
            ),
        );

        return $result;
    }


    public function getMerchantAccount()
    {
       return $this->adyenClient->getMerchantName();
    }



    public function createOrder(
        $booking_id,
        $merchant_reference,
        $split,
        $channel_id,
        $total,
        $currency,
        $alt_total,
        $alt_currency,
        $lead_customer_name,
        $shopper_reference,
        $returnUrl,
        $data
    ) {
        try {
            $client = $this->adyenClient->getClient();
            $service = new \Adyen\Service\Checkout($client);

            if ($alt_currency && $alt_total) {
                $total = $alt_total;
                $currency = $alt_currency;
            }

            $acceptHeaders = isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8";

            $amount = $this->adyenMoney->integerAmount((string)$total, (string)$currency);

            $apple_pay_token = array_key_exists('applepay_token', $data) ? (string)$data['applepay_token'] : false;
            $apple_pay = $apple_pay_token ? 1 : 0;

            if ($apple_pay) {
                $payment_method = array(
                    "type" => "applepay",
                    "applepay.token" => $apple_pay_token,
                );
            } else {
                $payment_method = array(
                    "type" => "scheme",
                    "encryptedCardNumber" => (string)$data['encryptedCardNumber'],
                    "encryptedExpiryMonth" => (string)$data['encryptedExpiryMonth'],
                    "encryptedExpiryYear" => (string)$data['encryptedExpiryYear'],
                    "encryptedSecurityCode" => (string)$data['encryptedSecurityCode'],
                    "holderName" => (string)$lead_customer_name,
                );
            }

            $billing_address = array();
            // check to see if postal code is set
            // if ($data['postcode']) {
            //     $billing_address = array(
            //         'city' => $data['city'] ? htmlspecialchars((string)$data['city']) : 'ZZ',
            //         'country' => $data['country'] ? htmlspecialchars((string)$data['country']) : 'ZZ',
            //         'houseNumberOrName' => $data['house_number'] ? htmlspecialchars((string)$data['house_number']) : 'ZZ',
            //         'postalCode' => $data['postcode'] ? htmlspecialchars((string)$data['postcode']) : 'ZZ',
            //         'stateOrProvince' => $data['state'] ? htmlspecialchars((string)$data['state']) : 'ZZ',
            //         'street' => $data['address1'] ? htmlspecialchars((string)$data['address1']) : 'ZZ',
            //     );
            // }

            $params = array(
                "applicationInfo" => array(
                    "externalPlatform" => array(
                        "name" => "GrayLine.com",
                        "version" => $this->version,
                        "integrator" => "Palisis AG"
                    )
                ),
                "amount" => array(
                    "currency" => $currency,
                    "value" => $amount,
                ),
                "reference" => $merchant_reference,
                "splits" => $split,
                "paymentMethod" => $payment_method,
                "storePaymentMethod" => true,
                "shopperInteraction" => "Ecommerce",
                "shopperEmail" => htmlspecialchars((string)$data['email']),
                "shopperIP" => $this->geoLocate->getIp(),
                "shopperStatement" => $this->shopper_statement,
                "returnUrl" => $returnUrl,
                "merchantAccount" => $this->adyenClient->getMerchantName(),
                "additionalData" => array(
                    "executeThreeD" => "true",
                    "allow3DS2" => "true",
                ),
                "threeDS2RequestData" => [
                    "merchantName" => $this->shopper_statement,
                ],
                "shopperName" => array(
                    'firstName' => htmlspecialchars((string)$data['firstname']),
                    'lastName' => htmlspecialchars((string)$data['surname']),
                ),
                "billingAddress" => $billing_address,
                "browserInfo" => array(
                    "userAgent" => $_SERVER['HTTP_USER_AGENT'],
                    "acceptHeader" => $acceptHeaders,
                    "language" => (string)$data['language'],
                    "colorDepth" => (string)$data['colorDepth'],
                    "screenHeight" => (string)$data['screenHeight'],
                    "screenWidth" => (string)$data['screenWidth'],
                    "timeZoneOffset" => (string)$data['timezoneOffset'],
                    "javaEnabled" => false,
                ),
                "channel" => "Web",
                "origin" => home_url(),
            );

            if ($this->store) {
                $params['store'] = $this->store;
            }

            // Store log of adyen create order
            $string = "Adyen Create Order Params: ".json_encode($params, JSON_UNESCAPED_SLASHES);
            $this->logger->logBookingFlow($booking_id, $channel_id, $string);

            $result = $service->payments($params);

            // Store log of adyen create order result
            $string = "Adyen Create Order Result ".json_encode($result, JSON_UNESCAPED_SLASHES);
            $this->logger->logBookingFlow($booking_id, $channel_id, $string);

            $result['apple_pay'] = $apple_pay;

            return $result;

        } catch (AdyenException $e) {

            $errorMsg = "CRITICAL AdyenException Error adyen create order, booking ID:  $booking_id ";
            $errorMsg .= "{$e->getErrorType()}, {$e->getPspReference()}, {$e->getStatus()}, {$e->getCode()}, {$e->getFile()}, {$e->getLine()}, {$e->getMessage()}";

            // Store log of exception on booking
            $string = "Adyen Create Order AdyenException: ".$errorMsg;
            $this->logger->logBookingFlow($booking_id, $channel_id, $string);

            $publicError = $e->getMessage();
            $flag = 'CRITICAL';
            if (strtolower($e->getMessage()) === "invalid card number") {
                $flag = 'DEFAULT';
            }
            else if (strtolower($e->getMessage()) === strtolower("Payment details are not supported")) {
                $flag = 'DEFAULT';
                $error_msg = $e->getMessage() . ". Please choose another payment type (we support: ";
                $apm = Dic::objectAdyenPaymentMethods();
                $amount = $this->adyenMoney->integerAmount((string)$total, (string)$currency);
                $brands = $apm->brandNames($currency, $amount);
                $error_msg .= $brands .')';
                $publicError = $error_msg;
            }
            try {
                // get email alert for critical items
                throw new \AdyenPaymentException($errorMsg, $flag);
            }
            catch (AdyenPaymentException $e) {
                // continue...
            }

            throw new PublicException($publicError);


        } catch (\Exception $e) {
            $errorMsg = "CRITICAL Exception Error adyen create order, booking ID:  $booking_id ";
            $errorMsg .= "{$e->getCode()}, {$e->getFile()}, {$e->getLine()}, {$e->getMessage()}";

            // Store log of exception on booking
            $string = "Adyen Create Order Exception: ".$errorMsg;
            $this->logger->logBookingFlow($booking_id, $channel_id, $string);

            throw new AdyenPaymentException($errorMsg, 'CRITICAL');
        }
    }

    public function paymentDetails($booking_id, $merchant_reference, $channel_id, $md, $pa_res, $payload, $payment_data)
    {
        try {
            $client = $this->adyenClient->getClient();
            $service = new \Adyen\Service\Checkout($client);
            $params = null;
            if ($md && $pa_res) {
                $params = array(
                    "paymentData" => $payment_data,
                    "details" => array(
                        "MD" => $md,
                        "PaRes" => $pa_res,
                    ),
                );
            } else {
                if ($payload) {
                    $params = array(
                        "paymentData" => $payment_data,
                        "details" => array(
                            "redirectResult" => $payload,
                        ),
                    );
                }
            }

            // Store log
            $string = "Adyen Payment Details Params ".json_encode($params, JSON_UNESCAPED_SLASHES);
            $this->logger->logBookingFlow($booking_id, $channel_id, $string);

            $result = $service->paymentsDetails($params);

            // Store log
            $string = "Adyen Payment Details Result ".json_encode($result, JSON_UNESCAPED_SLASHES);
            $this->logger->logBookingFlow($booking_id, $channel_id, $string);

            return $result;

        } catch (AdyenException $e) {

            $errorMsg = "CRITICAL AdyenException Error adyen payment details, booking ID:  $booking_id ";
            $errorMsg .= "{$e->getErrorType()}, {$e->getPspReference()}, {$e->getStatus()}, {$e->getCode()}, {$e->getFile()}, {$e->getLine()}, {$e->getMessage()}";

            // Store log of exception on booking
            $string = "Adyen Payment Details AdyenException: ".$errorMsg;
            $this->logger->logBookingFlow($booking_id, $channel_id, $string);

            $flag = 'CRITICAL';
            if (strtolower($e->getMessage()) === "invalid card number") {
                $flag = 'DEFAULT';
                throw new PublicException($e->getMessage(), $flag);
            } else {
                // it could be the case that the booking is a duplicate / timeout
                // so we have to use async webhook notifications to check status of payment
                $result = $this->webhookNotificationsCheck($merchant_reference, $booking_id, $channel_id, $e);

                return $result;
            }


        } catch (\Exception $e) {
            $errorMsg = "CRITICAL Exception Error adyen payment details, booking ID:  $booking_id ";
            $errorMsg .= "{$e->getCode()}, {$e->getFile()}, {$e->getLine()}, {$e->getMessage()}";

            // Store log of exception on booking
            $string = "Adyen Payment Details Exception: ".$errorMsg;
            $this->logger->logBookingFlow($booking_id, $channel_id, $string);

            throw new AdyenPaymentException($errorMsg, 'CRITICAL');
        }
    }

//
//
//    public function paymentDetails3ds2(
//        $booking_id,
//        $merchant_reference,
//        $channel_id,
//        $fingerprint_result,
//        $challenge_result,
//        $payment_data
//    ) {
//        try {
//            $client = $this->adyenClient->getClient();
//            $service = new \Adyen\Service\Checkout($client);
//            $params = null;
//            if ($fingerprint_result) {
//                $params = array(
//                    "paymentData" => $payment_data,
//                    "details" => array(
//                        "threeds2.fingerprint" => $fingerprint_result,
//                    ),
//                );
//            } else {
//                if ($challenge_result) {
//                    $params = array(
//                        "paymentData" => $payment_data,
//                        "details" => array(
//                            "threeds2.challengeResult" => $challenge_result,
//                        ),
//                    );
//                }
//            }
//
//            // Store log
//            $string = "Adyen Payment Details 3ds2 Params ".json_encode($params, JSON_UNESCAPED_SLASHES);
//            $this->logger->logBookingFlow($booking_id, $channel_id, $string);
//
//            $result = $service->paymentsDetails($params);
//
//            // Store log
//            $string = "Adyen Payment Details 3ds2 Result ".json_encode($result, JSON_UNESCAPED_SLASHES);
//            $this->logger->logBookingFlow($booking_id, $channel_id, $string);
//
//
//            return $result;
//
//        } catch (AdyenException $e) {
//
//            $errorMsg = "CRITICAL AdyenException Error adyen payment details 3ds2, booking ID:  $booking_id ";
//            $errorMsg .= "{$e->getErrorType()}, {$e->getPspReference()}, {$e->getStatus()}, {$e->getCode()}, {$e->getFile()}, {$e->getLine()}, {$e->getMessage()}";
//
//            // Store log of exception on booking
//            $string = "Adyen Payment Details 3ds2 AdyenException: ".$errorMsg;
//            $this->logger->logBookingFlow($booking_id, $channel_id, $string);
//
//            $flag = 'CRITICAL';
//            if (strtolower($e->getMessage()) === "invalid card number") {
//                $flag = 'DEFAULT';
//                throw new PublicException($e->getMessage(), $flag);
//            } else {
//                // it could be the case that the booking is a duplicate / timeout
//                // so we have to use async webhook notifications to check status of payment
//                $result = $this->webhookNotificationsCheck($merchant_reference, $booking_id, $channel_id, $e);
//
//                return $result;
//            }
//
//
//        } catch (\Exception $e) {
//            $errorMsg = "CRITICAL Exception Error adyen payment details 3ds2, booking ID:  $booking_id ";
//            $errorMsg .= "{$e->getCode()}, {$e->getFile()}, {$e->getLine()}, {$e->getMessage()}";
//
//            // Store log of exception on booking
//            $string = "Adyen Payment Details Exception: ".$errorMsg;
//            $this->logger->logBookingFlow($booking_id, $channel_id, $string);
//
//            throw new AdyenPaymentException($errorMsg, 'CRITICAL');
//        }
//    }


    public function paymentDetailsStateData(
        $booking_id,
        $merchant_reference,
        $channel_id,
        $post_vars,
        $payment_data
    ) {
        try {
            $client = $this->adyenClient->getClient();
            $service = new \Adyen\Service\Checkout($client);

            $params = array(
                "paymentData" => $payment_data,
                "details" => $post_vars['data']['details']
            );

            // Store log
            $string = "Adyen Payment Details State Data Params ".json_encode($params, JSON_UNESCAPED_SLASHES);
            $this->logger->logBookingFlow($booking_id, $channel_id, $string);

            $result = $service->paymentsDetails($params);

            // Store log
            $string = "Adyen Payment Details State Data Result ".json_encode($result, JSON_UNESCAPED_SLASHES);
            $this->logger->logBookingFlow($booking_id, $channel_id, $string);


            return $result;

        } catch (AdyenException $e) {

            $errorMsg = "CRITICAL AdyenException Error adyen payment details State Data, booking ID:  $booking_id ";
            $errorMsg .= "{$e->getErrorType()}, {$e->getPspReference()}, {$e->getStatus()}, {$e->getCode()}, {$e->getFile()}, {$e->getLine()}, {$e->getMessage()}";

            // Store log of exception on booking
            $string = "Adyen Payment Details State Data AdyenException: ".$errorMsg;
            $this->logger->logBookingFlow($booking_id, $channel_id, $string);

            $flag = 'CRITICAL';
            if (strtolower($e->getMessage()) === "invalid card number") {
                $flag = 'DEFAULT';
                throw new PublicException($e->getMessage(), $flag);
            } else {
                // it could be the case that the booking is a duplicate / timeout
                // so we have to use async webhook notifications to check status of payment
                $result = $this->webhookNotificationsCheck($merchant_reference, $booking_id, $channel_id, $e);

                return $result;
            }


        } catch (\Exception $e) {
            $errorMsg = "CRITICAL Exception Error adyen payment details State Data, booking ID:  $booking_id ";
            $errorMsg .= "{$e->getCode()}, {$e->getFile()}, {$e->getLine()}, {$e->getMessage()}";

            // Store log of exception on booking
            $string = "Adyen Payment Details Exception: ".$errorMsg;
            $this->logger->logBookingFlow($booking_id, $channel_id, $string);

            throw new AdyenPaymentException($errorMsg, 'CRITICAL');
        }
    }

    public function webhookNotificationsCheck($merchant_reference, $booking_id, $channel_id, $e)
    {
        $string = "WebhookNotificationsCheck initiated due to error message: ".$e->getMessage();
        $this->logger->logBookingFlow($booking_id, $channel_id, $string);
        // error could be that the payment is already in process / completed / timeout error from Adyen
        try {
            // Store log of exception on booking
            // Note real world data took just under 90s to arrive in DB, so we now have async processing of timeout bookings auto updating them
            $string = "BOOKING IN PROCESS / ALREADY PROCESSED, Sleep 10 seconds then check webhook notifications";
            $this->logger->logBookingFlow($booking_id, $channel_id, $string);
            sleep(10);

            $result = $this->adyenWebhookProcess->resultFromDb($merchant_reference);

            if ($result) {
                // Store log of adyen create order result
                $string = "Waited 10, Adyen webhookNotificationsCheck Result ".json_encode($result, JSON_UNESCAPED_SLASHES);
                $this->logger->logBookingFlow($booking_id, $channel_id, $string);

                return $result;
            }
            sleep(5);
            $result = $this->adyenWebhookProcess->resultFromDb($merchant_reference);
            if ($result) {
                // Store log of adyen create order result
                $string = "Waited 15, Adyen webhookNotificationsCheck Result ".json_encode($result, JSON_UNESCAPED_SLASHES);
                $this->logger->logBookingFlow($booking_id, $channel_id, $string);

                return $result;
            }
            sleep(5);
            $result = $this->adyenWebhookProcess->resultFromDb($merchant_reference);
            if ($result) {
                // Store log of adyen create order result
                $string = "Waited 20, Adyen webhookNotificationsCheck Result ".json_encode($result, JSON_UNESCAPED_SLASHES);
                $this->logger->logBookingFlow($booking_id, $channel_id, $string);

                return $result;
            }
            sleep(5);
            $result = $this->adyenWebhookProcess->resultFromDb($merchant_reference);
            if ($result) {
                // Store log of adyen create order result
                $string = "Waited 25, Adyen webhookNotificationsCheck Result ".json_encode($result, JSON_UNESCAPED_SLASHES);
                $this->logger->logBookingFlow($booking_id, $channel_id, $string);

                return $result;
            }
            sleep(5);
            $result = $this->adyenWebhookProcess->resultFromDb($merchant_reference);
            if ($result) {
                // Store log of adyen create order result
                $string = "Waited 30, Adyen webhookNotificationsCheck Result ".json_encode($result, JSON_UNESCAPED_SLASHES);
                $this->logger->logBookingFlow($booking_id, $channel_id, $string);

                return $result;
            }
            sleep(5);
            $result = $this->adyenWebhookProcess->resultFromDb($merchant_reference);
            if ($result) {
                // Store log of adyen create order result
                $string = "Waited 35, Adyen webhookNotificationsCheck Result ".json_encode($result, JSON_UNESCAPED_SLASHES);
                $this->logger->logBookingFlow($booking_id, $channel_id, $string);

                return $result;
            }
            sleep(5);
            $result = $this->adyenWebhookProcess->resultFromDb($merchant_reference);
            if ($result) {
                // Store log of adyen create order result
                $string = "Waited 40, Adyen webhookNotificationsCheck Result ".json_encode($result, JSON_UNESCAPED_SLASHES);
                $this->logger->logBookingFlow($booking_id, $channel_id, $string);

                return $result;
            }
            sleep(5);
            $result = $this->adyenWebhookProcess->resultFromDb($merchant_reference);
            if ($result) {
                // Store log of adyen create order result
                $string = "Waited 45, Adyen webhookNotificationsCheck Result ".json_encode($result, JSON_UNESCAPED_SLASHES);
                $this->logger->logBookingFlow($booking_id, $channel_id, $string);

                return $result;
            }
            sleep(5);
            $result = $this->adyenWebhookProcess->resultFromDb($merchant_reference);
            if ($result) {
                // Store log of adyen create order result
                $string = "Waited 50s, Adyen webhookNotificationsCheck Result ".json_encode($result, JSON_UNESCAPED_SLASHES);
                $this->logger->logBookingFlow($booking_id, $channel_id, $string);

                return $result;
            } else {
                // Store log of exception on booking
                $string = "Slept 50 seconds, cannot access booking in webhook db for merchant ref $merchant_reference";
                $this->logger->logBookingFlow($booking_id, $channel_id, $string);

                throw new AdyenWebhookException(
                    "Adyen Webhook lookup fail for merchant ref ($merchant_reference) timeout booking_id $booking_id. PLEASE MANUALLY CHECK THIS BOOKING IN ADYEN AND MARK AS AUTHORISED / FAILED ACCORDINGLY, OR WAIT FOR AN ASYNC AUTO-UPDATE"
                );
            }

        } // doing this on purpose, so calling code can process this
        catch (AdyenWebhookException $e) {
            throw new AdyenWebhookException($e->getMessage(), 'CRITICAL');
        } // catching potential other types of exception
        catch (\Exception $e) {
            $errorMsg = "ERROR, Exception webhookNotificationsCheck ";
            $extra = $e == null ? $e : "{$e->getCode()}, {$e->getFile()}, {$e->getLine()}, {$e->getMessage()}";
            $errorMsg .= $extra;

            // Store log of exception on booking
            $string = "Adyen WebhookNotificationCheck  Exception: ".$errorMsg." ".$e->getMessage();
            $this->logger->logBookingFlow($booking_id, $channel_id, $string);

            throw new AdyenPaymentException($errorMsg, 'CRITICAL');
        }
    }


}
