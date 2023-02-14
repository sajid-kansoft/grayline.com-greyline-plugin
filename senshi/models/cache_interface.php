<?php

namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

interface CacheInterface
{
    public function saveCache($data, $dir, $filename, $seconds=null);
    public function curlCache($url, $dir, $filename, $user=null, $pass=null);
    public function getCache($dir, $filename);
}