<?php

namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

class PalisisCookie
{

    public function setCookie($name, $val, $expire=0, $path='/')
    {        
        setcookie($name, $val, $expire, $path, get_site_url(), TRUE );
    }

    public function getCookie($name)
    {
        return isset($_COOKIE[$name]) ? $_COOKIE[$name] : null;
    }

}