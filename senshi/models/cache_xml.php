<?php

namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

Loader::library("exceptions", "senshi");
Loader::model('cache_interface', 'senshi');

class CacheXml implements CacheInterface
{

    protected $cacheLocation;
    const CACHE_TIME_DEFAULT = 21600;    // 6 hours


    public function __construct()
    {
        //\FB::log("Cache Xml Object instantiated");
        include("tourcms/config.php");
        // Basic config - cache location comes from here
        $this->cacheLocation = $cache_location;
        //\FB::warn("CACHE LOCATION IN CACHE XML IS $cache_location");
    }

    public function saveCache($data, $dir, $filename, $seconds=null)
    {
        // TODO: Implement saveCache() method.
    }

    public function getCache($dir, $filename)
    {
        try {
            $xml = $this->loadCache($dir, $filename);
            //\FB::log("LOAD CACHE ATTEMPT 1 $dir $filename");
            return $xml;
        }
        catch (FileException $e) {
            // let's try again
        }
        try {
            $xml = $this->loadCache($dir, $filename);
            //\FB::log("LOAD CACHE ATTEMPT 2 $dir $filename");
            return $xml;
        }
        catch (FileException $e) {
            // let's try again
        }
        try {
            $xml = $this->loadCache($dir, $filename);
            //\FB::log("LOAD CACHE ATTEMPT 3 $dir $filename");
            return $xml;
        }
        catch (FileException $e) {
            //echo 'We seem to be having file system issues. We are sorry for the inconvenience: ',  $e->getMessage(), "\n";
            return false;
        }
    }

    protected function loadCache($dir, $filename)
    {
        //\FB::log("loadCache $dir $filename called");
        // Let's convert this data to XML
        libxml_use_internal_errors(true);

        $folder = $this->mkCacheDir($dir);
        // Check if the file exists
        $cache_file = $this->filePath($folder, $filename);
        $xml = simplexml_load_file($cache_file);
        if (!$xml) {
            $error = 'Error: Cannot create XML object ' . $cache_file;
            throw new FileException($error);
            libxml_clear_errors();
        }
        //$xml = simplexml_load_string($data) or die("Error: Cannot create XML object");
        ////////\FB::log("Return simple xml object");
        return $xml;

    }

    protected function cacheData($data, $dir, $filename, $seconds=null)
    {
        $seconds = (int)$seconds > 0 ? (int)$seconds : self::CACHE_TIME_DEFAULT;
        $cache_time = $seconds;
        $loaded_cache = false;

        $folder = $this->mkCacheDir($dir);

        // Check if the file exists
        $cache_file = $this->filePath($folder, $filename);


        if(file_exists($cache_file) && (time() - $cache_time < filemtime($cache_file))) {
            //$result = simplexml_load_file($cache_file);
            $loaded_cache = true;
        }

        if(!$loaded_cache) {
            $fh = fopen($cache_file, 'w');
            $stringData = $data->asXML();
            fwrite($fh, $stringData);
            fclose($fh);
        }
    }

    protected function mkCacheDir($dir)
    {
        $cache_location = $this->cacheLocation;
        // Create cache directory if it doesn't exist
        $folder = $cache_location . $dir ."/";
        // if not a directory, then create it
        if (! is_dir($folder)) {
            mkdir($folder, 0750, true);
        }
        return $folder;
    }

    protected function filePath($folder, $filename)
    {
        $cacheFile = $folder . $filename . ".xml";
        return $cacheFile;
    }

    protected function curlData($url, $dir, $filename, $user=null, $pass=null)
    {
        //FB::warn("CURL, url, user, pass: $url | $user | $pass");
        $folder = $this->mkCacheDir($dir);
        $filepath = $this->filePath($folder, $filename);
        //////FB::log("Url is $url");
        //////FB::log("Cache location is $filepath");
        // Need to delete old file to create fresh one.
        if (file_exists($filepath))
            unlink($filepath);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); //timeout after 30 seconds
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        if ($user && $pass) {
            curl_setopt($ch, CURLOPT_USERPWD, "$user:$pass");
        }
        $fp = fopen($filepath, "w");
        curl_setopt($ch, CURLOPT_FILE, $fp);        // save to file
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);   //get status code
        $error = curl_error($ch);
        $data = curl_exec($ch);
        curl_close($ch);
        if ($error) {
            throw new FileException("Curl Cache Error: $error, Status Code: $status_code, File $filepath");
        }
        if (!$data) {
            throw new FileException("Curl Cache Error: $error, No Data Loaded $url, File $filepath");
        }
        return $data;
    }

    // cURL to connect to source
    // Error handling
    // return $datas
    public function curlCache($url, $dir, $filename, $user=null, $pass=null)
    {
        try {
            $this->curlData($url, $dir, $filename, $user=null, $pass=null);
            //\FB::log("CURL CACHE ATTEMPT 1 $url $dir $filename");
            return true;
        } catch (FileException $e)  {
            // let's try again
        }
        try {
            $this->curlData($url, $dir, $filename, $user=null, $pass=null);
            //\FB::log("CURL CACHE ATTEMPT 2 $url $dir $filename");
            return true;
        } catch (FileException $e)  {
            // let's try again
        }
        try {
            $this->curlData($url, $dir, $filename, $user=null, $pass=null);
            //\FB::log("CURL CACHE ATTEMPT 3 $url $dir $filename");
            return true;
        } catch (FileException $e)  {
            throw new PublicException($e);
        }
    }

}
