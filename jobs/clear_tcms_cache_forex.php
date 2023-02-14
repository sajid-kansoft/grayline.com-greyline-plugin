<?php

namespace GrayLineTourCMSJobs;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

/*
	Clear TourCMS tour cache and tour search cache after forex rates change at 12.30pm GMT
    TCMS caches prices in USD, we need fresh data to make sure from price is accurate

*/
class ClearTcmsCacheForex
{

    protected $job_name = "Clear TCMS Cache with Fex Rate change";
    protected $job_description = "Delete old tour cachea and tour search cache files";

    public function getJobName() {
        return $this->job_name;
    }

    public function getJobDescription() {
        return $this->job_description;
    }

    public function run() {
        include(GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."libraries/3rdparty/tourcms/config.php");

        $response = $this->curlClearCache();
        //\FB::log($response);
        return $response;
    }

    // we also want to flush cached TourCMS search results and tours because the from price will get cached at
    // old USD rate
    private function curlClearCache()
    {
        $url = BASE_URL . DIR_REL . '/nc/clear_cache.php?at=1&s=1';
        //\FB::log("curl url $url");
        // Get cURL resource
        $curl = curl_init();
        // Set some options - we are passing in a useragent too here
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $url
        ));
        // Send the request & save response to $resp
        $resp = curl_exec($curl);
        // Close request to clear up some resources
        curl_close($curl);
        return $resp;
    }


}
?>
