<?php

namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

class Cart
{
    protected $cart_id;
    protected $ping_secret;

    public function __construct()
    {
        $this->cart_id = isset($_SESSION["tcmscartid"]) ? (int)$_SESSION["tcmscartid"] : 0;
        $this->ping_secret = $this->generate_random_string();
    }

    public function getCartId()
    {
        if ($this->cart_id == 0) {
            // problem, no cart id session
            $errorMsg = "Cannot find shopping cart";
            $redirect_url = DIR_REL."/cart?status=error&action=cart_not_found";
            throw new \RedirectException($errorMsg, $redirect_url);
        }

        return $this->cart_id;
    }

    public function getItemsFromDb($channel_id)
    {
        global $wpdb;
        // Query the DB to get our items
        $sql =  $wpdb->prepare(
            
            "SELECT * from wp_tcmsc_cartitems where cart_id = %s AND channel_id = %s",
            
            array(
                $this->cart_id,
                $channel_id
            )
        );

        $items = $wpdb->get_results($sql, ARRAY_A);

        return $items;
    }


    public function processTemporaryBooking($channel_id, TourcmsWrapper $tourcmsWrapper)
    {
        global $wpdb;
        // Check if we have any existing temporary bookings
        $sql =  $wpdb->prepare(
            
            "SELECT * FROM wp_tcmsc_bookings WHERE cart_id = %s AND channel_id = %s AND status = 'TEMP'",
            
            array(
                $this->cart_id,
                $channel_id
            )
        );

        $rows = $wpdb->get_results($sql, ARRAY_A);

        // If so, cancel them (should be max 1)
        if (count($rows) > 0) {
            foreach ($rows as $booking) {
                //	print $booking_id;
                $booking_id = (int)$booking["booking_id"];

                $deleted = $tourcmsWrapper->delete_booking($booking_id, $channel_id);

                $q =  $wpdb->prepare(
                    
                    "DELETE FROM wp_tcmsc_bookings WHERE cart_id = %s AND channel_id = %s AND booking_id = %s",
                    
                    array(
                        $this->cart_id,
                        $channel_id,
                        $booking_id
                    )
                );

                $wpdb->query($q, ARRAY_A);

            }
        }
    }

    public function storeTemporaryBooking(
        $booking_id,
        $channel_id,
        $amount_due,
        $sale_currency,
        $alt_total,
        $alt_currency
    ) {
        global $wpdb;

        // Store booking ref in DB
        $q =  $wpdb->prepare(
            
             "INSERT INTO wp_tcmsc_bookings (cart_id, channel_id, booking_id, status, amount_due, sale_currency, alt_total, alt_currency) VALUES (%s, %s, %s, %s, %s, %s, %s, %s)",
            
            array(
                $this->cart_id,
                $channel_id,
                $booking_id,
                "TEMP",
                $amount_due,
                $sale_currency,
                $alt_total,
                $alt_currency
            )
        );

        $wpdb->query($q);
    }

    public function bookingCommitted($booking_id, $channel_id, $payment_reference, $voucher_url)
    {
        global $wpdb;

        // Log new status
        $q =  $wpdb->prepare(
            
             "UPDATE wp_tcmsc_bookings SET status = %s, payment_reference = %s, voucher_url = %s, ping_secret = %s WHERE cart_id = %s AND channel_id = %s AND booking_id = %s",
            
            array(
                "CONFIRMED",
                $payment_reference,
                $voucher_url,
                $channel_id,
                $booking_id,
                $this->ping_secret
            )
        );

        $wpdb->query($q);
    }

    public function paymentAuthorised($booking_id, $channel_id, $reference=null)
    {
        global $wpdb;

        // Log new status
        // we might not have a cart id now due to async payments
        if ($this->cart_id) {
            $q =  $wpdb->prepare(
                
                 "UPDATE wp_tcmsc_bookings SET status = %s, payment_reference = %s WHERE cart_id = %s AND channel_id = %s AND booking_id = %s",
                
                array(
                    "AUTHORISED",
                    $this->cart_id,
                    $channel_id,
                    $booking_id,
                    $reference
                )
            );

            $wpdb->query($q);

        } else {
            $q =  $wpdb->prepare(
                
                 "UPDATE wp_tcmsc_bookings SET status = %s, payment_reference = %s WHERE channel_id = %s AND booking_id = %s",
                
                array(
                    "AUTHORISED",
                    $channel_id,
                    $booking_id,
                    $reference
                )
            );

            $wpdb->query($q);

        }
          
    }

    public function paymentTimeout($booking_id, $channel_id)
    {
        global $wpdb;

        // Log new status
        $q =  $wpdb->prepare(
            
             "UPDATE wp_tcmsc_bookings SET status = %s WHERE cart_id = %s AND channel_id = %s AND booking_id = %s",
            
            array(
                "TIMEOUT",
                $this->cart_id,
                $channel_id,
                $booking_id
            )
        );

        $wpdb->query($q);
    }

    public function getBookingFromDb($booking_id) : array
    {
        global $wpdb;

        // Query the DB to get our items
        $query =  $wpdb->prepare(
            
             "SELECT * from wp_wp_tcmsc_bookings where booking_id = %s",
            
            array(
                $booking_id
            )
        );

        $items = $wpdb->get_results($query, ARRAY_A);

        $item = isset($items[0]) ? $items[0] : array();

        return $item;
    }


    public function getBookingTimeoutStatusFromDb($booking_id)
    {
        global $wpdb;

        $sql =  $wpdb->prepare(
            
             "SELECT * from wp_tcmsc_bookings where booking_id = %s",
            
            array(
                $booking_id
            )
        );

        $items = $wpdb->get_results($sql, ARRAY_A);

        $item = isset($items[0]) ? $items[0] : null;

        $bool = $item && isset($item['status']) && $item['status'] === "TIMEOUT" ? true : false;

        return $bool;
    }

    public function cancelBooking($booking_id, $channel_id, $reference = null)
    {
        global $wpdb;

        // Log new status
        // we might not have a cart id now due to async payments
        if ($this->cart_id) {
            $q =  $wpdb->prepare(
                
                 "UPDATE wp_wp_tcmsc_bookings SET status = %s, payment_reference = %s WHERE cart_id = %s AND channel_id = %s AND booking_id = %s",
                
                array(
                    "CANCELLED",
                    $this->cart_id,
                    $channel_id,
                    $booking_id,
                    $reference
                )
            );

        } else {
            $q =  $wpdb->prepare(
                
                 "UPDATE wp_tcmsc_bookings SET status = %s, payment_reference = %s WHERE channel_id = %s AND booking_id = %s",
                
                array(
                    "CANCELLED",
                    $channel_id,
                    $booking_id,
                    $reference
                )
            );

        }
        $wpdb->query($q);
    }

    public function feeInfo($booking_id, AdyenBinLookup $adyenBinLookup)
    {
        global $wpdb;

        // Log new status
        $q =  $wpdb->prepare(
            
             "UPDATE wp_tcmsc_bookings SET fee_curr = %s, fee_value = %s, fee_result_code = %s, fee_percent = %s WHERE booking_id = %s",
            
            array(
                $booking_id,
                $adyenBinLookup->getFeeCurrency(),
                $adyenBinLookup->getFeeValue(),
                $adyenBinLookup->getFeeResultCode(),
                $adyenBinLookup->getFeePercent()
            )
        );

        $wpdb->query($q);
    }


    private function generate_random_string($length = 12)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $random_string = '';
        for ($i = 0; $i < $length; $i++) {
            $random_string .= $characters[rand(0, strlen($characters) - 1)];
        }

        return $random_string;
    }
}
