<?php

namespace GrayLineTourCMSJobs;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

use \InternalException as InternalException;
/*
	Pull in the latest currency conversions from Open Exchange Rates
*/
class CacheCurrencies {

	protected $job_name = "Cache Currencies";
	protected $job_description = "Cache latest rates from Open Exchange Rates";

	public function getJobName() {
		return $this->job_name;
	}
	
	public function getJobDescription() {
		return $this->job_description;
	}

	public function run() {

		try { 
		    $this->loadGenFex();
        } catch (InternalException $e) {
            return "Fail" . $e->getMessage();
        } catch (\PublicException $e) {
            return "Fail" . $e->getMessage();
        } catch (\Exception $e) {
            echo $e->getMessage()."\n";
        }

	}

	private function loadGenFex() {
        include(GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."libraries/3rdparty/tourcms/config.php");
        
        // Free
        $app_id = "9061eceb4183436da3d53beb84ab226c";
        // TourCMS paid
        $app_id = "3ebb1a48cc814c42837d42b2b3687a94";

        $json = file_get_contents("http://openexchangerates.org/api/latest.json?app_id=$app_id");

        $errorMsg = "Cannot load Open Exchange currency data";

        if(strlen($json) > 0) {
            $ob = json_decode($json);

            if($ob === null) {
                throw new InternalException("CURRENCY Error: Json decode is null", "CRITICAL");
            } else { 
                $myFile = $cache_location."currencies.php";
                $fh = fopen($myFile, 'w') or die("can't open file");
                fwrite($fh, $json);
                fclose($fh); 
                return "Success";
            }
        }
        else {
            throw new InternalException($errorMsg . " strlen json is 0", "CRITICAL");
        }
    }
}
