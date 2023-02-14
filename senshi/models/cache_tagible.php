<?php

namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

class CacheTagible
{

    protected $cacheXml;
    protected $dir = 'tagible';
    protected $filename = 'tagible_product_feed';
    protected $cacheTime =  86400;    // 24 hours
    protected $curlUrl = 'https://app.tagibletravel.com/xml/grayline';


    public function __construct(CacheXml $cacheXml)
    {
        $this->cacheXml = $cacheXml;
    }

    public function saveCache()
    {
        try {
            $this->cacheXml->curlCache($this->curlUrl, $this->dir, $this->filename);
        } catch (\PublicException $e)  {
            return false;
        }
    }

    public function getXml()
    {
        $xml = $this->loadCache();
        return $xml;
    }

    protected function loadCache()
    {
        try {
            $xml = $this->cacheXml->getCache($this->dir, $this->filename);
            if (! $xml) {
                $this->saveCache();
                $xml = $this->cacheXml->getCache($this->dir, $this->filename);
            }
            return $xml;
        } catch (\FileException $e) {
            //echo 'We seem to be having file system issues. We are sorry for the inconvenience: ',  $e->getMessage(), "\n";
            return false;
        }
    }




}
