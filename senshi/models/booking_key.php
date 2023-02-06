<?php

namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

class BookingKey
{

    protected $palisisCookie;
    protected $booking_key;
    protected $booking_key_agent;
    const BOOKING_KEY_COOKIE_NAME = 'tcms_bk_12130';
    const BOOKING_KEY_AGENT_COOKIE_NAME = 'tcms_ag_bk';

    public function __construct(PalisisCookie $palisisCookie)
    {
        // this works to include library config file ok
//        include_once('functions.php');
        $this->palisisCookie = $palisisCookie;
        $this->retrieveBookingKey();
    }

    public function getBookingKey()
    {
        return $this->booking_key;
    }

    public function getBookingKeyAgent()
    {
        return $this->booking_key_agent;
    }

    protected function retrieveBookingKey()
    {
        $this->booking_key = $this->palisisCookie->getCookie(self::BOOKING_KEY_COOKIE_NAME);
        $this->booking_key_agent = $this->palisisCookie->getCookie(self::BOOKING_KEY_AGENT_COOKIE_NAME);
    }

}
