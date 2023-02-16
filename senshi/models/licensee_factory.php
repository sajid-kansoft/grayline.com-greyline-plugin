<?php
namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");


class LicenseeFactory
{

    public static function create($row)
    {
        loadSenshiModal("licensee"); 
        return new Licensee($row);
    }


}