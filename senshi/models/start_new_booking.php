<?php

namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

class StartNewBooking
{
    protected $errors;
    protected $tourcmsWrapper;
    protected $booking_id;
    protected $cart;
    protected $bookingKey;
    protected $fex;
    protected $searchBookingsService;
    protected $cart_id;
    protected $logger;
    protected $channel_id;
    protected $postVars;
    protected $items = array();
    protected $bookingXml;
    protected $promoCode;
    protected $promoMemb;
    protected $env;
    const DEBUG_ID = '0000_DEBUG';

    protected $resultXml;
    protected $actual_amount_due_now;
    protected $sale_currency;
    protected $alt_currency;
    protected $alt_total; // we use internal php on tourcms value to generate this inside PHP only
    private $alt_amount; // only use this for logging, not transactions, susceptible to hijacking
    protected $alt_amount_hash; // we need this now, due to subtotal / total mismatch on FEX, we might need to rely on the alt amount posted via Ajax

    public function __construct(
        TourcmsWrapper $tourcmsWrapper,
        Cart $cart,
        BookingKey $bookingKey,
        Fex $fex,
        SearchBookingsService $searchBookingsService,
        array $postVars,
        Logger $logger
    ) {
        // this works to include library config file ok
        include(GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."libraries/3rdparty/tourcms/config.php");
        $this->env = $adyen_env;
        $this->tourcmsWrapper = $tourcmsWrapper;
        $this->cart = $cart;
        $this->fex = $fex;
        $this->bookingKey = $bookingKey;
        $this->searchBookingsService = $searchBookingsService;
        $this->cart_id = $cart->getCartId();
        $this->channel_id = $tourcmsWrapper->getChannelId();
        $this->postVars = $postVars;
        $this->logger = $logger;
    }

    public function setPostVars($vars)
    {
        $this->postVars = $vars;
    }

    public function getResultXml()
    {
        return $this->resultXml;
    }

    public function getBookingId()
    {
        return $this->booking_id;
    }

    public function getTotal()
    {
        return $this->actual_amount_due_now;
    }

    public function getCurrency()
    {
        return $this->sale_currency;
    }


    public function getAltCurrency()
    {
        return $this->alt_currency;
    }

    public function getAltTotal()
    {
        return $this->alt_total;
    }

    // Used to add booking to existing customer record, if customer exists inside tourcms
    public function searchCustomerId($email, $firstname, $surname)
    {
        try {
            $this->logger->logBookingFlow(
                self::DEBUG_ID,
                $this->channel_id,
                "DEBUG: Searching for customer $email $firstname $surname"
            );
            $this->searchBookingsService->searchBookings($email, $firstname, $surname);
            $customer_id = $this->searchBookingsService->getCustomerId();
            $this->logger->logBookingFlow(
                self::DEBUG_ID,
                $this->channel_id,
                "DEBUG: Customer ID for $email returned $customer_id"
            );
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
            $this->logger->logBookingFlow(
                self::DEBUG_ID,
                $this->channel_id,
                "DEBUG: NO Customer ID for email $email, $errorMsg"
            );
            // no customer record found for email, so store as new customer
            $customer_id = null;
        }

        return $customer_id;
    }

    private function get_alt_amount_hash($amount)
    {
        return hash("md5", "9^SsS6DXd1m$".($amount * 100));
    }

    // This is a quick call to TourCMS in order to get the shopping cart price
    // Check availability of everything and check for any errors
    public function startNewBooking($onLoad = false)
    {
        try {
            $vars_string = implode(", ", $this->postVars);
            $this->logger->logBookingFlow(self::DEBUG_ID, $this->channel_id, "DEBUG: Booking Initiated $vars_string");
            // Check if we have any existing temporary bookings
            try {
                $this->cart->processTemporaryBooking($this->channel_id, $this->tourcmsWrapper);
            }
            catch (TourcmsException $e) {
                $errorMsg = "Process Temporary Booking - Error: CART ID {$this->cart_id}, Message: ".$e->getMessage();
                $this->logger->logBookingFlow(self::DEBUG_ID, $this->channel_id, $errorMsg);
                // continue on, we might just not be able to delete a temporary booking, not a show stopper
            }
            // Get the cart items from the DB
            $items = $this->cart->getItemsFromDb($this->channel_id);
            if (!$items || is_array($items) && count($items) == 0) {
                $errorMsg = "cannot find any cart items for cart_id {$this->cart_id}";
                $redirect_url = DIR_REL."/cart?status=error&action=checkout_payment";
                throw new RedirectException($errorMsg, $redirect_url);
            }
            $this->items = $items;

            $bookingXml = $this->generateBookingXml($onLoad);
            $this->logger->logBookingFlow(self::DEBUG_ID, $this->channel_id, "DEBUG: Start New Booking Called");
            $temp_booking = $this->tourcmsWrapper->start_new_booking($bookingXml, $this->channel_id);

            $this->resultXml = $temp_booking;

            $booking_id = (int)$this->resultXml->booking->booking_id;
            $this->logger->logBookingFlow(self::DEBUG_ID, $this->channel_id, "DEBUG: Booking ID $booking_id");
            $this->booking_id = $booking_id;

            // LOG INFO ON BOOKING IN INDIVIDUAL LOG FILES ONE PER BOOKING ID (can't do this until after we've called
            // start new booking, as we don't have a booking_id until then
            // Store log of cart items
            foreach ($this->items as $cart_item) {
                $string = "Item JSON: ".json_encode($cart_item);
                $this->logger->logBookingFlow($booking_id, $this->channel_id, $string);
            }
            // Store log of start new booking XML
            $string = "Start XML";
            $this->logger->logBookingFlow($booking_id, $this->channel_id, $string, $bookingXml);

            // Store log of response to start new booking
            $string = "Response to Start XML";
            $this->logger->logBookingFlow($booking_id, $this->channel_id, $string, $this->resultXml);

            $num_unavailable = (int)$this->resultXml->unavailable_component_count;
            if ($num_unavailable > 0) {
                $errorMsg = "Start New Booking Error - unavailable items ({$num_unavailable}) cart_id({$this->cart_id})";
                $redirect_url = wp_redirect(home_url("/checkout_check?channel_id=$this->channel_id&from=checkout_payment&check=all"));
                // non critical
                throw new RedirectException($errorMsg, $redirect_url);
            }

            $this->sale_currency = (string)$this->resultXml->booking->sale_currency;
            // A bug in "_due_now" values, not taking into account the fixed price promo
            // $actual_amount_due_now = (string)$temp_booking->booking->sales_revenue_due_now;
            $actual_amount_due_now = (float)$temp_booking->booking->sales_revenue_due_now > (float)$temp_booking->booking->sales_price_due_ever ? (string)$temp_booking->booking->sales_price_due_ever : (string)$temp_booking->booking->sales_revenue_due_now;
            $this->actual_amount_due_now = $actual_amount_due_now;
            // ** NOTE MUST VERIFY THE ALT AMOUNT BEFORE USAGE ON PAYMENT PROCESSING, COULD HAVE BEEN HIJACKED ON POST
            $altC = $this->postVars['alt_currency'] === $this->sale_currency ? null : strtoupper(
                $this->postVars['alt_currency']
            );
            $altA = $this->postVars['alt_amount'];
            $this->alt_amount_hash = $this->postVars['a_a_h_2'];
            if ($altC && $altC !== 'USD') {
                // Alt amount hash could contain a slightly different value due to subtotal / total non match on FEX conversion
                try {
                    $alt_hash = $this->get_alt_amount_hash($altA);
                    $altTotal = $this->fex->convertPrice(
                        $altC,
                        $this->actual_amount_due_now
                    );
                    $this->logger->logBookingFlow(
                        $booking_id,
                        $this->channel_id,
                        "FEX Transaction. Alt_A ($altA), altTotal $altTotal, a_a_h_2 {$this->alt_amount_hash}, altA hashed for comparison $alt_hash"
                    );
                    // if the alt total post is the same as the alt total calculated, e.g. single item checkout, just proceed
                    if ($altTotal === $altA) {
                        $this->logger->logBookingFlow(
                            $booking_id,
                            $this->channel_id,
                            "DEBUG FEX: alt total calculated is same as post alt total, proceed...."
                        );
                    } else {
                        if ($this->alt_amount_hash !== $alt_hash) {
                            $message = "Hash problem, posted hash value ($this->alt_amount_hash) does not match hash ($alt_hash) of alt amount ($altA). Fatal error, stop transaction";
                            throw new PaymentException($message, 'CRITICAL');
                        }
                        // assume we always get an alt amount hash on all fex transactions, allow a 5% margin of error
                        $ceiling = 1.05 * $altTotal;
                        $floor = 0.95 * $altTotal;
                        if ($altA <= $ceiling && $altA >= $floor) {
                            $altTotal = $altA;
                            $this->logger->logBookingFlow(
                                $booking_id,
                                $this->channel_id,
                                "Alt Amount from POST ($altA) is within range. Floor ($floor), Alt Total ($altTotal), Ceiling $ceiling. Proceed"
                            );
                        } else {
                            $message = "Alt Amount from POST ($altA) is NOT within range. Floor ($floor), Alt Total ($altTotal), Ceiling $ceiling. DO NOT Proceed";
                            throw new PaymentException($message, 'CRITICAL');
                        }
                    }
                } catch (PaymentException $e) {

                    // we continue on anyway, but log this
                    $this->logger->logBookingFlow($booking_id, $this->channel_id, $e->getMessage());
                    throw new PublicException(
                        "Sorry there was a problem with the foreign exchange values. Please refresh & try again"
                    );
                }
                $this->alt_total = $altTotal;
                $this->alt_currency = $altC;
                $this->alt_amount = $altTotal;

                // Log the currency conversion
                $string = "Converting ".$actual_amount_due_now." ".$this->sale_currency." to ".$this->alt_amount." ".$this->alt_currency;
                $this->logger->logBookingFlow($booking_id, $this->channel_id, $string);

            } else {
                $this->alt_amount = null;
                $this->alt_currency = null;
            }
            // ** NOTE MUST VERIFY THE ALT AMOUNT BEFORE USAGE ON PAYMENT PROCESSING, COULD HAVE BEEN HIJACKED ON POST

            // Record temporary booking in DB
            $this->cart->storeTemporaryBooking(
                $booking_id,
                $this->channel_id,
                $this->actual_amount_due_now,
                $this->sale_currency,
                $this->alt_amount,
                $this->alt_currency
            );


            // TODO - change this, there will be a CC Fee
            //$this->creditCardFee($resultXml);
            // If it was an onload call, we delete the booking to freeup stock
            try {
                if ($onLoad) {
                    $this->tourcmsWrapper->delete_booking($this->booking_id, $this->channel_id);
                }
            } catch (TourcmsException $e) {
                // if we have an INVALID BOOKING ID error when trying to delete, just ignore and continue on, don't redirect
                return true;
            }

            // if there is a currency discrepancy, note this, but do not stop the flow, as we use our own PHP wrapped conversion value,
            // not the vulnerable POST value
            if ($altC && $altA != $this->alt_amount) {
                $postConversion = "{$this->sale_currency} {$this->actual_amount_due_now} to {$altC} {$altA}";
                $actualConversion = "{$this->sale_currency} {$this->actual_amount_due_now} to {$this->alt_currency} {$this->alt_amount}";
                $errorMsg = "Currency Conversion Error Booking $booking_id. POST $postConversion. ACTUAL $actualConversion";
                throw new AdyenPaymentException($errorMsg, 'CRITICAL');
            }

            // we want to catch this redirect exception in calling code, so that we can redirect via AJAX or PHP accordingly
//        catch (RedirectException $e) {
//            echo $e->getMessage() . " redirect to " . $e->getRedirectUrl();
//            error_log($e->getMessage());
//            header("Location: ". $e->getRedirectUrl());
//            exit;
//        }
        } catch (AdyenPaymentException $e) {
            return true;
        } catch (TourcmsException $e) {
            $errorMsg = "Start New Booking - Error: CART ID {$this->cart_id}, Message: ".$e->getMessage();
            $booking_id = $this->booking_id ? $this->booking_id : self::DEBUG_ID;
            $this->logger->logBookingFlow($booking_id, $this->channel_id, $e->getMessage());
            // could be the fact that the booking key has expired, num available no longer valid etc
            $redirect_url = wp_redirect(home_url("/checkout_check?channel_id=$this->channel_id&from=checkout_payment&check=all"));
            throw new RedirectException($errorMsg, $redirect_url);
        }

    }


    private function generateBookingXml($onLoad = false)
    {
        $booking_start_date = "9999-99-99";
        $booking_end_date = "0000-00-00";
        $people_on_booking = 1;
        $bookingXml = new \SimpleXMLElement("<booking />");
        // Booked on grayline.com
        $bookingXml->addChild('note', 'Booked on grayline.com');
        // Booking key - have to be careful about agent booking logic here
        // We are either making a booking as an agent using an undocumented login
        // In which case we send agent_booking_key
        // OR
        // We are booking as a regular customer
        // In which case we send booking_key
        if ($this->bookingKey->getBookingKeyAgent() && !empty($_SESSION["agent_name"])) {
            $bookingXml->addChild('agent_booking_key', $this->bookingKey->getBookingKeyAgent());
            if (!empty($this->postVars['agent_ref'])) {
                $bookingXml->addChild('agent_ref', (string)$this->postVars['agent_ref']);
            }
        } else {
            $bookingXml->addChild('booking_key', $this->bookingKey->getBookingKey());
        }
        $bookingXml = $this->promoCodeBooking($bookingXml);
        // Promo Code - this does effect price, so MUST check it
        $bookingXml = $this->promoCodeBooking($bookingXml);
        // Append a container for the components to be booked
        $components = $bookingXml->addChild('components');
        if (is_array($this->items)) {
            foreach ($this->items as $cart_item) {
                if ($cart_item["start_date"] < $booking_start_date) {
                    $booking_start_date = $cart_item["start_date"];
                }

                if ($cart_item["end_date"] > $booking_end_date) {
                    $booking_end_date = $cart_item["end_date"];
                }

                $component = $components->addChild("component");

                $component->addChild("component_key", $cart_item["component_key"]);

                $note = "";

                // Total number of people
                $json_rate_breakdown = $cart_item["rate_breakdown"];
                $rate_breakdown = json_decode($json_rate_breakdown);

                $people_on_item = 0;

                foreach ($rate_breakdown as $rate_item) {
                    $people_on_item += (int)$rate_item->quantity;
                }

                if ($people_on_item > $people_on_booking) {
                    $people_on_booking = $people_on_item;
                }

                // Transfer details
                if ($cart_item["product_type"] == 2) {
                    $note_lines = array();

                    $trans_fields = array("airline", "flightno", "time", "hotel");

                    $i_n = "trans_".$cart_item["id"];

                    foreach ($trans_fields as $field) {
                        if (isset($_POST[$i_n."_".$field])) {
                            $note_lines[] = ucwords($field).": ".$_POST[$i_n."_".$field];
                        }
                    }

                    if ($cart_item["sub_type"] == 3) {
                        // add in return date if it's a 2 way transfer
                        $trans_fields[] = "date";
                        foreach ($trans_fields as $field) {
                            if (isset($_POST[$i_n."_".$field."2"])) {
                                $note_lines[] = "Ret. ".ucwords($field).": ".$_POST[$i_n."_".$field."2"];
                            }
                        }
                    }

                    $note = implode("\n", $note_lines);
                }

                // Pickup details
                if (!empty($cart_item["pickup_key"])) {
                    $component->addChild("pickup_key", $cart_item["pickup_key"]);
                    $component->addChild("pickup_note", $cart_item["pickup_text"]);
                } else {
                    if (!empty($cart_item["pickup_choice"])) {

                        $pickup_choices = array(
                            "unlisted" => "Pickup point not listed",
                            "notbooked" => "Not booked a hotel yet",
                            "friend" => "Staying with a friend or relative",
                            "chooselater" > "I'll select my pickup point at a later date",
                        );

                        if (isset($pickup_choices[$cart_item["pickup_choice"]])) {

                            $component->addChild("pickup_note", $pickup_choices[$cart_item["pickup_choice"]]);
                        }
                    }
                }

                $component->addChild("note", $note);
            }
            // Append the total customers, we'll add their details on below
            $bookingXml->addChild('total_customers', $people_on_booking);


            // Append a container for the customer records
            $customers = $bookingXml->addChild('customers');
            $customer = $customers->addChild('customer');

            $firstname = $this->postVars["firstname"];
            $surname = $this->postVars["surname"];
            $email = $this->postVars['email'];

            // check to see if customer exists already, only do this on live as it's very slow on test
            $customer_id = null;
            if ($this->env !== 'test') {
                $customer_id = $this->searchCustomerId($email, $firstname, $surname);
            }
            if ($customer_id) {
                $customer->addChild('customer_id', $customer_id);
            }

            // Customer deets do not effect price, so do not process on initial call of this method
            if (!$onLoad && !$customer_id) {
                // Call participants
                $email = $this->postVars['email'];
                // $address = $this->postVars['house_number'].' '.$this->postVars["address1"];
                // $postcode = $this->postVars["postcode"];
                // $county = $this->postVars['state'];
                // $country = substr($this->postVars["country"], 0, 2);
                // $tel_home = $this->postVars["tel_home"];
                // $tel_mobile = $tel_home;
                $title = '';    // on purpose field doesn't exist checkout page
                $address2 = '';  // on purpose field doesn't exist checkout page
                $city = ''; // on purpose field doesn't exist checkout page
                $state = ''; // on purpose field doesn't exist checkout page


                // if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                //     $errorMsg = "Start New Booking  - cannot validate email {$email}";
                //     $publicError = urlencode("Sorry, we cannot validate your email address");
                //     $redirect_url = DIR_REL."/checkout?status=error&action=validate_email&channel_id=".$this->channel_id."&reason=$publicError";
                //     throw new RedirectException($errorMsg, $redirect_url);
                // }


                if (!$customer_id) {
                    $customer->addChild('title', $title);
                    $customer->addChild('firstname', $firstname);
                    $customer->addChild('surname', $surname);

                    $customer->addChild('email', $email);



                    if (isset($this->postVars["specialOffers"]) && $this->postVars["specialOffers"] === 'yes') {
                        $customer->addChild('perm_email', '1');
                    } else {
                        $customer->addChild('perm_email', '0');
                    }

                    // $customer->addChild('tel_home', $tel_home);
                    // $customer->addChild('tel_mobile', $tel_mobile);

                    // $customer->addChild('address', $address);

                    // $customer->addChild('city', $city);

                    // $customer->addChild('postcode', $postcode);

                    // $customer->addChild('county', $county);

                    // $customer->addChild('country', $country);
                }

                // For customers after the lead customer
                // Just store marketing preferences
                if ($people_on_booking - 1 > 0) {
                    for ($i = 2; $i <= $people_on_booking; $i++) {

                        $extra_customer = $customers->addChild('customer');

                        if (isset($this->postVars["specialOffers"])) {
                            $extra_customer->addChild('perm_email', '1');
                        } else {
                            $extra_customer->addChild('perm_email', '0');
                        }
                    }
                }

            }
            $this->bookingXml = $bookingXml;

            return $bookingXml;
        }
    }

    private function promoCodeBooking($bookingXml)
    {
        $promo_block = isset($this->postVars["promo_block"]) ? $this->postVars["promo_block"] : null;
        if ($promo_block === '1') {
            return $bookingXml;
        }
        $promoCode = isset($this->postVars["promo_code"]) ? $this->postVars["promo_code"] : null;
        if (!$promoCode) {
            $promoCode = isset($this->postVars["hidden_promo"]) ? $this->postVars["hidden_promo"] : null;
        }
        if (!$promoCode) {
            return $bookingXml;
        }
        $promoMemb = isset($this->postVars["promo_memb"]) ? $this->postVars["promo_memb"] : null;
        // Let's remove spaces and whitespaces from promo codes
        $promoCode = removeWhitespace($promoCode);
        $promoCode = removeSpaces($promoCode);
        if ($promoCode) {
            $this->promoCode = $promoCode;
            $bookingXml->promo_code = (string)$promoCode;
        }
        if ($promoMemb) {
            $this->promoMemb = $promoMemb;
            $bookingXml->promo_membership = (string)$promoMemb;
        }

        return $bookingXml;
    }


}
