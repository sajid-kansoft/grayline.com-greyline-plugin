<?php

namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

require GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH.'vendor/autoload.php';

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use MaxMind\Db\Reader\InvalidDatabaseException;

//Loader::library('exceptions', 'senshi');

class GeoLocate
{
    protected $ip;
    protected $countryCode;
    protected $countryName;
    protected $logger;
    protected $defaultCode = "US";
    protected $localeIps = ['192.168.10.1', '127.0.0.1'];
    protected $testIp = '185.193.224.15'; // zurich
    protected $cookieName = 'gl_geo_country_code';
    protected $cookieNameCountry = 'gl_geo_country_name';

    public function __construct()
    {   
        $this->locateUser();
    }

    /**
     * @return mixed
     */
    public function getCountryCode()
    {
        return $this->countryCode;
    }

    public function getCountryName()
    {
        return $this->countryName;
    }

    public function getIp()
    {  
        if ($this->ip) {
            return $this->ip;
        }
        // CloudFlare deal with their IP address
        $ip = $_SERVER["REMOTE_ADDR"]; 
        if (array_key_exists('HTTP_CF_CONNECTING_IP', $_SERVER)) {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        }

        $ip = trim($ip);

        if (in_array($ip, $this->localeIps)) {
            $ip = $this->testIp;
        } 
        $this->ip = $ip;
        return $this->ip;
    }


    public function locateUser()
    {   
        if ($this->getCookie()) { 
            $this->countryCode = $this->getCookie();    
            $this->countryName = isset($_COOKIE[$this->cookieNameCountry]) ? $_COOKIE[$this->cookieNameCountry] : ''; 

            return true;
        } 
        $ip = $this->getIp();

        
        try {
            // This creates the Reader object, which should be reused across
            // lookups.

            $filename = GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH . 'libraries/3rdparty/geolite2/GeoLite2-City.mmdb';

            $reader = new Reader($filename);

            $record = $reader->city($ip);

            $country_name = $record->country->name;
            $countryCode = $record->country->isoCode;
            $this->countryCode = $countryCode;
            $this->countryName = $country_name;
            $this->setCookie();
            //    print($record->country->name."\n"); // 'United States'
        } catch (\GeoIp2\Exception\AddressNotFoundException $e) {
            $errorMsg = "Get locale GEO MAXMIND IP ERROR ADDRESS $ip ".$e->getMessage();
            // We will need to set a default address instead here
            $this->countryCode = $this->defaultCode;
            $this->setCookie();
            error_log($errorMsg);
        } catch (\MaxMind\Db\InvalidDatabaseException $e) {
            $errorMsg = "Get locale GEO MAXMIND IP ERROR DB CORRUPT $ip ".$e->getMessage();
            error_log($errorMsg);
            // We will need to set a default address instead here
            $this->countryCode = $this->defaultCode;
            $this->setCookie();
        } catch (Exception $e) {
            $errorMsg = "Get locale GEO MAXMIND ERROR $ip ".$e->getMessage();
            error_log($errorMsg);
            // We will need to set a default address instead here
            $this->countryCode = $this->defaultCode;
            $this->setCookie();
        }
    }

    private function setCookie()
    {
        $name = $this->cookieName; 
        $val = $this->countryCode; 
        $expire = 0;
        
        // new cookie, for Chrome's 2020 CRSF security restrictions
        // need to check header already sent error
        // setcookie($name, $val, $expire, '/', get_site_url(), TRUE );
        setcookie($name, $val, $expire, '/');
        $name2 = $this->cookieNameCountry; 
        $val2 = $this->countryName;
       // setcookie($name, $val, $expire, '/', get_site_url(), TRUE ); 

        setcookie($name2, $val2, $expire, '/');
    }

    private function getCookie()
    {   
        return isset($_COOKIE[$this->cookieName]) ? $_COOKIE[$this->cookieName] : null;
    }
}
