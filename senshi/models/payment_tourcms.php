<?php

namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

class PaymentTourcms
{

    // TourCMS API object
    protected $tourcmsApi;
    protected $bookingId;
    protected $agent;
    protected $showBookingXml;
    protected $balanceOwedBy;


    public function __construct(TourcmsApi $tourcmsApi, Agent $agent)
    {
        $this->tourcmsApi = $tourcmsApi;
        $this->agent = $agent;
        //\FB::warn("Payment Tourcms");
    }

    public function setBookingId($val)
    {
        $this->bookingId = (int)$val;
    }

    public function createPayment($total, $currency, $cardType = null, $reference = null, $note = null)
    {
        try {
            // We assume the booking has been committed now, and we extract the payment_by data from this xml
            $this->showBookingXml = $this->tourcmsApi->showBooking($this->bookingId);
            // We don't want to throw an error here as the payment will have been successful in TMT and show booking data only used for balance owed by
            $balance_owed_by = $this->showBookingXml->booking->balance_owed_by;
            $this->balanceOwedBy = $balance_owed_by;
        } catch (\PublicException $e) {
            // keep going
        }

        try {
            $xml = new \SimpleXMLElement('<payment />');
            $payment_value = $total;
            $payment_currency = $currency;
            $payment_type = $cardType ? $cardType : "Credit Card";
            // Note is not being passed, so we temporarily add note to reference
            $reference .= $note ? " ($note)" : "";
            $payment_reference = $reference;
            $payment_transaction_reference = $reference;
            $payment_note = $note;
            $creditcard_fee_type = 0;
            $paid_by = $this->agent->getIsAgent() ? "A" : "C";
            $payment_by = $balance_owed_by ? $this->balanceOwedBy : $paid_by;


            //\FB::warn("BALANCE OWED BY $balance_owed_by PAID BY $paid_by");

            $xml->addChild('booking_id', $this->bookingId);
            $xml->addChild('payment_value', $payment_value);
            $xml->addChild('payment_currency', $payment_currency);
            $xml->addChild('payment_type', $payment_type);
            $xml->addChild('payment_reference', $payment_reference);
            $xml->addChild('payment_transaction_reference', $payment_transaction_reference);
            $xml->addChild('payment_note', $payment_note);
            $xml->addChild('payment_type', $payment_type);
            $xml->addChild('payment_by', $payment_by);
            $xml->addChild('creditcard_fee_type', $creditcard_fee_type);

            $this->tourcmsApi->createPayment($xml, $this->bookingId);
        } catch (\PublicException $e) {
            // throw this back up the chain
            throw new \PublicException($e->getMessage());
        }
    }

    public function updateBookingComponent($componentId, $costPrice)
    {
        try {
            $xml = new \SimpleXMLElement('<booking />');
            $xml->addChild('booking_id', $this->bookingId);
            $component = $xml->addChild('component');
            $component->addChild('component_id', $componentId);
            $component->addChild('cost_price', $costPrice);
            $this->tourcmsApi->bookingUpdateComponent($xml, $this->bookingId);
        } catch (\PublicException $e) {
            // throw this back up the chain
            throw new \PublicException($e->getMessage());
        }
    }

    public function updateBooking($json)
    {
        try {
            $xml = new \SimpleXMLElement('<booking />');
            $xml->addChild('booking_id', $this->bookingId);
            $cf = $xml->addChild('custom_fields');
            $f = $cf->addChild('field');
            $name = $f->addChild('name', 'financials');
            $v = $f->addChild('value', htmlspecialchars($json));
            //\FB::log("update booking json");
            //\FB::log($json);
            //\FB::log($xml);
            $this->tourcmsApi->updateBooking($xml, $this->bookingId);
        } catch (\PublicException $e) {
            // throw this back up the chain
            throw new \PublicException($e->getMessage());
        }
    }


    public function logFailedPayment($msg)
    {
        try {
            $xml = new \SimpleXMLElement('<booking />');
            $xml->addChild('booking_id', $this->bookingId);
            // Optionally add a message
            $xml->addChild('audit_trail_note', $msg);
            $this->tourcmsApi->logFailedPayment($xml, $this->bookingId);
            //\FB::info("logFailedPayment note: $msg");
        } catch (\PublicException $e) {
            // Need to relay to customer somehow TODO
            // throw this back up the chain
            throw new \PublicException($e->getMessage());
        }

    }


    public function cancelBooking($note = null)
    {
        try {
            $xml = new \SimpleXMLElement('<booking />');
            $xml->addChild('booking_id', $this->bookingId);
            // Optionally add a note explaining why the booking is cancelled
            $xml->addChild('note', $note);
            //\FB::info("Cancel booking note: $note");
            $this->tourcmsApi->cancelBooking($xml, $this->bookingId);
        } catch (\PublicException $e) {
            // throw this back up the chain
            throw new \PublicException($e->getMessage());
        }

    }


}
