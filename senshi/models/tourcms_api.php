<?php

namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

class TourcmsApi
{

    protected $tourcmsApi;
    protected $channelId;

    protected $supressEmail = true;


    public function __construct()
    {
    }

    public function setTourcmsApi($val) { $this->tourcmsApi = $val; }

    public function setChannelId($val) { $this->channelId = (int)$val; }

    public function getChannelId() { return $this->channelId; }

    protected function resultValid($result, $errorLog=null)
    {
        $ret = true;
        if ($result === false) {
            error_log("TCMS ERROR, NO RESPONSE $errorLog");
            $ret = false;
        }
        else if ((string)$result->error != "OK") {
            $tcmsE = (string)$result->error != "OK";
            error_log("TCMS ERROR, NOT OK, $tcmsE $errorLog");
            $ret = false;
        }
        return $ret;
    }
    // returns string
    public function apiStatus()
    {
        try
        {
            $channelId = $this->channelId;
            $errorLog = "API STATUS $channelId";
            $result = $this->tourcmsApi->api_rate_limit_status($channelId);
            if (! $this->resultValid($result, $errorLog)) {
                $logMsg = "TourCMS API Rate Limit Exception (Channel ID $channelId): ";
                ////\FB::error($logMsg . (string)$result->error);
                $logMsg .= json_encode($result, JSON_UNESCAPED_SLASHES);
                throw new \TourcmsException($logMsg, 'CRITICAL');
            }
            return $result;
        }
        catch( \TourcmsException $e )
        {
            $publicError = 'We seem to be having issues connecting to our tour API. We are sorry for the inconvenience.';
            throw new \PublicException($publicError);
        }
    }

    public function createPayment($xml, $bookingId=null)
    {
        try
        {
            $errorLog = "CREATE PAYMENT ($bookingId)";
            $channelId = $this->channelId;
            $result = $this->tourcmsApi->create_payment($xml, $channelId);
            ////\FB::error($result, $errorLog);
            // try again
            if (! $this->resultValid($result, $errorLog)) {
                $result = $this->tourcmsApi->create_payment($xml, $channelId);
                $errorLog .= " +attempt2";
                if (! $this->resultValid($result, $errorLog)) {
                    $errorLog .= " +attempt3";
                    $result = $this->tourcmsApi->create_payment($xml, $channelId);
                    if (! $this->resultValid($result, $errorLog)) {
                        $logMsg = "TourCMS Create Payment (Channel ID $channelId): ";
                        ////\FB::error($logMsg . (string)$result->error);
                        $logMsg .= json_encode($xml, JSON_UNESCAPED_SLASHES);
                        $logMsg .= json_encode($result, JSON_UNESCAPED_SLASHES);
                        throw new \TourcmsException($logMsg, 'CRITICAL');
                    }
                }
            }
            return $result;
        }
        catch (\TourcmsException $e)
        {
            $publicError = "Problem, unable to store the payment at this time";
            throw new \PublicException($publicError);
        }
    }

    public function showBooking($bookingId)
    {
        try
        {
            $errorLog = "SHOW BOOKING ($bookingId)";
            $channelId = $this->channelId;
            $result = $this->tourcmsApi->show_booking($bookingId, $channelId);
            ////\FB::error($result);
            // try again
            if (! $this->resultValid($result, $errorLog)) {
                $errorLog .= " +attempt2";
                $result = $this->tourcmsApi->show_booking($bookingId, $channelId);
                if (! $this->resultValid($result, $errorLog)) {
                    $errorLog .= " +attempt3";
                    $result = $this->tourcmsApi->show_booking($bookingId, $channelId);
                    if (! $this->resultValid($result, $errorLog)) {
                        $logMsg = "TourCMS Show Booking (Channel ID $channelId): ";
                        ////\FB::error($logMsg . (string)$result->error);
                        $logMsg .= "(Booking ID: $bookingId): ";
                        $logMsg .= json_encode($result, JSON_UNESCAPED_SLASHES);
                        throw new \TourcmsException($logMsg, 'CRITICAL');
                    }
                }
            }
            return $result;
        }
        catch (\TourcmsException $e)
        {
            $publicError = "Problem, unable to load booking at this time";
            throw new \PublicException($publicError);
        }
    }

    public function commitNewBooking($xml, $bookingId=null)
    {
        try
        {
            $errorLog = "COMMIT BOOKING ($bookingId)";
            $channelId = $this->channelId;
            $suppressEmail = $this->supressEmail;
            $result = $this->tourcmsApi->commit_new_booking($xml, $channelId, $suppressEmail);
            ////\FB::error($result);
            // could be the case that the booking is already committed
            if ($result !== false && (string)$result->error == "BOOKING ALREADY COMMITTED") {
                return $result;
            }
            // try again
            if (! $this->resultValid($result, $errorLog)) {
                $errorLog .= " +attempt2";
                $result = $this->tourcmsApi->commit_new_booking($xml, $channelId, $suppressEmail);
                // could be the case that the booking is already committed
                if ($result !== false && (string)$result->error == "BOOKING ALREADY COMMITTED") {
                    return $result;
                }
                if (! $this->resultValid($result, $errorLog)) {
                    $errorLog .= " +attempt3";
                    $result = $this->tourcmsApi->commit_new_booking($xml, $channelId, $suppressEmail);
                    // could be the case that the booking is already committed
                    if ($result !== false && (string)$result->error == "BOOKING ALREADY COMMITTED") {
                        return $result;
                    }
                    if (! $this->resultValid($result, $errorLog)) {
                        $logMsg = "TourCMS Commit New Booking (Channel ID $channelId): ";
                        ////\FB::error($logMsg . (string)$result->error);
                        $logMsg .= json_encode($xml, JSON_UNESCAPED_SLASHES);
                        $logMsg .= json_encode($result, JSON_UNESCAPED_SLASHES);
                        throw new \TourcmsException($logMsg, 'CRITICAL');
                    }
                }
            }
            return $result;
        }
        catch (\TourcmsException $e)
        {
            $publicError = "Problem, unable to store booking at this time";
            throw new \PublicException($publicError);
        }
    }

    public function bookingUpdateComponent($xml, $bookingId=null)
    {
        try
        {
            $errorLog = "BOOKING UPDATE COMPONENT ($bookingId)";
            $channelId = $this->channelId;
            $result = $this->tourcmsApi->booking_update_component($xml, $channelId);
            ////\FB::error($result);
            if (! $this->resultValid($result, $errorLog))  {
                $errorLog .= " +attempt2";
                $result = $this->tourcmsApi->booking_update_component($xml, $channelId);
                if (! $this->resultValid($result, $errorLog))  {
                    $errorLog .= " +attempt3";
                    $result = $this->tourcmsApi->booking_update_component($xml, $channelId);
                    if (! $this->resultValid($result, $errorLog))  {
                        $logMsg = "TourCMS Update Component (Channel ID $channelId): ";
                        ////\FB::error($logMsg . (string)$result->error);
                        $logMsg .= json_encode($xml, JSON_UNESCAPED_SLASHES);
                        $logMsg .= json_encode($result, JSON_UNESCAPED_SLASHES);
                        throw new \TourcmsException($logMsg, 'DEBUG');
                    }
                }
            }
            return $result;
        }
        catch (\TourcmsException $e)
        {
            // We don't want to stop checkout completing if this is the error, so just log & continue on
            $publicError = "Problem, unable to update components at this time";
            //throw new PublicException($publicError);
        }
    }


    public function updateBooking($xml, $bookingId=null)
    {
        try
        {
            $errorLog = "UPDATE BOOKING ($bookingId)";
            $channelId = $this->channelId;
            $result = $this->tourcmsApi->update_booking($xml, $channelId);
            ////\FB::error($result);
            if (! $this->resultValid($result, $errorLog)) {
                $errorLog .= " +attempt2";
                $result = $this->tourcmsApi->update_booking($xml, $channelId);
                if (! $this->resultValid($result, $errorLog)) {
                    $errorLog .= " +attempt3";
                    $result = $this->tourcmsApi->update_booking($xml, $channelId);
                    if (! $this->resultValid($result, $errorLog)) {
                        $logMsg = "TourCMS Update Booking (Channel ID $channelId): ";
                        ////\FB::error($logMsg . (string)$result->error);
                        $logMsg .= json_encode($xml, JSON_UNESCAPED_SLASHES);
                        $logMsg .= json_encode($result, JSON_UNESCAPED_SLASHES);
                        throw new \TourcmsException($logMsg, 'CRITICAL');
                    }
                }
            }
            return $result;
        }
        catch (\TourcmsException $e)
        {
            // We don't want to stop checkout completing if this is the error, so just log & continue on
            $publicError = "Problem, unable to update booking at this time";
            //throw new PublicException($publicError);
        }
    }


    public function cancelBooking($xml, $bookingId=null)
    {
        try
        {
            $errorLog = "CANCEL BOOKING ($bookingId)";
            $channelId = $this->channelId;
            $result = $this->tourcmsApi->cancel_booking($xml, $channelId);
            ////\FB::error($result);
            if (! $this->resultValid($result, $errorLog)) {
                $errorLog .= " +attempt2";
                $result = $this->tourcmsApi->cancel_booking($xml, $channelId);
                if (! $this->resultValid($result, $errorLog)) {
                    $errorLog .= " +attempt3";
                    $result = $this->tourcmsApi->cancel_booking($xml, $channelId);
                    if (! $this->resultValid($result, $errorLog)) {
                        $logMsg = "TourCMS Cancel Booking (Channel ID $channelId): ";
                        ////\FB::error($logMsg . (string)$result->error);
                        $logMsg .= json_encode($xml, JSON_UNESCAPED_SLASHES);
                        $logMsg .= json_encode($result, JSON_UNESCAPED_SLASHES);
                        throw new \TourcmsException($logMsg, 'CRITICAL');
                    }
                }
            }
            return $result;
        }
        catch (\TourcmsException $e)
        {
            $publicError = "Problem, unable to cancel booking at this time";
            throw new \PublicException($publicError);
        }
    }

    public function logFailedPayment($xml, $bookingId=null)
    {
        try {
            $channelId = $this->channelId;
            $logMsg = "TourCMS Log Failed Payment (Channel ID $channelId): ";
            $logMsg .= json_encode($xml, JSON_UNESCAPED_SLASHES);
            throw new \TourcmsException($logMsg, 'DEBUG');
        }
        catch (\TourcmsException $e) {
        }
        try
        {
            $errorLog = "LOG FAILED PAYMENT ($bookingId)";
            $channelId = $this->channelId;
            $result = $this->tourcmsApi->log_failed_payment($xml, $channelId);
            ////\FB::error($result);
            // We need to immediately throw an error here
            if (! $this->resultValid($result, $errorLog))
            {
                $logMsg = "TourCMS Log Failed Payment (Channel ID $channelId): ";
                ////\FB::error($logMsg . (string)$result->error);
                $logMsg .= json_encode($xml, JSON_UNESCAPED_SLASHES);
                $logMsg .= json_encode($result, JSON_UNESCAPED_SLASHES);
                throw new \TourcmsException($logMsg, 'CRITICAL');
            }
            return $result;
        }
        catch (\TourcmsException $e)
        {
            // We don't want to stop checkout completing if this is the error, so just log & continue on
            $publicError = "Problem, unable to log failed payment at this time";
            //throw new PublicException($publicError);
        }
    }


    public function apiInfo()
    {
        $result = $this->tourcmsApi->show_channel($this->channelId);
        return $result;
    }

    // returns simplexml
    public function search($query=null)
    {
        $query = $query . "&" . self::QS_404;
        $query = (string)$query;
        $result = $this->tourcmsApi->search_tours($query, $this->channelId);
        return $result;
    }

    public function searchAll($reverse=false)
    {
        // If the client has over 200 tours and we need them all
        $query = self::QS_ALL_1;
        if ($reverse)
            $query = self::QS_ALL_2;
        $query = (string)$query;
        $result = $this->tourcmsApi->search_tours($query, $this->channelId);

        return $result;
    }

    // Gets a list of all tours that have 404 errors to update
    public function search404()
    {
        $query = self::QS_404_ERROR;
        $result = $this->tourcmsApi->search_tours($query, $this->channelId);
        return $result;
    }

    // returns simplexml
    public function listTourLocations()
    {
        $result = $this->tourcmsApi->list_tour_locations($this->channelId);
        return $result;
    }

    // returns simplexml
    public function listTourImages()
    {
        $result = $this->tourcmsApi->list_tour_images($this->channelId);
        return $result;
    }

    // returns booking key
    public function bookingKey($xml)
    {
        $result = $this->tourcmsApi->get_booking_redirect_url($xml, $this->channelId);
        return $result;
    }

    // returns booking xml
    public function newBooking($xml)
    {
        $result = $this->tourcmsApi->start_new_booking($xml, $this->channelId);
        return $result;
    }

    // makes spreedly payment
    public function spreedlyPayment($xml)
    {
        $result = $this->tourcmsApi->spreedly_create_payment($xml, $this->channelId);
        return $result;
    }

    // promo code test
    public function promoCode($promoCode)
    {
        $result = $this->tourcmsApi->show_promo($promoCode, $this->channelId);
        return $result;
    }

    // delete a booking / free up stock
    public function deleteBooking($bookingId)
    {
        $result = $this->tourcmsApi->delete_booking($bookingId, $this->channelId);
        return $result;
    }

    // check the availability of tour date and rate selection
    public function checkAvailability($query, $tourId)
    {
        $result = $this->tourcmsApi->check_tour_availability($query, $tourId, $this->channelId);
        return $result;
    }

}
