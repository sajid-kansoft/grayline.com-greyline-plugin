<?php

namespace TmtAuthstring;

class Create
{
    /**
     * Date stamp for the key in YmdHis format.
     *
     * @var string.
     */
    protected $dateStamp = '';

    /**
     * Sets date stamp.
     *
     * @return void
     */
    public function setDateStamp()
    {
        $dateObject      = new \DateTime('now', new \DateTimeZone('GMT'));
        $this->dateStamp = $dateObject->format('YmdHis');
    }

    /**
     * Generates the auth string.
     *
     * @param  integer $channel_id        The ID of the channel the auth is for.
     * @param  string  $channel_currency  The base currency of the channel as a three letter ISO code.
     * @param  integer $booking_total     The total of the booking in base currency as a cent value.
     * @param  string  $channel_secret    The secret key for the channel the auth is for.
     * @param  array   $additional_fields Any additional fields to include in the auth string.
     * @return string                     Hashed and salted auth key.
     */
    public function getAuthstring($channel_id, $channel_currency, $booking_total, $channel_secret, $additional_fields = [])
    {  
        $this->setDateStamp();

        $varString = $this->getVarString($channel_id, $channel_currency, $booking_total, $additional_fields);

        $authString       = hash('sha256', $varString);
        $saltedAuthString = hash('sha256', $authString . $channel_secret);
        
        return $saltedAuthString . $this->dateStamp;
    }

    /**
     * Concatenates relevant booking variables into a query string.
     *
     * @param  integer $channel_id         The ID of the channel the auth is for.
     * @param  string  $booking_currencies The base currency of the channel the auth is for as a three letter ISO code.
     * @param  integer $booking_total      The total of the booking in base currency as a cent value.
     * @param  array   $additional_fields  Any additional fields to include in the auth string.
     * @return string                      Booking variables as a query string.
     */
    public function getVarString($channel_id, $channel_currency, $booking_total, $additional_fields)
    {
        $additional_fields['channels']   = $channel_id;
        $additional_fields['currencies'] = $channel_currency;
        $additional_fields['total']      = $booking_total;

        ksort($additional_fields);

        $additional_fields['timestamp'] = $this->dateStamp;

        return implode('&', array_map(function ($item) {
            return is_array($item) ? json_encode($item) : $item;
        }, $additional_fields));
    }
}
