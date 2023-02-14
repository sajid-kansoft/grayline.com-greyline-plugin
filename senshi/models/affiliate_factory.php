<?php

namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

class AffiliateFactory
{

    public static function create()
    {
        $affiliate = loadSenshiModal("affiliate");
        return $affiliate;
    }


}