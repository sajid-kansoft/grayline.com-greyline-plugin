<?php

namespace GrayLineTourCMSControllers;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

/**
 * cookie.php
 * new cookie protocol, for Chrome's 2020 CRSF security restrictions, 3ds1 redirect processing requires this
 * User: Iona iona@palisis.com
 * Date: 2021-02-03
 * Time: 09:55
 */

class PalisisCookie extends MainController
{

    public function setCookie($name, $val, $expire=0, $path='/')
    {
        // if ($expire > 0) {
        //     $expire = date("D, d M Y H:i:s", $expire).'GMT';
        // }
        
        setcookie($name, $val, $expire, $path);
        // setcookie($name, $val, time() + (86400 * 30), $path);
    }

    public function getCookie($name)
    {
        return isset($_COOKIE[$name]) ? $_COOKIE[$name] : null;
    }

}