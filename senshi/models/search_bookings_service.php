<?php

namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

class SearchBookingsService
{

    protected $booking;
    protected $customer_id;
    protected $customer_email;
    protected $tourcmsWrapper;
    protected $customer_record;
    protected $cache_type = 'customer';

    /**
     * @return mixed
     */
    public function getCustomerId()
    {
        return $this->customer_id;
    }


    public function __construct(TourcmsWrapper $tourcmsWrapper)
    {
        $this->tourcmsWrapper = $tourcmsWrapper;
    }

    public function searchBookings(string $customer_email, string $firstname, string $surname)
    {
        $this->booking = $this->customerExists($customer_email, $firstname, $surname);
        $customer_record = $this->booking->bookings->booking[0];
        $this->customer_id = (int)$customer_record->lead_customer_id;
    }


    protected function updatePhone()
    {

    }
    protected function customerExists(string $customer_email, string $firstname, string $surname)
    {
        try {
            $this->customer_email = $customer_email;
            $vars = array();
            $vars["lead_customer_email"] = $customer_email;
            $vars["lead_customer_firstname"] = $firstname;
            $vars["lead_customer_surname"] = $surname;
            $vars["show_payments"] = 0;
            $params = http_build_query($vars);
            $result = $this->tourcmsWrapper->list_bookings($params, $this->tourcmsWrapper->getChannelId());
            $total = (int)$result->total_bookings_count;

            if ($total == 0) {
                $errorMsg = "No customer record for $customer_email";
                throw new InternalException($errorMsg, 'DEFAULT');
            }

            return $result;
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}
