<?php

namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

use InternalException as InternalException;

// Dependency Inversion Container

class Dic
{

    public function objectCacheTagible()
    {
        try {
            loadSenshiModal('cache_xml');
            loadSenshiModal('cache_tagible');

            $cacheXml = new CacheXml();
            if (!is_object($cacheXml)) {
                throw new InternalException("can't create cacheXml object DIC");
            }
            $cacheTagible = new CacheTagible($cacheXml);
            if (!is_object($cacheTagible)) {
                throw new InternalException("can't create cacheTagible object DIC");
            }

            return $cacheTagible;
        } catch (InternalException $e) {
            throw new \PublicException($e);
        }
    }

    public function objectTagibleProduct($tourId)
    {
        try {
            loadSenshiModal('tagible_product');
            $cacheTagible = $this->objectCacheTagible();
            $tagibleProduct = new TagibleProduct($cacheTagible, $tourId);
            if (!is_object($tagibleProduct)) {
                throw new InternalException("can't create tagibleProduct object DIC");
            }

            return $tagibleProduct;
        } catch (InternalException $e) {
            throw new \PublicException($e);
        }
    }

    // public static function objectCartDb()
    // {
    //     try {
    //         loadSenshiModal('cart_db');
    //         $object = new CartDb();
    //         if (!is_object($object)) {
    //             throw new InternalException("can't create CartDb object DIC");
    //         }
    //         return $object;
    //     } catch (InternalException $e) {
    //         throw new PublicException($e);
    //     }
    // }

    public static function objectCart()
    {
        try {
            loadSenshiModal('cart');
            $object = new Cart();
            if (!is_object($object)) {
                throw new InternalException("can't create Cart object DIC");
            }
            return $object;
        } catch (InternalException $e) {
            throw new \PublicException($e);
        }
    }

    public static function objectAdyenMoney()
    {
        try {
            loadSenshiModal('adyen_money');
            $object = new AdyenMoney();
            if (!is_object($object)) {
                throw new InternalException("can't create AdyenMoney object DIC");
            }
            return $object;
        } catch (InternalException $e) {
            throw new \PublicException($e);
        }
    }

    public static function objectTourcmsWrapper()
    {
        try {
            loadSenshiModal('tourcms_wrapper');
            $object = new TourcmsWrapper();
            if (!is_object($object)) {
                throw new InternalException("can't create TourcmsWrapper object DIC");
            }
            return $object;
        } catch (InternalException $e) {
            throw new \PublicException($e);
        }
    }

    public static function objectLogger()
    {
        try {
            loadSenshiModal('logger');
            $object = new Logger();
            if (!is_object($object)) {
                throw new InternalException("can't create Logger object DIC");
            }
            return $object;
        } catch (InternalException $e) {
            throw new \PublicException($e);
        }
    }

    public static function objectGeoLocate()
    {
        try {
            loadSenshiModal('geo_locate');
            $object = new GeoLocate();
            if (!is_object($object)) {
                throw new InternalException("can't create GeoLocate object DIC");
            }
            return $object;
        } catch (InternalException $e) {
            throw new \PublicException($e);
        }
    }

    public static function objectAgent()
    {
        try {
            loadSenshiModal('agent');
            $object = new Agent();
            if (!is_object($object)) {
                throw new InternalException("can't create Agent object DIC");
            }
            return $object;
        } catch (InternalException $e) {
            throw new \PublicException($e);
        }
    }

    public static function objectCurrencyConverter()
    {
        try {
            loadSenshiModal('currency_converter');
            $object = new CurrencyConverter();
            if (!is_object($object)) {
                throw new InternalException("can't create CurrencyConverter object DIC");
            }
            return $object;
        } catch (InternalException $e) {
            throw new \PublicException($e);
        }
    }

    public static function objectBookingKey()
    {
        try {

            loadSenshiModal('palisis_cookie');
            loadSenshiModal('booking_key');
            $palisisCookie = new PalisisCookie();
            $object = new BookingKey($palisisCookie);
            if (!is_object($object)) {
                throw new InternalException("can't create BookingKey object DIC");
            }
            return $object;
        } catch (InternalException $e) {
            throw new \PublicException($e);
        }
    }

    public static function objectAdyenAppleSession()
    {
        try {
            loadSenshiModal('adyen_apple_session');
            $object = new AdyenAppleSession();
            if (!is_object($object)) {
                throw new InternalException("can't create AdyenAppleSession object DIC");
            }
            return $object;
        } catch (InternalException $e) {
            throw new \PublicException($e);
        }
    }


    public static function objectAdyenClient()
    {
        try {
            loadSenshiModal('adyen_client');

            $object = new AdyenClient();
            if (!is_object($object)) {
                throw new InternalException("can't create AdyenClient object DIC");
            }
            return $object;
        } catch (InternalException $e) {
            throw new \PublicException($e);
        }
    }

    public static function objectAdyenClientKey()
    {
        try {
            loadSenshiModal('adyen_client_key');
            $object = new AdyenClientKey();
            if (!is_object($object)) {
                throw new InternalException("can't create AdyenClientKey object DIC");
            }
            return $object;
        } catch (InternalException $e) {
            throw new \PublicException($e);
        }
    }

    public static function objectAdyenBinLookup()
    {
        try {
            loadSenshiModal('adyen_bin_lookup');
            $object = new AdyenBinLookup(self::objectAdyenClient(), self::objectAdyenMoney(), self::objectLogger());
            if (!is_object($object)) {
                throw new InternalException("can't create AdyenBinLookup object DIC");
            }
            return $object;
        } catch (InternalException $e) {
            throw new \PublicException($e);
        }
    }


    public static function objectAdyenPaymentMethods()
    {
        try {
            loadSenshiModal('adyen_payment_methods');
            $object = new AdyenPaymentMethods(self::objectAdyenClient(), self::objectGeoLocate(), self::objectAdyenMoney(), self::objectAdyenClientKey());
            if (!is_object($object)) {
                throw new InternalException("can't create AdyenPaymentMethods object DIC");
            }
            return $object;
        } catch (InternalException $e) {
            throw new \PublicException($e);
        }
    }

    public static function objectAdyenPayment()
    {
        try {
            loadSenshiModal('adyen_payment');
            $object = new AdyenPayment(self::objectGeoLocate(), self::objectLogger(), self::objectAdyenWebhookProcess(), self::objectAdyenMoney(), self::objectAdyenClient());
            if (!is_object($object)) {
                throw new InternalException("can't create AdyenPayment object DIC");
            }
            return $object;
        } catch (InternalException $e) {
            throw new \PublicException($e);
        }
    }


    public static function objectLicenseesPayout()
    {
        try {
            loadSenshiModal('licensees_payout');
            $object = new LicenseesPayout(self::objectTourcmsWrapper(), self::objectAgent());
            if (!is_object($object)) {
                throw new InternalException("can't create Licensees Payout object DIC");
            }
            return $object;
        } catch (InternalException $e) {
            throw new \PublicException($e);
        }
    }

    public static function objectSearchBookingsService()
    {
        try {
            loadSenshiModal('search_bookings_service');
            $object = new SearchBookingsService(self::objectTourcmsWrapper());
            if (!is_object($object)) {
                throw new InternalException("can't create Search Bookings Service object DIC");
            }
            return $object;
        } catch (InternalException $e) {
            throw new \PublicException($e);
        }
    }

    public static function objectStartNewBooking($postVars)
    {
        try {
            loadSenshiModal('start_new_booking');
            $object = new StartNewBooking(self::objectTourcmsWrapper(), self::objectCart(), self::objectBookingKey(), self::objectFex(), self::objectSearchBookingsService(), $postVars, self::objectLogger());
            if (!is_object($object)) {
                throw new InternalException("can't create StartNewBooking object DIC");
            }
            return $object;
        } catch (InternalException $e) {
            throw new \PublicException($e);
        }
    }

    public static function objectAdyenSplit()
    {
        try {
            loadSenshiModal('adyen_split');
            $object = new AdyenSplit(self::objectAdyenMoney(), self::objectFex());
            if (!is_object($object)) {
                throw new InternalException("can't create AdyenSplit object DIC");
            }
            return $object;
        } catch (InternalException $e) {
            throw new \PublicException($e);
        }
    }

    public static function objectTourcmsBookingProcess()
    {
        try {
            loadSenshiModal('tourcms_booking_process');
            $object = new TourcmsBookingProcess(self::objectTourcmsWrapper(), self::objectAdyenPayment(), self::objectCart(), self::objectAdyenBinLookup(), self::objectTourcmsBookingFinalise(), self::objectAgent(), self::objectLicenseesPayout(), self::objectAdyenSplit(), self::objectLogger());
            if (!is_object($object)) {
                throw new InternalException("can't create TourcmsBookingProcess object DIC");
            }
            return $object;
        } catch (InternalException $e) {
            throw new \PublicException($e);
        }
    }

    public static function objectTourcmsBookingFinalise()
    {
        try {
            loadSenshiModal('tourcms_booking_finalise');
            $object = new TourcmsBookingFinalise(self::objectTourcmsWrapper(), self::objectAdyenResult(), self::objectCart(), self::objectLogger());
            if (!is_object($object)) {
                throw new InternalException("can't create TourcmsBookingFinalise object DIC");
            }
            return $object;
        } catch (InternalException $e) {
            throw new \PublicException($e);
        }
    }


    public static function objectAdyenResult()
    {
        try {
            loadSenshiModal('adyen_result');
            $object = new AdyenResult(self::objectAdyenMoney());
            if (!is_object($object)) {
                throw new InternalException("can't create AdyenResult object DIC");
            }
            return $object;
        } catch (InternalException $e) {
            throw new \PublicException($e);
        }
    }

    public static function objectAdyenWebhookProcess()
    {
        try {
            loadSenshiModal('adyen_webhook_process');
            $object = new AdyenWebhookProcess(self::objectTourcmsBookingFinalise());
            if (!is_object($object)) {
                throw new InternalException("can't create AdyenWebhookProcess object DIC");
            }
            return $object;
        } catch (InternalException $e) {
            throw new \PublicException($e);
        }
    }

    public static function objectAdyenWebhookAccept()
    {
        try {
            loadSenshiModal('adyen_webhook_accept');
            $object = new AdyenWebhookAccept(self::objectAdyenWebhookProcess());
            if (!is_object($object)) {
                throw new InternalException("can't create AdyenWebhookAccept object DIC");
            }
            return $object;
        } catch (InternalException $e) {
            throw new \PublicException($e);
        }
    }

    public static function objectFex()
    {
        try {
            loadSenshiModal('fex');
            loadSenshiModal('geo_locate');
            loadSenshiModal('palisis_cookie');

            loadJob('cache_currencies');

            $cacheCurrenciesJob = new \GrayLineTourCMSJobs\CacheCurrencies(); 
            $geoLocate = new GeoLocate(); 
            $palisisCookie = new PalisisCookie();
            $object = new fex($cacheCurrenciesJob, $geoLocate, $palisisCookie);
            if (!is_object($object)) {
                throw new InternalException("can't create Fex object DIC");
            }
            return $object;
        } catch (InternalException $e) {
            throw new \PublicException($e);
        }
    }

    public static function objectCheckAvailability()
    {
        try {
            $fex = self::objectFex();
            loadSenshiModal('check_availability');
            $object = new CheckAvailability($fex);
            if (!is_object($object)) {
                throw new InternalException("can't create Check Availability object DIC");
            }
            return $object;
        } catch (InternalException $e) {
            throw new \PublicException($e);
        }
    }

    public static function objectCronChecker()
    {
        try {
            loadSenshiModal('cron_checker');
            $object = new CronChecker();
            if (!is_object($object)) {
                throw new InternalException("can't create CronChecker object DIC");
            }
            return $object;
        } catch (InternalException $e) {
            throw new \PublicException($e);
        }
    }

    public static function objectWebhookChecker()
    {
        try {
            loadSenshiModal('webhook_checker');
            $object = new WebhookChecker();
            if (!is_object($object)) {
                throw new InternalException("can't create WebhookChecker object DIC");
            }
            return $object;
        } catch (InternalException $e) {
            throw new \PublicException($e);
        }
    }
}
